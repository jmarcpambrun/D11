/**
 * Custom hook for node and edge configuration management
 * Handles configuration changes, updates, and auto-layout functionality
 */

import { useCallback } from 'react';
import { useGraphStore } from '../store/useGraphStore';
import { useSelectionStore } from '../store/useSelectionStore';
import { autoLayout } from '../utils/modelUtils';
import { getEdgeType, getEdgeTypeWithCondition } from '../utils/edgeTypeUtils';
import type { StoreEdge as Edge, NodeData, EdgeData } from '../types/settings';

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
  const selectedEdge = useSelectionStore(state => state.selectedEdge);
  const setSelectedEdge = useSelectionStore(state => state.setSelectedEdge);

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

  // Edge configuration change handler
  const onEdgeConfigurationChange = useCallback((edgeId: string, configurationOrCallback: Record<string, unknown> | null | ((edge: Edge) => Record<string, unknown> | null)) => {
    if (saveHistory) saveHistory();

    // Check if the condition is being removed so we can re-layout.
    const targetEdge = edges.find(e => e.id === edgeId);
    const resolvedConfig = targetEdge && typeof configurationOrCallback === 'function'
      ? configurationOrCallback(targetEdge)
      : configurationOrCallback;
    const hadCondition = targetEdge && !!(
      targetEdge.data?.condition ||
      targetEdge.data?.conditionLabel
    );
    const isRemoving = resolvedConfig === null;

    // Helper to compute the updated edge.
    const computeUpdatedEdge = (edge: Edge): Edge => {
      const configuration = typeof configurationOrCallback === 'function'
        ? configurationOrCallback(edge)
        : configurationOrCallback;

      const rawConditionLabel = configuration?._conditionLabel ?? configuration?.conditionLabel;
      const newConditionLabel = rawConditionLabel != null ? String(rawConditionLabel) : undefined;
      const { _conditionLabel: _, conditionLabel: __, ...restConfiguration } = configuration || {};

      const newData = configuration
        ? {
            ...edge.data,
            conditionConfiguration: { ...edge.data?.conditionConfiguration, ...restConfiguration },
            ...(newConditionLabel !== undefined ? { conditionLabel: newConditionLabel } : {}),
          }
        : {
            ...edge.data,
            condition: null,
            conditionLabel: null,
            conditionConfiguration: null,
            annotation: null,
            isAnnotationVisible: false,
          };

      return {
        ...edge,
        ...(configuration
          ? (newConditionLabel !== undefined ? { label: newConditionLabel } : {})
          : { label: '' }),
        type: getEdgeTypeWithCondition(configuration ? 'has-config' : null, newData),
        data: newData
      };
    };

    const updatedEdges = edges.map(edge =>
      edge.id === edgeId ? computeUpdatedEdge(edge) : edge,
    );

    // When a condition is removed, re-run auto-layout with the updated edges
    // so the spacing algorithm reclaims the extra space cleanly.
    if (hadCondition && isRemoving && targetEdge) {
      const layoutedNodes = autoLayout(nodes, updatedEdges);
      if (layoutedNodes) {
        setNodes(layoutedNodes);
      }
    }

    setEdges(updatedEdges);

    // Also update selectedEdge if it's the one being changed
    if (selectedEdge?.id === edgeId && setSelectedEdge) {
      setTimeout(() => {
        const updatedEdge = edges.find(e => e.id === edgeId);
        if (updatedEdge) {
          setSelectedEdge(updatedEdge);
        }
      }, 10);
    }
    
    setHasUnsavedChanges(true);
  }, [nodes, setNodes, setEdges, selectedEdge, setSelectedEdge, edges, setHasUnsavedChanges, saveHistory]);

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
    onEdgeConfigurationChange,
    onNodeUpdate,
    onEdgeUpdate,
    handleAutoLayout
  };
}