/**
 * Layout strategies for positioning nodes in the workflow
 * Contains the core algorithms for node placement
 */

import type { StoreNode as Node, StoreEdge as Edge } from '../types/settings';
import {
  LayoutPosition,
  GraphData,
  LAYOUT_CONFIG,
  calculateIdealXPosition,
} from './layoutHelpers';

/**
 * Check whether an edge carries a condition (and therefore needs extra vertical space).
 */
function edgeHasCondition(edge: Edge): boolean {
  return !!(
    edge.data?.condition ||
    edge.data?.conditionLabel ||
    (edge.data?.conditionConfiguration &&
      Object.keys(edge.data.conditionConfiguration as Record<string, unknown>).length > 0)
  );
}

// ============ Flow-based Layout Strategy ============

/**
 * Process nodes using depth-first search from start nodes.
 *
 * Events are laid out in their original order (preserving the user's
 * mental model). After each event's full subtree is positioned we
 * record the rightmost column any of its nodes occupied, and the
 * next event starts two columns to the right of that boundary.
 * This prevents successor trees of adjacent events from crossing.
 */
export function processFlowLayout(
  startNodes: Node[],
  nodes: Node[],
  graphData: GraphData
): Map<string, LayoutPosition> {
  const positioned = new Set<string>();
  const nodePositions = new Map<string, LayoutPosition>();
  const rowColumns = new Map<number, number>(); // row -> next available column
  let maxRow = 0;

  // Track the next available starting column for each event flow.
  // After laying out one event's complete subtree we advance this
  // to max-column-used + 1 so the next event starts in the adjacent
  // column to the right, avoiding crossings without wasting space.
  let nextEventColumn = 0;
  
  // Process each event/start node and its complete flow
  startNodes.forEach((startNode) => {
    if (positioned.has(startNode.id)) return;

    // Track the max column used by this event's subtree
    let eventMaxColumn = nextEventColumn;
    
    // Use DFS to process the complete flow from this event
    const stack = [{
      nodeId: startNode.id,
      row: 0,
      column: nextEventColumn,
      parent: null as string | null
    }];
    
    while (stack.length > 0) {
      const { nodeId, row, column, parent: _parent } = stack.pop()!;
      
      if (positioned.has(nodeId)) {
        // Node already positioned — keep its first (shallowest) position.
        // Back-edges in cycles would otherwise push nodes (e.g. gateways)
        // below their own descendants.
        continue;
      }
      
      // Get next available column for this row
      let assignedColumn = column;
      if (rowColumns.has(row)) {
        const nextCol = rowColumns.get(row)!;
        assignedColumn = Math.max(column, nextCol);
        rowColumns.set(row, assignedColumn + 1);
      } else {
        rowColumns.set(row, assignedColumn + 1);
      }
      
      // Position the node
      nodePositions.set(nodeId, { row, column: assignedColumn });
      positioned.add(nodeId);
      maxRow = Math.max(maxRow, row);
      eventMaxColumn = Math.max(eventMaxColumn, assignedColumn);
      
      // Get children in the order they appear in edges
      const children = graphData.adjacencyMap.get(nodeId) || [];
      
      // Check if current node is a gateway
      const currentNode = nodes.find(n => n.id === nodeId);
      const isGateway = currentNode && (
        currentNode.type === 'gateway' || 
        currentNode.data?.nodeType === 'gateway'
      );
      
      // Process children in reverse order (for stack-based DFS)
      // to maintain the original order when popped
      [...children].reverse().forEach((childId, index) => {
        if (!positioned.has(childId)) {
          let childColumn: number;
          let childRow: number;
          
          if (isGateway) {
            // Gateway successors: spread horizontally for multiple branches,
            // but keep single-child in the same column as the gateway.
            childColumn = children.length > 1 
              ? assignedColumn + 1 + index - Math.floor(children.length / 2)
              : assignedColumn;
            childRow = row + LAYOUT_CONFIG.GATEWAY_VERTICAL_OFFSET;
          } else {
            // Normal successors
            childColumn = children.length > 1 
              ? assignedColumn + index - Math.floor(children.length / 2)
              : assignedColumn;
            childRow = row + 1;
          }
          
          stack.push({
            nodeId: childId,
            row: childRow,
            column: childColumn,
            parent: nodeId
          });
        }
      });
    }

    // Next event starts in the column right after this event's rightmost node
    nextEventColumn = eventMaxColumn + 1;
  });
  
  // Handle any remaining unpositioned nodes
  nodes.forEach(node => {
    if (!positioned.has(node.id)) {
      const row = maxRow + 1;
      const column = rowColumns.get(row) || 0;
      nodePositions.set(node.id, { row, column });
      rowColumns.set(row, column + 1);
      positioned.add(node.id);
    }
  });
  
  return nodePositions;
}

// ============ Position Conversion ============
/**
 * Build a map of nodeId → extra vertical offset caused by condition edges
 * **in that node's own flow**.
 *
 * Unlike a global row-offset approach, this traces the directed-edge
 * ancestry of each node independently so that parallel flows are not
 * affected by conditions in neighboring flows.
 *
 * Algorithm: BFS from every root (nodes with in-degree 0 among the layout
 * edges).  When traversing an edge, the child inherits its parent's
 * cumulative offset and adds CONDITION_EXTRA_SPACING if the edge carries a
 * condition.  If a node is reachable via multiple paths the *maximum*
 * offset is kept, ensuring no condition card is clipped.
 */
function buildNodeOffsets(
  nodePositions: Map<string, LayoutPosition>,
  edges: Edge[],
): Map<string, number> {
  const nodeOffset = new Map<string, number>();

  // Build adjacency list and in-degree map, limited to positioned nodes.
  const outEdges = new Map<string, Array<{ target: string; hasCondition: boolean }>>();
  const incomingOffers = new Map<string, number[]>();
  const inDeg = new Map<string, number>();
  const remaining = new Map<string, number>(); // parents left to process

  for (const id of nodePositions.keys()) {
    outEdges.set(id, []);
    incomingOffers.set(id, []);
    inDeg.set(id, 0);
    remaining.set(id, 0);
  }

  for (const edge of edges) {
    if (nodePositions.has(edge.source) && nodePositions.has(edge.target)) {
      outEdges.get(edge.source)!.push({
        target: edge.target,
        hasCondition: edgeHasCondition(edge),
      });
      const deg = (inDeg.get(edge.target) || 0) + 1;
      inDeg.set(edge.target, deg);
      remaining.set(edge.target, deg);
    }
  }

  // Kahn's algorithm: process nodes in topological order so each node is
  // handled exactly once, after all its parents have been resolved.
  // This avoids infinite loops even if the workflow graph contains cycles
  // (cycle nodes simply get zero offset as they are never enqueued).
  const queue: string[] = [];
  for (const [id, deg] of inDeg) {
    if (deg === 0) {
      queue.push(id);
      nodeOffset.set(id, 0);
    }
  }

  while (queue.length > 0) {
    const current = queue.shift()!;
    const parentOffset = nodeOffset.get(current) || 0;

    for (const { target, hasCondition } of outEdges.get(current) || []) {
      const extra = hasCondition ? LAYOUT_CONFIG.CONDITION_EXTRA_SPACING : 0;
      const proposed = parentOffset + extra;

      // Collect all offers; the node will take the max when all parents are done.
      incomingOffers.get(target)!.push(proposed);

      const left = (remaining.get(target) || 1) - 1;
      remaining.set(target, left);

      if (left === 0) {
        // All parents processed — pick the maximum offset.
        const offers = incomingOffers.get(target)!;
        nodeOffset.set(target, Math.max(...offers));
        queue.push(target);
      }
    }
  }

  // Second pass: break cycle deadlocks.
  // Nodes in cycles were never enqueued because they wait for parents that
  // are also in the cycle.  Resolve them by using whatever partial offers
  // they have already collected, then propagate normally.
  let changed = true;
  while (changed) {
    changed = false;
    for (const id of nodePositions.keys()) {
      if (nodeOffset.has(id)) continue; // already resolved
      const offers = incomingOffers.get(id)!;
      if (offers.length > 0) {
        // At least one non-cycle parent delivered an offer — use the max.
        nodeOffset.set(id, Math.max(...offers));
        queue.push(id);
        changed = true;
      }
    }
    // Propagate from the newly resolved cycle nodes.
    while (queue.length > 0) {
      const current = queue.shift()!;
      const parentOffset = nodeOffset.get(current) || 0;

      for (const { target, hasCondition } of outEdges.get(current) || []) {
        const extra = hasCondition ? LAYOUT_CONFIG.CONDITION_EXTRA_SPACING : 0;
        const proposed = parentOffset + extra;
        incomingOffers.get(target)!.push(proposed);

        if (!nodeOffset.has(target)) {
          const left = (remaining.get(target) || 1) - 1;
          remaining.set(target, left);
          if (left <= 0) {
            const allOffers = incomingOffers.get(target)!;
            nodeOffset.set(target, Math.max(...allOffers));
            queue.push(target);
            changed = true;
          }
        }
      }
    }
  }

  // Any node still not reached (fully disconnected) gets zero offset.
  for (const id of nodePositions.keys()) {
    if (!nodeOffset.has(id)) {
      nodeOffset.set(id, 0);
    }
  }

  return nodeOffset;
}

/**
 * Convert row/column positions to actual x/y coordinates.
 *
 * Rows where an incoming edge carries a condition get extra vertical space
 * so the condition card fits between the two connected nodes.
 */
export function convertPositionsToCoordinates(
  nodes: Node[],
  nodePositions: Map<string, LayoutPosition>,
  startPos: { x: number; y: number },
  _lockedNodeBounds: unknown[],
  edges: Edge[] = [],
): Node[] {
  const layoutNodes = [...nodes];
  const positionedBounds: Array<{ x1: number; y1: number; x2: number; y2: number }> = [];

  // Pre-compute per-node extra offsets for condition edges.
  const nodeOffsets = buildNodeOffsets(nodePositions, edges);
  
  layoutNodes.forEach(node => {
    const pos = nodePositions.get(node.id);
    if (pos) {
      const targetX = startPos.x + (pos.column * LAYOUT_CONFIG.HORIZONTAL_SPACING);
      const extraY = nodeOffsets.get(node.id) || 0;
      const targetY = startPos.y + (pos.row * LAYOUT_CONFIG.VERTICAL_SPACING) + extraY;
      
      // Find non-colliding position among already-placed nodes
      const finalPosition = findNonCollidingPosition(targetX, targetY, positionedBounds);
      
      node.position = finalPosition;
      
      // Add this positioned node to bounds to prevent overlaps with subsequent nodes
      positionedBounds.push({
        x1: finalPosition.x - LAYOUT_CONFIG.NODE_WIDTH / 2,
        y1: finalPosition.y - LAYOUT_CONFIG.NODE_HEIGHT / 2,
        x2: finalPosition.x + LAYOUT_CONFIG.NODE_WIDTH / 2,
        y2: finalPosition.y + LAYOUT_CONFIG.NODE_HEIGHT / 2
      });
    }
  });
  
  return layoutNodes;
}

// ============ Layout Optimization ============
/**
 * Group nodes by row for alignment optimization
 */
export function groupNodesByRow(
  nodePositions: Map<string, LayoutPosition>,
  nodes: Node[]
): Map<number, string[]> {
  const rowNodes = new Map<number, string[]>();
  
  nodePositions.forEach((pos, nodeId) => {
    const node = nodes.find(n => n.id === nodeId);
    if (node) {
      if (!rowNodes.has(pos.row)) {
        rowNodes.set(pos.row, []);
      }
      rowNodes.get(pos.row)!.push(nodeId);
    }
  });
  
  return rowNodes;
}

/**
 * Align nodes within rows to minimize edge crossings
 */
export function optimizeRowAlignment(
  nodes: Node[],
  edges: Edge[],
  rowNodes: Map<number, string[]>,
): void {
  rowNodes.forEach((nodeIds, _row) => {
    if (nodeIds.length <= 1) return;
    
    // Calculate ideal positions based on connected nodes
    nodeIds.forEach(nodeId => {
      const node = nodes.find(n => n.id === nodeId);
      if (!node || !node.position) return;
      
      const idealX = calculateIdealXPosition(nodeId, edges, nodes);
      
      // Apply gentle adjustment toward ideal position
      if (idealX !== null) {
        const currentX = node.position.x;
        node.position.x = currentX + (idealX - currentX) * LAYOUT_CONFIG.IDEAL_POSITION_WEIGHT;
      }
    });
    
    // Ensure minimum spacing between nodes in the same row
    enforceMinimumSpacing(nodeIds, nodes);
  });
}

/**
 * Ensure minimum spacing between nodes in the same row
 */
function enforceMinimumSpacing(
  nodeIds: string[],
  nodes: Node[],
): void {
  const sortedNodes = nodeIds
    .map(id => nodes.find(n => n.id === id))
    .filter((n): n is Node => n !== undefined && n.position !== undefined)
    .sort((a, b) => a.position!.x - b.position!.x);
  
  for (let i = 1; i < sortedNodes.length; i++) {
    const prevNode = sortedNodes[i - 1];
    const currNode = sortedNodes[i];
    
    if (!prevNode.position || !currNode.position) continue;
    
    const minDistance = LAYOUT_CONFIG.MIN_NODE_DISTANCE;
    
    if (currNode.position.x - prevNode.position.x < minDistance) {
      currNode.position.x = prevNode.position.x + minDistance;
    }
  }
}

/**
 * Find a non-colliding position near the target position
 */
function findNonCollidingPosition(
  targetX: number,
  targetY: number,
  bounds: Array<{ x1: number; y1: number; x2: number; y2: number }>
): { x: number; y: number } {
  let candidateX = targetX;
  let candidateY = targetY;
  let attempts = 0;
  const maxAttempts = 50;
  let deltaX = 0;
  let deltaY = 0;

  const isOccupied = (x: number, y: number): boolean => {
    const candidateBounds = {
      x1: x - LAYOUT_CONFIG.NODE_WIDTH / 2 - LAYOUT_CONFIG.COLLISION_PADDING,
      y1: y - LAYOUT_CONFIG.NODE_HEIGHT / 2 - LAYOUT_CONFIG.COLLISION_PADDING,
      x2: x + LAYOUT_CONFIG.NODE_WIDTH / 2 + LAYOUT_CONFIG.COLLISION_PADDING,
      y2: y + LAYOUT_CONFIG.NODE_HEIGHT / 2 + LAYOUT_CONFIG.COLLISION_PADDING
    };
    return bounds.some(b =>
      candidateBounds.x1 < b.x2 &&
      candidateBounds.x2 > b.x1 &&
      candidateBounds.y1 < b.y2 &&
      candidateBounds.y2 > b.y1
    );
  };
  
  while (isOccupied(candidateX, candidateY) && attempts < maxAttempts) {
    attempts++;
    
    if (attempts <= 10) {
      deltaX += LAYOUT_CONFIG.HORIZONTAL_SPACING / 3;
      candidateX = targetX + deltaX;
    } else if (attempts <= 20) {
      deltaY += LAYOUT_CONFIG.VERTICAL_SPACING / 3;
      candidateY = targetY + deltaY;
      candidateX = targetX;
    } else if (attempts <= 30) {
      deltaX -= LAYOUT_CONFIG.HORIZONTAL_SPACING / 3;
      candidateX = targetX + deltaX;
      candidateY = targetY;
    } else {
      deltaX += LAYOUT_CONFIG.HORIZONTAL_SPACING / 4;
      deltaY += LAYOUT_CONFIG.VERTICAL_SPACING / 4;
      candidateX = targetX + deltaX;
      candidateY = targetY + deltaY;
    }
  }
  
  return { x: candidateX, y: candidateY };
}
