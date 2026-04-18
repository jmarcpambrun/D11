/**
 * replayHighlightUtils - Pure functions for replay step highlighting
 * 
 * Provides node/edge state transformations for applying and clearing
 * replay visual highlights. These are pure functions (no React hooks)
 * that transform node/edge arrays, shared by useSimpleReplaySync
 * and useReplayController.
 */

import { Node, Edge } from 'reactflow';
import { ReplayStep, isNodeExecutionStep, isSuccessorStep, isAccessDeniedStep, isExceptionStep, isAddSuccessorStep, isConditionStep } from './replayStepUtils';

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
 * Apply full replay step highlighting to nodes.
 * Clears existing highlights and applies new ones based on the step.
 */
export function highlightNodesForStep(
  nodes: Node[],
  step: ReplayStep
): Node[] {
  const cleared = clearNodeHighlights(nodes);

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
 * Find the edge to highlight for a condition step.
 * Returns the edge ID if found, null otherwise.
 */
function findEdgeForConditionStep(
  edges: Edge[],
  step: ReplayStep
): string | null {
  if (!isConditionStep(step)) return null;

  // Priority 1: Find edge that has this condition node attached
  const edgeByCondition = edges.find(e => e.data?.condition === step.conditionId);
  if (edgeByCondition) return edgeByCondition.id;

  // Priority 2: Find by source/target relationship with condition
  if (step.id && step.successorId) {
    const edgeBySourceTarget = edges.find(e =>
      e.source === step.id && e.target === step.successorId && e.data?.condition
    );
    if (edgeBySourceTarget) return edgeBySourceTarget.id;
  }

  return null;
}

/**
 * Apply full replay step highlighting to edges.
 * Clears existing highlights and applies new ones based on the step.
 */
export function highlightEdgesForStep(
  edges: Edge[],
  step: ReplayStep
): Edge[] {
  const cleared = clearEdgeHighlights(edges);

  if (!isConditionStep(step)) return cleared;

  const edgeId = findEdgeForConditionStep(edges, step);
  if (!edgeId) return cleared;

  return applyEdgeHighlight(cleared, edgeId, isAddSuccessorStep(step));
}
