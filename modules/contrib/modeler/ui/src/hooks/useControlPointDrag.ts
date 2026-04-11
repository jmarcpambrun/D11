/**
 * Custom hook for handling drag interactions on edge control points.
 * Manages mouse events to allow users to reposition the bezier curve
 * control point by converting screen coordinates to flow coordinates.
 */
import { useState, useCallback } from 'react';

interface UseControlPointDragProps {
  id: string;
  edgeCenterX: number;
  edgeCenterY: number;
  isLocked: boolean;
  hasCondition: boolean;
  label: string | undefined | React.ReactNode;
  controlOffset: { x: number; y: number };
  onEdgeUpdate?: (id: string, updates: { controlOffset: { x: number; y: number } }) => void;
}

export function useControlPointDrag({
  id,
  edgeCenterX,
  edgeCenterY,
  isLocked,
  hasCondition,
  label,
  controlOffset,
  onEdgeUpdate,
}: UseControlPointDragProps) {
  const [isDragging, setIsDragging] = useState(false);

  const handleControlPointDrag = useCallback((event: React.MouseEvent) => {
    if (isLocked || !onEdgeUpdate) return;

    event.stopPropagation();
    event.preventDefault();
    setIsDragging(true);

    // Get the ReactFlow wrapper element to access the viewport transform
    const reactFlowWrapper = document.querySelector('.react-flow__renderer') as HTMLElement;
    const viewportElement = document.querySelector('.react-flow__viewport') as HTMLElement;

    if (!reactFlowWrapper || !viewportElement) return;

    // Get the transform values from the viewport
    const transform = viewportElement.style.transform;
    const transformMatch = transform.match(/translate\(([-\d.]+)px,\s*([-\d.]+)px\)\s*scale\(([\d.]+)\)/);

    let translateX = 0, translateY = 0, scale = 1;
    if (transformMatch) {
      translateX = parseFloat(transformMatch[1]);
      translateY = parseFloat(transformMatch[2]);
      scale = parseFloat(transformMatch[3]);
    }

    // Account for initial offset if condition label exists
    const initialYOffset = hasCondition && label && (controlOffset.x === 0 && controlOffset.y === 0) ? -25 : 0;

    const handleMouseMove = (e: MouseEvent) => {
      // Get mouse position relative to the ReactFlow wrapper
      const rect = reactFlowWrapper.getBoundingClientRect();
      const mouseX = e.clientX - rect.left;
      const mouseY = e.clientY - rect.top;

      // Convert to flow coordinates by accounting for pan and zoom
      const flowX = (mouseX - translateX) / scale;
      const flowY = (mouseY - translateY) / scale;

      // Calculate offset from edge center
      const newOffset = {
        x: flowX - edgeCenterX,
        y: flowY - edgeCenterY - initialYOffset
      };

      onEdgeUpdate(id, { controlOffset: newOffset });
    };

    const handleMouseUp = () => {
      setIsDragging(false);
      document.removeEventListener('mousemove', handleMouseMove);
      document.removeEventListener('mouseup', handleMouseUp);
    };

    document.addEventListener('mousemove', handleMouseMove);
    document.addEventListener('mouseup', handleMouseUp);
  }, [id, edgeCenterX, edgeCenterY, isLocked, onEdgeUpdate, hasCondition, label, controlOffset]);

  return {
    isDragging,
    handleControlPointDrag,
  };
}
