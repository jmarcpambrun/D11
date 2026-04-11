import { create } from 'zustand';

interface FilterState {
  isTokenDragging: boolean;
  setTokenDragging: (isDragging: boolean) => void;
  visibleStartNodeIds: string[] | null;
  setVisibleStartNodeIds: (ids: string[] | null) => void;
}

export const useFilterStore = create<FilterState>((set) => ({
  isTokenDragging: false,
  setTokenDragging: (isDragging) => set({ isTokenDragging: isDragging }),

  visibleStartNodeIds: null,
  setVisibleStartNodeIds: (ids) => set({ visibleStartNodeIds: ids }),
}));
