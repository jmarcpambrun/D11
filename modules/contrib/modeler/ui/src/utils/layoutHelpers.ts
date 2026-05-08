/**
 * Layout helper utilities that survive the auto-layout unification.
 *
 * Most of this module was deleted along with the legacy row/column
 * auto-layout (see issue #3588454).  The single remaining helper —
 * {@link findNearestEdge} — supports drag-and-drop interactions in
 * useDragAndDrop.ts and has nothing to do with node placement.
 */

import type { StoreNode as Node, StoreEdge as Edge } from '../types/settings';
import { NODE_DIMENSIONS } from '../constants/dimensions';

/**
 * Find the nearest edge to a given position (for condition drag-and-drop).
 * Calculates distance from the position to the midpoint of each edge.
 */
export function findNearestEdge(
  position: { x: number; y: number },
  edges: Edge[],
  nodes: Node[],
  maxDistance = 80
): Edge | null {
  let nearestEdge: Edge | null = null;
  let minDistance = Infinity;

  edges.forEach(edge => {
    const sourceNode = nodes.find(n => n.id === edge.source);
    const targetNode = nodes.find(n => n.id === edge.target);

    if (!sourceNode || !targetNode) return;

    // Calculate node centers (considering node dimensions)
    const sourceCenter = {
      x: sourceNode.position.x + (sourceNode.width || NODE_DIMENSIONS.DEFAULT_WIDTH) / 2,
      y: sourceNode.position.y + (sourceNode.height || NODE_DIMENSIONS.DEFAULT_HEIGHT) / 2,
    };
    const targetCenter = {
      x: targetNode.position.x + (targetNode.width || NODE_DIMENSIONS.DEFAULT_WIDTH) / 2,
      y: targetNode.position.y + (targetNode.height || NODE_DIMENSIONS.DEFAULT_HEIGHT) / 2,
    };

    // Calculate edge midpoint (where conditions are typically placed)
    const midpoint = {
      x: (sourceCenter.x + targetCenter.x) / 2,
      y: (sourceCenter.y + targetCenter.y) / 2,
    };

    // Calculate distance from position to edge midpoint
    const distance = Math.sqrt(
      Math.pow(position.x - midpoint.x, 2) + Math.pow(position.y - midpoint.y, 2)
    );

    if (distance < minDistance && distance <= maxDistance) {
      minDistance = distance;
      nearestEdge = edge;
    }
  });

  return nearestEdge;
}
