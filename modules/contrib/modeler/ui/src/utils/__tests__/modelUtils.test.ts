import {
  parseModelData,
  exportModelData,
  autoLayout,
  getFitViewport,
} from '../modelUtils';
import type { StoreNode as Node, StoreEdge as Edge } from '../../types/settings';

describe('modelUtils', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('parseModelData', () => {
    it('should return empty arrays and null for null input', () => {
      const result = parseModelData(null);
      expect(result.nodes).toEqual([]);
      expect(result.edges).toEqual([]);
      expect(result.modelData).toBeNull();
    });

    it('should parse JSON string input', () => {
      const jsonString = JSON.stringify({
        nodes: [{ id: 'node1', type: 'element', position: { x: 100, y: 200 } }],
        edges: [],
      });

      const result = parseModelData(jsonString);
      expect(result.nodes).toHaveLength(1);
      expect(result.nodes[0].id).toBe('node1');
    });

    it('should parse object input', () => {
      const data = {
        nodes: [{ id: 'node1', type: 'start', position: { x: 100, y: 200 } }],
        edges: [],
      } as any;

      const result = parseModelData(data);
      expect(result.nodes).toHaveLength(1);
      expect(result.nodes[0].type).toBe('start');
    });

    it('should ensure node IDs are strings', () => {
      const data = {
        nodes: [{ id: 123, type: 'element', position: { x: 0, y: 0 } }],
        edges: [],
      } as any;

      const result = parseModelData(data);
      expect(typeof result.nodes[0].id).toBe('string');
      expect(result.nodes[0].id).toBe('123');
    });

    it('should set default position if not provided', () => {
      const data = {
        nodes: [{ id: 'node1', type: 'element' }],
        edges: [],
      } as any;

      const result = parseModelData(data);
      expect(result.nodes[0].position).toBeDefined();
      expect(result.nodes[0].position.x).toBeDefined();
      expect(result.nodes[0].position.y).toBeDefined();
    });

    it('should use node ID as label if no label provided', () => {
      const data = {
        nodes: [{ id: 'node1', type: 'element', position: { x: 0, y: 0 } }],
        edges: [],
      } as any;

      const result = parseModelData(data);
      expect(result.nodes[0].data.label).toBe('node1');
    });

    it('should preserve node configuration', () => {
      const config = { key1: 'value1', key2: 'value2' };
      const data = {
        nodes: [{ id: 'node1', type: 'element', position: { x: 0, y: 0 }, configuration: config }],
        edges: [],
      } as any;

      const result = parseModelData(data);
      expect(result.nodes[0].data.configuration).toEqual(config);
    });

    it('should resolve type from componentType when type is missing', () => {
      const data = {
        nodes: [
          { id: 'event1', componentType: 1, position: { x: 0, y: 0 }, plugin: 'content_entity:insert', label: 'Insert' },
          { id: 'action1', componentType: 4, position: { x: 100, y: 0 }, plugin: 'entity:save', label: 'Save' },
          { id: 'cond1', componentType: 5, position: { x: 200, y: 0 }, plugin: 'entity:is_new', label: 'Is New' },
          { id: 'gw1', componentType: 6, position: { x: 300, y: 0 }, plugin: 'gateway', label: 'Gateway' },
        ],
        edges: [],
      } as any;

      const result = parseModelData(data);
      expect(result.nodes[0].type).toBe('start');
      expect(result.nodes[1].type).toBe('element');
      expect(result.nodes[2].type).toBe('link');
      expect(result.nodes[3].type).toBe('gateway');
    });

    it('should prefer explicit type over componentType', () => {
      const data = {
        nodes: [{ id: 'node1', type: 'start', componentType: 4, position: { x: 0, y: 0 } }],
        edges: [],
      } as any;

      const result = parseModelData(data);
      expect(result.nodes[0].type).toBe('start');
    });

    it('should default to element when neither type nor componentType is present', () => {
      const data = {
        nodes: [{ id: 'node1', position: { x: 0, y: 0 } }],
        edges: [],
      } as any;

      const result = parseModelData(data);
      expect(result.nodes[0].type).toBe('element');
    });

    it('should filter out edges with non-existent source nodes', () => {
      const data = {
        nodes: [{ id: 'node1', type: 'element', position: { x: 0, y: 0 } }],
        edges: [{ id: 'edge1', source: 'nonexistent', target: 'node1' }],
      } as any;

      const result = parseModelData(data);
      expect(result.edges).toHaveLength(0);
    });

    it('should filter out edges with non-existent target nodes', () => {
      const data = {
        nodes: [{ id: 'node1', type: 'element', position: { x: 0, y: 0 } }],
        edges: [{ id: 'edge1', source: 'node1', target: 'nonexistent' }],
      } as any;

      const result = parseModelData(data);
      expect(result.edges).toHaveLength(0);
    });

    it('should set correct edge type for condition edges', () => {
      const data = {
        nodes: [
          { id: 'node1', type: 'element', position: { x: 0, y: 0 } },
          { id: 'node2', type: 'element', position: { x: 100, y: 0 } },
        ],
        edges: [{ id: 'edge1', source: 'node1', target: 'node2', condition: 'test_condition' }],
      } as any;

      const result = parseModelData(data);
      expect(result.edges[0].type).toBe('condition');
    });

    it('should set default edge type for edges with annotation but no condition', () => {
      const data = {
        nodes: [
          { id: 'node1', type: 'element', position: { x: 0, y: 0 } },
          { id: 'node2', type: 'element', position: { x: 100, y: 0 } },
        ],
        edges: [{ id: 'edge1', source: 'node1', target: 'node2', annotation: 'Some annotation' }],
      } as any;

      const result = parseModelData(data);
      // Annotations belong to conditions, not edges. An edge without a condition is always 'default'.
      expect(result.edges[0].type).toBe('default');
    });

    it('should set default edge type when backend sends empty condition fields', () => {
      // Regression: backend sends condition: "", conditionLabel: "", conditionConfiguration: {}
      // for edges without conditions. The empty object {} is truthy in JS, so the edge was
      // incorrectly classified as 'condition' type.
      const data = {
        nodes: [
          { id: 'node1', type: 'element', position: { x: 0, y: 0 } },
          { id: 'node2', type: 'element', position: { x: 100, y: 0 } },
        ],
        edges: [{
          id: 'edge1',
          source: 'node1',
          target: 'node2',
          condition: '',
          conditionLabel: '',
          conditionConfiguration: {},
          annotation: '',
        }],
      } as any;

      const result = parseModelData(data);
      expect(result.edges[0].type).toBe('default');
    });

    it('should set condition edge type when conditionConfiguration has actual keys', () => {
      const data = {
        nodes: [
          { id: 'node1', type: 'element', position: { x: 0, y: 0 } },
          { id: 'node2', type: 'element', position: { x: 100, y: 0 } },
        ],
        edges: [{
          id: 'edge1',
          source: 'node1',
          target: 'node2',
          condition: 'some_condition',
          conditionConfiguration: { permission: 'access content' },
        }],
      } as any;

      const result = parseModelData(data);
      expect(result.edges[0].type).toBe('condition');
    });

    it('should set default edge type when only conditionConfiguration is empty object', () => {
      // Edge case: conditionConfiguration: {} alone should not make an edge a condition edge.
      const data = {
        nodes: [
          { id: 'node1', type: 'element', position: { x: 0, y: 0 } },
          { id: 'node2', type: 'element', position: { x: 100, y: 0 } },
        ],
        edges: [{
          id: 'edge1',
          source: 'node1',
          target: 'node2',
          conditionConfiguration: {},
        }],
      } as any;

      const result = parseModelData(data);
      expect(result.edges[0].type).toBe('default');
    });

    it('should assign output handle for gateway nodes (same as other node types)', () => {
      const data = {
        nodes: [
          { id: 'gateway1', type: 'gateway', position: { x: 0, y: 0 } },
          { id: 'node2', type: 'element', position: { x: 100, y: 0 } },
          { id: 'node3', type: 'element', position: { x: 100, y: 100 } },
        ],
        edges: [
          { id: 'edge1', source: 'gateway1', target: 'node2' },
          { id: 'edge2', source: 'gateway1', target: 'node3' },
        ],
      } as any;

      const result = parseModelData(data);
      // All edges use the same 'output' handle regardless of source node type
      expect(result.edges[0].sourceHandle).toBe('output');
      expect(result.edges[1].sourceHandle).toBe('output');
    });

    it('should handle empty nodes and edges arrays', () => {
      const data = { nodes: [], edges: [] };
      const result = parseModelData(data);
      expect(result.nodes).toEqual([]);
      expect(result.edges).toEqual([]);
    });

    it('should handle missing nodes array', () => {
      const data = { edges: [] };
      const result = parseModelData(data as any);
      expect(result.nodes).toEqual([]);
    });

    it('should handle missing edges array', () => {
      const data = { nodes: [] };
      const result = parseModelData(data as any);
      expect(result.edges).toEqual([]);
    });
  });

  describe('exportModelData', () => {
    const mockNodes: Node[] = [
      {
        id: 'node1',
        type: 'element',
        position: { x: 100.5, y: 200.7 },
        data: {
          label: 'Test Node',
          plugin: 'test_plugin',
          configuration: { key: 'value' },
          componentType: 4,
          annotation: 'Test annotation',
        },
      },
    ];

    const mockEdges: Edge[] = [
      {
        id: 'edge1',
        source: 'node1',
        target: 'node2',
        sourceHandle: 'output',
        targetHandle: 'input',
        data: {
          condition: 'test_condition',
          conditionId: 'original_condition_id',
          conditionLabel: 'Test Condition',
          conditionConfiguration: { config: 'value' },
          annotation: 'Edge annotation',
          controlOffset: { x: 10, y: 20 },
        },
      },
    ];

    it('should export basic model structure', () => {
      const result = exportModelData([], []);
      expect(result.id).toBeDefined();
      expect(result.version).toBe('1.0.0');
      expect(result.metadata).toBeDefined();
      expect(result.nodes).toEqual([]);
      expect(result.edges).toEqual([]);
    });

    it('should export with custom metadata', () => {
      const metadata = {
        id: 'custom-id',
        label: 'Custom Model',
        documentation: 'A test model',
        tags: ['test', 'demo'],
      };

      const result = exportModelData([], [], metadata);
      expect(result.id).toBe('custom-id');
      expect(result.metadata.label).toBe('Custom Model');
      expect(result.metadata.documentation).toBe('A test model');
      expect(result.metadata.tags).toEqual(['test', 'demo']);
    });

    it('should round node positions to integers', () => {
      const result = exportModelData(mockNodes, []);
      expect(result.nodes[0].position.x).toBe(101);
      expect(result.nodes[0].position.y).toBe(201);
    });

    it('should include node properties in export', () => {
      const result = exportModelData(mockNodes, []);
      const exportedNode = result.nodes[0];

      expect(exportedNode.id).toBe('node1');
      expect(exportedNode.componentType).toBe(4);
      expect(exportedNode.plugin).toBe('test_plugin');
      expect(exportedNode.label).toBe('Test Node');
      expect(exportedNode.configuration).toEqual({ key: 'value' });
      expect(exportedNode.annotation).toBe('Test annotation');
    });

    it('should include edge properties in export', () => {
      const result = exportModelData([], mockEdges);
      const exportedEdge = result.edges[0];

      expect(exportedEdge.id).toBe('edge1');
      expect(exportedEdge.source).toBe('node1');
      expect(exportedEdge.target).toBe('node2');
      expect(exportedEdge.sourceHandle).toBe('output');
      expect(exportedEdge.targetHandle).toBe('input');
      expect(exportedEdge.condition).toBe('test_condition');
      expect(exportedEdge.conditionId).toBe('original_condition_id');
      expect(exportedEdge.conditionLabel).toBe('Test Condition');
      expect(exportedEdge.conditionConfiguration).toEqual({ config: 'value' });
      expect(exportedEdge.annotation).toBe('Edge annotation');
      expect(exportedEdge.controlOffset).toEqual({ x: 10, y: 20 });
    });

    it('should preserve conditionId through parse-export round-trip', () => {
      // Simulate data coming from the backend with an original condition ID
      const data = {
        nodes: [
          { id: 'event1', componentType: 1, position: { x: 0, y: 0 }, plugin: 'content_entity:insert', label: 'Insert' },
          { id: 'action1', componentType: 4, position: { x: 200, y: 0 }, plugin: 'entity:save', label: 'Save' },
        ],
        edges: [{
          id: 'event1_action1',
          source: 'event1',
          target: 'action1',
          condition: 'eca_entity_is_new',
          conditionId: 'eca_entity_is_new_10j5tps',
          conditionLabel: 'is new?',
          conditionConfiguration: { negate: false },
        }],
      } as any;

      // Parse (load into frontend)
      const parsed = parseModelData(data);
      expect(parsed.edges[0].data?.conditionId).toBe('eca_entity_is_new_10j5tps');

      // Export (save back to backend)
      const exported = exportModelData(parsed.nodes, parsed.edges, { id: 'test' });
      expect(exported.edges[0].conditionId).toBe('eca_entity_is_new_10j5tps');
    });

    it('should export empty conditionId for new edges without original condition ID', () => {
      // When a user creates a new condition in the modeler, there is no
      // original conditionId — the backend generates one on save.
      const edges: Edge[] = [
        {
          id: 'edge_new',
          source: 'node1',
          target: 'node2',
          data: {
            condition: 'entity:is_new',
            conditionLabel: 'Is New',
            conditionConfiguration: { negate: false },
            // No conditionId — newly created in the frontend
          },
        },
      ];

      const result = exportModelData([], edges, { id: 'test' });
      expect(result.edges[0].condition).toBe('entity:is_new');
      expect(result.edges[0].conditionId).toBe('');
    });

    it('should preserve all condition data across a full model round-trip', () => {
      // Comprehensive model with multiple node types, conditions on different
      // edge sources (events, actions, gateways), annotations, and config.
      const fullModel = {
        id: 'round_trip_test',
        version: '1.0.0',
        metadata: {
          label: 'Round-trip Test',
          documentation: 'Tests full data preservation',
        },
        nodes: [
          { id: 'event_abc12345', componentType: 1, position: { x: 50, y: 100 }, plugin: 'content_entity:insert', label: 'Insert', configuration: { type: 'node article' } },
          { id: 'action_def67890', componentType: 4, position: { x: 300, y: 100 }, plugin: 'entity:save', label: 'Save', configuration: {} },
          { id: 'action_ghi11111', componentType: 4, position: { x: 550, y: 100 }, plugin: 'email:send', label: 'Send Email', configuration: { to: 'admin@example.com' } },
          { id: 'Gateway_12iwh1d', componentType: 6, position: { x: 300, y: 300 }, plugin: 'gateway', label: 'Check', configuration: {} },
        ],
        edges: [
          {
            id: 'event_abc12345_action_def67890',
            source: 'event_abc12345',
            target: 'action_def67890',
            condition: 'eca_entity_is_new',
            conditionId: 'eca_entity_is_new_10j5tps',
            conditionLabel: 'is new?',
            conditionConfiguration: { negate: false, entity: '' },
            annotation: 'Only new entities',
          },
          {
            id: 'action_def67890_action_ghi11111',
            source: 'action_def67890',
            target: 'action_ghi11111',
          },
          {
            id: 'Gateway_12iwh1d_action_ghi11111',
            source: 'Gateway_12iwh1d',
            target: 'action_ghi11111',
            condition: 'eca_count',
            conditionId: 'Flow_0h27nee',
            conditionLabel: '>0?',
            conditionConfiguration: { left: 'items', right: '0', operator: 'greaterthan' },
          },
        ],
      } as any;

      // Parse
      const parsed = parseModelData(fullModel);
      expect(parsed.nodes).toHaveLength(4);
      expect(parsed.edges).toHaveLength(3);

      // Verify condition data survived parsing
      const condEdge1 = parsed.edges.find(e => e.id === 'event_abc12345_action_def67890');
      expect(condEdge1?.data?.conditionId).toBe('eca_entity_is_new_10j5tps');
      expect(condEdge1?.data?.condition).toBe('eca_entity_is_new');
      expect(condEdge1?.data?.annotation).toBe('Only new entities');
      expect(condEdge1?.type).toBe('condition');

      const condEdge2 = parsed.edges.find(e => e.id === 'Gateway_12iwh1d_action_ghi11111');
      expect(condEdge2?.data?.conditionId).toBe('Flow_0h27nee');
      expect(condEdge2?.data?.conditionConfiguration).toEqual({ left: 'items', right: '0', operator: 'greaterthan' });

      const plainEdge = parsed.edges.find(e => e.id === 'action_def67890_action_ghi11111');
      expect(plainEdge?.type).toBe('default');

      // Export
      const exported = exportModelData(parsed.nodes, parsed.edges, {
        id: fullModel.id,
        label: fullModel.metadata.label,
      });

      // Verify all condition IDs are preserved
      const expEdge1 = exported.edges.find((e: any) => e.id === 'event_abc12345_action_def67890');
      expect(expEdge1?.conditionId).toBe('eca_entity_is_new_10j5tps');
      expect(expEdge1?.condition).toBe('eca_entity_is_new');
      expect(expEdge1?.conditionLabel).toBe('is new?');
      expect(expEdge1?.conditionConfiguration).toEqual({ negate: false, entity: '' });
      expect(expEdge1?.annotation).toBe('Only new entities');

      const expEdge2 = exported.edges.find((e: any) => e.id === 'Gateway_12iwh1d_action_ghi11111');
      expect(expEdge2?.conditionId).toBe('Flow_0h27nee');
      expect(expEdge2?.condition).toBe('eca_count');
      expect(expEdge2?.conditionLabel).toBe('>0?');

      // Plain edge should have empty conditionId
      const expPlain = exported.edges.find((e: any) => e.id === 'action_def67890_action_ghi11111');
      expect(expPlain?.conditionId).toBe('');
      expect(expPlain?.condition).toBe('');

      // Verify node data preserved
      const expEvent = exported.nodes.find((n: any) => n.id === 'event_abc12345');
      expect(expEvent?.plugin).toBe('content_entity:insert');
      expect(expEvent?.configuration).toEqual({ type: 'node article' });
      expect(expEvent?.position).toEqual({ x: 50, y: 100 });
    });

    it('should handle edges without data', () => {
      const edgesWithoutData: Edge[] = [
        { id: 'edge1', source: 'node1', target: 'node2' },
      ];

      const result = exportModelData([], edgesWithoutData);
      expect(result.edges[0].condition).toBe('');
    });

    it('should set executable to true by default', () => {
      const result = exportModelData([], []);
      expect(result.metadata.executable).toBe(true);
    });

    it('should allow executable to be set to false', () => {
      const result = exportModelData([], [], { executable: false });
      expect(result.metadata.executable).toBe(false);
    });

    it('should filter out internal properties starting with underscore from configuration', () => {
      const nodesWithInternalProps: Node[] = [
        {
          id: 'node1',
          type: 'element',
          position: { x: 100, y: 200 },
          data: {
            label: 'Test Node',
            plugin: 'test_plugin',
            configuration: {
              validKey: 'value',
              anotherKey: 123,
              _componentLabel: 'Internal Label',
              _internalState: 'something',
            },
          },
        },
      ];

      const result = exportModelData(nodesWithInternalProps, []);
      const exportedConfig = result.nodes[0].configuration;

      expect(exportedConfig.validKey).toBe('value');
      expect(exportedConfig.anotherKey).toBe(123);
      expect(exportedConfig._componentLabel).toBeUndefined();
      expect(exportedConfig._internalState).toBeUndefined();
    });

    it('should handle undefined configuration when filtering', () => {
      const nodesWithoutConfig: Node[] = [
        {
          id: 'node1',
          type: 'element',
          position: { x: 100, y: 200 },
          data: {
            label: 'Test Node',
            plugin: 'test_plugin',
          },
        },
      ];

      const result = exportModelData(nodesWithoutConfig, []);
      expect(result.nodes[0].configuration).toEqual({});
    });
  });

  describe('autoLayout', () => {
    it('should return null for null nodes', () => {
      expect(autoLayout(null as any, [])).toBeNull();
    });

    it('should return null for empty nodes array', () => {
      expect(autoLayout([], [])).toBeNull();
    });

    it('should handle undefined edges', () => {
      const nodes: Node[] = [
        { id: 'node1', type: 'element', position: { x: 0, y: 0 }, data: {} },
      ];

      const result = autoLayout(nodes, undefined as any);
      expect(result).toBeDefined();
    });

    it('should layout all nodes', () => {

      const nodes: Node[] = [
        { id: 'node1', type: 'start', position: { x: 0, y: 0 }, data: {} },
        { id: 'node2', type: 'element', position: { x: 0, y: 0 }, data: {} },
      ];

      const edges: Edge[] = [
        { id: 'edge1', source: 'node1', target: 'node2' },
      ];

      const result = autoLayout(nodes, edges);
      expect(result).toBeDefined();
      expect(result?.length).toBe(2);
    });
  });

  describe('getFitViewport', () => {
    it('should return default viewport for empty nodes', () => {
      const result = getFitViewport([], 800, 600);
      expect(result).toEqual({ x: 0, y: 0, zoom: 1 });
    });

    it('should calculate viewport to fit nodes', () => {
      const nodes: Node[] = [
        { id: 'node1', position: { x: 0, y: 0 }, data: {} },
        { id: 'node2', position: { x: 1000, y: 800 }, data: {} },
      ];

      const result = getFitViewport(nodes, 800, 600);
      expect(result.zoom).toBeLessThanOrEqual(4);
      expect(result.zoom).toBeGreaterThan(0);
    });

    it('should respect max zoom limit', () => {
      const nodes: Node[] = [
        { id: 'node1', position: { x: 100, y: 100 }, data: {} },
      ];

      // Small nodes in large viewport should trigger max zoom
      const result = getFitViewport(nodes, 8000, 6000);
      expect(result.zoom).toBeLessThanOrEqual(4);
    });

    it('should apply padding factor', () => {
      const nodes: Node[] = [
        { id: 'node1', position: { x: 0, y: 0 }, data: {} },
        { id: 'node2', position: { x: 400, y: 300 }, data: {} },
      ];

      const withPadding = getFitViewport(nodes, 800, 600, 0.2);
      const withoutPadding = getFitViewport(nodes, 800, 600, 0);

      // With padding should have lower zoom to fit more
      expect(withPadding.zoom).toBeLessThanOrEqual(withoutPadding.zoom);
    });

    it('should internally compute node bounds for viewport calculation', () => {
      // getNodesBounds is now private, but we can verify its behavior
      // through getFitViewport by checking that spread-out nodes produce
      // a lower zoom than tightly grouped nodes
      const spreadNodes: Node[] = [
        { id: 'node1', position: { x: 0, y: 0 }, data: {} },
        { id: 'node2', position: { x: 2000, y: 1500 }, data: {} },
      ];

      const tightNodes: Node[] = [
        { id: 'node1', position: { x: 100, y: 100 }, data: {} },
        { id: 'node2', position: { x: 150, y: 150 }, data: {} },
      ];

      const spreadResult = getFitViewport(spreadNodes, 800, 600);
      const tightResult = getFitViewport(tightNodes, 800, 600);

      expect(spreadResult.zoom).toBeLessThan(tightResult.zoom);
    });
  });
});
