/**
 * Custom hook for computing edge styles during condition drag-and-drop.
 * Applies visual feedback (color, width, dasharray) to edges when a condition
 * component is being dragged over the canvas.
 */
import { useMemo } from 'react';
import type { StoreEdge as Edge } from '../types/settings';

interface HoveredDropEdge {
  id: string;
}

interface UseEdgeStylingProps {
  edges: Edge[];
  isDraggingCondition: boolean;
  hoveredDropEdge: HoveredDropEdge | null;
}

/**
 * Computes styled edges with visual feedback for condition drag-and-drop operations.
 * Returns edges with appropriate styling classes based on drag state.
 */
export function useEdgeStyling({
  edges,
  isDraggingCondition,
  hoveredDropEdge,
}: UseEdgeStylingProps): Edge[] {
  return useMemo(() => {
    if (!isDraggingCondition) return edges;

    return edges.map(edge => {
      const isDraggingOverEdge = true;
      const isHoveredDropTarget = hoveredDropEdge?.id === edge.id;
      const hasExistingCondition = edge.data?.condition;
      const isReplaceTarget = hasExistingCondition && isHoveredDropTarget;
      const isAddTarget = !hasExistingCondition && isHoveredDropTarget;

      let edgeStyle = { ...edge.style };
      let className = '';

      if (isDraggingOverEdge && isHoveredDropTarget) {
        if (isReplaceTarget) {
          // Orange for replacement
          className = 'workflow-edge-replace-target';
          edgeStyle = {
            ...edgeStyle,
            strokeWidth: 8,
            stroke: 'var(--modeler-color-warning)',
            strokeDasharray: '10,5',
          };
        } else if (isAddTarget) {
          // Green for adding new
          className = 'workflow-edge-hovered-target';
          edgeStyle = {
            ...edgeStyle,
            strokeWidth: 8,
            stroke: 'var(--modeler-color-success)',
            strokeDasharray: '10,5',
          };
        }
      } else if (isDraggingOverEdge && !isHoveredDropTarget) {
        // Subtle highlight for all valid drop targets
        className = 'workflow-edge-drop-target';
        edgeStyle = {
          ...edgeStyle,
          strokeWidth: 4,
          stroke: hasExistingCondition ? 'var(--modeler-color-warning-subtle)' : 'var(--modeler-color-success-subtle)',
          strokeDasharray: '5,5',
        };
      }

      return {
        ...edge,
        style: edgeStyle,
        className,
        animated: isHoveredDropTarget,
      };
    });
  }, [edges, isDraggingCondition, hoveredDropEdge]);
}
