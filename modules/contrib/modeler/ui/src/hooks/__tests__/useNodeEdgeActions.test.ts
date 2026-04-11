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
jest.mock('reactflow', () => ({
  ...jest.requireActual('reactflow'),
  useReactFlow: () => ({ setCenter: mockSetCenter }),
}));

jest.mock('../../utils/clipboardUtils', () => ({
  generateNodeId: jest.fn((label, type) => `${type}_${label}_1`),
}));

// Mock autoLayout — returns the nodes as-is (with all selected flags cleared)
jest.mock('../../utils/modelUtils', () => ({
  autoLayout: jest.fn((nodes: any[]) =>
    nodes.map((n: any) => ({ ...n, selected: false })),
  ),
}));



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
      })
    );
  };

  describe('return values', () => {
    it('should return handleAddCondition and handleAddEvent', () => {
      const { result } = renderUseNodeEdgeActions();
      expect(typeof result.current.handleAddCondition).toBe('function');
      expect(typeof result.current.handleAddEvent).toBe('function');
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

    it('should deselect all nodes when adding condition via autoLayout', () => {
      mockNodes = [{ id: 'node-1', position: { x: 0, y: 0 }, selected: true }];
      mockEdges = [{ id: 'edge-1', source: 'a', target: 'b', data: {} }];
      const { result } = renderUseNodeEdgeActions();

      act(() => {
        result.current.handleAddCondition('edge-1', {
          plugin: 'test.condition.test',
          label: 'Test',
        });
      });

      // setNodes should have been called with autoLayout result (nodes deselected)
      expect(mockSetNodes).toHaveBeenCalled();
      const updatedNodes = mockSetNodes.mock.calls[0][0];
      expect(updatedNodes[0].selected).toBe(false);
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
      expect(newNodes[0].position.x).toBe(100);
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
});
