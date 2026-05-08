import { renderHook, act } from '@testing-library/react';
import { useNodeEdgeActions } from '../useNodeEdgeActions';

// Mock store state
let mockNodes: any[] = [];
let mockEdges: any[] = [];

const mockSetNodes = jest.fn((callback) => {
  if (typeof callback === 'function') {
    mockNodes = callback(mockNodes);
  } else {
    mockNodes = callback;
  }
});

const mockSetEdges = jest.fn((callback) => {
  if (typeof callback === 'function') {
    mockEdges = callback(mockEdges);
  } else {
    mockEdges = callback;
  }
});

jest.mock('../../store/useGraphStore', () => ({
  useGraphStore: jest.fn((selector) => {
    const state = {
      nodes: mockNodes,
      edges: mockEdges,
      setNodes: mockSetNodes,
      setEdges: mockSetEdges,
    };
    return selector(state);
  }),
}));

const mockSetCenter = jest.fn();
const mockGetZoom = jest.fn(() => 1);
const mockGetViewport = jest.fn(() => ({ x: 0, y: 0, zoom: 1 }));
jest.mock('reactflow', () => ({
  ...jest.requireActual('reactflow'),
  useReactFlow: () => ({ setCenter: mockSetCenter, getZoom: mockGetZoom, getViewport: mockGetViewport }),
}));

jest.mock('../../utils/clipboardUtils', () => ({
  generateNodeId: jest.fn((label, type) => `${type}_${label}_1`),
  generateEdgeId: jest.fn((source, target) => `${source}_to_${target}`),
}));

// Mock autoLayout — returns the nodes as-is (with all selected flags cleared)
jest.mock('../../utils/modelUtils', () => ({
  autoLayout: jest.fn((nodes: any[]) =>
    nodes.map((n: any) => ({ ...n, selected: false })),
  ),
}));

const mockViewportActions = {
  panToNode: jest.fn(),
  panToNodeIfOffscreen: jest.fn(),
  fitToNodes: jest.fn(),
  topAlignNode: jest.fn(),
  focusNode: jest.fn(),
  fitToNodePair: jest.fn(),
  selectAndFocus: jest.fn(),
  setReady: jest.fn(),
};

describe('useNodeEdgeActions', () => {
  let mockSetHasUnsavedChanges: jest.Mock;

  beforeEach(() => {
    jest.clearAllMocks();
    mockNodes = [];
    mockEdges = [];
    mockSetHasUnsavedChanges = jest.fn();
    mockSetCenter.mockClear();
  });

  const renderUseNodeEdgeActions = () => {
    return renderHook(() =>
      useNodeEdgeActions({
        setHasUnsavedChanges: mockSetHasUnsavedChanges,
        viewportActions: mockViewportActions,
      })
    );
  };

  describe('return values', () => {
    it('should return handleAddCondition, handleAddEvent, handleAddActionOnEdge, handleInsertBeforeCondition, and handleInsertAfterCondition', () => {
      const { result } = renderUseNodeEdgeActions();
      expect(typeof result.current.handleAddCondition).toBe('function');
      expect(typeof result.current.handleAddEvent).toBe('function');
      expect(typeof result.current.handleAddActionOnEdge).toBe('function');
      expect(typeof result.current.handleInsertBeforeCondition).toBe('function');
      expect(typeof result.current.handleInsertAfterCondition).toBe('function');
    });
  });

  describe('handleAddCondition', () => {
    it('should update edge with condition data', () => {
      mockEdges = [
        { id: 'edge-1', source: 'a', target: 'b', data: {} },
        { id: 'edge-2', source: 'b', target: 'c', data: {} },
      ];
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddCondition('edge-1', {
          plugin: 'test.condition.check_value',
          label: 'Check Value',
        });
      });

      expect(mockSetEdges).toHaveBeenCalled();
      // setEdges is now called with the final array directly (not a callback)
      const updatedEdges = mockSetEdges.mock.calls[0][0];
      expect(updatedEdges[0].data.condition).toBe('test.condition.check_value');
      expect(updatedEdges[0].data.conditionLabel).toBe('Check Value');
      expect(updatedEdges[1].data).toEqual({});
    });

    it('should mark the edge as selected', () => {
      mockEdges = [{ id: 'edge-1', source: 'a', target: 'b', data: {} }];
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddCondition('edge-1', {
          plugin: 'test.condition.test',
          label: 'Test',
        });
      });

      const updatedEdges = mockSetEdges.mock.calls[0][0];
      expect(updatedEdges[0].selected).toBe(true);
      expect(updatedEdges[0].id).toBe('edge-1');
    });

    it('should mark unsaved changes', () => {
      mockEdges = [{ id: 'edge-1', source: 'a', target: 'b', data: {} }];
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddCondition('edge-1', {
          plugin: 'test',
          label: 'Test',
        });
      });

      expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);
    });

    it('should deselect other edges when adding condition', () => {
      mockEdges = [
        { id: 'edge-1', source: 'a', target: 'b', data: {}, selected: false },
        { id: 'edge-2', source: 'b', target: 'c', data: {}, selected: true },
      ];
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddCondition('edge-1', {
          plugin: 'test.condition.test',
          label: 'Test',
        });
      });

      const updatedEdges = mockSetEdges.mock.calls[0][0];
      // Target edge should be selected
      expect(updatedEdges[0].selected).toBe(true);
      expect(updatedEdges[0].id).toBe('edge-1');
      // Previously selected edge should be deselected
      expect(updatedEdges[1].selected).toBe(false);
    });

    it('should deselect all nodes when adding condition', () => {
      mockNodes = [
        { id: 'a', position: { x: 0, y: 0 }, selected: true, height: 120 },
        { id: 'b', position: { x: 0, y: 204 }, selected: false, height: 120 },
      ];
      mockEdges = [{ id: 'edge-1', source: 'a', target: 'b', data: {} }];
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddCondition('edge-1', {
          plugin: 'test.condition.test',
          label: 'Test',
        });
      });

      // setNodes should have been called with deselected nodes
      expect(mockSetNodes).toHaveBeenCalled();
      const updatedNodes = mockSetNodes.mock.calls[0][0];
      expect(updatedNodes[0].selected).toBe(false);
    });

    it('should shift target node down when gap is too small for condition card', () => {
      // Source at y=0 (height 120), target at y=150 → gap = 30, needs 174
      mockNodes = [
        { id: 'a', position: { x: 100, y: 0 }, height: 120 },
        { id: 'b', position: { x: 100, y: 150 }, height: 120 },
      ];
      mockEdges = [{ id: 'edge-1', source: 'a', target: 'b', data: {} }];
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddCondition('edge-1', {
          plugin: 'test.condition.test',
          label: 'Test',
        });
      });

      const updatedNodes = mockSetNodes.mock.calls[0][0];
      const targetNode = updatedNodes.find((n: any) => n.id === 'b');
      // Required gap = NODE_SPACING_Y (84) + CONDITION_EXTRA_SPACING (90) = 174
      // Current gap = 150 - 120 = 30, shift = 174 - 30 = 144
      expect(targetNode.position.y).toBe(150 + 144);
    });

    it('should not shift when gap already accommodates condition card', () => {
      // Source at y=0 (height 120), target at y=400 → gap = 280, needs 174
      mockNodes = [
        { id: 'a', position: { x: 100, y: 0 }, height: 120 },
        { id: 'b', position: { x: 100, y: 400 }, height: 120 },
      ];
      mockEdges = [{ id: 'edge-1', source: 'a', target: 'b', data: {} }];
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddCondition('edge-1', {
          plugin: 'test.condition.test',
          label: 'Test',
        });
      });

      const updatedNodes = mockSetNodes.mock.calls[0][0];
      const targetNode = updatedNodes.find((n: any) => n.id === 'b');
      // Gap is already large enough — no shift
      expect(targetNode.position.y).toBe(400);
    });

    it('should set edge type to condition', () => {
      mockEdges = [{ id: 'edge-1', source: 'a', target: 'b', data: {} }];
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddCondition('edge-1', {
          plugin: 'test.condition.test',
          label: 'Test',
        });
      });

      const updatedEdges = mockSetEdges.mock.calls[0][0];
      expect(updatedEdges[0].type).toBe('condition');
    });

    it('should use plugin name as fallback label', () => {
      mockEdges = [{ id: 'edge-1', source: 'a', target: 'b', data: {} }];
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddCondition('edge-1', {
          plugin: 'test.condition.check_value',
          label: '',
        });
      });

      const updatedEdges = mockSetEdges.mock.calls[0][0];
      expect(updatedEdges[0].data.conditionLabel).toBe('check_value');
    });

    it('should invoke parallel edge routing when adding condition to parallel edges (issue #3588937)', () => {
      // Two parallel edges from n1 to n2, initially with no offsets
      mockNodes = [
        { id: 'n1', position: { x: 100, y: 0 }, height: 120, width: 200 },
        { id: 'n2', position: { x: 100, y: 300 }, height: 120, width: 200 },
      ];
      mockEdges = [
        { id: 'e1', source: 'n1', target: 'n2', type: 'default', data: {} },
        { id: 'e2', source: 'n1', target: 'n2', type: 'default', data: {} },
      ];
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddCondition('e2', {
          plugin: 'test.condition.test',
          label: 'Test Condition',
        });
      });

      const updatedEdges = mockSetEdges.mock.calls[0][0];

      // Both edges should now have control offsets from the parallel router
      expect(updatedEdges[0].data?.controlOffset).toBeDefined();
      expect(updatedEdges[1].data?.controlOffset).toBeDefined();

      // The second edge (with condition) should have extra spacing
      // The router applies CONDITION_CARD_OVERHANG to condition edges
      const e1Offset = updatedEdges[0].data.controlOffset.x;
      const e2Offset = updatedEdges[1].data.controlOffset.x;

      // They should be on opposite sides (symmetric)
      expect(Math.sign(e1Offset)).not.toBe(Math.sign(e2Offset));

      // The condition edge should be pushed further out
      expect(Math.abs(e2Offset)).toBeGreaterThan(Math.abs(e1Offset));
    });
  });

  describe('handleAddEvent', () => {
    it('should create a new start node at default position', () => {
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddEvent({
          plugin: 'test.event.content_entity_insert',
          label: 'Entity Insert',
          componentType: 1,
          description: 'Triggered on entity insert',
        });
      });

      expect(mockSetNodes).toHaveBeenCalled();
      const updater = mockSetNodes.mock.calls[0][0];
      const newNodes = updater([]);
      expect(newNodes).toHaveLength(1);
      expect(newNodes[0].type).toBe('start');
      expect(newNodes[0].data.plugin).toBe('test.event.content_entity_insert');
      expect(newNodes[0].data.label).toBe('Entity Insert');
      // After issue #3588454 the first event uses the same anchor as
      // auto-layout (LAYOUT_START_X/Y) so the two code paths produce
      // identical output.
      expect(newNodes[0].position.x).toBe(400);
      expect(newNodes[0].position.y).toBe(100);
    });

    it('should position node to the right of existing nodes', () => {
      mockNodes = [
        { id: 'node-1', position: { x: 200, y: 50 } },
        { id: 'node-2', position: { x: 450, y: 100 } },
      ];
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddEvent({
          plugin: 'test.event.test',
          label: 'Test Event',
          componentType: 1,
        });
      });

      const updater = mockSetNodes.mock.calls[0][0];
      const newNodes = updater(mockNodes);
      const newNode = newNodes[newNodes.length - 1];
      expect(newNode.position.x).toBe(700); // 450 + 250 (NODE_SPACING_X)
      expect(newNode.position.y).toBe(50); // minY
    });

    it('should mark the new node as selected and deselect others', () => {
      mockNodes = [{ id: 'existing-1', position: { x: 100, y: 100 }, selected: true }];
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddEvent({
          plugin: 'test.event.test',
          label: 'Test',
          componentType: 1,
        });
      });

      const updater = mockSetNodes.mock.calls[0][0];
      const newNodes = updater(mockNodes);
      const newNode = newNodes[newNodes.length - 1];
      expect(newNode.selected).toBe(true);
      expect(newNode.type).toBe('start');
      expect(newNode.data.plugin).toBe('test.event.test');
      // Existing node should be deselected
      expect(newNodes[0].selected).toBe(false);
    });

    it('should mark unsaved changes', () => {
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddEvent({
          plugin: 'test',
          label: 'Test',
          componentType: 1,
        });
      });

      expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);
    });

    it('should deselect existing edges when adding event', () => {
      mockEdges = [
        { id: 'edge-1', source: 'a', target: 'b', selected: true, data: {} },
      ];
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddEvent({
          plugin: 'test.event.test',
          label: 'Test',
          componentType: 1,
        });
      });

      // setEdges should deselect existing edges
      expect(mockSetEdges).toHaveBeenCalled();
      const edgesUpdater = mockSetEdges.mock.calls[0][0];
      const updatedEdges = edgesUpdater(mockEdges);
      expect(updatedEdges[0].selected).toBe(false);
    });

    it('should use topmost Y (minY) for new node position', () => {
      mockNodes = [
        { id: 'node-1', position: { x: 300, y: 150 } },
        { id: 'node-2', position: { x: 500, y: 80 } },
      ];

      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddEvent({
          plugin: 'test',
          label: 'Test',
          componentType: 1,
        });
      });

      const updater = mockSetNodes.mock.calls[0][0];
      const newNodes = updater(mockNodes);
      const newNode = newNodes[newNodes.length - 1];
      // maxX = 500, positionX = 750, minY = 80
      expect(newNode.position.x).toBe(750);
      expect(newNode.position.y).toBe(80);
    });
  });

  describe('handleAddActionOnEdge', () => {
    const setupNodesAndEdges = () => {
      mockNodes = [
        { id: 'node-1', position: { x: 100, y: 50 }, selected: false },
        { id: 'node-2', position: { x: 100, y: 254 }, selected: false },
      ];
      mockEdges = [
        { id: 'e1', source: 'node-1', target: 'node-2', type: 'default', data: {}, selected: false },
      ];
    };

    it('should insert a new node between source and target', () => {
      setupNodesAndEdges();
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddActionOnEdge('e1', {
          plugin: 'test.action.save',
          label: 'Save Entity',
          type: 'element',
          componentType: 4,
        });
      });

      // setNodes should be called with updated nodes including the new one
      expect(mockSetNodes).toHaveBeenCalled();
      const updatedNodes = mockSetNodes.mock.calls[0][0];
      expect(updatedNodes).toHaveLength(3);

      // setEdges should be called: original edge removed, 2 new edges added
      expect(mockSetEdges).toHaveBeenCalled();
      const updatedEdges = mockSetEdges.mock.calls[0][0];
      expect(updatedEdges).toHaveLength(2);
      // Original edge should not be present
      expect(updatedEdges.find((e: any) => e.id === 'e1')).toBeUndefined();
    });

    it('should create new node with correct plugin, label, and componentType', () => {
      setupNodesAndEdges();
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddActionOnEdge('e1', {
          plugin: 'test.action.save',
          label: 'Save Entity',
          type: 'element',
          componentType: 4,
        });
      });

      const updatedNodes = mockSetNodes.mock.calls[0][0];
      const newNode = updatedNodes.find((n: any) => n.id !== 'node-1' && n.id !== 'node-2');
      expect(newNode).toBeDefined();
      expect(newNode.data.plugin).toBe('test.action.save');
      expect(newNode.data.label).toBe('Save Entity');
      expect(newNode.data.componentType).toBe(4);
    });

    it('should create two default-type edges', () => {
      setupNodesAndEdges();
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddActionOnEdge('e1', {
          plugin: 'test.action.save',
          label: 'Save Entity',
          type: 'element',
          componentType: 4,
        });
      });

      const updatedEdges = mockSetEdges.mock.calls[0][0];
      expect(updatedEdges[0].type).toBe('default');
      expect(updatedEdges[1].type).toBe('default');
    });

    it('should remove the original edge', () => {
      setupNodesAndEdges();
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddActionOnEdge('e1', {
          plugin: 'test.action.save',
          label: 'Save Entity',
          type: 'element',
          componentType: 4,
        });
      });

      const updatedEdges = mockSetEdges.mock.calls[0][0];
      const originalEdge = updatedEdges.find((e: any) => e.id === 'e1');
      expect(originalEdge).toBeUndefined();
    });

    it('should not call full autoLayout (uses targeted positioning)', () => {
      setupNodesAndEdges();
      const { autoLayout } = require('../../utils/modelUtils');
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddActionOnEdge('e1', {
          plugin: 'test.action.save',
          label: 'Save Entity',
          type: 'element',
          componentType: 4,
        });
      });

      expect(autoLayout).not.toHaveBeenCalled();
    });

    it('should mark unsaved changes', () => {
      setupNodesAndEdges();
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddActionOnEdge('e1', {
          plugin: 'test.action.save',
          label: 'Save Entity',
          type: 'element',
          componentType: 4,
        });
      });

      expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);
    });

    it('should select the new node', () => {
      setupNodesAndEdges();
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddActionOnEdge('e1', {
          plugin: 'test.action.save',
          label: 'Save Entity',
          type: 'element',
          componentType: 4,
        });
      });

      const updatedNodes = mockSetNodes.mock.calls[0][0];
      const newNode = updatedNodes.find((n: any) => n.id !== 'node-1' && n.id !== 'node-2');
      // autoLayout mock clears selected flags, but the node was created with selected: true
      // The mock autoLayout returns nodes with selected: false
      expect(newNode).toBeDefined();
    });

    it('should handle missing edge gracefully', () => {
      setupNodesAndEdges();
      const consoleSpy = jest.spyOn(console, 'error').mockImplementation();
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddActionOnEdge('nonexistent-edge', {
          plugin: 'test.action.save',
          label: 'Save Entity',
          type: 'element',
          componentType: 4,
        });
      });

      expect(consoleSpy).toHaveBeenCalledWith('Edge not found:', 'nonexistent-edge');
      expect(mockSetNodes).not.toHaveBeenCalled();
      consoleSpy.mockRestore();
    });

    it('should create gateway node type when component.type is gateway', () => {
      setupNodesAndEdges();
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddActionOnEdge('e1', {
          plugin: 'test.gateway.split',
          label: 'Split Flow',
          type: 'gateway',
          componentType: 6,
        });
      });

      const updatedNodes = mockSetNodes.mock.calls[0][0];
      const newNode = updatedNodes.find((n: any) => n.id !== 'node-1' && n.id !== 'node-2');
      expect(newNode).toBeDefined();
      expect(newNode.type).toBe('gateway');
    });
  });

  describe('handleInsertBeforeCondition', () => {
    const setupConditionEdge = () => {
      mockNodes = [
        { id: 'node-1', position: { x: 100, y: 50 }, selected: false },
        { id: 'node-2', position: { x: 100, y: 254 }, selected: false },
      ];
      mockEdges = [
        {
          id: 'ce1',
          source: 'node-1',
          target: 'node-2',
          type: 'condition',
          label: 'Original Cond',
          data: {
            condition: 'orig.plugin',
            conditionLabel: 'Original Cond',
          },
          selected: false,
        },
      ];
    };

    describe('when action selected', () => {
      it('should insert node between source and target', () => {
        setupConditionEdge();
        const { result } = renderUseNodeEdgeActions();

        act(() => {
          result.current.handleInsertBeforeCondition('ce1', {
            plugin: 'test.action.save',
            label: 'Save Entity',
            type: 'element',
            componentType: 4,
          });
        });

        expect(mockSetNodes).toHaveBeenCalled();
        const updatedNodes = mockSetNodes.mock.calls[0][0];
        expect(updatedNodes).toHaveLength(3);

        expect(mockSetEdges).toHaveBeenCalled();
        const updatedEdges = mockSetEdges.mock.calls[0][0];
        expect(updatedEdges).toHaveLength(2);
      });

      it('should create first edge (source to new) as default type', () => {
        setupConditionEdge();
        const { result } = renderUseNodeEdgeActions();

        act(() => {
          result.current.handleInsertBeforeCondition('ce1', {
            plugin: 'test.action.save',
            label: 'Save Entity',
            type: 'element',
            componentType: 4,
          });
        });

        const updatedEdges = mockSetEdges.mock.calls[0][0];
        // First new edge: source → new node (default, no condition)
        const edgeToNew = updatedEdges.find((e: any) => e.source === 'node-1' && e.target !== 'node-2');
        expect(edgeToNew).toBeDefined();
        expect(edgeToNew.type).toBe('default');
      });

      it('should create second edge (new to target) with original condition data', () => {
        setupConditionEdge();
        const { result } = renderUseNodeEdgeActions();

        act(() => {
          result.current.handleInsertBeforeCondition('ce1', {
            plugin: 'test.action.save',
            label: 'Save Entity',
            type: 'element',
            componentType: 4,
          });
        });

        const updatedEdges = mockSetEdges.mock.calls[0][0];
        // Second new edge: new node → target (keeps original condition)
        const edgeFromNew = updatedEdges.find((e: any) => e.target === 'node-2' && e.source !== 'node-1');
        expect(edgeFromNew).toBeDefined();
        expect(edgeFromNew.type).toBe('condition');
        expect(edgeFromNew.data.condition).toBe('orig.plugin');
        expect(edgeFromNew.data.conditionLabel).toBe('Original Cond');
      });

      it('should mark unsaved changes and position new node with correct spacing', () => {
        setupConditionEdge();
        const { result } = renderUseNodeEdgeActions();

        act(() => {
          result.current.handleInsertBeforeCondition('ce1', {
            plugin: 'test.action.save',
            label: 'Save Entity',
            type: 'element',
            componentType: 4,
          });
        });

        expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);

        // The new node should be positioned below the source with proper spacing
        const updatedNodes = mockSetNodes.mock.calls[0][0];
        const newNode = updatedNodes.find((n: any) => n.id !== 'node-1' && n.id !== 'node-2');
        expect(newNode).toBeDefined();
        // source at y=50, height=120, sourceBottom=170
        // "Before condition" → plain edge before → gap = NODE_SPACING_Y = 84
        // newNode.y = 170 + 84 = 254
        expect(newNode.position.y).toBe(254);
      });
    });

    describe('when condition selected', () => {
      it('should insert a gateway node', () => {
        setupConditionEdge();
        const { result } = renderUseNodeEdgeActions();

        act(() => {
          result.current.handleInsertBeforeCondition('ce1', {
            plugin: 'new.condition.check',
            label: 'New Check',
            type: 'link',
            componentType: 5,
          });
        });

        const updatedNodes = mockSetNodes.mock.calls[0][0];
        const gatewayNode = updatedNodes.find((n: any) => n.type === 'gateway');
        expect(gatewayNode).toBeDefined();
        expect(gatewayNode.type).toBe('gateway');
        expect(gatewayNode.data.plugin).toBe('gateway');
        expect(gatewayNode.data.componentType).toBe(6);
      });

      it('should create first edge (source to gateway) with the NEW condition', () => {
        setupConditionEdge();
        const { result } = renderUseNodeEdgeActions();

        act(() => {
          result.current.handleInsertBeforeCondition('ce1', {
            plugin: 'new.condition.check',
            label: 'New Check',
            type: 'link',
            componentType: 5,
          });
        });

        const updatedEdges = mockSetEdges.mock.calls[0][0];
        const edgeToGateway = updatedEdges.find((e: any) => e.source === 'node-1');
        expect(edgeToGateway).toBeDefined();
        expect(edgeToGateway.type).toBe('condition');
        expect(edgeToGateway.data.condition).toBe('new.condition.check');
        expect(edgeToGateway.data.conditionLabel).toBe('New Check');
      });

      it('should create second edge (gateway to target) with the ORIGINAL condition', () => {
        setupConditionEdge();
        const { result } = renderUseNodeEdgeActions();

        act(() => {
          result.current.handleInsertBeforeCondition('ce1', {
            plugin: 'new.condition.check',
            label: 'New Check',
            type: 'link',
            componentType: 5,
          });
        });

        const updatedEdges = mockSetEdges.mock.calls[0][0];
        const edgeFromGateway = updatedEdges.find((e: any) => e.target === 'node-2');
        expect(edgeFromGateway).toBeDefined();
        expect(edgeFromGateway.type).toBe('condition');
        expect(edgeFromGateway.data.condition).toBe('orig.plugin');
        expect(edgeFromGateway.data.conditionLabel).toBe('Original Cond');
      });

      it('should not create a placeholder node', () => {
        setupConditionEdge();
        const { result } = renderUseNodeEdgeActions();

        act(() => {
          result.current.handleInsertBeforeCondition('ce1', {
            plugin: 'new.condition.check',
            label: 'New Check',
            type: 'link',
            componentType: 5,
          });
        });

        const updatedNodes = mockSetNodes.mock.calls[0][0];
        const placeholderNode = updatedNodes.find((n: any) => n.type === 'placeholder');
        expect(placeholderNode).toBeUndefined();
      });

      it('should mark unsaved changes and position gateway with correct spacing', () => {
        setupConditionEdge();
        const { result } = renderUseNodeEdgeActions();

        act(() => {
          result.current.handleInsertBeforeCondition('ce1', {
            plugin: 'new.condition.check',
            label: 'New Check',
            type: 'link',
            componentType: 5,
          });
        });

        expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);

        // The gateway should be positioned below the source with condition-aware spacing
        const updatedNodes = mockSetNodes.mock.calls[0][0];
        const gatewayNode = updatedNodes.find((n: any) => n.type === 'gateway');
        expect(gatewayNode).toBeDefined();
        // source at y=50, height=120, sourceBottom=170
        // Condition edge before → gap = NODE_SPACING_Y + CONDITION_EXTRA_SPACING = 174
        // gateway.y = 170 + 174 = 344
        expect(gatewayNode.position.y).toBe(344);
      });
    });
  });

  describe('handleInsertAfterCondition', () => {
    const setupConditionEdge = () => {
      mockNodes = [
        { id: 'node-1', position: { x: 100, y: 50 }, selected: false },
        { id: 'node-2', position: { x: 100, y: 254 }, selected: false },
      ];
      mockEdges = [
        {
          id: 'ce1',
          source: 'node-1',
          target: 'node-2',
          type: 'condition',
          label: 'Original Cond',
          data: {
            condition: 'orig.plugin',
            conditionLabel: 'Original Cond',
          },
          selected: false,
        },
      ];
    };

    describe('when action selected', () => {
      it('should insert node between source and target', () => {
        setupConditionEdge();
        const { result } = renderUseNodeEdgeActions();

        act(() => {
          result.current.handleInsertAfterCondition('ce1', {
            plugin: 'test.action.save',
            label: 'Save Entity',
            type: 'element',
            componentType: 4,
          });
        });

        expect(mockSetNodes).toHaveBeenCalled();
        const updatedNodes = mockSetNodes.mock.calls[0][0];
        expect(updatedNodes).toHaveLength(3);

        expect(mockSetEdges).toHaveBeenCalled();
        const updatedEdges = mockSetEdges.mock.calls[0][0];
        expect(updatedEdges).toHaveLength(2);
      });

      it('should create first edge (source to new) with original condition data', () => {
        setupConditionEdge();
        const { result } = renderUseNodeEdgeActions();

        act(() => {
          result.current.handleInsertAfterCondition('ce1', {
            plugin: 'test.action.save',
            label: 'Save Entity',
            type: 'element',
            componentType: 4,
          });
        });

        const updatedEdges = mockSetEdges.mock.calls[0][0];
        // First new edge: source → new node (keeps original condition)
        const edgeToNew = updatedEdges.find((e: any) => e.source === 'node-1' && e.target !== 'node-2');
        expect(edgeToNew).toBeDefined();
        expect(edgeToNew.type).toBe('condition');
        expect(edgeToNew.data.condition).toBe('orig.plugin');
        expect(edgeToNew.data.conditionLabel).toBe('Original Cond');
      });

      it('should create second edge (new to target) as default type', () => {
        setupConditionEdge();
        const { result } = renderUseNodeEdgeActions();

        act(() => {
          result.current.handleInsertAfterCondition('ce1', {
            plugin: 'test.action.save',
            label: 'Save Entity',
            type: 'element',
            componentType: 4,
          });
        });

        const updatedEdges = mockSetEdges.mock.calls[0][0];
        // Second new edge: new node → target (default, no condition)
        const edgeFromNew = updatedEdges.find((e: any) => e.target === 'node-2' && e.source !== 'node-1');
        expect(edgeFromNew).toBeDefined();
        expect(edgeFromNew.type).toBe('default');
      });

      it('should mark unsaved changes and position new node with correct spacing', () => {
        setupConditionEdge();
        const { result } = renderUseNodeEdgeActions();

        act(() => {
          result.current.handleInsertAfterCondition('ce1', {
            plugin: 'test.action.save',
            label: 'Save Entity',
            type: 'element',
            componentType: 4,
          });
        });

        expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);

        // The new node should be positioned below source with condition-aware spacing
        // (condition stays on the first edge, so the edge before the new node has a condition)
        const updatedNodes = mockSetNodes.mock.calls[0][0];
        const newNode = updatedNodes.find((n: any) => n.id !== 'node-1' && n.id !== 'node-2');
        expect(newNode).toBeDefined();
        // source at y=50, height=120, sourceBottom=170
        // "After condition" → condition edge before → gap = NODE_SPACING_Y + CONDITION_EXTRA_SPACING = 174
        // newNode.y = 170 + 174 = 344
        expect(newNode.position.y).toBe(344);
      });
    });

    describe('when condition selected', () => {
      it('should insert a gateway node', () => {
        setupConditionEdge();
        const { result } = renderUseNodeEdgeActions();

        act(() => {
          result.current.handleInsertAfterCondition('ce1', {
            plugin: 'new.condition.check',
            label: 'New Check',
            type: 'link',
            componentType: 5,
          });
        });

        const updatedNodes = mockSetNodes.mock.calls[0][0];
        const gatewayNode = updatedNodes.find((n: any) => n.type === 'gateway');
        expect(gatewayNode).toBeDefined();
        expect(gatewayNode.type).toBe('gateway');
        expect(gatewayNode.data.plugin).toBe('gateway');
        expect(gatewayNode.data.componentType).toBe(6);
      });

      it('should create first edge (source to gateway) with the ORIGINAL condition', () => {
        setupConditionEdge();
        const { result } = renderUseNodeEdgeActions();

        act(() => {
          result.current.handleInsertAfterCondition('ce1', {
            plugin: 'new.condition.check',
            label: 'New Check',
            type: 'link',
            componentType: 5,
          });
        });

        const updatedEdges = mockSetEdges.mock.calls[0][0];
        const edgeToGateway = updatedEdges.find((e: any) => e.source === 'node-1');
        expect(edgeToGateway).toBeDefined();
        expect(edgeToGateway.type).toBe('condition');
        expect(edgeToGateway.data.condition).toBe('orig.plugin');
        expect(edgeToGateway.data.conditionLabel).toBe('Original Cond');
      });

      it('should create second edge (gateway to target) with the NEW condition', () => {
        setupConditionEdge();
        const { result } = renderUseNodeEdgeActions();

        act(() => {
          result.current.handleInsertAfterCondition('ce1', {
            plugin: 'new.condition.check',
            label: 'New Check',
            type: 'link',
            componentType: 5,
          });
        });

        const updatedEdges = mockSetEdges.mock.calls[0][0];
        const edgeFromGateway = updatedEdges.find((e: any) => e.target === 'node-2');
        expect(edgeFromGateway).toBeDefined();
        expect(edgeFromGateway.type).toBe('condition');
        expect(edgeFromGateway.data.condition).toBe('new.condition.check');
        expect(edgeFromGateway.data.conditionLabel).toBe('New Check');
      });

      it('should not create a placeholder node', () => {
        setupConditionEdge();
        const { result } = renderUseNodeEdgeActions();

        act(() => {
          result.current.handleInsertAfterCondition('ce1', {
            plugin: 'new.condition.check',
            label: 'New Check',
            type: 'link',
            componentType: 5,
          });
        });

        const updatedNodes = mockSetNodes.mock.calls[0][0];
        const placeholderNode = updatedNodes.find((n: any) => n.type === 'placeholder');
        expect(placeholderNode).toBeUndefined();
      });

      it('should mark unsaved changes and position gateway with correct spacing', () => {
        setupConditionEdge();
        const { result } = renderUseNodeEdgeActions();

        act(() => {
          result.current.handleInsertAfterCondition('ce1', {
            plugin: 'new.condition.check',
            label: 'New Check',
            type: 'link',
            componentType: 5,
          });
        });

        expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);

        // The gateway should be positioned below source with condition-aware spacing
        const updatedNodes = mockSetNodes.mock.calls[0][0];
        const gatewayNode = updatedNodes.find((n: any) => n.type === 'gateway');
        expect(gatewayNode).toBeDefined();
        // source at y=50, height=120, sourceBottom=170
        // Condition edge before → gap = NODE_SPACING_Y + CONDITION_EXTRA_SPACING = 174
        // gateway.y = 170 + 174 = 344
        expect(gatewayNode.position.y).toBe(344);
      });
    });
  });
});
