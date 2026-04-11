import { useEffect, useRef } from 'react';
import { Node } from 'reactflow';
import type { ViewportTarget } from '../types/settings';
import { VIEWPORT, NODE_DIMENSIONS, TIMING } from '../constants/dimensions';

interface UseViewportEffectsProps {
  viewportTarget: ViewportTarget | null;
  nodes: Node[];
  setCenter: (x: number, y: number, options?: any) => void;
  fitView: (options?: any) => void;
  onViewportChange?: () => void;
}

/**
 * Hook to handle viewport changes as effects rather than direct commands
 * 
 * This prevents race conditions, double jumps, and jitter by treating
 * viewport changes as derived effects from state changes.
 */
export const useViewportEffects = ({
  viewportTarget,
  nodes,
  setCenter,
  fitView,
  onViewportChange
}: UseViewportEffectsProps) => {
  const lastTargetRef = useRef<ViewportTarget | null>(null);

  useEffect(() => {
    // Skip if no target or if it's the same as last target
    if (!viewportTarget || viewportTarget === lastTargetRef.current) {
      return;
    }

    lastTargetRef.current = viewportTarget;

    // Use a small timeout instead of requestAnimationFrame to reduce frequency
    const timeoutId = setTimeout(() => {
      if ((viewportTarget.type === 'center' || viewportTarget.type === 'top-align') && viewportTarget.nodeId) {
        const node = nodes.find(n => n.id === viewportTarget.nodeId);
        if (node) {
          const options = {
            zoom: viewportTarget.options?.zoom || 1.5,
            duration: viewportTarget.options?.duration || TIMING.VIEWPORT_PAN_DURATION
          };
          
          if (viewportTarget.type === 'top-align') {
            // For top-align, position the node near the top of the viewport
            // We want the node to be about 100-150 pixels from the top
            const topOffset = VIEWPORT.TOP_ALIGN_OFFSET;
            const viewportHeight = window.innerHeight || 800;
            
            // Calculate Y position to place node at top
            // setCenter expects the center point, so we need to calculate where
            // the viewport center should be to have the node appear at the top
            const nodeCenterX = node.position.x + (node.width || NODE_DIMENSIONS.DEFAULT_WIDTH) / 2;
            const nodeCenterY = node.position.y + (node.height || NODE_DIMENSIONS.DEFAULT_HEIGHT) / 2;
            
            // To place the node at the top, we need to move the viewport center down
            // The viewport center should be positioned so the node appears at topOffset from the top
            const zoomLevel = options.zoom || VIEWPORT.FIT_VIEW_ZOOM;
            // Calculate how far below the node center the viewport center should be
            const viewportCenterOffset = (viewportHeight / (2 * zoomLevel)) - (topOffset / zoomLevel);
            const adjustedY = nodeCenterY + viewportCenterOffset;
            
            setCenter(
              nodeCenterX,
              adjustedY,
              options
            );
          } else {
            // Standard center on node
            setCenter(
              node.position.x + (node.width || NODE_DIMENSIONS.DEFAULT_WIDTH) / 2, 
              node.position.y + (node.height || NODE_DIMENSIONS.DEFAULT_HEIGHT) / 2, 
              options
            );
          }
        }
      } else if (viewportTarget.type === 'fit') {
        const options: any = {};
        
        if (viewportTarget.options?.padding !== undefined) {
          options.padding = viewportTarget.options.padding;
        }
        
        // If nodes are directly provided in options, use them
        if (viewportTarget.options?.nodes) {
          options.nodes = viewportTarget.options.nodes;
        } else if (viewportTarget.nodeId) {
          // If nodeId is specified, include all nodes for context
          options.nodes = nodes;
        }
        
        fitView(options);
      }

      // Notify that viewport change has been applied
      if (onViewportChange) {
        onViewportChange();
      }
    }, TIMING.VIEWPORT_EFFECT_DELAY);
    
    return () => clearTimeout(timeoutId);
  }, [viewportTarget, nodes, setCenter, fitView, onViewportChange]);

  return {
    isApplyingViewportChange: viewportTarget !== null
  };
};