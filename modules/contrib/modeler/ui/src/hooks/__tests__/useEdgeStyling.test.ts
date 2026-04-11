import { renderHook } from '@testing-library/react';
import { useEdgeStyling } from '../useEdgeStyling';

describe('useEdgeStyling', () => {
  const createMockEdge = (overrides: any = {}) => ({
    id: 'edge-1',
    source: 'node-1',
    target: 'node-2',
    style: { stroke: '#000' },
    data: {},
    ...overrides,
  });

  describe('return value', () => {
    it('should return edges unchanged when not dragging condition', () => {
      const edges = [createMockEdge()];
      const { result } = renderHook(() =>
        useEdgeStyling({
          edges,
          isDraggingCondition: false,
          hoveredDropEdge: null,
        })
      );
      expect(result.current).toBe(edges);
    });
  });

  describe('condition drag active', () => {
    it('should add drop target styling to unlocked edges', () => {
      const edges = [createMockEdge({ id: 'edge-1', data: {} })];
      const { result } = renderHook(() =>
        useEdgeStyling({
          edges,
          isDraggingCondition: true,
          hoveredDropEdge: null,
        })
      );
      expect(result.current[0].className).toBe('workflow-edge-drop-target');
      expect(result.current[0].style?.strokeWidth).toBe(4);
      expect(result.current[0].style?.stroke).toBe('var(--modeler-color-success-subtle)');
      expect(result.current[0].style?.strokeDasharray).toBe('5,5');
    });

    it('should show green hovered styling for new condition target', () => {
      const edges = [createMockEdge({ id: 'edge-1', data: {} })];
      const { result } = renderHook(() =>
        useEdgeStyling({
          edges,
          isDraggingCondition: true,
          hoveredDropEdge: { id: 'edge-1' },
        })
      );
      expect(result.current[0].className).toBe('workflow-edge-hovered-target');
      expect(result.current[0].style?.strokeWidth).toBe(8);
      expect(result.current[0].style?.stroke).toBe('var(--modeler-color-success)');
      expect(result.current[0].animated).toBe(true);
    });

    it('should show orange replace styling for existing condition target', () => {
      const edges = [createMockEdge({ id: 'edge-1', data: { condition: 'some_condition' } })];
      const { result } = renderHook(() =>
        useEdgeStyling({
          edges,
          isDraggingCondition: true,
          hoveredDropEdge: { id: 'edge-1' },
        })
      );
      expect(result.current[0].className).toBe('workflow-edge-replace-target');
      expect(result.current[0].style?.strokeWidth).toBe(8);
      expect(result.current[0].style?.stroke).toBe('var(--modeler-color-warning)');
      expect(result.current[0].animated).toBe(true);
    });

    it('should show yellow subtle highlight for edges with existing conditions', () => {
      const edges = [createMockEdge({ id: 'edge-1', data: { condition: 'some_condition' } })];
      const { result } = renderHook(() =>
        useEdgeStyling({
          edges,
          isDraggingCondition: true,
          hoveredDropEdge: { id: 'other-edge' },
        })
      );
      expect(result.current[0].className).toBe('workflow-edge-drop-target');
      expect(result.current[0].style?.stroke).toBe('var(--modeler-color-warning-subtle)');
    });

    it('should handle multiple edges with different states', () => {
      const edges = [
        createMockEdge({ id: 'edge-1', data: {} }),
        createMockEdge({ id: 'edge-2', data: {} }),
        createMockEdge({ id: 'edge-3', data: { condition: 'cond' } }),
      ];
      const { result } = renderHook(() =>
        useEdgeStyling({
          edges,
          isDraggingCondition: true,
          hoveredDropEdge: { id: 'edge-1' },
        })
      );
      expect(result.current[0].className).toBe('workflow-edge-hovered-target');
      expect(result.current[1].className).toBe('workflow-edge-drop-target');
      expect(result.current[2].className).toBe('workflow-edge-drop-target');
    });
  });

  describe('memoization', () => {
    it('should return same reference when inputs are stable', () => {
      const edges = [createMockEdge()];
      const { result, rerender } = renderHook(() =>
        useEdgeStyling({
          edges,
          isDraggingCondition: false,
          hoveredDropEdge: null,
        })
      );
      const first = result.current;
      rerender();
      expect(result.current).toBe(first);
    });
  });
});
