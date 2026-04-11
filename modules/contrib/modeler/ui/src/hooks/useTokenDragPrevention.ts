/**
 * useTokenDragPrevention - Hook that provides handlers to prevent
 * token drag-and-drop on native input fields (label, annotation).
 *
 * When a token is being dragged from the token browser, native fields
 * should reject the drop so that tokens only land in custom token-aware
 * fields.
 */

import { useCallback } from 'react';
import { useFilterStore } from '../store/useFilterStore';

interface TokenDragPreventionHandlers {
  isTokenDragging: boolean;
  handleNativeFieldDragOver: (e: React.DragEvent) => void;
  handleNativeFieldDrop: (e: React.DragEvent) => void;
}

export function useTokenDragPrevention(): TokenDragPreventionHandlers {
  const isTokenDragging = useFilterStore(s => s.isTokenDragging);

  const handleNativeFieldDragOver = useCallback((e: React.DragEvent) => {
    if (isTokenDragging) {
      e.preventDefault();
      e.dataTransfer.dropEffect = 'none';
    }
  }, [isTokenDragging]);

  const handleNativeFieldDrop = useCallback((e: React.DragEvent) => {
    if (isTokenDragging) {
      e.preventDefault();
    }
  }, [isTokenDragging]);

  return { isTokenDragging, handleNativeFieldDragOver, handleNativeFieldDrop };
}
