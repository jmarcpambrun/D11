/**
 * Layout helper functions for auto-layout algorithm
 * Extracted from the monolithic autoLayout function for better maintainability
 */

import type { StoreNode as Node, StoreEdge as Edge } from '../types/settings';
import { NODE_DIMENSIONS } from '../constants/dimensions';

// ============ Type Definitions ============
export interface LayoutPosition {
  row: number;
  column: number;
}

export interface GraphData {
  adjacencyMap: Map<string, string[]>;
  inDegree: Map<string, number>;
  outDegree: Map<string, number>;
}

// ============ Constants ============
export const LAYOUT_CONFIG = {
  NODE_WIDTH: NODE_DIMENSIONS.DEFAULT_WIDTH,
  NODE_HEIGHT: NODE_DIMENSIONS.DEFAULT_HEIGHT,
  HORIZONTAL_SPACING: 300,
  VERTICAL_SPACING: 204, // Center-to-center row spacing: NODE_HEIGHT (120) + 84px gap (60% reduction from original 210px gap)
  CONDITION_EXTRA_SPACING: 90, // Extra vertical space when an edge has a condition card
  COLLISION_PADDING: 25,
  MIN_NODE_DISTANCE: NODE_DIMENSIONS.DEFAULT_WIDTH + 50,
  GATEWAY_VERTICAL_OFFSET: 0.75, // 75% of normal distance for gateway children
  IDEAL_POSITION_WEIGHT: 0.3, // Weight for adjusting nodes toward ideal position
} as const;

/**
 * Find event/start nodes that should be positioned first
 */
export function findStartNodes(nodes: Node[], inDegree: Map<string, number>): Node[] {
  const eventNodes = nodes.filter(
    node => node.type === 'start' || 
            inDegree.get(node.id) === 0 ||
            (node.data?.plugin && node.data.plugin.includes('event'))
  ).sort((a, b) => {
    // Prioritize 'start' type nodes
    if (a.type === 'start' && b.type !== 'start') return -1;
    if (b.type === 'start' && a.type !== 'start') return 1;
    return 0;
  });
  
  // If no event nodes found, use nodes with no incoming edges
  const startNodes = eventNodes.length > 0 ? eventNodes : 
    nodes.filter(node => inDegree.get(node.id) === 0);
  
  return startNodes.length > 0 ? startNodes : (nodes.length > 0 ? [nodes[0]] : []);
}

// ============ Graph Building ============
/**
 * Build adjacency map and calculate in/out degrees for nodes
 */
export function buildGraphData(nodes: Node[], edges: Edge[]): GraphData {
  const adjacencyMap = new Map<string, string[]>();
  const inDegree = new Map<string, number>();
  const outDegree = new Map<string, number>();
  
  // Initialize maps for nodes
  nodes.forEach(node => {
    adjacencyMap.set(node.id, []);
    inDegree.set(node.id, 0);
    outDegree.set(node.id, 0);
  });

  // Build the graph from edges - maintaining order of edges
  edges.forEach(edge => {
    if (adjacencyMap.has(edge.source) && adjacencyMap.has(edge.target)) {
      adjacencyMap.get(edge.source)!.push(edge.target);
      inDegree.set(edge.target, (inDegree.get(edge.target) || 0) + 1);
      outDegree.set(edge.source, (outDegree.get(edge.source) || 0) + 1);
    }
  });

  return { adjacencyMap, inDegree, outDegree };
}

// ============ Position Calculation ============
/**
 * Calculate ideal X position based on connected nodes
 */
export function calculateIdealXPosition(
  nodeId: string,
  edges: Edge[],
  nodes: Node[]
): number | null {
  let idealX = 0;
  let connectionCount = 0;
  
  // Consider parent positions
  edges.forEach(edge => {
    if (edge.target === nodeId) {
      const parentNode = nodes.find(n => n.id === edge.source);
      if (parentNode?.position) {
        idealX += parentNode.position.x;
        connectionCount++;
      }
    }
  });
  
  // Consider child positions
  edges.forEach(edge => {
    if (edge.source === nodeId) {
      const childNode = nodes.find(n => n.id === edge.target);
      if (childNode?.position) {
        idealX += childNode.position.x;
        connectionCount++;
      }
    }
  });
  
  return connectionCount > 0 ? idealX / connectionCount : null;
}

// ============ Edge Proximity ============
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



