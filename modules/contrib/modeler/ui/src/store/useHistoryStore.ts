import { create } from 'zustand';
import type { StoreNode, StoreEdge } from '../types/settings';

interface GraphSnapshot {
  nodes: StoreNode[];
  edges: StoreEdge[];
}

interface HistoryState {
  past: GraphSnapshot[];
  future: GraphSnapshot[];
  maxHistorySize: number;
  pushHistory: (state: GraphSnapshot) => void;
  undo: (currentState: GraphSnapshot) => GraphSnapshot | null;
  redo: (currentState: GraphSnapshot) => GraphSnapshot | null;
  canUndo: () => boolean;
  canRedo: () => boolean;
  clearHistory: () => void;
}

export const useHistoryStore = create<HistoryState>((set, get) => ({
  past: [],
  future: [],
  maxHistorySize: 50,

  pushHistory: (state) => set((historyState) => {
    const newPast = [...historyState.past, { 
      nodes: [...state.nodes], 
      edges: [...state.edges] 
    }];
    
    if (newPast.length > historyState.maxHistorySize) {
      newPast.shift();
    }
    
    return {
      past: newPast,
      future: [],
    };
  }),

  undo: (currentState) => {
    const { past } = get();
    if (past.length === 0) return null;

    const newPast = [...past];
    const previousState = newPast.pop()!;
    
    set((state) => ({ 
      past: newPast,
      future: [{
        nodes: [...currentState.nodes],
        edges: [...currentState.edges],
      }, ...state.future],
    }));
    return previousState;
  },

  redo: (currentState) => {
    const { future } = get();
    if (future.length === 0) return null;

    const newFuture = [...future];
    const nextState = newFuture.shift()!;
    
    set((state) => ({ 
      past: [...state.past, {
        nodes: [...currentState.nodes],
        edges: [...currentState.edges],
      }],
      future: newFuture,
    }));
    return nextState;
  },

  canUndo: () => get().past.length > 0,

  canRedo: () => get().future.length > 0,

  clearHistory: () => set({ past: [], future: [] }),
}));
