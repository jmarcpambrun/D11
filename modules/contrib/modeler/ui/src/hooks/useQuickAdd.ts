/**
 * Custom hook for quick-add functionality
 * Handles creating new nodes and connecting them to existing nodes.
 *
 * All node placement decisions are delegated to the shared incremental
 * layout primitives in utils/incrementalLayout.ts so that quick-add
 * produces visually identical output to auto-layout (issue #3588454).
 */

import { useCallback } from 'react';
import { useGraphStore } from '../store/useGraphStore';
import type { StoreComponent as Component } from '../types/settings';
import { generateNodeId, generateEdgeId } from '../utils/clipboardUtils';

import { LAYOUT, NODE_DIMENSIONS } from '../constants/dimensions';
import { shiftNodesDown } from '../utils/positionUtils';
import {
  computeSuccessorPosition,
  applyFlowShifts,
} from '../utils/incrementalLayout';
import { t } from '../utils/translation';
import type { ViewportActions } from './useViewportActions';

interface UseQuickAddProps {
  setHasUnsavedChanges: (value: boolean) => void;
  /** Callback to save history before making changes */
  saveHistory?: () => void;
  /** Unified viewport actions for pan/zoom operations */
  viewportActions: ViewportActions;
}

export function useQuickAdd({ setHasUnsavedChanges, saveHistory, viewportActions }: UseQuickAddProps) {
  const nodes = useGraphStore(state => state.nodes);
  const edges = useGraphStore(state => state.edges);
  const setNodes = useGraphStore(state => state.setNodes);
  const setEdges = useGraphStore(state => state.setEdges);

  /**
   * Add a new node as a successor to an existing node.
   *
   * Position is computed by the shared {@link computeSuccessorPosition}
   * primitive — same code path as auto-layout.
   */
  const addSuccessorNode = useCallback((component: Component, sourceNodeId: string) => {
    if (saveHistory) saveHistory();
    const sourceNode = nodes.find(n => n.id === sourceNodeId);
    if (!sourceNode) {
      console.error('Source node not found:', sourceNodeId);
      return;
    }

    const { position: newPosition, shiftNodeIds, shiftAmount } = computeSuccessorPosition({
      nodes,
      edges,
      sourceNodeId,
      hasCondition: false,
    });

    // Determine node type from component
    const nodeType = component.type === 'start' ? 'start' :
                     component.type === 'gateway' ? 'gateway' :
                     'element';

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

    const newEdgeId = generateEdgeId(sourceNodeId, newNodeId);
    const newEdge = {
      id: newEdgeId,
      source: sourceNodeId,
      target: newNodeId,
      type: 'default',
      data: {},
    };

    setNodes(prev => {
      const updated = applyFlowShifts(prev, shiftNodeIds, shiftAmount);
      return [...updated, newNode];
    });
    setEdges(prev => [...prev.map(e => e.selected ? { ...e, selected: false } : e), newEdge]);

    setHasUnsavedChanges(true);
    viewportActions.panToNodeIfOffscreen(newNodeId);
  }, [nodes, edges, setNodes, setEdges, setHasUnsavedChanges, saveHistory, viewportActions]);

  /**
   * Add a condition with a placeholder action node.
   *
   * Creates a placeholder node (no plugin assigned) as a successor to the
   * source node, with a connecting edge that already has the selected
   * condition attached.  This supports the condition-first authoring flow
   * where users want to define a condition before choosing an action.
   *
   * Position is computed by the shared {@link computeSuccessorPosition}
   * primitive with `hasCondition: true`, which guarantees the same gap
   * the auto-layout would have produced.
   */
  const addConditionWithPlaceholder = useCallback((component: Component, sourceNodeId: string) => {
    if (saveHistory) saveHistory();

    const sourceNode = nodes.find(n => n.id === sourceNodeId);
    if (!sourceNode) {
      console.error('Source node not found:', sourceNodeId);
      return;
    }

    const { position: newPosition, shiftNodeIds, shiftAmount } = computeSuccessorPosition({
      nodes,
      edges,
      sourceNodeId,
      hasCondition: true,
    });

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

    // Apply horizontal flow shifts for neighboring flows.
    let updatedNodes = applyFlowShifts(nodes, shiftNodeIds, shiftAmount);

    // Then shift everything at or below the placeholder's Y downward so
    // nothing overlaps the newly added placeholder + condition card.
    const placeholderBottom = newPosition.y + NODE_DIMENSIONS.CARD_HEIGHT;
    const verticalShift = placeholderBottom + LAYOUT.NODE_SPACING_Y;
    updatedNodes = shiftNodesDown(
      updatedNodes,
      newPosition.y,
      Math.max(0, verticalShift - newPosition.y),
      new Set([sourceNodeId]),
    );

    const allNodes = [...updatedNodes, placeholderNode];
    const allEdges = [
      ...edges.map(e => e.selected ? { ...e, selected: false } : e),
      newEdge,
    ];

    setNodes(allNodes);
    setEdges(allEdges);
    setHasUnsavedChanges(true);

    // Pan to show both the source node and the new placeholder node so the
    // condition edge connecting them is fully visible.
    viewportActions.fitToNodePair(sourceNodeId, placeholderNodeId);
  }, [nodes, edges, setNodes, setEdges, setHasUnsavedChanges, saveHistory, viewportActions]);

  return {
    addSuccessorNode,
    addConditionWithPlaceholder,
  };
}
