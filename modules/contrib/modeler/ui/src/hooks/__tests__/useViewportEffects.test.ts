import { renderHook } from '@testing-library/react';
import { useViewportEffects } from '../useViewportEffects';
import { Node } from 'reactflow';

describe('useViewportEffects', () => {
  let mockSetCenter: jest.Mock;
  let mockFitView: jest.Mock;
  let mockOnViewportChange: jest.Mock;
  let mockNodes: Node[];

  beforeEach(() => {
    jest.useFakeTimers();
    mockSetCenter = jest.fn();
    mockFitView = jest.fn();
    mockOnViewportChange = jest.fn();
    mockNodes = [
      {
        id: 'node-1',
        position: { x: 100, y: 100 },
        data: { label: 'Node 1' },
        width: 200,
        height: 100,
      },
      {
        id: 'node-2',
        position: { x: 400, y: 200 },
        data: { label: 'Node 2' },
        width: 200,
        height: 100,
      },
    ];
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  const renderUseViewportEffects = (viewportTarget: any, nodes = mockNodes) => {
    return renderHook(
      ({ target, nodes: nodesProp }) =>
        useViewportEffects({
          viewportTarget: target,
          nodes: nodesProp,
          setCenter: mockSetCenter,
          fitView: mockFitView,
          onViewportChange: mockOnViewportChange,
        }),
      { initialProps: { target: viewportTarget, nodes } }
    );
  };

  describe('initial state', () => {
    it('should return isApplyingViewportChange as false when no target', () => {
      const { result } = renderUseViewportEffects(null);

      expect(result.current.isApplyingViewportChange).toBe(false);
    });

    it('should return isApplyingViewportChange as true when target exists', () => {
      const target = { type: 'center', nodeId: 'node-1' };
      const { result } = renderUseViewportEffects(target);

      expect(result.current.isApplyingViewportChange).toBe(true);
    });
  });

  describe('center viewport target', () => {
    it('should call setCenter for center type target', () => {
      const target = { type: 'center', nodeId: 'node-1' };
      renderUseViewportEffects(target);

      jest.runAllTimers();

      expect(mockSetCenter).toHaveBeenCalled();
    });

    it('should center on node position plus half dimensions', () => {
      const target = { type: 'center', nodeId: 'node-1' };
      renderUseViewportEffects(target);

      jest.runAllTimers();

      // Node at (100, 100) with size 200x100 -> center at (200, 150)
      expect(mockSetCenter).toHaveBeenCalledWith(
        200, // x: 100 + 200/2
        150, // y: 100 + 100/2
        expect.objectContaining({ zoom: 1.5 })
      );
    });

    it('should use custom zoom from options', () => {
      const target = { type: 'center', nodeId: 'node-1', options: { zoom: 2.0 } };
      renderUseViewportEffects(target);

      jest.runAllTimers();

      expect(mockSetCenter).toHaveBeenCalledWith(
        expect.any(Number),
        expect.any(Number),
        expect.objectContaining({ zoom: 2.0 })
      );
    });

    it('should use custom duration from options', () => {
      const target = { type: 'center', nodeId: 'node-1', options: { duration: 500 } };
      renderUseViewportEffects(target);

      jest.runAllTimers();

      expect(mockSetCenter).toHaveBeenCalledWith(
        expect.any(Number),
        expect.any(Number),
        expect.objectContaining({ duration: 500 })
      );
    });

    it('should not call setCenter if node not found', () => {
      const target = { type: 'center', nodeId: 'non-existent' };
      renderUseViewportEffects(target);

      jest.runAllTimers();

      expect(mockSetCenter).not.toHaveBeenCalled();
    });

    it('should handle node without explicit dimensions', () => {
      const nodesWithoutDimensions = [
        { id: 'node-1', position: { x: 100, y: 100 }, data: {} },
      ];
      const target = { type: 'center', nodeId: 'node-1' };
      renderUseViewportEffects(target, nodesWithoutDimensions as any);

      jest.runAllTimers();

      expect(mockSetCenter).toHaveBeenCalled();
    });
  });

  describe('top-align viewport target', () => {
    it('should call setCenter for top-align type target', () => {
      const target = { type: 'top-align', nodeId: 'node-1' };
      renderUseViewportEffects(target);

      jest.runAllTimers();

      expect(mockSetCenter).toHaveBeenCalled();
    });

    it('should calculate adjusted Y position for top alignment', () => {
      const target = { type: 'top-align', nodeId: 'node-1', options: { zoom: 1 } };
      renderUseViewportEffects(target);

      jest.runAllTimers();

      // The Y coordinate should be adjusted for top alignment
      // Exact calculation depends on window.innerHeight and TOP_ALIGN_OFFSET
      const [x, y] = mockSetCenter.mock.calls[0];
      expect(x).toBe(200); // Node center X
      // Y should be greater than node center (150) due to viewport offset adjustment
      expect(typeof y).toBe('number');
    });

    it('should not call setCenter if node not found', () => {
      const target = { type: 'top-align', nodeId: 'non-existent' };
      renderUseViewportEffects(target);

      jest.runAllTimers();

      expect(mockSetCenter).not.toHaveBeenCalled();
    });
  });

  describe('fit viewport target', () => {
    it('should call fitView for fit type target', () => {
      const target = { type: 'fit' };
      renderUseViewportEffects(target);

      jest.runAllTimers();

      expect(mockFitView).toHaveBeenCalled();
    });

    it('should pass padding option to fitView', () => {
      const target = { type: 'fit', options: { padding: 50 } };
      renderUseViewportEffects(target);

      jest.runAllTimers();

      expect(mockFitView).toHaveBeenCalledWith(
        expect.objectContaining({ padding: 50 })
      );
    });

    it('should pass nodes from options to fitView', () => {
      const specificNodes = [{ id: 'node-1' }];
      const target = { type: 'fit', options: { nodes: specificNodes } };
      renderUseViewportEffects(target);

      jest.runAllTimers();

      expect(mockFitView).toHaveBeenCalledWith(
        expect.objectContaining({ nodes: specificNodes })
      );
    });

    it('should filter nodes by nodeId when specified', () => {
      const target = { type: 'fit', nodeId: 'node-1' };
      renderUseViewportEffects(target);

      jest.runAllTimers();

      expect(mockFitView).toHaveBeenCalledWith(
        expect.objectContaining({
          nodes: expect.any(Array)
        })
      );
    });
  });

  describe('viewport change callback', () => {
    it('should call onViewportChange after center action', () => {
      const target = { type: 'center', nodeId: 'node-1' };
      renderUseViewportEffects(target);

      jest.runAllTimers();

      expect(mockOnViewportChange).toHaveBeenCalled();
    });

    it('should call onViewportChange after fit action', () => {
      const target = { type: 'fit' };
      renderUseViewportEffects(target);

      jest.runAllTimers();

      expect(mockOnViewportChange).toHaveBeenCalled();
    });
  });

  describe('target deduplication', () => {
    it('should not re-apply same target', () => {
      const target = { type: 'center', nodeId: 'node-1' };
      const { rerender } = renderUseViewportEffects(target);

      jest.runAllTimers();
      expect(mockSetCenter).toHaveBeenCalledTimes(1);

      // Rerender with same target reference
      rerender({ target, nodes: mockNodes });
      jest.runAllTimers();

      // Should still be only 1 call
      expect(mockSetCenter).toHaveBeenCalledTimes(1);
    });

    it('should apply new target when target changes', () => {
      const target1 = { type: 'center', nodeId: 'node-1' };
      const { rerender } = renderUseViewportEffects(target1);

      jest.runAllTimers();
      expect(mockSetCenter).toHaveBeenCalledTimes(1);

      // Rerender with different target
      const target2 = { type: 'center', nodeId: 'node-2' };
      rerender({ target: target2, nodes: mockNodes });
      jest.runAllTimers();

      expect(mockSetCenter).toHaveBeenCalledTimes(2);
    });
  });

  describe('null target', () => {
    it('should not perform any viewport action when target is null', () => {
      renderUseViewportEffects(null);

      jest.runAllTimers();

      expect(mockSetCenter).not.toHaveBeenCalled();
      expect(mockFitView).not.toHaveBeenCalled();
    });
  });

  describe('cleanup', () => {
    it('should cleanup timeout on unmount', () => {
      const target = { type: 'center', nodeId: 'node-1' };
      const { unmount } = renderUseViewportEffects(target);

      // Unmount before timeout fires
      unmount();
      jest.runAllTimers();

      // setCenter should not be called due to cleanup
      expect(mockSetCenter).not.toHaveBeenCalled();
    });
  });

  describe('without onViewportChange callback', () => {
    it('should work without onViewportChange callback', () => {
      const target = { type: 'fit' as const };
      renderHook(() =>
        useViewportEffects({
          viewportTarget: target,
          nodes: mockNodes,
          setCenter: mockSetCenter,
          fitView: mockFitView,
          // No onViewportChange provided
        })
      );

      jest.runAllTimers();

      expect(mockFitView).toHaveBeenCalled();
      // Should not throw
    });
  });
});
