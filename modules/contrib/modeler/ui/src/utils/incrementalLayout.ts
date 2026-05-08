/**
 * Incremental layout primitives.
 *
 * This module is the **single source of placement truth** for the modeler.
 * It contains pure (React-free, store-free) functions that decide where to
 * place a new node, given the current set of nodes and edges.
 *
 * Both code paths use these primitives:
 *
 * - **Quick-add / drag-to-connect / plugin API addNode/addEdge**: place one
 *   new successor at a time by calling {@link placeSuccessor}.
 * - **Auto-layout (model load + toolbar button)**: simulate the incremental
 *   build by walking the graph from each start node and calling
 *   {@link placeSuccessor} once per edge in topological order.
 *
 * Because both paths share the same primitives, the auto-layout output is
 * by definition equivalent to "what the user would have seen if they had
 * built this model node-by-node with quick-add".
 *
 * Coordinates are top-left throughout (matching ReactFlow's coordinate
 * system).  All gap math goes through {@link requiredVerticalGap} and
 * {@link shiftNodesDown} from positionUtils.ts so there is no duplicated
 * spacing logic anywhere else in the codebase.
 */

import type { StoreNode as Node, StoreEdge as Edge } from '../types/settings';
import { LAYOUT, NODE_DIMENSIONS } from '../constants/dimensions';
import {
  findFlowAwarePosition,
  findFreePosition,
  shiftNodesDown,
  requiredVerticalGap,
} from './positionUtils';

// ============ Types ============

/** Result of placing a single successor node. */
export interface PlaceSuccessorResult {
  /** Updated node array with the new node inserted and any shifts applied. */
  nodes: Node[];
  /** The position assigned to the new node (top-left, ReactFlow coordinates). */
  position: { x: number; y: number };
}

/** Options for placing a successor of an existing node. */
export interface PlaceSuccessorOptions {
  /** All nodes currently on the canvas. */
  nodes: Node[];
  /** All edges currently on the canvas. */
  edges: Edge[];
  /** ID of the parent node the new successor connects from. */
  sourceNodeId: string;
  /** Whether the connecting edge carries a condition card. */
  hasCondition?: boolean;
  /**
   * For multi-child fan-out (gateway successors only): the index of this
   * successor in the parent's child list and the total number of siblings.
   * When omitted (or when totalSiblings <= 1), the new node is placed in
   * its parent's column — the tidy single-column layout used for plain
   * action / start nodes.
   */
  siblingIndex?: number;
  totalSiblings?: number;
  /** Whether the parent is a gateway (controls horizontal fan-out). */
  isGatewayChild?: boolean;
}

/** Options for placing a fresh event node (no parent). */
export interface PlaceNewEventOptions {
  /** All nodes currently on the canvas. */
  nodes: Node[];
  /** Optional explicit candidate position; defaults to the right of all existing flows. */
  candidate?: { x: number; y: number };
  /** Width of the new event node (defaults to START_NODE_WIDTH). */
  width?: number;
  /** Height of the new event node (defaults to START_NODE_HEIGHT). */
  height?: number;
}

// ============ Helpers ============

/**
 * Decide whether an edge carries a condition (and therefore needs the
 * extra vertical gap for the condition card).
 */
export function edgeHasCondition(edge: Edge): boolean {
  return !!(
    edge.data?.condition ||
    edge.data?.conditionLabel ||
    (edge.data?.conditionConfiguration &&
      Object.keys(edge.data.conditionConfiguration as Record<string, unknown>).length > 0)
  );
}

/**
 * Center X of a candidate child node directly under its parent so that the
 * two cards visually align in a single column.
 */
function columnAlignedX(parent: Node, childWidth: number): number {
  const parentWidth = parent.width || NODE_DIMENSIONS.CARD_WIDTH;
  return parent.position.x + (parentWidth - childWidth) / 2;
}

/**
 * Compute the X position for a gateway child given its sibling index and
 * count.  Two siblings sit symmetrically around the parent's center, three
 * siblings get one centered and the others to either side, and so on.
 */
function gatewayChildX(
  parent: Node,
  childWidth: number,
  siblingIndex: number,
  totalSiblings: number,
): number {
  const centerX = columnAlignedX(parent, childWidth);
  const step = childWidth + LAYOUT.NODE_SPACING_X;
  // Offset the index so the children are centered around the parent.
  // For 2 children: indices 0,1 -> offsets -0.5, +0.5
  // For 3 children: indices 0,1,2 -> offsets -1, 0, +1
  const offset = siblingIndex - (totalSiblings - 1) / 2;
  return centerX + offset * step;
}

// ============ Single-successor placement ============

/**
 * Place one new successor below its parent, optionally shifting downstream
 * nodes to make vertical room and shifting neighboring flows to make
 * horizontal room.  This is the primitive that drives quick-add,
 * drag-to-connect, the plugin API's addNode/addEdge, and (via
 * {@link simulateIncrementalBuild}) auto-layout.
 *
 * The new successor must already exist in `options.nodes` with whatever
 * temporary position it was created at; this function returns an updated
 * node array in which the new node has been moved to its final position
 * and any shifts have been applied.
 *
 * @returns The updated nodes array and the position assigned to the new
 *          successor.  Callers that don't yet have the new node in the
 *          array can use {@link computeSuccessorPosition} instead.
 */
export function computeSuccessorPosition(options: PlaceSuccessorOptions): {
  position: { x: number; y: number };
  shiftNodeIds: Set<string>;
  shiftAmount: number;
} {
  const {
    nodes,
    edges,
    sourceNodeId,
    hasCondition = false,
    siblingIndex = 0,
    totalSiblings = 1,
    isGatewayChild = false,
  } = options;

  const sourceNode = nodes.find(n => n.id === sourceNodeId);
  if (!sourceNode) {
    return {
      position: { x: LAYOUT.DEFAULT_POSITION_X, y: LAYOUT.DEFAULT_POSITION_Y },
      shiftNodeIds: new Set(),
      shiftAmount: 0,
    };
  }

  const childWidth = NODE_DIMENSIONS.CARD_WIDTH;
  const childHeight = NODE_DIMENSIONS.CARD_HEIGHT;
  const sourceHeight = sourceNode.height || nodeHeightFallback(sourceNode);

  // Vertical: directly below the parent, with a gap that accounts for
  // condition cards on the connecting edge.
  const candidateY = sourceNode.position.y + sourceHeight + requiredVerticalGap(hasCondition);

  // Horizontal: gateway children fan out, plain successors stay in column.
  const candidateX = isGatewayChild && totalSiblings > 1
    ? gatewayChildX(sourceNode, childWidth, siblingIndex, totalSiblings)
    : columnAlignedX(sourceNode, childWidth);

  // Apply flow-aware collision avoidance: if the candidate lands on top of
  // an existing node, shift right within the source flow's safe zone, or
  // push neighboring flows further right to make room.
  return findFlowAwarePosition(
    { x: candidateX, y: candidateY },
    sourceNodeId,
    nodes,
    edges,
    childWidth,
    childHeight,
  );
}

/**
 * Apply the result of {@link computeSuccessorPosition} to a node array,
 * deselecting any previously selected nodes and shifting neighboring
 * flows where required.  Does **not** add the new successor itself —
 * callers are expected to push their newly created node onto the
 * returned array.
 */
export function applyFlowShifts(
  nodes: Node[],
  shiftNodeIds: Set<string>,
  shiftAmount: number,
  { deselectAll = true }: { deselectAll?: boolean } = {},
): Node[] {
  if (shiftAmount <= 0 && !deselectAll) {
    return nodes;
  }
  return nodes.map(n => {
    let node = deselectAll && n.selected ? { ...n, selected: false } : n;
    if (shiftAmount > 0 && n.id && shiftNodeIds.has(n.id)) {
      node = node === n ? { ...n } : node;
      node.position = { x: node.position.x + shiftAmount, y: node.position.y };
    }
    return node;
  });
}

// ============ New-event placement ============

/**
 * Compute the position for a brand-new event flow.  By default it sits to
 * the right of every existing flow at the topmost row, mirroring the
 * incremental "add event" UX.
 */
export function computeNewEventPosition(options: PlaceNewEventOptions): { x: number; y: number } {
  const { nodes, candidate, width, height } = options;
  const w = width ?? NODE_DIMENSIONS.START_NODE_WIDTH;
  const h = height ?? NODE_DIMENSIONS.START_NODE_HEIGHT;

  if (candidate) {
    return findFreePosition(candidate, nodes, w, h);
  }

  if (nodes.length === 0) {
    return { x: LAYOUT.LAYOUT_START_X, y: LAYOUT.LAYOUT_START_Y };
  }

  const maxX = Math.max(...nodes.map(n => n.position.x));
  const minY = Math.min(...nodes.map(n => n.position.y));
  return findFreePosition(
    { x: maxX + LAYOUT.NODE_SPACING_X, y: minY },
    nodes,
    w,
    h,
  );
}

// ============ Edge-insertion placement ============

/**
 * Position a node that has just been inserted on an existing edge,
 * splitting it into source→newNode and newNode→target.  Returns an
 * updated nodes array with the new node placed and the target node
 * (plus all its descendants) shifted downward if required.
 */
export function placeNodeOnEdge(
  nodes: Node[],
  edges: Edge[],
  newNode: Node,
  sourceNodeId: string,
  targetNodeId: string,
  hasConditionBefore: boolean,
  hasConditionAfter: boolean,
): Node[] {
  const sourceNode = nodes.find(n => n.id === sourceNodeId);
  const targetNode = nodes.find(n => n.id === targetNodeId);

  if (!sourceNode || !targetNode) {
    return [...nodes, newNode];
  }

  const sourceBottom = sourceNode.position.y +
    (sourceNode.height || NODE_DIMENSIONS.CARD_HEIGHT);

  // X aligns under the source.
  const newNodeX = columnAlignedX(sourceNode, NODE_DIMENSIONS.CARD_WIDTH);
  // Y sits one condition-aware gap below the source.
  const newNodeY = sourceBottom + requiredVerticalGap(hasConditionBefore);

  const positionedNewNode: Node = {
    ...newNode,
    position: { x: newNodeX, y: newNodeY },
  };

  // Shift target (and descendants) downward if there is not enough room.
  const newNodeBottom = newNodeY + NODE_DIMENSIONS.CARD_HEIGHT;
  const desiredTargetY = newNodeBottom + requiredVerticalGap(hasConditionAfter);
  const shift = Math.max(0, desiredTargetY - targetNode.position.y);

  const descendants = collectDescendants(targetNodeId, edges);
  descendants.add(targetNodeId);

  const updated = nodes.map(n => {
    if (shift > 0 && descendants.has(n.id)) {
      return { ...n, position: { x: n.position.x, y: n.position.y + shift } };
    }
    return n;
  });

  return [...updated, positionedNewNode];
}

/**
 * Collect all descendant node IDs reachable from `startId` via directed
 * edges.  Used by edge-insertion to know which nodes must shift down.
 */
function collectDescendants(startId: string, edges: Edge[]): Set<string> {
  const descendants = new Set<string>();
  const queue = [startId];
  while (queue.length > 0) {
    const current = queue.shift()!;
    for (const edge of edges) {
      if (edge.source === current && !descendants.has(edge.target)) {
        descendants.add(edge.target);
        queue.push(edge.target);
      }
    }
  }
  return descendants;
}

// ============ Condition-card spacing on existing edges ============

/**
 * Apply the vertical shift required when a condition card is added to an
 * existing edge: the target node (and everything below it) moves down so
 * the condition card has room between source and target.  Returns the
 * (possibly updated) nodes array.
 */
export function ensureGapForCondition(
  nodes: Node[],
  sourceNodeId: string,
  targetNodeId: string,
): Node[] {
  const sourceNode = nodes.find(n => n.id === sourceNodeId);
  const targetNode = nodes.find(n => n.id === targetNodeId);
  if (!sourceNode || !targetNode) return nodes;

  const sourceBottom = sourceNode.position.y +
    (sourceNode.height || NODE_DIMENSIONS.CARD_HEIGHT);
  const currentGap = targetNode.position.y - sourceBottom;
  const neededGap = requiredVerticalGap(true);
  const shift = Math.max(0, neededGap - currentGap);

  if (shift <= 0) return nodes;

  return shiftNodesDown(
    nodes,
    targetNode.position.y,
    shift,
    new Set([sourceNode.id]),
  );
}

// ============ Auto-layout: simulate incremental build ============

/**
 * Identify start nodes for layout simulation.  A node qualifies as a
 * start if it is typed `start`, or has zero incoming edges, or its
 * plugin name contains `event`.  The list is sorted so explicit
 * `start`-typed nodes come first (preserving the user's mental model).
 */
function identifyStartNodes(nodes: Node[], edges: Edge[]): Node[] {
  const inDegree = new Map<string, number>();
  for (const node of nodes) inDegree.set(node.id, 0);
  for (const edge of edges) {
    if (inDegree.has(edge.target)) {
      inDegree.set(edge.target, (inDegree.get(edge.target) || 0) + 1);
    }
  }

  const candidates = nodes.filter(node =>
    node.type === 'start' ||
    inDegree.get(node.id) === 0 ||
    (node.data?.plugin && String(node.data.plugin).includes('event'))
  );

  candidates.sort((a, b) => {
    if (a.type === 'start' && b.type !== 'start') return -1;
    if (b.type === 'start' && a.type !== 'start') return 1;
    return 0;
  });

  if (candidates.length > 0) return candidates;
  // Fallback: nodes with zero in-degree (covers cycles where no node has
  // type === 'start' and no plugin is an event).
  const orphans = nodes.filter(node => inDegree.get(node.id) === 0);
  if (orphans.length > 0) return orphans;
  return nodes.length > 0 ? [nodes[0]] : [];
}

/**
 * Build an outgoing-edge adjacency map preserving the order in which
 * children appear in the edge array.  Used to drive the simulation in a
 * deterministic, user-meaningful order.
 */
function buildAdjacency(nodes: Node[], edges: Edge[]): Map<string, string[]> {
  const adj = new Map<string, string[]>();
  for (const node of nodes) adj.set(node.id, []);
  for (const edge of edges) {
    if (adj.has(edge.source) && adj.has(edge.target)) {
      adj.get(edge.source)!.push(edge.target);
    }
  }
  return adj;
}

/**
 * Decide whether a node should branch its children horizontally
 * (gateway-style) or keep them in a single column (action-style).
 * Only nodes typed `gateway` (or with `data.nodeType === 'gateway'`)
 * fan out — matching the user's mental model where gateways are the
 * explicit branching primitive.
 */
function isGatewayNode(node: Node | undefined): boolean {
  if (!node) return false;
  if (node.type === 'gateway') return true;
  if (node.data?.nodeType === 'gateway') return true;
  return false;
}

/**
 * Return the correct height fallback for a node based on its type.
 * Gateway nodes use a compact height; all others use the standard card height.
 */
function nodeHeightFallback(node: Node): number {
  return isGatewayNode(node) ? NODE_DIMENSIONS.GATEWAY_HEIGHT : NODE_DIMENSIONS.CARD_HEIGHT;
}

/**
 * Lay out a complete graph by simulating the incremental build process.
 *
 * Algorithm:
 *
 *   1. Identify start nodes and place each one to the right of the
 *      previously laid out flow.  The first start node anchors at
 *      `LAYOUT_START_X / LAYOUT_START_Y`.
 *   2. For each start node, BFS-traverse its descendants.  When visiting
 *      a node, queue every not-yet-placed child but only place it once
 *      *all* of its forward-reachable parents have been placed.  This
 *      ensures convergent (multi-parent) nodes know every parent's
 *      position before computing their own.
 *   3. Single-parent placement uses the same primitive as quick-add:
 *      column-aligned for non-gateway parents, fan-out for gateways.
 *   4. Multi-parent (convergent) placement uses the centroid of the
 *      parents' centers — naturally centering merge points under a
 *      common ancestor (e.g. event with two condition branches that
 *      converge into a single action).
 *   5. Cycle back-edges are excluded from the "all parents placed"
 *      requirement to break deadlocks; nodes still waiting at the end
 *      of BFS are placed using whichever parents have already been
 *      laid out, falling back to first-parent column when none are.
 *   6. Any nodes that remain unplaced after all reachable traversals
 *      (fully disconnected components) are placed to the right of the
 *      last laid-out flow as fresh events.
 *
 * @param nodes - Nodes to lay out (their existing positions are ignored
 *                and replaced).
 * @param edges - Edges driving the traversal order.
 * @returns A new node array with the same nodes but freshly assigned
 *          positions.  Returns `null` for empty input to preserve the
 *          existing autoLayout contract.
 */
export function simulateIncrementalBuild(nodes: Node[], edges: Edge[]): Node[] | null {
  if (!nodes || nodes.length === 0) return null;
  const safeEdges = edges || [];

  // Single start at the layout origin; subsequent starts step one flow
  // gap to the right of the previous flow's rightmost node.
  const startNodes = identifyStartNodes(nodes, safeEdges);
  const adjacency = buildAdjacency(nodes, safeEdges);

  // Build incoming-edge map so we can ask "who are this node's parents?".
  const incoming = new Map<string, string[]>();
  for (const node of nodes) incoming.set(node.id, []);
  for (const edge of safeEdges) {
    if (incoming.has(edge.target) && incoming.has(edge.source)) {
      incoming.get(edge.target)!.push(edge.source);
    }
  }

  // Build a fast edge lookup (source,target) -> Edge so we can ask
  // whether a given edge carries a condition.
  const edgeLookup = new Map<string, Edge>();
  for (const edge of safeEdges) {
    edgeLookup.set(`${edge.source}\u0000${edge.target}`, edge);
  }

  // Working node array.  We seed it with each start node's anchor as we
  // process them, so flow-aware placement can see existing flows.
  let working: Node[] = [];
  const placed = new Set<string>();

  // Reusable map from original node ID to the original node object, so
  // we can read its type/data when needed without searching.
  const nodeById = new Map<string, Node>();
  for (const node of nodes) nodeById.set(node.id, node);

  /**
   * Place a node that has at least one already-placed parent.  Single
   * parent → use the column-aligned / fan-out primitive.  Multiple
   * parents → use the centroid of placed parents.
   */
  const placeChildNow = (childId: string, parentId: string, siblingIndex: number, totalSiblings: number): void => {
    const childOriginal = nodeById.get(childId);
    if (!childOriginal) return;

    const placedParents = (incoming.get(childId) || []).filter(p => placed.has(p));
    const parentObjects = placedParents
      .map(p => working.find(n => n.id === p))
      .filter((n): n is Node => n !== undefined);

    let position: { x: number; y: number };
    let shiftNodeIds = new Set<string>();
    let shiftAmount = 0;

    if (parentObjects.length >= 2) {
      // Convergent node: centroid of parent centers, Y below the lowest parent.
      const childWidth = childOriginal.width || NODE_DIMENSIONS.CARD_WIDTH;
      const childHeight = childOriginal.height || nodeHeightFallback(childOriginal);

      let centerXSum = 0;
      let maxBottom = -Infinity;
      let anyHasCondition = false;
      for (const parent of parentObjects) {
        const pw = parent.width || NODE_DIMENSIONS.CARD_WIDTH;
        const ph = parent.height || nodeHeightFallback(parent);
        centerXSum += parent.position.x + pw / 2;
        const bottom = parent.position.y + ph;
        if (bottom > maxBottom) maxBottom = bottom;
        const edge = edgeLookup.get(`${parent.id}\u0000${childId}`);
        if (edge && edgeHasCondition(edge)) anyHasCondition = true;
      }
      const centroidX = centerXSum / parentObjects.length;
      const candidateX = centroidX - childWidth / 2;
      const candidateY = maxBottom + requiredVerticalGap(anyHasCondition);

      // Use flow-aware positioning anchored on the first placed parent so
      // collision avoidance and neighbor-flow shifts still work.  We feed
      // the centroid candidate; if it overlaps a same-flow node, the
      // primitive will adjust within the flow's safe zone.
      const anchorParentId = parentObjects[0].id!;
      const result = findFlowAwarePosition(
        { x: candidateX, y: candidateY },
        anchorParentId,
        working,
        safeEdges,
        childWidth,
        childHeight,
      );
      position = result.position;
      shiftNodeIds = result.shiftNodeIds;
      shiftAmount = result.shiftAmount;
    } else {
      // Single-parent placement — same code path as quick-add.
      const parentOriginal = nodeById.get(parentId);
      const isGateway = isGatewayNode(parentOriginal);
      const edge = edgeLookup.get(`${parentId}\u0000${childId}`);
      const hasCondition = edge ? edgeHasCondition(edge) : false;

      const result = computeSuccessorPosition({
        nodes: working,
        edges: safeEdges,
        sourceNodeId: parentId,
        hasCondition,
        siblingIndex,
        totalSiblings,
        isGatewayChild: isGateway,
      });
      position = result.position;
      shiftNodeIds = result.shiftNodeIds;
      shiftAmount = result.shiftAmount;
    }

    if (shiftAmount > 0) {
      working = working.map(n => {
        if (n.id && shiftNodeIds.has(n.id)) {
          return { ...n, position: { x: n.position.x + shiftAmount, y: n.position.y } };
        }
        return n;
      });
    }

    const positionedChild: Node = { ...childOriginal, position };
    working = [...working, positionedChild];
    placed.add(childId);
  };

  for (const startNode of startNodes) {
    if (placed.has(startNode.id)) continue;

    // Place this start node to the right of all existing flows.
    const startPosition = computeNewEventPosition({
      nodes: working,
      candidate: working.length === 0
        ? { x: LAYOUT.LAYOUT_START_X, y: LAYOUT.LAYOUT_START_Y }
        : undefined,
      width: startNode.width || NODE_DIMENSIONS.START_NODE_WIDTH,
      height: startNode.height || NODE_DIMENSIONS.START_NODE_HEIGHT,
    });

    const positionedStart: Node = { ...startNode, position: startPosition };
    working = [...working, positionedStart];
    placed.add(startNode.id);

    // BFS from this start node.  Process each parent in dequeue order
    // so siblings keep their relative ordering from the edges array.
    // Children whose other parents haven't been placed yet are deferred
    // until the last of their parents is dequeued — this gives the
    // centroid placement complete information.
    const queue: string[] = [startNode.id];
    while (queue.length > 0) {
      const parentId = queue.shift()!;
      const childIds = adjacency.get(parentId) || [];

      // Determine which children are placeable now (all forward parents
      // already placed) versus deferred for a later iteration.
      const placeableNow: string[] = [];
      for (const childId of childIds) {
        if (placed.has(childId)) continue;
        const parentsOfChild = incoming.get(childId) || [];
        // Forward parents = parents that are not the child itself in a
        // cycle and that aren't downstream of childId.  We approximate
        // by counting whether every parent has been placed.  Cycle
        // back-edges will leave a parent unplaced, so we exclude
        // children whose parent is reachable only through them.
        const allParentsPlaced = parentsOfChild.every(p =>
          placed.has(p) || isOnlyReachableThrough(p, childId, adjacency, placed),
        );
        if (allParentsPlaced) placeableNow.push(childId);
      }

      const totalNew = placeableNow.length;
      placeableNow.forEach((childId, index) => {
        placeChildNow(childId, parentId, index, totalNew);
        queue.push(childId);
      });
    }

    // Sweep up any reachable children that were deferred but never
    // satisfied (cycles where one parent is downstream of the child).
    // Place them using whichever parents are already laid out.
    let progressed = true;
    while (progressed) {
      progressed = false;
      const queue2: string[] = [];
      for (const node of nodes) {
        if (placed.has(node.id)) continue;
        const parentsPlaced = (incoming.get(node.id) || []).some(p => placed.has(p));
        if (parentsPlaced) {
          const firstPlacedParent = (incoming.get(node.id) || []).find(p => placed.has(p))!;
          placeChildNow(node.id, firstPlacedParent, 0, 1);
          queue2.push(node.id);
          progressed = true;
        }
      }
      // Drain queue2 just to keep BFS-like ordering for any of their
      // children that now become placeable; the for-loop above will
      // pick them up on the next outer iteration anyway.
      void queue2;
    }
  }

  // Any nodes still unplaced are part of a fully disconnected sub-graph
  // (no path from any start node).  Treat each as its own event.
  for (const node of nodes) {
    if (placed.has(node.id)) continue;
    const position = computeNewEventPosition({
      nodes: working,
      width: node.width || NODE_DIMENSIONS.CARD_WIDTH,
      height: node.height || nodeHeightFallback(node),
    });
    working = [...working, { ...node, position }];
    placed.add(node.id);
  }

  return working;
}

/**
 * Heuristic: is `parentCandidate` only reachable through `childId`
 * (i.e. the edge `parentCandidate -> childId` is part of a back-edge
 * cycle)?  Returns true if every path from a placed node to
 * `parentCandidate` goes through `childId`, which means we should not
 * wait for `parentCandidate` to be placed before placing `childId`.
 *
 * Conservative: returns true only when `parentCandidate` is directly
 * downstream of `childId` (closing a simple cycle).  Larger cycles are
 * resolved by the post-BFS sweep instead.
 */
function isOnlyReachableThrough(
  parentCandidate: string,
  childId: string,
  adjacency: Map<string, string[]>,
  placed: Set<string>,
): boolean {
  if (placed.has(parentCandidate)) return false;
  // BFS forward from childId; if we reach parentCandidate, it's a
  // descendant of childId (i.e. the edge from parentCandidate to
  // childId is a back-edge).
  const visited = new Set<string>([childId]);
  const queue = [childId];
  while (queue.length > 0) {
    const current = queue.shift()!;
    for (const next of adjacency.get(current) || []) {
      if (next === parentCandidate) return true;
      if (!visited.has(next)) {
        visited.add(next);
        queue.push(next);
      }
    }
  }
  return false;
}
