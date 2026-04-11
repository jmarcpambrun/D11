/**
 * Tests for useFilterStore — token dragging flag and visible start node IDs.
 */

import { useFilterStore } from '../useFilterStore';

describe('useFilterStore', () => {
  beforeEach(() => {
    useFilterStore.setState({
      isTokenDragging: false,
      visibleStartNodeIds: null,
    });
  });

  describe('initial state', () => {
    it('should not be token dragging and have null visible IDs', () => {
      const s = useFilterStore.getState();
      expect(s.isTokenDragging).toBe(false);
      expect(s.visibleStartNodeIds).toBeNull();
    });
  });

  describe('setTokenDragging', () => {
    it('should set the dragging flag', () => {
      useFilterStore.getState().setTokenDragging(true);
      expect(useFilterStore.getState().isTokenDragging).toBe(true);

      useFilterStore.getState().setTokenDragging(false);
      expect(useFilterStore.getState().isTokenDragging).toBe(false);
    });
  });

  describe('setVisibleStartNodeIds', () => {
    it('should set an array of IDs', () => {
      useFilterStore.getState().setVisibleStartNodeIds(['n1', 'n2']);
      expect(useFilterStore.getState().visibleStartNodeIds).toEqual(['n1', 'n2']);
    });

    it('should reset to null', () => {
      useFilterStore.setState({ visibleStartNodeIds: ['n1'] });
      useFilterStore.getState().setVisibleStartNodeIds(null);
      expect(useFilterStore.getState().visibleStartNodeIds).toBeNull();
    });
  });
});
