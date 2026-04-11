import React, { Profiler, useMemo } from 'react';
import ReactFlow, {
  Node,
  Edge,
  Connection,
  NodeChange,
  EdgeChange,
  ConnectionLineType,
  MarkerType,
  SelectionMode,
  OnSelectionChangeParams,
  OnConnectStartParams,
  MiniMap,
  Viewport,
  PanOnScrollMode,
  EdgeLabelRenderer,
} from 'reactflow';
import type { ReplayStep } from '../hooks/useSimpleReplaySync';
import type { StoreComponent, EdgeData, NodeData, ModelConstraints } from '../types/settings';
import { useEdgeOrdering } from '../hooks/useEdgeOrdering';

// Node Components
import CustomNode from './nodes/CustomNode';
import StartNode from './nodes/StartNode';
import GatewayNode from './nodes/GatewayNode';
import SubprocessNode from './nodes/SubprocessNode';
import PlaceholderNode from './nodes/PlaceholderNode';

// Edge Components
import DefaultEdge from './edges/DefaultEdge';
import ConditionEdge from './edges/ConditionEdge';

import { onRenderCallback } from '../utils/profiling';
import { VIEWPORT } from '../constants/dimensions';

/** ReactFlow event handlers passed through to the canvas. */
interface FlowCanvasEventHandlers {
  onNodesChange: (changes: NodeChange[]) => void;
  onEdgesChange: (changes: EdgeChange[]) => void;
  onConnect: (connection: Connection) => void;
  onSelectionChange: (params: OnSelectionChangeParams) => void;
  onConnectStart: (event: React.MouseEvent | React.TouchEvent, params: OnConnectStartParams) => void;
  onConnectEnd: (event: MouseEvent | TouchEvent) => void;
  onDrop: (event: React.DragEvent) => void;
  onDragOver: (event: React.DragEvent) => void;
  onDragEnter: (event: React.DragEvent) => void;
  onDragLeave: (event: React.DragEvent) => void;
  onNodeClick: (event: React.MouseEvent, node: Node) => void;
  onEdgeClick: (event: React.MouseEvent, edge: Edge) => void;
  onPaneClick: (event: React.MouseEvent) => void;
  onNodeDragStart: (event: React.MouseEvent, node: Node) => void;
  onNodeDragStop: (event: React.MouseEvent, node: Node) => void;
  onInit: (instance: Record<string, unknown>) => void;
}

/** Callbacks for updating individual nodes/edges. */
interface FlowCanvasElementCallbacks {
  onEdgeUpdate?: (edgeId: string, updates: Partial<EdgeData>) => void;
  onNodeUpdate?: (nodeId: string, newData: Partial<NodeData>) => void;
  onDeleteNode?: (nodeId: string) => void;
  onEdgeConfigurationChange?: (edgeId: string, configuration: Record<string, unknown> | null) => void;
}

/** Keyboard modifier key state. */
interface FlowCanvasModifierKeys {
  isShiftPressed: boolean;
  isCtrlPressed: boolean;
  isAltPressed: boolean;
}

/** Toggle flags controlling canvas UI features. */
interface FlowCanvasUIState {
  isDragActive: boolean;
  isLocked: boolean;
  showEdgeOrderNumbers: boolean;
  showAllAnnotations: boolean;
}

/** Search-related state. */
interface FlowCanvasSearchState {
  searchTerm: string;
  highlightedSearchResult: { id: string; type: string } | null;
}

/** Replay visualization state. */
interface FlowCanvasReplayState {
  replayData: ReplayStep[];
  currentReplayStep: number;
  isReplayMode: boolean;
  replayIndicators: Array<{
    id: string;
    x: number;
    y: number;
    color: string;
  }>;
}

/** Quick-add node/condition callbacks. */
interface FlowCanvasQuickAddProps {
  onQuickAdd?: (component: StoreComponent, sourceNodeId: string) => void;
  onAddCondition?: (edgeId: string, component: StoreComponent) => void;
  onReplacePlaceholder?: (nodeId: string, component: StoreComponent) => void;
}

interface FlowCanvasProps {
  // Data
  nodes: Node[];
  edges: Edge[];

  // ReactFlow event handlers
  eventHandlers: FlowCanvasEventHandlers;

  // Element update callbacks
  elementCallbacks: FlowCanvasElementCallbacks;

  // Viewport
  viewport: Viewport;

  // Modifier keys
  modifierKeys: FlowCanvasModifierKeys;

  // UI state
  uiState: FlowCanvasUIState;

  // Search
  search: FlowCanvasSearchState;

  // Replay
  replay: FlowCanvasReplayState;

  // Edge ordering
  setEdges: (updater: (edges: Edge[]) => Edge[]) => void;
  setHasUnsavedChanges: (value: boolean) => void;

  // Quick add
  quickAdd: FlowCanvasQuickAddProps;

  // Model constraints (successor cardinality per component type)
  modelConstraints?: ModelConstraints;
}

// Node types configuration
const nodeTypes = {
  customNode: CustomNode,
  element: CustomNode,  // 'element' type uses CustomNode component
  start: StartNode,
  gateway: GatewayNode,
  subprocess: SubprocessNode,
  placeholder: PlaceholderNode,
};

// Edge types configuration
const edgeTypes = {
  default: DefaultEdge,
  condition: ConditionEdge,
};

const FlowCanvas: React.FC<FlowCanvasProps> = ({
  nodes,
  edges,
  eventHandlers,
  elementCallbacks,
  viewport: _viewport,
  modifierKeys,
  uiState,
  search,
  replay,
  setEdges,
  setHasUnsavedChanges,
  quickAdd,
  modelConstraints,
}) => {
  // Destructure grouped props for use in the component body
  const {
    onNodesChange, onEdgesChange, onConnect, onSelectionChange,
    onConnectStart, onConnectEnd, onDrop, onDragOver, onDragEnter,
    onDragLeave, onNodeClick, onEdgeClick, onPaneClick, onNodeDragStart, onNodeDragStop, onInit,
  } = eventHandlers;
  const { onEdgeUpdate, onNodeUpdate, onDeleteNode, onEdgeConfigurationChange } = elementCallbacks;
  const { isShiftPressed: _isShiftPressed, isCtrlPressed: _isCtrlPressed, isAltPressed: _isAltPressed } = modifierKeys;
  const { isDragActive, isLocked, showEdgeOrderNumbers, showAllAnnotations } = uiState;
  const { searchTerm, highlightedSearchResult } = search;
  const { replayData, currentReplayStep, isReplayMode, replayIndicators } = replay;
  const { onQuickAdd, onAddCondition, onReplacePlaceholder } = quickAdd;
  // Edge ordering hook - handles drag/drop reordering and order info calculation
  const {
    handleDragStart,
    handleDragEnd,
    handleEdgeOrderDrop,
    handleReorderEdge,
    getEdgeOrderInfo,
  } = useEdgeOrdering({ edges, nodes, setEdges, setHasUnsavedChanges });

  // Selection mode: always partial so partially-overlapped nodes are included
  const selectionMode = SelectionMode.Partial;

  // No modifier-based cursor classes needed with the Figma-like gesture scheme.
  // ReactFlow handles cursors internally (default for selection, grab for Space+drag).

  // Enhanced edges with order numbers and drag handlers
  const enhancedEdges = useMemo(() => {
    return edges.map((edge, _index) => {
      const edgeOrderInfo = getEdgeOrderInfo(edge, edges);
      const hasCondition = edge.data?.condition;
      
      // Note: Condition result indicators are now handled via separate indicator objects
      
      return {
        ...edge,
        data: {
          ...edge.data,
          // Global lock state (canvas locked / read-only / standalone).
          // Distinct from per-edge isLocked/locked which tracks individual
          // element locks.  Used by edge components to disable interactions
          // (e.g. edge order reorder) without hiding visual indicators.
          globalLocked: isLocked,
          edgeOrdersVisible: showEdgeOrderNumbers,
          edgeOrderInfo: edgeOrderInfo,
          showOrderNumbers: showEdgeOrderNumbers,
          showAnnotations: showAllAnnotations,
          // Individual annotation visibility (stored in edge data)
          isAnnotationVisible: showAllAnnotations || edge.data?.isAnnotationVisible || false,
          onToggleAnnotation: () => {
            // Toggle individual annotation visibility
            if (onEdgeUpdate) {
              const newAnnotationVisible = !edge.data?.isAnnotationVisible;
              onEdgeUpdate(edge.id, {
                ...edge.data,
                isAnnotationVisible: newAnnotationVisible
              });
            }
          },
          onDragStart: handleDragStart,
          onDragEnd: handleDragEnd,
          onDrop: handleEdgeOrderDrop,
          onReorderEdge: handleReorderEdge,
          onEdgeUpdate: onEdgeUpdate,
          // Only show quick add condition button on edges without conditions
          onAddCondition: !hasCondition && onAddCondition && !isLocked ? onAddCondition : undefined,
          // Wire up condition deletion from the canvas trash icon
          onDeleteCondition: hasCondition && onEdgeConfigurationChange && !isLocked ? (edgeId: string) => {
            onEdgeConfigurationChange(edgeId, null);
          } : undefined,
          searchTerm,
          isHighlighted: highlightedSearchResult?.id === edge.id,
          // Replay state
          replayData,
          currentReplayStep,
          isReplayMode,
        },
      };
    });
  }, [
    edges,
    getEdgeOrderInfo,
    showEdgeOrderNumbers,
    showAllAnnotations,
    handleDragStart,
    handleDragEnd,
    handleEdgeOrderDrop,
    handleReorderEdge,
    onEdgeUpdate,
    onEdgeConfigurationChange,
    onAddCondition,
    isLocked,
    searchTerm,
    highlightedSearchResult,
    replayData,
    currentReplayStep,
    isReplayMode
  ]);

  // Pre-compute outgoing edge counts per node for successor constraint checks.
  const outgoingEdgeCounts = useMemo(() => {
    const counts: Record<string, number> = {};
    for (const edge of edges) {
      counts[edge.source] = (counts[edge.source] ?? 0) + 1;
    }
    return counts;
  }, [edges]);

  // Enhanced nodes with search highlighting and replay state
  const enhancedNodes = useMemo(() => {
    return nodes.map(node => {
      // Check successor constraint: should we suppress quick-add and source handle?
      const typeConstraint = node.type ? modelConstraints?.[node.type as keyof ModelConstraints] : undefined;
      const maxSuccessors = typeConstraint?.successors?.max;
      const outgoing = outgoingEdgeCounts[node.id] ?? 0;
      const atMaxSuccessors = maxSuccessors !== undefined && outgoing >= maxSuccessors;

      return {
        ...node,
        data: {
          ...node.data,
          showAnnotations: showAllAnnotations,
          // Individual annotation visibility (stored in node data)
          isAnnotationVisible: showAllAnnotations || node.data?.isAnnotationVisible || false,
          onToggleAnnotation: () => {
            // Toggle individual annotation visibility
            if (onNodeUpdate) {
              const newAnnotationVisible = !node.data?.isAnnotationVisible;
              onNodeUpdate(node.id, {
                ...node.data,
                isAnnotationVisible: newAnnotationVisible
              });
            }
          },
          searchTerm,
          isHighlighted: highlightedSearchResult?.id === node.id,
          // Replay state
          replayData,
          currentReplayStep,
          isReplayMode,
          // Node operations
          onDelete: onDeleteNode ? () => onDeleteNode(node.id) : undefined,
          onQuickAdd: onQuickAdd && !isLocked && !atMaxSuccessors
            ? (component: StoreComponent) => onQuickAdd(component, node.id)
            : undefined,
          onReplacePlaceholder: node.type === 'placeholder' && onReplacePlaceholder && !isLocked
            ? (component: StoreComponent) => onReplacePlaceholder(node.id, component)
            : undefined,
          sourceHandleDisabled: atMaxSuccessors,
          isLocked,
        },
      };
    });
  }, [
    nodes,
    edges,
    outgoingEdgeCounts,
    modelConstraints,
    showAllAnnotations, 
    onNodeUpdate,
    searchTerm, 
    highlightedSearchResult,
    replayData,
    currentReplayStep,
    isReplayMode,
    onDeleteNode,
    onQuickAdd,
    onReplacePlaceholder,
    isLocked
  ]);

  return (
    <Profiler id="FlowCanvas" onRender={onRenderCallback}>
    <div 
      className={`reactflow-wrapper ${isDragActive ? 'drag-active' : ''}`}
    >
      <ReactFlow
        nodes={enhancedNodes}
        edges={enhancedEdges}
        onNodesChange={onNodesChange}
        onEdgesChange={onEdgesChange}
        onConnect={onConnect}
        onSelectionChange={onSelectionChange}
        onConnectStart={onConnectStart}
        onConnectEnd={onConnectEnd}
        onDrop={onDrop}
        onDragOver={onDragOver}
        onDragEnter={onDragEnter}
        onDragLeave={onDragLeave}
        onNodeClick={onNodeClick}
        onEdgeClick={onEdgeClick}
        onPaneClick={onPaneClick}
        onNodeDragStart={onNodeDragStart}
        onNodeDragStop={onNodeDragStop}
        onInit={onInit}
        nodeTypes={nodeTypes}
        edgeTypes={edgeTypes}
        connectionLineType={ConnectionLineType.SmoothStep}
        selectionMode={selectionMode}
        defaultMarkerColor="var(--modeler-color-edge-default)"
        defaultEdgeOptions={{
          type: 'default',
          markerEnd: {
            type: MarkerType.Arrow,
            strokeWidth: 1.5,
            color: 'var(--modeler-color-edge-default)',
          },
        }}
        isValidConnection={(connection) => {
          if (!connection.source) return true;
          // Block new edges from nodes that have reached max successors.
          const sourceNode = nodes.find(n => n.id === connection.source);
          if (!sourceNode?.type || !modelConstraints) return true;
          const sConstraint = modelConstraints[sourceNode.type as keyof ModelConstraints]?.successors;
          if (sConstraint?.max === undefined) return true;
          const outgoing = outgoingEdgeCounts[connection.source] ?? 0;
          return outgoing < sConstraint.max;
        }}
        nodesDraggable={!isLocked}
        nodesConnectable={!isLocked}
        elementsSelectable={true}  // Allow selection even when locked for viewing properties
        selectNodesOnDrag={false}
        // Figma-like gesture scheme:
        // - Wheel/trackpad scroll = pan (smooth, natural for touchpads/Magic Mouse)
        // - Ctrl/Cmd+wheel = zoom
        // - Pinch-to-zoom = zoom (trackpad native)
        // - Left-click+drag on empty canvas = selection rectangle
        // - Middle-click or right-click+drag = pan
        // - Space+drag = pan (hand tool)
        panOnScroll={true}
        panOnScrollMode={PanOnScrollMode.Free}
        panOnScrollSpeed={0.5}
        zoomOnScroll={false}
        zoomOnPinch={true}
        zoomOnDoubleClick={false}
        minZoom={VIEWPORT.MIN_ZOOM}
        maxZoom={VIEWPORT.MAX_ZOOM}
        panOnDrag={[1, 2]}
        selectionOnDrag={!isLocked}
        multiSelectionKeyCode="Shift"
        deleteKeyCode={null} // Handled by useKeyboardShortcuts
        fitView={false}
        attributionPosition="bottom-left"
      >
        {/* MiniMap is always visible in the bottom-left corner */}
        <MiniMap
          style={{
            backgroundColor: 'var(--modeler-color-minimap-bg)',
            border: '1px solid var(--modeler-color-minimap-border)',
          }}
          nodeColor={(node: Node) => {
            if (node.selected) return 'var(--modeler-color-minimap-selected)';
            return 'var(--modeler-color-minimap-node)';
          }}
          maskColor="var(--modeler-color-minimap-mask)"
          position="bottom-left"
          pannable
        />
        {/* Replay condition indicators rendered inside EdgeLabelRenderer so
            they automatically follow canvas pan/zoom transforms. */}
        {replayIndicators.length > 0 && (
          <EdgeLabelRenderer>
            {replayIndicators.map(indicator => (
              <div
                key={indicator.id}
                className="replay-indicator"
                style={{
                  transform: `translate(-50%, -50%) translate(${indicator.x}px, ${indicator.y}px)`,
                  backgroundColor: indicator.color,
                }}
              />
            ))}
          </EdgeLabelRenderer>
        )}
      </ReactFlow>

    </div>
    </Profiler>
  );
};

export default FlowCanvas;