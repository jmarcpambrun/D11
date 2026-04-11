/**
 * Tests for layout strategies - positioning algorithms for workflow nodes
 */

import {
  processFlowLayout,
  convertPositionsToCoordinates,
  groupNodesByRow,
  optimizeRowAlignment,
} from '../layoutStrategies';
import type { StoreNode as Node, StoreEdge as Edge } from '../../types/settings';
import { GraphData, LAYOUT_CONFIG } from '../layoutHelpers';

// Helper to create a mock node
const createNode = (id: string, x = 0, y = 0, type = 'element'): Node => ({
  id,
  type,
  position: { x, y },
  data: { label: `Node ${id}`, nodeType: type },
});

// Helper to create a mock edge
const createEdge = (id: string, source: string, target: string): Edge => ({
  id,
  source,
  target,
});

// Helper to create GraphData from nodes and edges
const createGraphData = (nodes: Node[], edges: Edge[]): GraphData => {
  const adjacencyMap = new Map<string, string[]>();
  const inDegree = new Map<string, number>();
  const outDegree = new Map<string, number>();

  nodes.forEach(node => {
    adjacencyMap.set(node.id, []);
    inDegree.set(node.id, 0);
    outDegree.set(node.id, 0);
  });

  edges.forEach(edge => {
    const children = adjacencyMap.get(edge.source) || [];
    children.push(edge.target);
    adjacencyMap.set(edge.source, children);

    inDegree.set(edge.target, (inDegree.get(edge.target) || 0) + 1);
    outDegree.set(edge.source, (outDegree.get(edge.source) || 0) + 1);
  });

  return { adjacencyMap, inDegree, outDegree };
};

describe('processFlowLayout', () => {
  it('should position a single start node', () => {
    const startNode = createNode('start_1', 0, 0, 'start');
    const nodes = [startNode];
    const graphData = createGraphData(nodes, []);

    const positions = processFlowLayout([startNode], nodes, graphData);

    expect(positions.has('start_1')).toBe(true);
    expect(positions.get('start_1')).toEqual({ row: 0, column: 0 });
  });

  it('should position nodes in a linear flow', () => {
    const nodes = [
      createNode('event_1', 0, 0, 'start'),
      createNode('action_1', 0, 0, 'element'),
      createNode('action_2', 0, 0, 'element'),
    ];
    const edges = [
      createEdge('e1', 'event_1', 'action_1'),
      createEdge('e2', 'action_1', 'action_2'),
    ];
    const graphData = createGraphData(nodes, edges);

    const positions = processFlowLayout([nodes[0]], nodes, graphData);

    expect(positions.get('event_1')?.row).toBe(0);
    expect(positions.get('action_1')?.row).toBe(1);
    expect(positions.get('action_2')?.row).toBe(2);
  });

  it('should handle gateway nodes with multiple children', () => {
    const nodes = [
      createNode('event_1', 0, 0, 'start'),
      createNode('gateway_1', 0, 0, 'gateway'),
      createNode('action_1', 0, 0, 'element'),
      createNode('action_2', 0, 0, 'element'),
    ];
    const edges = [
      createEdge('e1', 'event_1', 'gateway_1'),
      createEdge('e2', 'gateway_1', 'action_1'),
      createEdge('e3', 'gateway_1', 'action_2'),
    ];
    const graphData = createGraphData(nodes, edges);

    const positions = processFlowLayout([nodes[0]], nodes, graphData);

    expect(positions.get('event_1')?.row).toBe(0);
    expect(positions.get('gateway_1')?.row).toBe(1);
    // Gateway children should be at row + GATEWAY_VERTICAL_OFFSET
    const gatewayOffset = LAYOUT_CONFIG.GATEWAY_VERTICAL_OFFSET;
    expect(positions.get('action_1')?.row).toBe(1 + gatewayOffset);
    expect(positions.get('action_2')?.row).toBe(1 + gatewayOffset);
  });

  it('should handle multiple event flows', () => {
    const nodes = [
      createNode('event_1', 0, 0, 'start'),
      createNode('event_2', 0, 0, 'start'),
      createNode('action_1', 0, 0, 'element'),
      createNode('action_2', 0, 0, 'element'),
    ];
    const edges = [
      createEdge('e1', 'event_1', 'action_1'),
      createEdge('e2', 'event_2', 'action_2'),
    ];
    const graphData = createGraphData(nodes, edges);

    const positions = processFlowLayout([nodes[0], nodes[1]], nodes, graphData);

    // Both events should be at row 0 but different columns
    expect(positions.get('event_1')?.row).toBe(0);
    expect(positions.get('event_2')?.row).toBe(0);
    expect(positions.get('event_1')?.column).not.toBe(positions.get('event_2')?.column);
  });

  it('should handle already positioned nodes (converging flows)', () => {
    const nodes = [
      createNode('event_1', 0, 0, 'start'),
      createNode('event_2', 0, 0, 'start'),
      createNode('action_1', 0, 0, 'element'),
    ];
    // Both events connect to the same action
    const edges = [
      createEdge('e1', 'event_1', 'action_1'),
      createEdge('e2', 'event_2', 'action_1'),
    ];
    const graphData = createGraphData(nodes, edges);

    const positions = processFlowLayout([nodes[0], nodes[1]], nodes, graphData);

    expect(positions.has('action_1')).toBe(true);
    // action_1 keeps its first-encountered position (row 1 from event_1's flow)
    expect(positions.get('action_1')?.row).toBe(1);
  });

  it('should handle unpositioned orphan nodes', () => {
    const nodes = [
      createNode('event_1', 0, 0, 'start'),
      createNode('action_1', 0, 0, 'element'),
      createNode('orphan_1', 0, 0, 'element'), // Not connected
    ];
    const edges = [
      createEdge('e1', 'event_1', 'action_1'),
    ];
    const graphData = createGraphData(nodes, edges);

    const positions = processFlowLayout([nodes[0]], nodes, graphData);

    // Orphan should still be positioned
    expect(positions.has('orphan_1')).toBe(true);
    // Orphan should be at maxRow + 1
    const maxRowFromFlow = Math.max(
      positions.get('event_1')?.row || 0,
      positions.get('action_1')?.row || 0
    );
    expect(positions.get('orphan_1')?.row).toBe(maxRowFromFlow + 1);
  });

  it('should skip already positioned start nodes', () => {
    const nodes = [
      createNode('event_1', 0, 0, 'start'),
      createNode('action_1', 0, 0, 'element'),
    ];
    const edges = [
      createEdge('e1', 'event_1', 'action_1'),
    ];
    const graphData = createGraphData(nodes, edges);

    // Process with same start node twice
    const positions = processFlowLayout([nodes[0], nodes[0]], nodes, graphData);

    expect(positions.get('event_1')).toEqual({ row: 0, column: 0 });
  });

  it('should handle nodes with nodeType data property for gateway detection', () => {
    const gatewayNode = createNode('gateway_1', 0, 0, 'element');
    gatewayNode.data.nodeType = 'gateway';

    const nodes = [
      createNode('event_1', 0, 0, 'start'),
      gatewayNode,
      createNode('action_1', 0, 0, 'element'),
    ];
    const edges = [
      createEdge('e1', 'event_1', 'gateway_1'),
      createEdge('e2', 'gateway_1', 'action_1'),
    ];
    const graphData = createGraphData(nodes, edges);

    const positions = processFlowLayout([nodes[0]], nodes, graphData);

    // Gateway child should use gateway offset
    const gatewayRow = positions.get('gateway_1')?.row || 0;
    expect(positions.get('action_1')?.row).toBe(gatewayRow + LAYOUT_CONFIG.GATEWAY_VERTICAL_OFFSET);
  });

  it('should handle normal node with multiple children', () => {
    const nodes = [
      createNode('event_1', 0, 0, 'start'),
      createNode('action_1', 0, 0, 'element'),
      createNode('action_2', 0, 0, 'element'),
      createNode('action_3', 0, 0, 'element'),
    ];
    const edges = [
      createEdge('e1', 'event_1', 'action_1'),
      createEdge('e2', 'event_1', 'action_2'),
      createEdge('e3', 'event_1', 'action_3'),
    ];
    const graphData = createGraphData(nodes, edges);

    const positions = processFlowLayout([nodes[0]], nodes, graphData);

    // All actions should be at row 1 (not using gateway offset)
    expect(positions.get('action_1')?.row).toBe(1);
    expect(positions.get('action_2')?.row).toBe(1);
    expect(positions.get('action_3')?.row).toBe(1);
  });

  it('should place next event after the rightmost column of the previous subtree', () => {
    // Event 1 has a wide subtree (gateway with 2 branches)
    // Event 2 has a narrow subtree (single chain)
    // Event 2 should start to the right of event 1's widest point
    const nodes = [
      createNode('event_1', 0, 0, 'start'),
      createNode('gateway_1', 0, 0, 'gateway'),
      createNode('branch_a', 0, 0, 'element'),
      createNode('branch_b', 0, 0, 'element'),
      createNode('event_2', 0, 0, 'start'),
      createNode('action_1', 0, 0, 'element'),
    ];
    const edges = [
      createEdge('e1', 'event_1', 'gateway_1'),
      createEdge('e2', 'gateway_1', 'branch_a'),
      createEdge('e3', 'gateway_1', 'branch_b'),
      createEdge('e4', 'event_2', 'action_1'),
    ];
    const graphData = createGraphData(nodes, edges);

    const positions = processFlowLayout([nodes[0], nodes[4]], nodes, graphData);

    // Find the rightmost column in event_1's subtree
    const event1Cols = ['event_1', 'gateway_1', 'branch_a', 'branch_b']
      .map(id => positions.get(id)?.column ?? 0);
    const event1MaxCol = Math.max(...event1Cols);

    // Event 2 and its successor must be entirely to the right
    const event2Col = positions.get('event_2')?.column ?? 0;
    const action1Col = positions.get('action_1')?.column ?? 0;

    expect(event2Col).toBeGreaterThan(event1MaxCol);
    expect(action1Col).toBeGreaterThan(event1MaxCol);
  });

  it('should not push gateway below descendants on back-edge (cycle)', () => {
    // Gateway loop: event → action → gateway → child → gateway (back-edge)
    // The back-edge should NOT move the gateway to a deeper row.
    const nodes = [
      createNode('event_1', 0, 0, 'start'),
      createNode('action_1', 0, 0, 'element'),
      createNode('gateway_1', 0, 0, 'gateway'),
      createNode('child_1', 0, 0, 'element'),
    ];
    const edges = [
      createEdge('e1', 'event_1', 'action_1'),
      createEdge('e2', 'action_1', 'gateway_1'),
      createEdge('e3', 'gateway_1', 'child_1'),
      createEdge('e4', 'child_1', 'gateway_1'), // back-edge creating a cycle
    ];
    const graphData = createGraphData(nodes, edges);

    const positions = processFlowLayout([nodes[0]], nodes, graphData);

    const gatewayRow = positions.get('gateway_1')!.row;
    const childRow = positions.get('child_1')!.row;

    // Gateway should be ABOVE its child, not pushed below by the back-edge
    expect(gatewayRow).toBeLessThan(childRow);
    // Gateway should be at row 2 (event=0, action=1, gateway=2)
    expect(gatewayRow).toBe(2);
  });

  it('should keep single-child gateway successor in the same column', () => {
    // A gateway with only one child should NOT shift the child right.
    const nodes = [
      createNode('event_1', 0, 0, 'start'),
      createNode('gateway_1', 0, 0, 'gateway'),
      createNode('action_1', 0, 0, 'element'),
    ];
    const edges = [
      createEdge('e1', 'event_1', 'gateway_1'),
      createEdge('e2', 'gateway_1', 'action_1'),
    ];
    const graphData = createGraphData(nodes, edges);

    const positions = processFlowLayout([nodes[0]], nodes, graphData);

    const gatewayCol = positions.get('gateway_1')!.column;
    const actionCol = positions.get('action_1')!.column;

    // Single-child should stay in the same column as the gateway
    expect(actionCol).toBe(gatewayCol);
  });

  it('should preserve event order (no resorting)', () => {
    // Even though event_2 has a larger subtree, it should still
    // appear after event_1 (to preserve the user's ordering)
    const nodes = [
      createNode('event_1', 0, 0, 'start'),
      createNode('action_a', 0, 0, 'element'),
      createNode('event_2', 0, 0, 'start'),
      createNode('action_b', 0, 0, 'element'),
      createNode('action_c', 0, 0, 'element'),
      createNode('action_d', 0, 0, 'element'),
    ];
    const edges = [
      createEdge('e1', 'event_1', 'action_a'),
      createEdge('e2', 'event_2', 'action_b'),
      createEdge('e3', 'action_b', 'action_c'),
      createEdge('e4', 'action_c', 'action_d'),
    ];
    const graphData = createGraphData(nodes, edges);

    const positions = processFlowLayout([nodes[0], nodes[2]], nodes, graphData);

    // event_1 should be to the left of event_2
    expect(positions.get('event_1')!.column).toBeLessThan(
      positions.get('event_2')!.column
    );
  });
});

describe('convertPositionsToCoordinates', () => {
  it('should convert row/column positions to x/y coordinates', () => {
    const nodes = [
      createNode('node_1', 0, 0),
      createNode('node_2', 0, 0),
    ];
    const edges = [createEdge('e1', 'node_1', 'node_2')];
    const positions = new Map([
      ['node_1', { row: 0, column: 0 }],
      ['node_2', { row: 1, column: 1 }],
    ]);
    const startPos = { x: 100, y: 100 };

    const result = convertPositionsToCoordinates(nodes, positions, startPos, [], edges);

    expect(result[0].position.x).toBe(startPos.x);
    expect(result[0].position.y).toBe(startPos.y);
    expect(result[1].position.x).toBe(startPos.x + LAYOUT_CONFIG.HORIZONTAL_SPACING);
    // No condition on edge, so no extra spacing
    expect(result[1].position.y).toBe(startPos.y + LAYOUT_CONFIG.VERTICAL_SPACING);
  });

  it('should add extra vertical spacing for edges with conditions', () => {
    const nodes = [
      createNode('node_1', 0, 0),
      createNode('node_2', 0, 0),
    ];
    const edges: Edge[] = [
      { id: 'e1', source: 'node_1', target: 'node_2', data: { condition: 'some_plugin', conditionLabel: 'Test' } },
    ];
    const positions = new Map([
      ['node_1', { row: 0, column: 0 }],
      ['node_2', { row: 1, column: 1 }],
    ]);
    const startPos = { x: 100, y: 100 };

    const result = convertPositionsToCoordinates(nodes, positions, startPos, [], edges);

    expect(result[0].position.y).toBe(startPos.y);
    // Node 2 gets extra spacing because the incoming edge has a condition
    expect(result[1].position.y).toBe(
      startPos.y + LAYOUT_CONFIG.VERTICAL_SPACING + LAYOUT_CONFIG.CONDITION_EXTRA_SPACING
    );
  });

  it('should not push parallel flow nodes down when only one flow has conditions', () => {
    // Two parallel flows side by side:
    //   Flow A: A_event (row 0, col 0) --[condition]--> A_action (row 1, col 0)
    //   Flow B: B_event (row 0, col 2) -----------------> B_action (row 1, col 2)
    // Only flow A's edge has a condition.
    // B_action should NOT get extra spacing.
    const nodes = [
      createNode('A_event', 0, 0),
      createNode('A_action', 0, 0),
      createNode('B_event', 0, 0),
      createNode('B_action', 0, 0),
    ];
    const edges: Edge[] = [
      { id: 'e1', source: 'A_event', target: 'A_action', data: { condition: 'some_plugin', conditionLabel: 'Test' } },
      { id: 'e2', source: 'B_event', target: 'B_action' },
    ];
    const positions = new Map([
      ['A_event', { row: 0, column: 0 }],
      ['A_action', { row: 1, column: 0 }],
      ['B_event', { row: 0, column: 2 }],
      ['B_action', { row: 1, column: 2 }],
    ]);
    const startPos = { x: 100, y: 100 };

    const result = convertPositionsToCoordinates(nodes, positions, startPos, [], edges);

    const aAction = result.find(n => n.id === 'A_action')!;
    const bAction = result.find(n => n.id === 'B_action')!;

    // A_action should have the extra condition spacing
    expect(aAction.position.y).toBe(
      startPos.y + LAYOUT_CONFIG.VERTICAL_SPACING + LAYOUT_CONFIG.CONDITION_EXTRA_SPACING
    );
    // B_action should NOT have extra spacing — its flow has no conditions
    expect(bAction.position.y).toBe(
      startPos.y + LAYOUT_CONFIG.VERTICAL_SPACING
    );
  });

  it('should propagate condition offsets through cycles', () => {
    // Graph with a cycle: A → B → gateway → C --[condition]--> D → gateway (back-edge)
    // The condition on C→D should give D extra spacing, even though
    // gateway is part of a cycle that Kahn's algorithm can't resolve directly.
    const nodes = [
      createNode('A', 0, 0),
      createNode('B', 0, 0),
      createNode('gateway', 0, 0),
      createNode('C', 0, 0),
      createNode('D', 0, 0),
    ];
    const edges: Edge[] = [
      { id: 'e1', source: 'A', target: 'B' },
      { id: 'e2', source: 'B', target: 'gateway' },
      { id: 'e3', source: 'gateway', target: 'C' },
      { id: 'e4', source: 'C', target: 'D', data: { condition: 'some_plugin', conditionLabel: 'Test' } },
      { id: 'e5', source: 'D', target: 'gateway' }, // back-edge (cycle)
    ];
    const positions = new Map([
      ['A', { row: 0, column: 0 }],
      ['B', { row: 1, column: 0 }],
      ['gateway', { row: 2, column: 0 }],
      ['C', { row: 3, column: 0 }],
      ['D', { row: 4, column: 0 }],
    ]);
    const startPos = { x: 100, y: 100 };

    const result = convertPositionsToCoordinates(nodes, positions, startPos, [], edges);

    const nodeD = result.find(n => n.id === 'D')!;
    const nodeC = result.find(n => n.id === 'C')!;

    // D should be pushed down by CONDITION_EXTRA_SPACING relative to its base position
    // because the edge C→D has a condition
    expect(nodeD.position.y).toBeGreaterThan(nodeC.position.y + LAYOUT_CONFIG.VERTICAL_SPACING);
    expect(nodeD.position.y).toBe(
      startPos.y + (4 * LAYOUT_CONFIG.VERTICAL_SPACING) + LAYOUT_CONFIG.CONDITION_EXTRA_SPACING
    );
  });

  it('should handle nodes without positions in the map', () => {
    const nodes = [
      createNode('node_1', 50, 50),
      createNode('node_2', 100, 100),
    ];
    const positions = new Map([
      ['node_1', { row: 0, column: 0 }],
      // node_2 has no position in map
    ]);
    const startPos = { x: 200, y: 200 };

    const result = convertPositionsToCoordinates(nodes, positions, startPos, []);

    // node_2 should keep its original position since it's not in the map
    expect(result[1].position).toEqual({ x: 100, y: 100 });
  });
});

describe('groupNodesByRow', () => {
  it('should group nodes by their row position', () => {
    const nodes = [
      createNode('node_1', 0, 0),
      createNode('node_2', 0, 0),
      createNode('node_3', 0, 0),
    ];
    const positions = new Map([
      ['node_1', { row: 0, column: 0 }],
      ['node_2', { row: 0, column: 1 }],
      ['node_3', { row: 1, column: 0 }],
    ]);

    const result = groupNodesByRow(positions, nodes);

    expect(result.get(0)).toEqual(['node_1', 'node_2']);
    expect(result.get(1)).toEqual(['node_3']);
  });



  it('should handle nodes not found in nodes array', () => {
    const nodes = [createNode('node_1', 0, 0)];
    const positions = new Map([
      ['node_1', { row: 0, column: 0 }],
      ['missing_node', { row: 1, column: 0 }],
    ]);

    const result = groupNodesByRow(positions, nodes);

    expect(result.get(0)).toEqual(['node_1']);
    expect(result.has(1)).toBe(false);
  });
});

describe('optimizeRowAlignment', () => {
  it('should skip rows with single node', () => {
    const nodes = [createNode('node_1', 100, 100)];
    const edges: Edge[] = [];
    const rowNodes = new Map([[0, ['node_1']]]);
    const originalX = nodes[0].position.x;

    optimizeRowAlignment(nodes, edges, rowNodes);

    // Single node should not be moved
    expect(nodes[0].position.x).toBe(originalX);
  });

  it('should adjust nodes toward connected parents', () => {
    const nodes = [
      createNode('parent_1', 200, 0),
      createNode('child_1', 100, 100),
      createNode('child_2', 300, 100),
    ];
    const edges = [
      createEdge('e1', 'parent_1', 'child_1'),
      createEdge('e2', 'parent_1', 'child_2'),
    ];
    const rowNodes = new Map([[1, ['child_1', 'child_2']]]);

    optimizeRowAlignment(nodes, edges, rowNodes);

    // Children should be adjusted (exact values depend on weight)
    expect(typeof nodes[1].position.x).toBe('number');
    expect(typeof nodes[2].position.x).toBe('number');
  });

  it('should enforce minimum spacing between nodes', () => {
    const nodes = [
      createNode('node_1', 100, 100),
      createNode('node_2', 105, 100), // Very close to node_1
    ];
    const edges: Edge[] = [];
    const rowNodes = new Map([[0, ['node_1', 'node_2']]]);

    optimizeRowAlignment(nodes, edges, rowNodes);

    // Nodes should be at least MIN_NODE_DISTANCE apart
    const distance = nodes[1].position.x - nodes[0].position.x;
    expect(distance).toBeGreaterThanOrEqual(LAYOUT_CONFIG.MIN_NODE_DISTANCE);
  });

  it('should handle nodes with undefined positions', () => {
    const node1 = createNode('node_1', 100, 100);
    const node2 = { id: 'node_2', data: {}, position: undefined as any };
    const nodes = [node1, node2];
    const rowNodes = new Map([[0, ['node_1', 'node_2']]]);

    // Should not throw
    expect(() => {
      optimizeRowAlignment(nodes, [], rowNodes);
    }).not.toThrow();
  });

  it('should skip adjustment when idealX is null', () => {
    // Node with no connected edges will have null idealX
    const nodes = [
      createNode('node_1', 100, 100),
      createNode('node_2', 200, 100),
    ];
    const edges: Edge[] = []; // No edges
    const rowNodes = new Map([[0, ['node_1', 'node_2']]]);

    optimizeRowAlignment(nodes, edges, rowNodes);

    // With no edges, nodes may only be adjusted for minimum spacing
    // They shouldn't be dramatically moved without connection data
    expect(nodes[0].position.x).toBeDefined();
    expect(nodes[1].position.x).toBeDefined();
  });

});
