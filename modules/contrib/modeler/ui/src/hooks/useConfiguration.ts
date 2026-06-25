/**
 * Custom hook for node and edge configuration management
 * Handles configuration changes, updates, and auto-layout functionality
 */

import { useCallback } from 'react';
import { useGraphStore } from '../store/useGraphStore';
import { useSelectionStore } from '../store/useSelectionStore';
import { autoLayout } from '../utils/modelUtils';
import { getEdgeType } from '../utils/edgeTypeUtils';
import type { NodeData, EdgeData } from '../types/settings';

interface UseConfigurationProps {
  setHasUnsavedChanges: (value: boolean) => void;
  /** Callback to save history before making changes */
  saveHistory?: () => void;
}

export function useConfiguration({ setHasUnsavedChanges, saveHistory }: UseConfigurationProps) {
  const nodes = useGraphStore(state => state.nodes);
  const edges = useGraphStore(state => state.edges);
  const setNodes = useGraphStore(state => state.setNodes);
  const setEdges = useGraphStore(state => state.setEdges);
  const selectedNode = useSelectionStore(state => state.selectedNode);
  const setSelectedNode = useSelectionStore(state => state.setSelectedNode);

  // Configuration change handler - updates node configuration (primary callback)
  const onConfigurationChange = useCallback((nodeId: string, configuration: Record<string, unknown>) => {
    if (saveHistory) saveHistory();
    setNodes(prev => prev.map(node => 
      node.id === nodeId 
        ? { 
            ...node, 
            data: { 
              ...(node.data || {}), // Ensure data is never undefined
              // Store configuration in the configuration object
              configuration: { ...(node.data?.configuration || {}), ...configuration },
              // Also map _componentLabel to label for display
              ...(configuration._componentLabel ? { label: String(configuration._componentLabel) } : {}),
            }
          }
        : node
    ));
    
    // Update selected node to reflect changes in property panel
    if (selectedNode?.id === nodeId) {
      setTimeout(() => {
        const updatedNode = nodes.find(n => n.id === nodeId);
        if (updatedNode) {
          setSelectedNode(updatedNode);
        }
      }, 10);
    }
    
    setHasUnsavedChanges(true);
  }, [setNodes, selectedNode, setSelectedNode, setHasUnsavedChanges, nodes, saveHistory]);

  // Node update handler (for broader updates than just config)
  const onNodeUpdate = useCallback((nodeId: string, newData: Partial<NodeData>) => {
    if (saveHistory) saveHistory();
    setNodes(prev => prev.map(node => 
      node.id === nodeId 
        ? { ...node, data: { ...node.data, ...newData } }
        : node
    ));
    setHasUnsavedChanges(true);
  }, [setNodes, setHasUnsavedChanges, saveHistory]);

  // Edge update handler (for broader updates than just config)
  const onEdgeUpdate = useCallback((edgeId: string, newData: Partial<EdgeData>) => {
    if (saveHistory) saveHistory();
    setEdges(prev => prev.map(edge => 
      edge.id === edgeId 
        ? { 
            ...edge, 
            data: { ...edge.data, ...newData }, // Merge with existing data instead of replacing
            // Update edge type based on merged data
            type: getEdgeType({ ...edge.data, ...newData })
          }
        : edge
    ));
    setHasUnsavedChanges(true);
  }, [setEdges, setHasUnsavedChanges, saveHistory]);

  // Endpoint reconnection handler (issue #3585553).
  //
  // Unlike onEdgeUpdate (which merges into edge `.data`), reconnection changes
  // TOP-LEVEL edge fields — `source`, `target`, `sourceHandle`, `targetHandle`.
  // A pure reconnect does NOT change edge data, so the edge `type` is left
  // untouched (no getEdgeType recompute). saveHistory() runs first so undo/redo
  // and unsaved-changes tracking work exactly like other mutations. Only the
  // fields present in `updates` are applied; the unchanged end is preserved.
  const onReconnectEdge = useCallback(
    (
      edgeId: string,
      updates: {
        source?: string;
        sourceHandle?: string | null;
        target?: string;
        targetHandle?: string | null;
      },
    ) => {
      if (saveHistory) saveHistory();
      setEdges(prev =>
        prev.map(edge =>
          edge.id === edgeId
            ? {
                ...edge,
                ...(updates.source !== undefined ? { source: updates.source } : {}),
                ...(updates.target !== undefined ? { target: updates.target } : {}),
                ...(updates.sourceHandle !== undefined ? { sourceHandle: updates.sourceHandle } : {}),
                ...(updates.targetHandle !== undefined ? { targetHandle: updates.targetHandle } : {}),
              }
            : edge,
        ),
      );
      setHasUnsavedChanges(true);
    },
    [setEdges, setHasUnsavedChanges, saveHistory],
  );

  // Auto-layout handler
  const handleAutoLayout = useCallback(() => {
    if (saveHistory) saveHistory();
    const layoutedNodes = autoLayout(nodes, edges);
    if (layoutedNodes) {
      setNodes(layoutedNodes);
      setHasUnsavedChanges(true);
    }
  }, [nodes, edges, setNodes, setHasUnsavedChanges, saveHistory]);

  return {
    onConfigurationChange,
    onNodeUpdate,
    onEdgeUpdate,
    onReconnectEdge,
    handleAutoLayout
  };
}