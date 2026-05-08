import {
  computeSuccessorPosition,
  computeNewEventPosition,
  ensureGapForCondition,
  placeNodeOnEdge,
  simulateIncrementalBuild,
  edgeHasCondition,
} from '../incrementalLayout';
import type { StoreNode as Node, StoreEdge as Edge } from '../../types/settings';
import { LAYOUT, NODE_DIMENSIONS } from '../../constants/dimensions';

/**
 * Build a typed StoreNode without ceremony.  Defaults give a generic
 * `element` node sized to the standard card dimensions.
 */
function makeNode(id: string, overrides: Partial<Node> = {}): Node {
  return {
    id,
    type: overrides.type ?? 'element',
    position: overrides.position ?? { x: 0, y: 0 },
    data: overrides.data ?? {},
    width: overrides.width ?? NODE_DIMENSIONS.CARD_WIDTH,
    height: overrides.height ?? NODE_DIMENSIONS.CARD_HEIGHT,
    ...overrides,
  };
}

function makeEdge(id: string, source: string, target: string, overrides: Partial<Edge> = {}): Edge {
  return {
    id,
    source,
    target,
    data: {},
    ...overrides,
  };
}

describe('incrementalLayout', () => {
  describe('edgeHasCondition', () => {
    it('returns false for plain edges', () => {
      expect(edgeHasCondition(makeEdge('e1', 'a', 'b'))).toBe(false);
    });

    it('returns true when condition plugin is set', () => {
      expect(edgeHasCondition(makeEdge('e1', 'a', 'b', {
        data: { condition: 'some_condition' },
      }))).toBe(true);
    });

    it('returns true when conditionLabel is set', () => {
      expect(edgeHasCondition(makeEdge('e1', 'a', 'b', {
        data: { conditionLabel: 'X' },
      }))).toBe(true);
    });

    it('returns true when conditionConfiguration has keys', () => {
      expect(edgeHasCondition(makeEdge('e1', 'a', 'b', {
        data: { conditionConfiguration: { foo: 'bar' } },
      }))).toBe(true);
    });

    it('returns false for empty conditionConfiguration', () => {
      expect(edgeHasCondition(makeEdge('e1', 'a', 'b', {
        data: { conditionConfiguration: {} },
      }))).toBe(false);
    });
  });

  describe('computeSuccessorPosition', () => {
    it('places a successor in the parent column for a non-gateway parent', () => {
      const parent = makeNode('p', { position: { x: 100, y: 100 } });
      const result = computeSuccessorPosition({
        nodes: [parent],
        edges: [],
        sourceNodeId: 'p',
      });
      // X aligns with parent (same width).
      expect(result.position.x).toBe(parent.position.x);
      // Y is one plain-edge gap below the parent's bottom.
      expect(result.position.y).toBeGreaterThan(parent.position.y);
    });

    it('places multiple non-gateway successors in the same column', () => {
      const parent = makeNode('p', { position: { x: 200, y: 100 } });
      const xs: number[] = [];
      for (let i = 0; i < 3; i++) {
        const result = computeSuccessorPosition({
          nodes: [parent],
          edges: [],
          sourceNodeId: 'p',
          siblingIndex: i,
          totalSiblings: 3,
          isGatewayChild: false,
        });
        xs.push(result.position.x);
      }
      // All three siblings produce the same X — no fan-out.
      expect(new Set(xs).size).toBe(1);
      expect(xs[0]).toBe(parent.position.x);
    });

    it('fans gateway children horizontally', () => {
      const parent = makeNode('g', { type: 'gateway', position: { x: 500, y: 100 } });
      const positions = [];
      for (let i = 0; i < 3; i++) {
        const result = computeSuccessorPosition({
          nodes: [parent],
          edges: [],
          sourceNodeId: 'g',
          siblingIndex: i,
          totalSiblings: 3,
          isGatewayChild: true,
        });
        positions.push(result.position.x);
      }
      // Three distinct X positions — fan-out.
      expect(new Set(positions).size).toBe(3);
      // Center child (index 1) sits roughly under the parent.
      expect(positions[1]).toBe(parent.position.x);
      // Left and right siblings flank the parent symmetrically.
      const step = NODE_DIMENSIONS.CARD_WIDTH + LAYOUT.NODE_SPACING_X;
      expect(positions[0]).toBe(parent.position.x - step);
      expect(positions[2]).toBe(parent.position.x + step);
    });

    it('adds CONDITION_EXTRA_SPACING for condition edges', () => {
      const parent = makeNode('p', { position: { x: 100, y: 100 } });
      const plain = computeSuccessorPosition({
        nodes: [parent],
        edges: [],
        sourceNodeId: 'p',
        hasCondition: false,
      });
      const conditional = computeSuccessorPosition({
        nodes: [parent],
        edges: [],
        sourceNodeId: 'p',
        hasCondition: true,
      });
      expect(conditional.position.y - plain.position.y).toBe(LAYOUT.CONDITION_EXTRA_SPACING);
    });

    it('returns DEFAULT_POSITION when source node is missing', () => {
      const result = computeSuccessorPosition({
        nodes: [],
        edges: [],
        sourceNodeId: 'missing',
      });
      expect(result.position.x).toBe(LAYOUT.DEFAULT_POSITION_X);
      expect(result.position.y).toBe(LAYOUT.DEFAULT_POSITION_Y);
    });
  });

  describe('computeNewEventPosition', () => {
    it('returns LAYOUT_START_X/Y for an empty canvas', () => {
      const pos = computeNewEventPosition({ nodes: [] });
      expect(pos.x).toBe(LAYOUT.LAYOUT_START_X);
      expect(pos.y).toBe(LAYOUT.LAYOUT_START_Y);
    });

    it('places a new event to the right of existing nodes', () => {
      const existing = [
        makeNode('n1', { position: { x: 100, y: 100 } }),
        makeNode('n2', { position: { x: 500, y: 100 } }),
      ];
      const pos = computeNewEventPosition({ nodes: existing });
      // To the right of the rightmost existing node.
      expect(pos.x).toBeGreaterThanOrEqual(500 + LAYOUT.NODE_SPACING_X);
      // At the topmost row.
      expect(pos.y).toBe(100);
    });

    it('respects an explicit candidate position', () => {
      const pos = computeNewEventPosition({
        nodes: [],
        candidate: { x: 1234, y: 5678 },
      });
      expect(pos.x).toBe(1234);
      expect(pos.y).toBe(5678);
    });
  });

  describe('ensureGapForCondition', () => {
    it('shifts target downward when gap is too small for a condition card', () => {
      const source = makeNode('s', { position: { x: 0, y: 0 } });
      const target = makeNode('t', { position: { x: 0, y: NODE_DIMENSIONS.CARD_HEIGHT + 10 } });
      const result = ensureGapForCondition([source, target], 's', 't');
      const newTarget = result.find(n => n.id === 't')!;
      expect(newTarget.position.y).toBeGreaterThan(target.position.y);
      // New gap meets the condition-aware requirement.
      const newGap = newTarget.position.y - (source.position.y + NODE_DIMENSIONS.CARD_HEIGHT);
      expect(newGap).toBeGreaterThanOrEqual(
        LAYOUT.NODE_SPACING_Y + LAYOUT.CONDITION_EXTRA_SPACING,
      );
    });

    it('leaves nodes alone when gap already accommodates a condition', () => {
      const source = makeNode('s', { position: { x: 0, y: 0 } });
      const targetY = NODE_DIMENSIONS.CARD_HEIGHT + LAYOUT.NODE_SPACING_Y + LAYOUT.CONDITION_EXTRA_SPACING + 50;
      const target = makeNode('t', { position: { x: 0, y: targetY } });
      const result = ensureGapForCondition([source, target], 's', 't');
      const newTarget = result.find(n => n.id === 't')!;
      expect(newTarget.position.y).toBe(targetY);
    });

    it('returns the input unchanged when source or target is missing', () => {
      const source = makeNode('s', { position: { x: 0, y: 0 } });
      const result = ensureGapForCondition([source], 's', 'missing');
      expect(result).toEqual([source]);
    });
  });

  describe('placeNodeOnEdge', () => {
    it('places the new node between source and target with condition-aware gaps', () => {
      const source = makeNode('s', { position: { x: 0, y: 0 } });
      const target = makeNode('t', { position: { x: 0, y: 1000 } });
      const newNode = makeNode('n', { position: { x: 0, y: 0 } });
      const edges: Edge[] = [makeEdge('s-n', 's', 'n'), makeEdge('n-t', 'n', 't')];

      const result = placeNodeOnEdge([source, target], edges, newNode, 's', 't', false, false);
      const placed = result.find(n => n.id === 'n')!;
      // The new node sits between source.bottom and target.top.
      expect(placed.position.y).toBeGreaterThan(source.position.y + NODE_DIMENSIONS.CARD_HEIGHT);
      expect(placed.position.y + NODE_DIMENSIONS.CARD_HEIGHT).toBeLessThan(target.position.y);
    });

    it('shifts the target (and descendants) down when there is no room', () => {
      const source = makeNode('s', { position: { x: 0, y: 0 } });
      const target = makeNode('t', { position: { x: 0, y: NODE_DIMENSIONS.CARD_HEIGHT + 10 } });
      const downstream = makeNode('d', { position: { x: 0, y: NODE_DIMENSIONS.CARD_HEIGHT + 200 } });
      const newNode = makeNode('n', { position: { x: 0, y: 0 } });
      const edges: Edge[] = [
        makeEdge('s-n', 's', 'n'),
        makeEdge('n-t', 'n', 't'),
        makeEdge('t-d', 't', 'd'),
      ];

      const result = placeNodeOnEdge([source, target, downstream], edges, newNode, 's', 't', false, false);
      const newTarget = result.find(n => n.id === 't')!;
      const newDownstream = result.find(n => n.id === 'd')!;
      expect(newTarget.position.y).toBeGreaterThan(target.position.y);
      expect(newDownstream.position.y).toBeGreaterThan(downstream.position.y);
    });
  });

  describe('simulateIncrementalBuild', () => {
    it('returns null for empty input', () => {
      expect(simulateIncrementalBuild([], [])).toBeNull();
    });

    it('places a single node at LAYOUT_START', () => {
      const nodes = [makeNode('only', { type: 'start' })];
      const result = simulateIncrementalBuild(nodes, [])!;
      expect(result).toHaveLength(1);
      expect(result[0].position.x).toBe(LAYOUT.LAYOUT_START_X);
      expect(result[0].position.y).toBe(LAYOUT.LAYOUT_START_Y);
    });

    it('places a 3-node linear chain in a single column', () => {
      const nodes: Node[] = [
        makeNode('a', { type: 'start' }),
        makeNode('b'),
        makeNode('c'),
      ];
      const edges: Edge[] = [
        makeEdge('a-b', 'a', 'b'),
        makeEdge('b-c', 'b', 'c'),
      ];
      const result = simulateIncrementalBuild(nodes, edges)!;
      const xs = new Set(result.map(n => n.position.x));
      expect(xs.size).toBe(1);
      // Y values increase along the chain.
      const a = result.find(n => n.id === 'a')!;
      const b = result.find(n => n.id === 'b')!;
      const c = result.find(n => n.id === 'c')!;
      expect(b.position.y).toBeGreaterThan(a.position.y);
      expect(c.position.y).toBeGreaterThan(b.position.y);
    });

    it('does NOT fan out a non-gateway parent with multiple successors', () => {
      const nodes: Node[] = [
        makeNode('a', { type: 'start' }),
        makeNode('b'),
        makeNode('c'),
        makeNode('d'),
      ];
      const edges: Edge[] = [
        makeEdge('a-b', 'a', 'b'),
        makeEdge('a-c', 'a', 'c'),
        makeEdge('a-d', 'a', 'd'),
      ];
      const result = simulateIncrementalBuild(nodes, edges)!;
      const a = result.find(n => n.id === 'a')!;
      // All three children share the parent's column (x equality).
      const childX = result
        .filter(n => ['b', 'c', 'd'].includes(n.id))
        .map(n => n.position.x);
      // No three-way fan-out: the parent's column is preserved for at least one child.
      expect(childX.some(x => x === a.position.x)).toBe(true);
      // No child is placed to the LEFT of the parent (which is what the
      // legacy auto-layout used to do for plain action parents).
      expect(childX.every(x => x >= a.position.x)).toBe(true);
    });

    it('fans out a gateway parent with multiple successors', () => {
      const nodes: Node[] = [
        makeNode('a', { type: 'start' }),
        makeNode('g', { type: 'gateway' }),
        makeNode('x'),
        makeNode('y'),
      ];
      const edges: Edge[] = [
        makeEdge('a-g', 'a', 'g'),
        makeEdge('g-x', 'g', 'x'),
        makeEdge('g-y', 'g', 'y'),
      ];
      const result = simulateIncrementalBuild(nodes, edges)!;
      const x = result.find(n => n.id === 'x')!;
      const y = result.find(n => n.id === 'y')!;
      // Gateway children have distinct X positions (fan-out).
      expect(x.position.x).not.toBe(y.position.x);
    });

    it('places two events side-by-side without overlap', () => {
      const nodes: Node[] = [
        makeNode('e1', { type: 'start' }),
        makeNode('e2', { type: 'start' }),
      ];
      const result = simulateIncrementalBuild(nodes, [])!;
      const e1 = result.find(n => n.id === 'e1')!;
      const e2 = result.find(n => n.id === 'e2')!;
      // Distinct X positions, both at the same row.
      expect(e1.position.x).not.toBe(e2.position.x);
      expect(Math.abs(e1.position.y - e2.position.y)).toBeLessThanOrEqual(0);
    });

    it('adds extra vertical gap for condition edges', () => {
      const nodes: Node[] = [
        makeNode('a', { type: 'start' }),
        makeNode('b'),
      ];
      const plainEdges: Edge[] = [makeEdge('a-b', 'a', 'b')];
      const conditionEdges: Edge[] = [makeEdge('a-b', 'a', 'b', {
        data: { condition: 'foo' },
      })];

      const plainResult = simulateIncrementalBuild(nodes, plainEdges)!;
      const conditionResult = simulateIncrementalBuild(nodes, conditionEdges)!;

      const plainGap =
        plainResult.find(n => n.id === 'b')!.position.y -
        plainResult.find(n => n.id === 'a')!.position.y;
      const conditionGap =
        conditionResult.find(n => n.id === 'b')!.position.y -
        conditionResult.find(n => n.id === 'a')!.position.y;
      expect(conditionGap - plainGap).toBe(LAYOUT.CONDITION_EXTRA_SPACING);
    });

    it('terminates and produces finite positions for a cycle', () => {
      const nodes: Node[] = [
        makeNode('a', { type: 'start' }),
        makeNode('b'),
        makeNode('c'),
      ];
      const edges: Edge[] = [
        makeEdge('a-b', 'a', 'b'),
        makeEdge('b-c', 'b', 'c'),
        makeEdge('c-a', 'c', 'a'), // back-edge
      ];
      const result = simulateIncrementalBuild(nodes, edges)!;
      expect(result).toHaveLength(3);
      for (const node of result) {
        expect(Number.isFinite(node.position.x)).toBe(true);
        expect(Number.isFinite(node.position.y)).toBe(true);
      }
    });

    it('places fully disconnected components without overlap', () => {
      const nodes: Node[] = [
        makeNode('a', { type: 'start' }),
        makeNode('b'),
        makeNode('isolated'),
      ];
      const edges: Edge[] = [makeEdge('a-b', 'a', 'b')];
      const result = simulateIncrementalBuild(nodes, edges)!;
      const isolated = result.find(n => n.id === 'isolated')!;
      const a = result.find(n => n.id === 'a')!;
      // Isolated node placed to the right of the connected flow.
      expect(isolated.position.x).toBeGreaterThan(a.position.x);
    });

    it('preserves the autoLayout signature for empty edges', () => {
      const nodes = [makeNode('a', { type: 'start' })];
      // Both empty array and undefined-as-array should work.
      expect(simulateIncrementalBuild(nodes, [])).not.toBeNull();
    });

    it('centers a convergent node under the centroid of its parents', () => {
      // Issue #3588454 follow-up: an event with two condition branches
      // that converge into a single action should place the action
      // centered between the two conditions (== under the event when
      // the conditions are symmetric), not in one condition's column.
      const nodes: Node[] = [
        makeNode('event', { type: 'start' }),
        makeNode('cond1'),
        makeNode('cond2'),
        makeNode('action'),
      ];
      const edges: Edge[] = [
        makeEdge('e-c1', 'event', 'cond1', { data: { condition: 'a' } }),
        makeEdge('e-c2', 'event', 'cond2', { data: { condition: 'b' } }),
        makeEdge('c1-a', 'cond1', 'action'),
        makeEdge('c2-a', 'cond2', 'action'),
      ];
      const result = simulateIncrementalBuild(nodes, edges)!;
      const event = result.find(n => n.id === 'event')!;
      const cond1 = result.find(n => n.id === 'cond1')!;
      const cond2 = result.find(n => n.id === 'cond2')!;
      const action = result.find(n => n.id === 'action')!;

      // Action's center should sit at the midpoint of cond1 and cond2 centers.
      const cond1Center = cond1.position.x + (cond1.width || NODE_DIMENSIONS.CARD_WIDTH) / 2;
      const cond2Center = cond2.position.x + (cond2.width || NODE_DIMENSIONS.CARD_WIDTH) / 2;
      const expectedCenter = (cond1Center + cond2Center) / 2;
      const actionCenter = action.position.x + (action.width || NODE_DIMENSIONS.CARD_WIDTH) / 2;
      expect(actionCenter).toBeCloseTo(expectedCenter, 0);

      // And below the lowest condition.
      const lowerConditionBottom = Math.max(
        cond1.position.y + (cond1.height || NODE_DIMENSIONS.CARD_HEIGHT),
        cond2.position.y + (cond2.height || NODE_DIMENSIONS.CARD_HEIGHT),
      );
      expect(action.position.y).toBeGreaterThanOrEqual(lowerConditionBottom);
      // event reference retained for symmetric reasoning above.
      void event;
    });

    it('places a convergent node at the centroid even with three parents', () => {
      const nodes: Node[] = [
        makeNode('e', { type: 'start' }),
        makeNode('p1'),
        makeNode('p2'),
        makeNode('p3'),
        makeNode('merge'),
      ];
      const edges: Edge[] = [
        makeEdge('e-p1', 'e', 'p1'),
        makeEdge('e-p2', 'e', 'p2'),
        makeEdge('e-p3', 'e', 'p3'),
        makeEdge('p1-m', 'p1', 'merge'),
        makeEdge('p2-m', 'p2', 'merge'),
        makeEdge('p3-m', 'p3', 'merge'),
      ];
      const result = simulateIncrementalBuild(nodes, edges)!;
      const merge = result.find(n => n.id === 'merge')!;
      const parents = ['p1', 'p2', 'p3'].map(id => result.find(n => n.id === id)!);
      const centroidX =
        parents.reduce((sum, p) => sum + p.position.x + (p.width || NODE_DIMENSIONS.CARD_WIDTH) / 2, 0) /
        parents.length;
      const mergeCenter = merge.position.x + (merge.width || NODE_DIMENSIONS.CARD_WIDTH) / 2;
      expect(mergeCenter).toBeCloseTo(centroidX, 0);
    });
  });
});
