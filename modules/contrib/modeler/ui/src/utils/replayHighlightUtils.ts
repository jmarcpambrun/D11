/**
 * replayHighlightUtils - Pure functions for replay step highlighting
 * 
 * Provides node/edge state transformations for applying and clearing
 * replay visual highlights. These are pure functions (no React hooks)
 * that transform node/edge arrays, shared by useSimpleReplaySync
 * and useReplayController.
 */

import { Node, Edge } from 'reactflow';
import { ReplayStep, isNodeExecutionStep, isSuccessorStep, isAccessDeniedStep, isExceptionStep, isConditionStep } from './replayStepUtils';

// ============ Clear Highlights ============

/** Remove all replay-related highlighting from nodes */
export function clearNodeHighlights(nodes: Node[]): Node[] {
  return nodes.map(n => ({
    ...n,
    selected: false,
    data: { ...n.data, highlighted: false },
  }));
}

/** Remove all replay-related highlighting from edges */
export function clearEdgeHighlights(edges: Edge[]): Edge[] {
  return edges.map(e => ({
    ...e,
    selected: false,
    data: {
      ...e.data,
      highlighted: false,
      replayHighlight: undefined,
      fromReplay: false,
    },
    style: e.style ? { ...e.style, stroke: undefined, strokeWidth: undefined } : undefined,
    animated: false,
    className: e.className?.replace(/replay-highlighted|replay-add|replay-ignore/g, '').trim() || undefined,
  }));
}

// ============ Apply Highlights ============

/** Apply replay highlighting to a specific node */
export function applyNodeHighlight(
  nodes: Node[],
  nodeId: string,
  isAccessDenied: boolean
): Node[] {
  return nodes.map(n =>
    n.id === nodeId
      ? {
          ...n,
          selected: true,
          data: {
            ...n.data,
            highlighted: true,
            fromReplay: true,
          },
          style: isAccessDenied
            ? {
                ...n.style,
                border: '2px solid var(--modeler-color-danger-soft)',
                boxShadow: 'var(--modeler-shadow-danger-glow)',
              }
            : n.style,
        }
      : n
  );
}

/** Apply replay highlighting to a specific edge */
export function applyEdgeHighlight(
  edges: Edge[],
  edgeId: string,
  isAdd: boolean
): Edge[] {
  const highlightColor = isAdd
    ? 'var(--modeler-color-success)'
    : 'var(--modeler-color-danger-soft)';

  return edges.map(e =>
    e.id === edgeId
      ? {
          ...e,
          // Keep the incoming selected state (set by the caller) so that
          // ReactFlow fires onSelectionChange with this edge, which in turn
          // populates the selection store and the property panel.
          data: {
            ...e.data,
            highlighted: true,
            replayHighlight: highlightColor,
            fromReplay: true,
            replayType: isAdd ? 'add' : 'ignore',
          },
          style: {
            ...e.style,
            stroke: highlightColor,
            strokeWidth: 3,
            opacity: 1,
          },
          animated: true,
          className: `replay-highlighted replay-${isAdd ? 'add' : 'ignore'}`,
        }
      : e
  );
}

// ============ Composite Operations ============

/**
 * Find the condition NODE that a condition step refers to.
 *
 * Conditions are first-class nodes now (issue #3589093), so a condition
 * step highlights the condition node rather than a (nonexistent) condition
 * edge.  The replay step's `conditionId` historically matched the legacy
 * `edge.data.condition` value, which carried the condition *plugin* id (see
 * P2 `modelUtils.promoteConditionsToNodes`, where `node.data.plugin` is set
 * from `edge.condition`).  We therefore match `step.conditionId` against the
 * condition node's `data.plugin` FIRST, then fall back to `data.conditionId`
 * (the backend round-trip id).
 *
 * Returns the matching condition node id, or null when none matches.
 */
function findConditionNodeForStep(
  nodes: Node[],
  step: ReplayStep
): string | null {
  if (!isConditionStep(step)) return null;

  const isConditionNode = (n: Node): boolean =>
    n.type === 'condition' || n.data?.__isConditionNode === true;

  // Priority 1: match the step's conditionId against the node's plugin id
  // (this is what the legacy edge-based matcher used).
  const byPlugin = nodes.find(
    n => isConditionNode(n) && n.data?.plugin === step.conditionId
  );
  if (byPlugin) return byPlugin.id;

  // Priority 2: match against the node's backend round-trip conditionId.
  const byConditionId = nodes.find(
    n => isConditionNode(n) && n.data?.conditionId === step.conditionId
  );
  if (byConditionId) return byConditionId.id;

  return null;
}

/**
 * Apply full replay step highlighting to nodes.
 * Clears existing highlights and applies new ones based on the step.
 */
export function highlightNodesForStep(
  nodes: Node[],
  step: ReplayStep
): Node[] {
  const cleared = clearNodeHighlights(nodes);

  // For condition steps, highlight the condition NODE (issue #3589093).
  // Conditions used to be edges; they are nodes now, so this is where the
  // condition highlight lives.  Resolve via plugin id, then conditionId.
  if (isConditionStep(step)) {
    const conditionNodeId = findConditionNodeForStep(cleared, step);
    if (!conditionNodeId) return cleared;
    return applyNodeHighlight(cleared, conditionNodeId, false);
  }

  if (!step.id) return cleared;

  // For node execution steps (started, execute, access denied, exception)
  if (isNodeExecutionStep(step)) {
    return applyNodeHighlight(cleared, step.id, isAccessDeniedStep(step) || isExceptionStep(step));
  }

  // For successor steps without conditionId (treated as node steps)
  if (isSuccessorStep(step) && !step.conditionId) {
    return applyNodeHighlight(cleared, step.id, false);
  }

  return cleared;
}

/**
 * Apply full replay step highlighting to edges.
 *
 * Conditions are nodes now (issue #3589093), so condition steps no longer
 * highlight an edge — that work has moved to {@link highlightNodesForStep}.
 * This function is retained because non-condition steps may still rely on
 * edge clearing, and to keep a stable API for callers; for condition steps
 * it simply clears edge highlights (a no-op beyond clearing).
 */
export function highlightEdgesForStep(
  edges: Edge[],
  _step: ReplayStep
): Edge[] {
  return clearEdgeHighlights(edges);
}
