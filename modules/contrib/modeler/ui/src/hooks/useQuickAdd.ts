/**
 * Custom hook for quick-add functionality
 * Handles creating new nodes and connecting them to existing nodes
 */

import { useCallback } from 'react';
import { useReactFlow } from 'reactflow';
import type { Node } from 'reactflow';
import { useGraphStore } from '../store/useGraphStore';
import type { StoreComponent as Component } from '../types/settings';
import { generateNodeId, generateEdgeId } from '../utils/clipboardUtils';

import { LAYOUT, NODE_DIMENSIONS, VIEWPORT } from '../constants/dimensions';
import { findFlowAwarePosition } from '../utils/positionUtils';
import { autoLayout } from '../utils/modelUtils';
import { t } from '../utils/translation';

interface UseQuickAddProps {
  setHasUnsavedChanges: (value: boolean) => void;
  /** Callback to save history before making changes */
  saveHistory?: () => void;
}

export function useQuickAdd({ setHasUnsavedChanges, saveHistory }: UseQuickAddProps) {
  const { setCenter, fitView } = useReactFlow();
  const nodes = useGraphStore(state => state.nodes);
  const edges = useGraphStore(state => state.edges);
  const setNodes = useGraphStore(state => state.setNodes);
  const setEdges = useGraphStore(state => state.setEdges);

  /**
   * Add a new node as a successor to an existing node
   */
  const addSuccessorNode = useCallback((component: Component, sourceNodeId: string) => {
    if (saveHistory) saveHistory();
    // Find the source node
    const sourceNode = nodes.find(n => n.id === sourceNodeId);
    if (!sourceNode) {
      console.error('Source node not found:', sourceNodeId);
      return;
    }

    // Calculate position for the new node (below the source node)
    const sourceWidth = sourceNode.width || NODE_DIMENSIONS.DEFAULT_WIDTH;
    const sourceHeight = sourceNode.height || NODE_DIMENSIONS.DEFAULT_HEIGHT;
    
    const candidatePosition = {
      x: sourceNode.position.x + (sourceWidth / 2) - (NODE_DIMENSIONS.DEFAULT_WIDTH / 2),
      y: sourceNode.position.y + sourceHeight + LAYOUT.NODE_SPACING_Y
    };

    // Find a free position that respects flow boundaries.
    // If the position would cross into a neighboring flow, the result
    // includes a set of node IDs to shift right to create room.
    const { position: newPosition, shiftNodeIds, shiftAmount } = findFlowAwarePosition(
      candidatePosition,
      sourceNodeId,
      nodes,
      edges,
    );

    // Determine node type from component
    const nodeType = component.type === 'start' ? 'start' : 
                     component.type === 'gateway' ? 'gateway' : 
                     'element';
    
    // Create the new node
    const label = component.label || component.plugin?.split('.').pop() || 'New Node';
    const newNodeId = generateNodeId(label, nodeType);
    
    const newNode = {
      id: newNodeId,
      type: nodeType,
      position: newPosition,
      selected: true,
      data: {
        plugin: component.plugin,
        label,
        componentType: component.componentType,
        description: component.description,
        documentationUrl: component.documentationUrl,
      },
    };

    // Create the edge connecting source to new node
    const newEdgeId = generateEdgeId(sourceNodeId, newNodeId);
    const newEdge = {
      id: newEdgeId,
      source: sourceNodeId,
      target: newNodeId,
      type: 'default',
      data: {},
    };

    // Deselect all existing nodes/edges and add the new node as selected.
    // If neighboring flows need shifting, apply the shift in the same update.
    // ReactFlow's onSelectionChange will update the store's selection state.
    setNodes(prev => {
      const updated = prev.map(n => {
        let node = n.selected ? { ...n, selected: false } : n;
        // Shift nodes in neighboring flows to make room
        if (shiftAmount > 0 && n.id && shiftNodeIds.has(n.id)) {
          node = node === n ? { ...n } : node;
          node.position = { x: node.position.x + shiftAmount, y: node.position.y };
        }
        return node;
      });
      return [...updated, newNode];
    });
    setEdges(prev => [...prev.map(e => e.selected ? { ...e, selected: false } : e), newEdge]);
    
    // Mark as having unsaved changes
    setHasUnsavedChanges(true);

    // Pan to the new node
    const width = nodeType === 'start' ? NODE_DIMENSIONS.START_NODE_WIDTH : NODE_DIMENSIONS.DEFAULT_WIDTH;
    const height = nodeType === 'start' ? NODE_DIMENSIONS.START_NODE_HEIGHT : NODE_DIMENSIONS.DEFAULT_HEIGHT;

    setCenter(
      newPosition.x + width / 2,
      newPosition.y + height / 2,
      { duration: VIEWPORT.PAN_ANIMATION_DURATION }
    );
  }, [nodes, edges, setNodes, setEdges, setHasUnsavedChanges, saveHistory, setCenter]);

  /**
   * Add a condition with a placeholder action node.
   *
   * Creates a placeholder node (no plugin assigned) as a successor to the
   * source node, with a connecting edge that already has the selected
   * condition attached.  This supports the condition-first authoring flow
   * where users want to define a condition before choosing an action.
   */
  const addConditionWithPlaceholder = useCallback((component: Component, sourceNodeId: string) => {
    if (saveHistory) saveHistory();

    const sourceNode = nodes.find(n => n.id === sourceNodeId);
    if (!sourceNode) {
      console.error('Source node not found:', sourceNodeId);
      return;
    }

    // Calculate position for the placeholder node (below the source node)
    const sourceWidth = sourceNode.width || NODE_DIMENSIONS.DEFAULT_WIDTH;
    const sourceHeight = sourceNode.height || NODE_DIMENSIONS.DEFAULT_HEIGHT;

    const candidatePosition = {
      x: sourceNode.position.x + (sourceWidth / 2) - (NODE_DIMENSIONS.DEFAULT_WIDTH / 2),
      y: sourceNode.position.y + sourceHeight + LAYOUT.NODE_SPACING_Y,
    };

    const { position: newPosition, shiftNodeIds, shiftAmount } = findFlowAwarePosition(
      candidatePosition,
      sourceNodeId,
      nodes,
      edges,
    );

    // Create the placeholder node — uses the 'placeholder' node type so it
    // gets a distinct visual treatment and requires the user to pick a real
    // action/gateway before the model can be saved.
    const placeholderLabel = t('Select action...');
    const placeholderNodeId = generateNodeId(placeholderLabel, 'placeholder');

    const placeholderNode = {
      id: placeholderNodeId,
      type: 'placeholder' as const,
      position: newPosition,
      selected: false,
      data: {
        label: placeholderLabel,
      },
    };

    // Create the edge with the condition already attached and selected,
    // so the property panel opens on the condition for immediate configuration.
    const conditionLabel = component.label || component.plugin?.split('.').pop() || component.plugin;
    const newEdgeId = generateEdgeId(sourceNodeId, placeholderNodeId);
    const newEdge = {
      id: newEdgeId,
      source: sourceNodeId,
      target: placeholderNodeId,
      type: 'condition' as const,
      label: conditionLabel,
      selected: true,
      data: {
        condition: component.plugin,
        conditionLabel,
      },
    };

    // Build the updated node list (deselect existing, shift neighbors, add placeholder).
    const updatedNodes = nodes.map(n => {
      let node = n.selected ? { ...n, selected: false } : n;
      if (shiftAmount > 0 && n.id && shiftNodeIds.has(n.id)) {
        node = node === n ? { ...n } : node;
        node.position = { x: node.position.x + shiftAmount, y: node.position.y };
      }
      return node;
    });

    const allNodes = [...updatedNodes, placeholderNode];
    const allEdges = [
      ...edges.map(e => e.selected ? { ...e, selected: false } : e),
      newEdge,
    ];

    // Re-run auto-layout so the condition-aware spacing algorithm positions
    // all nodes cleanly.
    const layoutedNodes = autoLayout(allNodes, allEdges);
    setNodes(layoutedNodes || allNodes);
    setEdges(allEdges);

    setHasUnsavedChanges(true);

    // Pan to show both the source node and the new placeholder node so the
    // condition edge connecting them is fully visible.
    const finalNodes = layoutedNodes || allNodes;
    const fitNodes: Node[] = finalNodes.filter(
      n => n.id === sourceNodeId || n.id === placeholderNodeId
    );

    // Use fitView scoped to the two relevant nodes.  maxZoom prevents the
    // viewport from zooming in too far when both nodes are close together.
    fitView({
      nodes: fitNodes,
      duration: VIEWPORT.PAN_ANIMATION_DURATION,
      padding: VIEWPORT.FIT_VIEW_PADDING,
      maxZoom: VIEWPORT.FIT_VIEW_ZOOM,
    });
  }, [nodes, edges, setNodes, setEdges, setHasUnsavedChanges, saveHistory, fitView]);

  return {
    addSuccessorNode,
    addConditionWithPlaceholder,
  };
}
