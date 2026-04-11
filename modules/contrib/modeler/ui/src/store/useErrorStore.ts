import { create } from 'zustand';
import type { ErrorRecord } from '../utils/errorReporting';

interface ErrorState {
  errorLog: ErrorRecord[];
  addError: (record: ErrorRecord) => void;
  dismissError: (recordId: string) => void;
  clearErrors: () => void;
}

export const useErrorStore = create<ErrorState>((set) => ({
  errorLog: [],
  addError: (record) =>
    set((state) => ({
      errorLog: [...state.errorLog, record],
    })),
  dismissError: (recordId) =>
    set((state) => ({
      errorLog: state.errorLog.map((r) => (r.id === recordId ? { ...r, dismissed: true } : r)),
    })),
  clearErrors: () => set({ errorLog: [] }),
}));
