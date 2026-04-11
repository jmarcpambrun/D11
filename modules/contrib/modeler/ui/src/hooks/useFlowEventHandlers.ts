/**
 * Custom hook for ReactFlow event handlers
 * Handles node/edge interactions, connections, selections, and canvas events
 */

import { useCallback, useRef } from 'react';
import { useGraphStore } from '../store/useGraphStore';
import { useSelectionStore } from '../store/useSelectionStore';
import type { StoreNode as Node, StoreEdge as Edge } from '../types/settings';
import { generateUniqueEdgeId } from '../utils/clipboardUtils';
import { t } from '../utils/translation';

interface UseFlowEventHandlersProps {
  handleCanvasNodeClick: (node: Node) => void;
  handleCanvasEdgeClick: (edge: Edge) => void;
  setHasUnsavedChanges: (value: boolean) => void;
  /** General syncing flag (both directions) — used to guard autoSyncToReplay */
  isSyncing: boolean;
  /** Replay-to-canvas syncing ref — used to skip stale onSelectionChange events */
  isReplaySyncingRef: React.RefObject<boolean>;
  hasReplayData: boolean;
  isReplayMode: boolean;
  currentReplayStep: number;
  autoSyncToReplay: (node: Node | null) => void;
  /** Screen reader announcement callback */
  announce?: (text: string) => void;
  /** Callback to save history before making changes */
  saveHistory?: () => void;
}

export function useFlowEventHandlers({
  handleCanvasNodeClick,
  handleCanvasEdgeClick,
  setHasUnsavedChanges,
  isSyncing,
  isReplaySyncingRef,
  hasReplayData,
  isReplayMode,
  currentReplayStep,
  autoSyncToReplay,
  announce,
  saveHistory
}: UseFlowEventHandlersProps) {
  
  // Store state
  const nodes = useGraphStore(state => state.nodes);
  const edges = useGraphStore(state => state.edges);
  const setEdges = useGraphStore(state => state.setEdges);
  const removeNode = useGraphStore(state => state.removeNode);
  const removeEdge = useGraphStore(state => state.removeEdge);
  const applyNodeChangesToStore = useGraphStore(state => state.applyNodeChanges);
  const applyEdgeChangesToStore = useGraphStore(state => state.applyEdgeChanges);
  const setSelectedNode = useSelectionStore(state => state.setSelectedNode);
  const setSelectedEdge = useSelectionStore(state => state.setSelectedEdge);
  const setSelectedNodes = useSelectionStore(state => state.setSelectedNodes);
  const setSelectedEdges = useSelectionStore(state => state.setSelectedEdges);

  // Guard against stale onSelectionChange events after a pane click.
  // ReactFlow may fire onSelectionChange with the previously selected
  // node/edge *after* onPaneClick has already cleared the selection.
  // The ref is set by onPaneClick and consumed (reset) by the next
  // onSelectionChange that carries an empty selection.
  const paneClickedRef = useRef(false);

  // Snapshot node position at drag start so onNodeDragStop can detect
  // whether the node actually moved.  ReactFlow fires onNodeDragStop even
  // on simple clicks (zero-movement "drags"), so we need this comparison.
  const dragStartPositions = useRef<Record<string, { x: number; y: number }>>({});

  // ReactFlow change handlers
  const onNodesChange = useCallback((changes: any[]) => {
    applyNodeChangesToStore(changes);
  }, [applyNodeChangesToStore]);

  const onEdgesChange = useCallback((changes: any[]) => {
    applyEdgeChangesToStore(changes);
  }, [applyEdgeChangesToStore]);

  // Handle selection changes from canvas
  const onSelectionChange = useCallback(({ nodes: selectedNodes, edges: selectedEdges }: { nodes: Node[]; edges: Edge[] }) => {
    // During replay-to-canvas sync (clicking a step in the replay panel),
    // programmatic node/edge updates cause ReactFlow to fire onSelectionChange
    // with stale data (e.g. previously selected node still appearing alongside
    // the newly selected edge). The selection store is already set correctly
    // by the sync code, so skip ReactFlow selection events while the
    // replay-to-canvas sync is in progress.
    if (isReplaySyncingRef.current) {
      return;
    }

    const isEmpty = selectedNodes.length === 0 && selectedEdges.length === 0;

    if (paneClickedRef.current) {
      if (isEmpty) {
        // This is the confirming empty event after the pane click — consume
        // the flag and proceed normally to clear selection state.
        paneClickedRef.current = false;
      } else {
        // Stale event carrying the previous selection — ignore it.
        return;
      }
    }

    // Handle single selection for primary property display
    const newSelectedNode = selectedNodes[0] || null;
    const newSelectedEdge = selectedEdges[0] || null;
    
    setSelectedNode(newSelectedNode);
    setSelectedEdge(newSelectedEdge);
    
    // Handle multi-selection for property panel
    const nodeIds = selectedNodes.map((node: Node) => node.id);
    const edgeIds = selectedEdges.map((edge: Edge) => edge.id);
    
    setSelectedNodes(nodeIds);
    setSelectedEdges(edgeIds);
    
    // Auto-sync to replay if not already syncing
    if (!isSyncing && hasReplayData && (!isReplayMode || currentReplayStep === -1)) {
      autoSyncToReplay(newSelectedNode);
    }
  }, [
    setSelectedNode,
    setSelectedEdge,
    setSelectedNodes,
    setSelectedEdges,
    isSyncing,
    isReplaySyncingRef,
    hasReplayData,
    isReplayMode,
    currentReplayStep,
    autoSyncToReplay
  ]);

  // Handle node clicks
  const onNodeClick = useCallback((event: React.MouseEvent, node: Node) => {
    handleCanvasNodeClick(node);
  }, [handleCanvasNodeClick]);

  // Handle edge clicks  
  const onEdgeClick = useCallback((event: React.MouseEvent, edge: Edge) => {
    handleCanvasEdgeClick(edge);
  }, [handleCanvasEdgeClick]);

  // Handle individual node deletion (from node component)
  // Delegates to useGraphStore.removeNode which handles node removal
  // and connected edge removal.  Selection cleanup happens automatically
  // via useSelectionStore's subscription to graph-state changes.
  const onDeleteNode = useCallback((nodeId: string) => {
    removeNode(nodeId);
    setHasUnsavedChanges(true);
    if (announce) {
      announce(t('Element deleted.'));
    }
  }, [removeNode, setHasUnsavedChanges, announce]);

  // Handle delete selected elements
  // Delegates to useGraphStore.removeNode/removeEdge for graph mutations.
  // Selection cleanup happens automatically via useSelectionStore's
  // subscription to graph-state changes.
  const handleDeleteSelected = useCallback(() => {
    const nodesToDelete = nodes.filter(node => node.selected);
    const edgesToDelete = edges.filter(edge => edge.selected);
    
    if (nodesToDelete.length === 0 && edgesToDelete.length === 0) return;

    if (saveHistory) saveHistory();

    // Collect edge IDs that will be removed as a side-effect of node removal
    // so we can avoid double-removing them below.
    const nodeIdsToDelete = new Set(nodesToDelete.map(n => n.id));
    const implicitEdgeIds = new Set(
      edges
        .filter(e => nodeIdsToDelete.has(e.source) || nodeIdsToDelete.has(e.target))
        .map(e => e.id)
    );
    
    // Remove nodes (each call also removes connected edges + cleans selection)
    for (const node of nodesToDelete) {
      removeNode(node.id);
    }
    
    // Remove explicitly selected edges that weren't already removed by node deletion
    for (const edge of edgesToDelete) {
      if (!implicitEdgeIds.has(edge.id)) {
        removeEdge(edge.id);
      }
    }
    
    setHasUnsavedChanges(true);

    // Announce deletion count to screen readers
    if (announce) {
      const totalDeleted = nodesToDelete.length + edgesToDelete.length;
      announce(t('@count elements deleted.', { '@count': String(totalDeleted) }));
    }
  }, [
    nodes,
    edges,
    removeNode,
    removeEdge,
    setHasUnsavedChanges,
    announce,
    saveHistory
  ]);

  // Handle new connections between nodes
  const onConnect = useCallback((connection: { source: string | null; target: string | null; sourceHandle?: string | null; targetHandle?: string | null }) => {
    if (!connection.source || !connection.target) return;
    if (saveHistory) saveHistory();
    const id = generateUniqueEdgeId();
    const newEdge = {
      id,
      source: connection.source,
      target: connection.target,
      type: 'default',
    };
    setEdges(prev => [...prev, newEdge]);
    setHasUnsavedChanges(true);
  }, [setEdges, setHasUnsavedChanges, saveHistory]);

  // Handle canvas pane clicks (deselect all)
  const onPaneClick = useCallback(() => {
    paneClickedRef.current = true;
    setSelectedNode(null);
    setSelectedEdge(null);
  }, [setSelectedNode, setSelectedEdge]);

  // Handle node drag start — snapshot the position so we can detect actual movement
  // Save history here (before the drag changes any positions) so that undo
  // restores the pre-drag state.  The snapshot is only committed when
  // onNodeDragStop detects that the node actually moved.
  const onNodeDragStart = useCallback((_event: React.MouseEvent, node: Node) => {
    dragStartPositions.current[node.id] = { x: node.position.x, y: node.position.y };
    if (saveHistory) saveHistory();
  }, [saveHistory]);

  // Handle node drag stop — mark dirty only if the node actually moved
  const onNodeDragStop = useCallback((_event: React.MouseEvent, node: Node) => {
    const startPos = dragStartPositions.current[node.id];
    delete dragStartPositions.current[node.id];
    if (startPos && (startPos.x !== node.position.x || startPos.y !== node.position.y)) {
      setHasUnsavedChanges(true);
    }
  }, [setHasUnsavedChanges]);

  return {
    onNodesChange,
    onEdgesChange,
    onSelectionChange,
    onNodeClick,
    onEdgeClick,
    onDeleteNode,
    handleDeleteSelected,
    onConnect,
    onPaneClick,
    onNodeDragStart,
    onNodeDragStop,
  };
}