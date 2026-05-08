/**
 * Custom hook for adding conditions to edges, inserting action nodes on
 * edges, and adding event nodes to the canvas.
 *
 * All node placement decisions are delegated to the shared incremental
 * layout primitives in utils/incrementalLayout.ts so that interactive
 * edits produce visually identical output to auto-layout (see issue
 * #3588454).
 */
import { useCallback } from 'react';
import { useGraphStore } from '../store/useGraphStore';
import type { StoreNode as Node, StoreEdge as Edge, StoreComponent } from '../types/settings';
import { generateNodeId, generateEdgeId } from '../utils/clipboardUtils';

import { NODE_DIMENSIONS } from '../constants/dimensions';
import {
  computeNewEventPosition,
  ensureGapForCondition,
  placeNodeOnEdge,
} from '../utils/incrementalLayout';
import { routeParallelEdge, applyParallelEdgeRouting } from '../utils/parallelEdgeRouter';
import { t } from '../utils/translation';
import type { ViewportActions } from './useViewportActions';

interface UseNodeEdgeActionsProps {
  setHasUnsavedChanges: (value: boolean) => void;
  /** Callback to save history before making changes */
  saveHistory?: () => void;
  /** Unified viewport actions for pan/zoom operations */
  viewportActions: ViewportActions;
}

export function useNodeEdgeActions({ setHasUnsavedChanges, saveHistory, viewportActions }: UseNodeEdgeActionsProps) {
  const nodes = useGraphStore(state => state.nodes);
  const edges = useGraphStore(state => state.edges);
  const setNodes = useGraphStore(state => state.setNodes);
  const setEdges = useGraphStore(state => state.setEdges);

  // ── Add a condition to an existing edge ───────────────────────────
  const handleAddCondition = useCallback((edgeId: string, component: StoreComponent) => {
    if (saveHistory) saveHistory();
    const conditionLabel = component.label || component.plugin?.split('.').pop() || component.plugin;

    const updatedEdgeData = {
      label: conditionLabel,
      type: 'condition' as const,
      data: {
        condition: component.plugin,
        conditionLabel: conditionLabel
      }
    };

    let updatedEdges = edges.map(edge => {
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

    // Re-route parallel edges now that this edge carries a condition.
    // The router will redistribute offsets to account for the condition
    // card's width (issue #3588937).
    const targetEdge = updatedEdges.find(e => e.id === edgeId);
    if (targetEdge) {
      const routeResult = routeParallelEdge({
        newEdge: targetEdge,
        edges: updatedEdges,
        nodes,
      });
      updatedEdges = applyParallelEdgeRouting(updatedEdges, routeResult.updates);
    }

    // Shift the target node (and everything below it) downward to make
    // room for the condition card — same primitive used by auto-layout.
    let updatedNodes = nodes.some(n => n.selected)
      ? nodes.map(n => n.selected ? { ...n, selected: false } : n)
      : [...nodes];

    if (targetEdge) {
      updatedNodes = ensureGapForCondition(updatedNodes, targetEdge.source, targetEdge.target);
    }

    setNodes(updatedNodes);
    setEdges(updatedEdges);
    setHasUnsavedChanges(true);
  }, [nodes, edges, setNodes, setEdges, setHasUnsavedChanges, saveHistory]);

  // ── Add an event/start node to the canvas ─────────────────────────
  const handleAddEvent = useCallback((component: StoreComponent) => {
    if (saveHistory) saveHistory();
    const label = component.label || component.plugin?.split('.').pop() || t('New Event');
    const newNodeId = generateNodeId(label, 'start');

    // Shared primitive places the new event flow to the right of all
    // existing flows — identical behavior to auto-layout's start-node
    // reservation logic.
    const freePosition = computeNewEventPosition({
      nodes,
      width: NODE_DIMENSIONS.START_NODE_WIDTH,
      height: NODE_DIMENSIONS.START_NODE_HEIGHT,
    });

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

    setNodes(prev => [...prev.map(n => n.selected ? { ...n, selected: false } : n), newNode]);
    setEdges(prev => {
      const hasSelected = prev.some(e => e.selected);
      return hasSelected ? prev.map(e => e.selected ? { ...e, selected: false } : e) : prev;
    });

    setHasUnsavedChanges(true);
    viewportActions.panToNodeIfOffscreen(newNodeId);
  }, [nodes, setNodes, setEdges, setHasUnsavedChanges, saveHistory, viewportActions]);

  // ── Insert an action/gateway node on an existing edge ─────────────
  const handleAddActionOnEdge = useCallback((edgeId: string, component: StoreComponent) => {
    if (saveHistory) saveHistory();

    const targetEdge = edges.find(e => e.id === edgeId);
    if (!targetEdge) {
      console.error('Edge not found:', edgeId);
      return;
    }

    const sourceNodeId = targetEdge.source;
    const targetNodeId = targetEdge.target;

    const nodeType = component.type === 'gateway' ? 'gateway' : 'element';
    const label = component.label || component.plugin?.split('.').pop() || t('New Node');
    const newNodeId = generateNodeId(label, nodeType);

    const newNode: Node = {
      id: newNodeId,
      type: nodeType,
      // Temporary position — placeNodeOnEdge will replace it.
      position: { x: 0, y: 0 },
      selected: true,
      data: {
        plugin: component.plugin,
        label,
        componentType: component.componentType,
        description: component.description,
        documentationUrl: component.documentationUrl,
      },
    };

    const edgeToNew: Edge = {
      id: generateEdgeId(sourceNodeId, newNodeId),
      source: sourceNodeId,
      target: newNodeId,
      type: 'default' as const,
      data: {},
    };

    const edgeFromNew: Edge = {
      id: generateEdgeId(newNodeId, targetNodeId),
      source: newNodeId,
      target: targetNodeId,
      type: 'default' as const,
      data: {},
    };

    const allEdges = [
      ...edges.filter(e => e.id !== edgeId).map(e => e.selected ? { ...e, selected: false } : e),
      edgeToNew,
      edgeFromNew,
    ];

    const deselectedNodes = nodes.map(n => n.selected ? { ...n, selected: false } : n);
    const positionedNodes = placeNodeOnEdge(
      deselectedNodes,
      allEdges,
      newNode,
      sourceNodeId,
      targetNodeId,
      false, // edgeToNew has no condition
      false, // edgeFromNew has no condition
    );

    setNodes(positionedNodes);
    setEdges(allEdges);
    setHasUnsavedChanges(true);
    viewportActions.panToNodeIfOffscreen(newNodeId);
  }, [nodes, edges, setNodes, setEdges, setHasUnsavedChanges, saveHistory, viewportActions]);

  /**
   * Insert a component on a condition edge BEFORE the condition card.
   *
   * - **Action/gateway selected**: Insert the new node between source and the
   *   existing target.  The original condition moves to the edge between the
   *   new node and the original target.  The edge from source to the new node
   *   becomes a plain default edge.
   *
   * - **Condition selected**: Since only one condition per edge is allowed, we
   *   insert a gateway node.  The original condition stays on the edge from
   *   the gateway to the original target.  A new branch edge (gateway →
   *   placeholder) carries the newly selected condition.  The edge from source
   *   to gateway is a plain default edge.
   *
   * Positioning uses the same spacing primitives as auto-layout, then shifts
   * everything at or below the target node downward to create room.
   */
  const handleInsertBeforeCondition = useCallback((edgeId: string, component: StoreComponent) => {
    if (saveHistory) saveHistory();

    const targetEdge = edges.find(e => e.id === edgeId);
    if (!targetEdge) {
      console.error('Edge not found:', edgeId);
      return;
    }

    const sourceNodeId = targetEdge.source;
    const targetNodeId = targetEdge.target;

    // Preserve the original condition data from the edge.
    // targetEdge.label is a ReactNode; coerce to string for EdgeData compatibility.
    const origCondition = targetEdge.data?.condition;
    const origConditionLabel = String(targetEdge.label ?? targetEdge.data?.conditionLabel ?? '');
    const origConditionConfig = targetEdge.data?.conditionConfiguration;

    if (component.type === 'link') {
      // ── Condition selected → insert gateway in a linear chain ──
      // "Before" means the new condition goes on the source→gateway edge
      // and the original condition goes on the gateway→target edge:
      //   source --[new condition]--> gateway --[orig condition]--> target
      //
      // Both edges carry conditions, so use condition-aware gaps.
      const gatewayLabel = t('Gateway');
      const gatewayNodeId = generateNodeId(gatewayLabel, 'gateway');

      const gatewayNode: Node = {
        id: gatewayNodeId,
        type: 'gateway',
        position: { x: 0, y: 0 }, // placeNodeOnEdge will assign
        selected: true,
        data: {
          label: gatewayLabel,
          plugin: 'gateway',
          componentType: 6,
        },
      };

      const newConditionLabel = component.label || component.plugin?.split('.').pop() || component.plugin;
      const edgeSourceToGateway: Edge = {
        id: generateEdgeId(sourceNodeId, gatewayNodeId),
        source: sourceNodeId,
        target: gatewayNodeId,
        type: 'condition' as const,
        label: newConditionLabel,
        selected: true,
        data: {
          condition: component.plugin,
          conditionLabel: newConditionLabel,
        },
      };

      const edgeGatewayToTarget: Edge = {
        id: generateEdgeId(gatewayNodeId, targetNodeId),
        source: gatewayNodeId,
        target: targetNodeId,
        type: 'condition' as const,
        label: origConditionLabel,
        data: {
          condition: origCondition,
          conditionLabel: origConditionLabel,
          conditionConfiguration: origConditionConfig,
        },
      };

      const allEdges = [
        ...edges.filter(e => e.id !== edgeId).map(e => e.selected ? { ...e, selected: false } : e),
        edgeSourceToGateway,
        edgeGatewayToTarget,
      ];

      const deselectedNodes = nodes.map(n => n.selected ? { ...n, selected: false } : n);
      const positionedNodes = placeNodeOnEdge(
        deselectedNodes,
        allEdges,
        gatewayNode,
        sourceNodeId,
        targetNodeId,
        true, // both edges carry conditions
        true,
      );

      setNodes(positionedNodes);
      setEdges(allEdges);
    } else {
      // ── Action/gateway selected → insert node, condition moves to second edge ──
      // source → newNode (plain) then newNode -[condition]→ target
      const nodeType = component.type === 'gateway' ? 'gateway' : 'element';
      const label = component.label || component.plugin?.split('.').pop() || t('New Node');
      const newNodeId = generateNodeId(label, nodeType);

      const newNode: Node = {
        id: newNodeId,
        type: nodeType,
        position: { x: 0, y: 0 },
        selected: true,
        data: {
          plugin: component.plugin,
          label,
          componentType: component.componentType,
          description: component.description,
          documentationUrl: component.documentationUrl,
        },
      };

      const edgeToNew: Edge = {
        id: generateEdgeId(sourceNodeId, newNodeId),
        source: sourceNodeId,
        target: newNodeId,
        type: 'default' as const,
        data: {},
      };

      const edgeFromNew: Edge = {
        id: generateEdgeId(newNodeId, targetNodeId),
        source: newNodeId,
        target: targetNodeId,
        type: 'condition' as const,
        label: origConditionLabel,
        data: {
          condition: origCondition,
          conditionLabel: origConditionLabel,
          conditionConfiguration: origConditionConfig,
        },
      };

      const allEdges = [
        ...edges.filter(e => e.id !== edgeId).map(e => e.selected ? { ...e, selected: false } : e),
        edgeToNew,
        edgeFromNew,
      ];

      const deselectedNodes = nodes.map(n => n.selected ? { ...n, selected: false } : n);
      const positionedNodes = placeNodeOnEdge(
        deselectedNodes,
        allEdges,
        newNode,
        sourceNodeId,
        targetNodeId,
        false, // edgeToNew is plain
        true,  // edgeFromNew carries the condition
      );

      setNodes(positionedNodes);
      setEdges(allEdges);
      viewportActions.panToNodeIfOffscreen(newNodeId);
    }

    setHasUnsavedChanges(true);
  }, [nodes, edges, setNodes, setEdges, setHasUnsavedChanges, saveHistory, viewportActions]);

  /**
   * Insert a component on a condition edge AFTER the condition card.
   *
   * - **Action/gateway selected**: Insert the new node between source and the
   *   existing target.  The original condition stays on the edge from source to
   *   the new node.  The edge from the new node to the original target becomes
   *   a plain default edge.
   *
   * - **Condition selected**: Insert a gateway node.  The original condition
   *   stays on the edge from source to gateway.  The edge from gateway to the
   *   original target carries the new condition.
   *
   * Positioning uses the same spacing primitives as auto-layout, then shifts
   * everything at or below the target node downward to create room.
   */
  const handleInsertAfterCondition = useCallback((edgeId: string, component: StoreComponent) => {
    if (saveHistory) saveHistory();

    const targetEdge = edges.find(e => e.id === edgeId);
    if (!targetEdge) {
      console.error('Edge not found:', edgeId);
      return;
    }

    const sourceNodeId = targetEdge.source;
    const targetNodeId = targetEdge.target;

    // Preserve the original condition data from the edge.
    const origCondition = targetEdge.data?.condition;
    const origConditionLabel = String(targetEdge.label ?? targetEdge.data?.conditionLabel ?? '');
    const origConditionConfig = targetEdge.data?.conditionConfiguration;

    if (component.type === 'link') {
      // ── Condition selected → insert gateway in a linear chain ──
      //   source --[orig condition]--> gateway --[new condition]--> target
      const gatewayLabel = t('Gateway');
      const gatewayNodeId = generateNodeId(gatewayLabel, 'gateway');

      const gatewayNode: Node = {
        id: gatewayNodeId,
        type: 'gateway',
        position: { x: 0, y: 0 },
        selected: true,
        data: {
          label: gatewayLabel,
          plugin: 'gateway',
          componentType: 6,
        },
      };

      const edgeSourceToGateway: Edge = {
        id: generateEdgeId(sourceNodeId, gatewayNodeId),
        source: sourceNodeId,
        target: gatewayNodeId,
        type: 'condition' as const,
        label: origConditionLabel,
        data: {
          condition: origCondition,
          conditionLabel: origConditionLabel,
          conditionConfiguration: origConditionConfig,
        },
      };

      const newConditionLabel = component.label || component.plugin?.split('.').pop() || component.plugin;
      const edgeGatewayToTarget: Edge = {
        id: generateEdgeId(gatewayNodeId, targetNodeId),
        source: gatewayNodeId,
        target: targetNodeId,
        type: 'condition' as const,
        label: newConditionLabel,
        selected: true,
        data: {
          condition: component.plugin,
          conditionLabel: newConditionLabel,
        },
      };

      const allEdges = [
        ...edges.filter(e => e.id !== edgeId).map(e => e.selected ? { ...e, selected: false } : e),
        edgeSourceToGateway,
        edgeGatewayToTarget,
      ];

      const deselectedNodes = nodes.map(n => n.selected ? { ...n, selected: false } : n);
      const positionedNodes = placeNodeOnEdge(
        deselectedNodes,
        allEdges,
        gatewayNode,
        sourceNodeId,
        targetNodeId,
        true, // both edges carry conditions
        true,
      );

      setNodes(positionedNodes);
      setEdges(allEdges);
    } else {
      // ── Action/gateway selected → insert node, condition stays on first edge ──
      // source -[condition]→ newNode (plain edge) then newNode → target
      const nodeType = component.type === 'gateway' ? 'gateway' : 'element';
      const label = component.label || component.plugin?.split('.').pop() || t('New Node');
      const newNodeId = generateNodeId(label, nodeType);

      const newNode: Node = {
        id: newNodeId,
        type: nodeType,
        position: { x: 0, y: 0 },
        selected: true,
        data: {
          plugin: component.plugin,
          label,
          componentType: component.componentType,
          description: component.description,
          documentationUrl: component.documentationUrl,
        },
      };

      const edgeToNew: Edge = {
        id: generateEdgeId(sourceNodeId, newNodeId),
        source: sourceNodeId,
        target: newNodeId,
        type: 'condition' as const,
        label: origConditionLabel,
        data: {
          condition: origCondition,
          conditionLabel: origConditionLabel,
          conditionConfiguration: origConditionConfig,
        },
      };

      const edgeFromNew: Edge = {
        id: generateEdgeId(newNodeId, targetNodeId),
        source: newNodeId,
        target: targetNodeId,
        type: 'default' as const,
        data: {},
      };

      const allEdges = [
        ...edges.filter(e => e.id !== edgeId).map(e => e.selected ? { ...e, selected: false } : e),
        edgeToNew,
        edgeFromNew,
      ];

      const deselectedNodes = nodes.map(n => n.selected ? { ...n, selected: false } : n);
      const positionedNodes = placeNodeOnEdge(
        deselectedNodes,
        allEdges,
        newNode,
        sourceNodeId,
        targetNodeId,
        true,  // edgeToNew carries condition
        false, // edgeFromNew is plain
      );

      setNodes(positionedNodes);
      setEdges(allEdges);
      viewportActions.panToNodeIfOffscreen(newNodeId);
    }

    setHasUnsavedChanges(true);
  }, [nodes, edges, setNodes, setEdges, setHasUnsavedChanges, saveHistory, viewportActions]);

  return {
    handleAddCondition,
    handleAddEvent,
    handleAddActionOnEdge,
    handleInsertBeforeCondition,
    handleInsertAfterCondition,
  };
}
