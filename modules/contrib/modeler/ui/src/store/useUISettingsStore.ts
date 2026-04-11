import { create } from 'zustand';
import { STORAGE_KEYS } from '../constants/dimensions';

interface UISettingsState {
  darkMode: boolean;
  toggleDarkMode: () => void;
}

export const useUISettingsStore = create<UISettingsState>((set, get) => ({
  darkMode: (() => {
    try {
      const saved = localStorage.getItem(STORAGE_KEYS.THEME);
      if (saved === 'dark') return true;
      if (saved === 'light') return false;
      return window.matchMedia?.('(prefers-color-scheme: dark)').matches ?? false;
    } catch {
      return false;
    }
  })(),
  toggleDarkMode: () => {
    const newState = !get().darkMode;
    set({ darkMode: newState });
    localStorage.setItem(STORAGE_KEYS.THEME, newState ? 'dark' : 'light');
  },
}));
