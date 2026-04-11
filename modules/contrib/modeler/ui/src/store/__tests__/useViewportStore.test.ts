/**
 * Tests for useViewportStore — viewport target and ReactFlow readiness.
 */

import { useViewportStore } from '../useViewportStore';
import type { ViewportTarget } from '../../types/settings';

describe('useViewportStore', () => {
  beforeEach(() => {
    useViewportStore.setState({ viewportTarget: null, reactFlowReady: false });
  });

  describe('initial state', () => {
    it('should have null target and not ready', () => {
      const s = useViewportStore.getState();
      expect(s.viewportTarget).toBeNull();
      expect(s.reactFlowReady).toBe(false);
    });
  });

  describe('setViewportTarget', () => {
    it('should set a viewport target', () => {
      const target: ViewportTarget = { type: 'center', nodeId: 'n1' };
      useViewportStore.getState().setViewportTarget(target);
      expect(useViewportStore.getState().viewportTarget).toEqual(target);
    });

    it('should clear viewport target with null', () => {
      useViewportStore.setState({ viewportTarget: { type: 'center', nodeId: 'n1' } });
      useViewportStore.getState().setViewportTarget(null);
      expect(useViewportStore.getState().viewportTarget).toBeNull();
    });
  });

  describe('setReactFlowReady', () => {
    it('should set readiness flag', () => {
      useViewportStore.getState().setReactFlowReady(true);
      expect(useViewportStore.getState().reactFlowReady).toBe(true);

      useViewportStore.getState().setReactFlowReady(false);
      expect(useViewportStore.getState().reactFlowReady).toBe(false);
    });
  });
});
