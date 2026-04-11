/**
 * useVerticalPanelResize - Hook for resizing vertically stacked sub-panels
 *
 * Manages drag interactions on horizontal separator handles between sub-panels,
 * distributing available height among sections via flex-basis ratios.
 * Ratios are persisted to localStorage so they survive page reloads.
 */

import { useState, useCallback, useRef, useEffect } from 'react';

/** Minimum height in pixels any section may shrink to. */
const MIN_SECTION_HEIGHT = 60;

interface UseVerticalPanelResizeProps {
  /** Number of resizable sections currently rendered. */
  sectionCount: number;
  /** localStorage key for persisting the ratios. */
  storageKey: string;
}

interface UseVerticalPanelResizeReturn {
  /**
   * Current ratio for each section (values between 0 and 1, summing to 1).
   * Index corresponds to the section order (top = 0).
   */
  sectionRatios: number[];
  /** Whether a drag is currently in progress. */
  isResizing: boolean;
  /**
   * Returns a mousedown handler for the separator between
   * section `index` (above) and section `index + 1` (below).
   */
  startSeparatorDrag: (index: number) => (e: React.MouseEvent) => void;
  /**
   * Ref to attach to the container element whose height is the
   * available space distributed among the sections.
   */
  containerRef: React.RefObject<HTMLDivElement | null>;
}

function loadRatios(key: string, count: number): number[] {
  try {
    const saved = localStorage.getItem(key);
    if (saved) {
      const parsed: unknown = JSON.parse(saved);
      if (
        Array.isArray(parsed) &&
        parsed.length === count &&
        parsed.every((v: unknown) => typeof v === 'number' && v > 0)
      ) {
        // Re-normalize in case they don't exactly sum to 1.
        const sum = (parsed as number[]).reduce((a, b) => a + b, 0);
        return (parsed as number[]).map((v) => v / sum);
      }
    }
  } catch {
    // Ignore corrupt data.
  }
  // Default: equal distribution.
  return Array.from({ length: count }, () => 1 / count);
}

function saveRatios(key: string, ratios: number[]): void {
  try {
    localStorage.setItem(key, JSON.stringify(ratios));
  } catch {
    // Storage full or unavailable — silently ignore.
  }
}

export function useVerticalPanelResize({
  sectionCount,
  storageKey,
}: UseVerticalPanelResizeProps): UseVerticalPanelResizeReturn {
  const [sectionRatios, setSectionRatios] = useState<number[]>(() =>
    loadRatios(storageKey, sectionCount),
  );
  const [isResizing, setIsResizing] = useState(false);
  const containerRef = useRef<HTMLDivElement | null>(null);

  // When the number of visible sections changes, re-initialize ratios.
  useEffect(() => {
    setSectionRatios((prev) => {
      if (prev.length === sectionCount) return prev;
      return loadRatios(storageKey, sectionCount);
    });
  }, [sectionCount, storageKey]);

  const startSeparatorDrag = useCallback(
    (separatorIndex: number) => (e: React.MouseEvent) => {
      e.preventDefault();
      const container = containerRef.current;
      if (!container) return;

      setIsResizing(true);

      const startY = e.clientY;
      const containerHeight = container.getBoundingClientRect().height;

      // Snapshot the ratios at drag start so moves are relative.
      const startRatios = [...sectionRatios];

      const handleMouseMove = (moveEvent: MouseEvent) => {
        const deltaY = moveEvent.clientY - startY;
        const deltaRatio = containerHeight > 0 ? deltaY / containerHeight : 0;

        const above = startRatios[separatorIndex] + deltaRatio;
        const below = startRatios[separatorIndex + 1] - deltaRatio;

        // Enforce minimum height constraints (as a ratio).
        const minRatio =
          containerHeight > 0 ? MIN_SECTION_HEIGHT / containerHeight : 0.05;

        if (above < minRatio || below < minRatio) return;

        const newRatios = [...startRatios];
        newRatios[separatorIndex] = above;
        newRatios[separatorIndex + 1] = below;
        setSectionRatios(newRatios);
      };

      const handleMouseUp = () => {
        setIsResizing(false);
        // Persist the final ratios.
        setSectionRatios((latest) => {
          saveRatios(storageKey, latest);
          return latest;
        });
        document.removeEventListener('mousemove', handleMouseMove);
        document.removeEventListener('mouseup', handleMouseUp);
      };

      document.addEventListener('mousemove', handleMouseMove);
      document.addEventListener('mouseup', handleMouseUp);
    },
    [sectionRatios, storageKey],
  );

  return {
    sectionRatios,
    isResizing,
    startSeparatorDrag,
    containerRef,
  };
}
