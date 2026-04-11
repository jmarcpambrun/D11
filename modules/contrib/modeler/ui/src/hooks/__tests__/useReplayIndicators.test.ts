import { renderHook } from '@testing-library/react';
import { useReplayIndicators } from '../useReplayIndicators';

describe('useReplayIndicators', () => {
  let mockNodes: any[];
  let mockEdges: any[];

  beforeEach(() => {
    mockNodes = [
      { id: 'node-1', position: { x: 100, y: 100 }, width: 200, height: 100 },
      { id: 'node-2', position: { x: 400, y: 100 }, width: 200, height: 100 },
    ];
    mockEdges = [
      {
        id: 'edge-1',
        source: 'node-1',
        target: 'node-2',
        data: { condition: 'condition-1' },
      },
    ];
  });

  const renderUseReplayIndicators = (props = {}) => {
    return renderHook(() =>
      useReplayIndicators({
        isReplayMode: false,
        currentReplayStep: -1,
        replayData: null,
        edges: mockEdges,
        nodes: mockNodes,
        ...props,
      })
    );
  };

  describe('initial state', () => {
    it('should return empty replayIndicators array', () => {
      const { result } = renderUseReplayIndicators();

      expect(result.current.replayIndicators).toEqual([]);
    });
  });

  describe('when not in replay mode', () => {
    it('should not create indicators when isReplayMode is false', () => {
      const replayData = [
        { type: 'add successor', id: 'node-1', conditionId: 'condition-1', successorId: 'node-2' },
      ];
      const { result } = renderUseReplayIndicators({
        isReplayMode: false,
        currentReplayStep: 0,
        replayData,
      });

      expect(result.current.replayIndicators).toEqual([]);
    });
  });

  describe('when currentReplayStep is invalid', () => {
    it('should not create indicators when step is negative', () => {
      const replayData = [
        { type: 'add successor', id: 'node-1', conditionId: 'condition-1' },
      ];
      const { result } = renderUseReplayIndicators({
        isReplayMode: true,
        currentReplayStep: -1,
        replayData,
      });

      expect(result.current.replayIndicators).toEqual([]);
    });

    it('should not create indicators when step exceeds data length', () => {
      const replayData = [
        { type: 'add successor', id: 'node-1', conditionId: 'condition-1' },
      ];
      const { result } = renderUseReplayIndicators({
        isReplayMode: true,
        currentReplayStep: 5,
        replayData,
      });

      expect(result.current.replayIndicators).toEqual([]);
    });
  });

  describe('when replayData is null', () => {
    it('should not create indicators', () => {
      const { result } = renderUseReplayIndicators({
        isReplayMode: true,
        currentReplayStep: 0,
        replayData: null,
      });

      expect(result.current.replayIndicators).toEqual([]);
    });
  });

  describe('condition result indicators', () => {
    it('should create indicator for add successor step with condition', () => {
      const replayData = [
        { type: 'add successor', id: 'node-1', conditionId: 'condition-1', successorId: 'node-2' },
      ];
      const { result } = renderUseReplayIndicators({
        isReplayMode: true,
        currentReplayStep: 0,
        replayData,
      });

      expect(result.current.replayIndicators.length).toBe(1);
      expect(result.current.replayIndicators[0].id).toBe('condition-result-condition-1');
      expect(result.current.replayIndicators[0].color).toBe('var(--modeler-color-success)'); // Green for passed
    });

    it('should create red indicator for ignore successor step', () => {
      const replayData = [
        { type: 'ignore successor', id: 'node-1', conditionId: 'condition-1', successorId: 'node-2' },
      ];
      const { result } = renderUseReplayIndicators({
        isReplayMode: true,
        currentReplayStep: 0,
        replayData,
      });

      expect(result.current.replayIndicators.length).toBe(1);
      expect(result.current.replayIndicators[0].color).toBe('var(--modeler-color-danger-soft)'); // Red for failed
    });

    it('should find edge by condition label', () => {
      mockEdges = [
        {
          id: 'edge-1',
          source: 'node-1',
          target: 'node-2',
          data: { conditionLabel: 'my-condition' },
        },
      ];
      const replayData = [
        { type: 'add successor', id: 'node-1', conditionId: 'my-condition', successorId: 'node-2' },
      ];
      const { result } = renderUseReplayIndicators({
        isReplayMode: true,
        currentReplayStep: 0,
        replayData,
      });

      expect(result.current.replayIndicators.length).toBe(1);
    });

    it('should find edge by source/target fallback', () => {
      mockEdges = [
        {
          id: 'edge-1',
          source: 'node-1',
          target: 'node-2',
          data: { condition: 'different-condition' },
        },
      ];
      const replayData = [
        { type: 'add successor', id: 'node-1', conditionId: 'non-matching', successorId: 'node-2' },
      ];
      const { result } = renderUseReplayIndicators({
        isReplayMode: true,
        currentReplayStep: 0,
        replayData,
      });

      expect(result.current.replayIndicators.length).toBe(1);
    });

    it('should not create indicator if edge not found', () => {
      mockEdges = [];
      const replayData = [
        { type: 'add successor', id: 'node-1', conditionId: 'condition-1', successorId: 'node-2' },
      ];
      const { result } = renderUseReplayIndicators({
        isReplayMode: true,
        currentReplayStep: 0,
        replayData,
      });

      expect(result.current.replayIndicators).toEqual([]);
    });

    it('should not create indicator if source node not found', () => {
      mockNodes = [
        { id: 'node-2', position: { x: 400, y: 100 } },
      ];
      const replayData = [
        { type: 'add successor', id: 'node-1', conditionId: 'condition-1', successorId: 'node-2' },
      ];
      const { result } = renderUseReplayIndicators({
        isReplayMode: true,
        currentReplayStep: 0,
        replayData,
      });

      expect(result.current.replayIndicators).toEqual([]);
    });

    it('should not create indicator if target node not found', () => {
      mockNodes = [
        { id: 'node-1', position: { x: 100, y: 100 } },
      ];
      const replayData = [
        { type: 'add successor', id: 'node-1', conditionId: 'condition-1', successorId: 'node-2' },
      ];
      const { result } = renderUseReplayIndicators({
        isReplayMode: true,
        currentReplayStep: 0,
        replayData,
      });

      expect(result.current.replayIndicators).toEqual([]);
    });
  });

  describe('indicator positioning', () => {
    it('should calculate indicator position based on edge center', () => {
      const replayData = [
        { type: 'add successor', id: 'node-1', conditionId: 'condition-1', successorId: 'node-2' },
      ];
      const { result } = renderUseReplayIndicators({
        isReplayMode: true,
        currentReplayStep: 0,
        replayData,
      });

      const indicator = result.current.replayIndicators[0];
      // Node 1 center: (200, 150), Node 2 center: (500, 150)
      // Edge center: (350, 150) - 20 for offset = (350, 130)
      expect(indicator.x).toBe(350);
      expect(indicator.y).toBe(130);
    });

    it('should return flow coordinates (not screen coordinates)', () => {
      // Positions should be in flow coordinates — the rendering layer
      // (EdgeLabelRenderer) handles the viewport transform automatically.
      const replayData = [
        { type: 'add successor', id: 'node-1', conditionId: 'condition-1', successorId: 'node-2' },
      ];
      const { result } = renderUseReplayIndicators({
        isReplayMode: true,
        currentReplayStep: 0,
        replayData,
      });

      const indicator = result.current.replayIndicators[0];
      // Node 1 center: (200, 150), Node 2 center: (500, 150)
      // Edge center: (350, 150) - 20 for offset = (350, 130)
      // These are flow coordinates, NOT transformed by any viewport.
      expect(indicator.x).toBe(350);
      expect(indicator.y).toBe(130);
    });

    it('should apply control offset from edge data', () => {
      mockEdges = [
        {
          id: 'edge-1',
          source: 'node-1',
          target: 'node-2',
          data: { condition: 'condition-1', controlOffset: { x: 20, y: 30 } },
        },
      ];
      const replayData = [
        { type: 'add successor', id: 'node-1', conditionId: 'condition-1', successorId: 'node-2' },
      ];
      const { result } = renderUseReplayIndicators({
        isReplayMode: true,
        currentReplayStep: 0,
        replayData,
      });

      const indicator = result.current.replayIndicators[0];
      // Edge center: (350, 150) + offset (20, 30) - 20 vertical = (370, 160)
      expect(indicator.x).toBe(370);
      expect(indicator.y).toBe(160);
    });

    it('should handle nodes without explicit dimensions', () => {
      mockNodes = [
        { id: 'node-1', position: { x: 100, y: 100 } },
        { id: 'node-2', position: { x: 400, y: 100 } },
      ];
      const replayData = [
        { type: 'add successor', id: 'node-1', conditionId: 'condition-1', successorId: 'node-2' },
      ];
      const { result } = renderUseReplayIndicators({
        isReplayMode: true,
        currentReplayStep: 0,
        replayData,
      });

      // Should use default dimensions and not throw
      expect(result.current.replayIndicators.length).toBe(1);
    });
  });

  describe('step type filtering', () => {
    it('should not create indicator for non-successor step types', () => {
      const replayData = [
        { type: 'execute', id: 'node-1', conditionId: 'condition-1' },
      ];
      const { result } = renderUseReplayIndicators({
        isReplayMode: true,
        currentReplayStep: 0,
        replayData,
      });

      expect(result.current.replayIndicators).toEqual([]);
    });

    it('should not create indicator for step without conditionId', () => {
      const replayData = [
        { type: 'add successor', id: 'node-1', successorId: 'node-2' },
      ];
      const { result } = renderUseReplayIndicators({
        isReplayMode: true,
        currentReplayStep: 0,
        replayData,
      });

      expect(result.current.replayIndicators).toEqual([]);
    });
  });

  describe('step changes', () => {
    it('should update indicators when currentReplayStep changes', () => {
      const replayData = [
        { type: 'execute', id: 'node-1' },
        { type: 'add successor', id: 'node-1', conditionId: 'condition-1', successorId: 'node-2' },
      ];
      const { result, rerender } = renderHook(
        ({ step }) =>
          useReplayIndicators({
            isReplayMode: true,
            currentReplayStep: step,
            replayData,
            edges: mockEdges,
            nodes: mockNodes,
          }),
        { initialProps: { step: 0 } }
      );

      // Step 0: no indicator (execute step)
      expect(result.current.replayIndicators).toEqual([]);

      // Step 1: has indicator (add successor step)
      rerender({ step: 1 });
      expect(result.current.replayIndicators.length).toBe(1);
    });

    it('should clear indicators when exiting replay mode', () => {
      const replayData = [
        { type: 'add successor', id: 'node-1', conditionId: 'condition-1', successorId: 'node-2' },
      ];
      const { result, rerender } = renderHook(
        ({ isReplayMode }) =>
          useReplayIndicators({
            isReplayMode,
            currentReplayStep: 0,
            replayData,
            edges: mockEdges,
            nodes: mockNodes,
          }),
        { initialProps: { isReplayMode: true } }
      );

      expect(result.current.replayIndicators.length).toBe(1);

      // Exit replay mode
      rerender({ isReplayMode: false });
      expect(result.current.replayIndicators).toEqual([]);
    });
  });
});
