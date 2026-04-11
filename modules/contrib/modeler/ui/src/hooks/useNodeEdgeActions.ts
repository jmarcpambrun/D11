/**
 * Custom hook for adding conditions to edges and event nodes to the canvas.
 * Handles the creation of new nodes and updating edge data when conditions
 * are attached via drag-and-drop or the quick-add popups.
 */
import { useCallback } from 'react';
import { useReactFlow } from 'reactflow';
import { useGraphStore } from '../store/useGraphStore';
import type { StoreNode as Node, StoreComponent } from '../types/settings';
import { generateNodeId } from '../utils/clipboardUtils';

import { LAYOUT, NODE_DIMENSIONS, VIEWPORT } from '../constants/dimensions';
import { findFreePosition } from '../utils/positionUtils';
import { autoLayout } from '../utils/modelUtils';
import { t } from '../utils/translation';

interface UseNodeEdgeActionsProps {
  setHasUnsavedChanges: (value: boolean) => void;
  /** Callback to save history before making changes */
  saveHistory?: () => void;
}

export function useNodeEdgeActions({ setHasUnsavedChanges, saveHistory }: UseNodeEdgeActionsProps) {
  const { setCenter } = useReactFlow();
  const nodes = useGraphStore(state => state.nodes);
  const edges = useGraphStore(state => state.edges);
  const setNodes = useGraphStore(state => state.setNodes);
  const setEdges = useGraphStore(state => state.setEdges);

  // Add condition to edge handler
  const handleAddCondition = useCallback((edgeId: string, component: StoreComponent) => {
    if (saveHistory) saveHistory();
    const conditionLabel = component.label || component.plugin?.split('.').pop() || component.plugin;

    // Build the updated edge data
    const updatedEdgeData = {
      label: conditionLabel,
      type: 'condition' as const,
      data: {
        condition: component.plugin,
        conditionLabel: conditionLabel
      }
    };

    // Build the updated edges with the condition data applied.
    const updatedEdges = edges.map(edge => {
      if (edge.id === edgeId) {
        return {
          ...edge,
          ...updatedEdgeData,
          selected: true,
          data: {
            ...edge.data,
            ...updatedEdgeData.data
          }
        };
      }
      return edge.selected ? { ...edge, selected: false } : edge;
    });

    // Re-run auto-layout so the condition-aware spacing algorithm positions
    // all nodes cleanly, rather than naively shifting descendants.
    const deselectedNodes = nodes.some(n => n.selected)
      ? nodes.map(n => n.selected ? { ...n, selected: false } : n)
      : nodes;
    const layoutedNodes = autoLayout(deselectedNodes, updatedEdges);
    if (layoutedNodes) {
      setNodes(layoutedNodes);
    }

    setEdges(updatedEdges);
    setHasUnsavedChanges(true);
  }, [nodes, edges, setNodes, setEdges, setHasUnsavedChanges, saveHistory]);

  // Add event/start node to canvas handler
  const handleAddEvent = useCallback((component: StoreComponent) => {
    if (saveHistory) saveHistory();
    const label = component.label || component.plugin?.split('.').pop() || t('New Event');
    const newNodeId = generateNodeId(label, 'start');

    // Find a good position for the new event - to the right of existing nodes
    let candidateX: number = LAYOUT.DEFAULT_POSITION_X;
    let candidateY: number = LAYOUT.DEFAULT_POSITION_Y;

    // Check if there are existing nodes and position to the right of them
    if (nodes.length > 0) {
      // Find rightmost position and topmost Y position
      const maxX = Math.max(...nodes.map(n => n.position.x));
      const minY = Math.min(...nodes.map(n => n.position.y));

      // Place new event to the right of existing content
      candidateX = maxX + LAYOUT.NODE_SPACING_X;
      candidateY = minY;
    }

    // Find a free position that doesn't overlap any existing node
    const freePosition = findFreePosition(
      { x: candidateX, y: candidateY },
      nodes,
      NODE_DIMENSIONS.START_NODE_WIDTH,
      NODE_DIMENSIONS.START_NODE_HEIGHT,
    );

    const newNode: Node = {
      id: newNodeId,
      type: 'start',
      position: freePosition,
      selected: true,
      data: {
        plugin: component.plugin,
        label,
        componentType: component.componentType,
        description: component.description,
        documentationUrl: component.documentationUrl,
      },
    };

    // Deselect all existing nodes/edges and add the new node as selected,
    // so that ReactFlow's internal selection stays in sync with the store.
    setNodes(prev => [...prev.map(n => n.selected ? { ...n, selected: false } : n), newNode]);
    setEdges(prev => {
      const hasSelected = prev.some(e => e.selected);
      return hasSelected ? prev.map(e => e.selected ? { ...e, selected: false } : e) : prev;
    });

    // Mark as having unsaved changes
    setHasUnsavedChanges(true);

    // Pan to the new event node
    setCenter(
      freePosition.x + NODE_DIMENSIONS.START_NODE_WIDTH / 2,
      freePosition.y + NODE_DIMENSIONS.START_NODE_HEIGHT / 2,
      { duration: VIEWPORT.PAN_ANIMATION_DURATION }
    );
  }, [nodes, setNodes, setEdges, setHasUnsavedChanges, saveHistory, setCenter]);

  return {
    handleAddCondition,
    handleAddEvent,
  };
}
