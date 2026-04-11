/**
 * useToolbarHandlers - Hook for toolbar button click handlers
 *
 * Provides wrapped event handlers that prevent default behavior
 * and stop propagation for toolbar button clicks.
 */

import { useCallback } from 'react';
import { useReactFlow } from 'reactflow';
import { getFitViewport } from '../utils/modelUtils';

interface UseToolbarHandlersProps {
  onToggleLock?: () => void;
  onAutoLayout?: () => void;
  onCopy?: () => void;
  onPaste?: () => void;
  onToggleSearch?: () => void;
  onClose?: () => void;
}

interface UseToolbarHandlersReturn {
  handleToggleLock: (e: React.MouseEvent) => void;
  handleAutoLayout: (e: React.MouseEvent) => void;
  handleCopy: (e: React.MouseEvent) => void;
  handlePaste: (e: React.MouseEvent) => void;
  handleToggleSearch: (e: React.MouseEvent) => void;
  handleClose: (e: React.MouseEvent) => void;
  handleZoomIn: (e: React.MouseEvent) => void;
  handleZoomOut: (e: React.MouseEvent) => void;
  handleFitView: (e: React.MouseEvent) => void;
}

/**
 * Creates a toolbar click handler that prevents default behavior,
 * stops propagation, and then calls the provided callback.
 */
function useToolbarHandler(callback?: () => void) {
  return useCallback((e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();
    callback?.();
  }, [callback]);
}

export function useToolbarHandlers({
  onToggleLock,
  onAutoLayout,
  onCopy,
  onPaste,
  onToggleSearch,
  onClose,
}: UseToolbarHandlersProps): UseToolbarHandlersReturn {
  const { zoomIn, zoomOut, fitView, setViewport, getNodes } = useReactFlow();

  const handleToggleLock = useToolbarHandler(onToggleLock);
  const handleAutoLayout = useToolbarHandler(onAutoLayout);
  const handleCopy = useToolbarHandler(onCopy);
  const handlePaste = useToolbarHandler(onPaste);
  const handleToggleSearch = useToolbarHandler(onToggleSearch);
  const handleClose = useToolbarHandler(onClose);
  const handleZoomIn = useToolbarHandler(zoomIn);
  const handleZoomOut = useToolbarHandler(zoomOut);

  const handleFitView = useCallback((e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();

    // Get all nodes and filter out hidden ones
    const allNodes = getNodes();
    const visibleNodes = allNodes.filter(node => !node.hidden);

    // If we have visible nodes, fit view to them only
    if (visibleNodes.length > 0) {
      // Get viewport dimensions (approximate)
      const viewportWidth = window.innerWidth * 0.6; // Canvas is approximately 60% of window width
      const viewportHeight = window.innerHeight - 100; // Account for toolbar and other UI

      // Calculate the viewport to fit visible nodes
      const viewport = getFitViewport(visibleNodes, viewportWidth, viewportHeight, 0.1);

      // Apply the viewport with animation
      setViewport(viewport, { duration: 500 });
    } else {
      // Fall back to standard fitView if no visible nodes
      fitView({
        padding: 0.1,
        duration: 500
      });
    }
  }, [getNodes, setViewport, fitView]);

  return {
    handleToggleLock,
    handleAutoLayout,
    handleCopy,
    handlePaste,
    handleToggleSearch,
    handleClose,
    handleZoomIn,
    handleZoomOut,
    handleFitView,
  };
}

// Zoom limits (matching ReactFlow's minZoom and maxZoom)
const ZOOM_LIMITS = {
  MIN: 0.1,
  MAX: 4,
  TOLERANCE: 0.01,
};

/**
 * Check if zoom level is at minimum
 */
export function isAtMinZoom(zoomLevel: number): boolean {
  return zoomLevel <= ZOOM_LIMITS.MIN + ZOOM_LIMITS.TOLERANCE;
}

/**
 * Check if zoom level is at maximum
 */
export function isAtMaxZoom(zoomLevel: number): boolean {
  return zoomLevel >= ZOOM_LIMITS.MAX - ZOOM_LIMITS.TOLERANCE;
}
