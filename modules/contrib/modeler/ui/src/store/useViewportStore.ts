import { create } from 'zustand';
import type { ViewportTarget } from '../types/settings';

interface ViewportState {
  viewportTarget: ViewportTarget | null;
  setViewportTarget: (target: ViewportTarget | null) => void;
  reactFlowReady: boolean;
  setReactFlowReady: (ready: boolean) => void;
}

export const useViewportStore = create<ViewportState>((set) => ({
  viewportTarget: null,
  setViewportTarget: (target) => set({ viewportTarget: target }),

  reactFlowReady: false,
  setReactFlowReady: (ready) => set({ reactFlowReady: ready }),
}));
