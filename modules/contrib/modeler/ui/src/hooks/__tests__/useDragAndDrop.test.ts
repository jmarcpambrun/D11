import { renderHook, act } from '@testing-library/react';
import { useDragAndDrop } from '../useDragAndDrop';

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

// Mock ReactFlow
const mockScreenToFlowPosition = jest.fn((pos) => pos);

jest.mock('reactflow', () => ({
  useReactFlow: () => ({
    screenToFlowPosition: mockScreenToFlowPosition,
  }),
}));

// Mock utility functions
jest.mock('../../utils/clipboardUtils', () => ({
  generateNodeId: jest.fn((label, type) => `${type}_${label.toLowerCase().replace(/\s+/g, '_')}_1`),
}));



describe('useDragAndDrop', () => {
  let mockSetHasUnsavedChanges: jest.Mock;

  beforeEach(() => {
    jest.clearAllMocks();
    mockSetHasUnsavedChanges = jest.fn();
    mockNodes = [
      {
        id: 'node-1',
        position: { x: 100, y: 100 },
        width: 200,
        height: 100,
        data: { label: 'Node 1' },
      },
      {
        id: 'node-2',
        position: { x: 400, y: 100 },
        width: 200,
        height: 100,
        data: { label: 'Node 2' },
      },
    ];
    mockEdges = [
      {
        id: 'edge-1',
        source: 'node-1',
        target: 'node-2',
        data: {},
      },
    ];
  });

  const renderUseDragAndDrop = (isLocked = false) => {
    return renderHook(() =>
      useDragAndDrop({
        isLocked,
        setHasUnsavedChanges: mockSetHasUnsavedChanges,
      })
    );
  };

  describe('initial state', () => {
    it('should return initial state values', () => {
      const { result } = renderUseDragAndDrop();

      expect(result.current.isDraggingCondition).toBe(false);
      expect(result.current.hoveredDropEdge).toBeNull();
    });

    it('should return all required handlers', () => {
      const { result } = renderUseDragAndDrop();

      expect(typeof result.current.onDrop).toBe('function');
      expect(typeof result.current.onDragOver).toBe('function');
      expect(typeof result.current.findNearestEdge).toBe('function');
    });
  });

  describe('findNearestEdge', () => {
    it('should find nearest edge to position', () => {
      const { result } = renderUseDragAndDrop();

      // Position near the midpoint of edge-1 (between node-1 at 200,150 center and node-2 at 500,150 center)
      // Midpoint is approximately (350, 150)
      const nearestEdge = result.current.findNearestEdge({ x: 350, y: 150 });

      expect(nearestEdge).toBeDefined();
      expect(nearestEdge?.id).toBe('edge-1');
    });

    it('should return null when no edge within max distance', () => {
      const { result } = renderUseDragAndDrop();

      // Position far from any edge
      const nearestEdge = result.current.findNearestEdge({ x: 1000, y: 1000 });

      expect(nearestEdge).toBeNull();
    });

    it('should return null when edges array is empty', () => {
      mockEdges = [];
      const { result } = renderUseDragAndDrop();

      const nearestEdge = result.current.findNearestEdge({ x: 350, y: 150 });

      expect(nearestEdge).toBeNull();
    });

    it('should respect maxDistance parameter', () => {
      const { result } = renderUseDragAndDrop();

      // Position that's 100px away from edge midpoint
      const nearestEdgeSmallRadius = result.current.findNearestEdge({ x: 450, y: 150 }, 50);
      expect(nearestEdgeSmallRadius).toBeNull();

      const nearestEdgeLargeRadius = result.current.findNearestEdge({ x: 450, y: 150 }, 200);
      expect(nearestEdgeLargeRadius?.id).toBe('edge-1');
    });

    it('should use provided edges and nodes arrays', () => {
      const { result } = renderUseDragAndDrop();

      const customNodes = [
        { id: 'custom-1', position: { x: 0, y: 0 }, width: 100, height: 50, data: {} },
        { id: 'custom-2', position: { x: 200, y: 0 }, width: 100, height: 50, data: {} },
      ];
      const customEdges = [
        { id: 'custom-edge', source: 'custom-1', target: 'custom-2' },
      ];

      // Midpoint is at (150, 25)
      const nearestEdge = result.current.findNearestEdge(
        { x: 150, y: 25 },
        80,
        customEdges,
        customNodes
      );

      expect(nearestEdge?.id).toBe('custom-edge');
    });
  });

  describe('onDrop', () => {
    const createMockDragEvent = (data: Record<string, string>) => {
      return {
        preventDefault: jest.fn(),
        clientX: 300,
        clientY: 200,
        dataTransfer: {
          getData: jest.fn((key) => data[key] || ''),
        },
      } as unknown as React.DragEvent;
    };

    it('should prevent default behavior', () => {
      const { result } = renderUseDragAndDrop();
      const event = createMockDragEvent({
        'application/reactflow': 'element',
        'application/plugin': 'test_plugin',
        'component': JSON.stringify({ label: 'Test', type: 'element' }),
      });

      act(() => {
        result.current.onDrop(event);
      });

      expect(event.preventDefault).toHaveBeenCalled();
    });

    it('should not create node when canvas is locked', () => {
      const { result } = renderUseDragAndDrop(true); // locked = true
      const event = createMockDragEvent({
        'application/reactflow': 'element',
        'application/plugin': 'test_plugin',
        'component': JSON.stringify({ label: 'Test', type: 'element' }),
      });

      act(() => {
        result.current.onDrop(event);
      });

      expect(mockSetNodes).not.toHaveBeenCalled();
    });

    it('should create node on drop with correct data', () => {
      const { result } = renderUseDragAndDrop();
      const event = createMockDragEvent({
        'application/reactflow': 'element',
        'application/plugin': 'action:test',
        'component': JSON.stringify({ label: 'Test Action', type: 'element' }),
      });

      act(() => {
        result.current.onDrop(event);
      });

      expect(mockSetNodes).toHaveBeenCalled();
      expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);
    });

    it('should create start node for start type', () => {
      const { result } = renderUseDragAndDrop();
      const event = createMockDragEvent({
        'application/reactflow': 'start',
        'application/plugin': 'event:test',
        'component': JSON.stringify({ label: 'Test Event', type: 'start' }),
      });

      act(() => {
        result.current.onDrop(event);
      });

      expect(mockSetNodes).toHaveBeenCalled();
      const nodesCall = mockSetNodes.mock.calls[0][0];
      const newNode = typeof nodesCall === 'function'
        ? nodesCall(mockNodes)[mockNodes.length]
        : nodesCall[nodesCall.length - 1];
      expect(newNode.type).toBe('start');
    });

    it('should create gateway node for gateway type', () => {
      const { result } = renderUseDragAndDrop();
      const event = createMockDragEvent({
        'application/reactflow': 'gateway',
        'application/plugin': 'gateway',
        'component': JSON.stringify({ label: 'Gateway', type: 'gateway' }),
      });

      act(() => {
        result.current.onDrop(event);
      });

      expect(mockSetNodes).toHaveBeenCalled();
      const nodesCall = mockSetNodes.mock.calls[0][0];
      const newNode = typeof nodesCall === 'function'
        ? nodesCall(mockNodes)[mockNodes.length]
        : nodesCall[nodesCall.length - 1];
      expect(newNode.type).toBe('gateway');
    });

    it('should mark the dropped node as selected and deselect existing nodes', () => {
      // Pre-select an existing node
      mockNodes[0].selected = true;
      const { result } = renderUseDragAndDrop();
      const event = createMockDragEvent({
        'application/reactflow': 'element',
        'application/plugin': 'action:test',
        'component': JSON.stringify({ label: 'Test Action', type: 'element' }),
      });

      act(() => {
        result.current.onDrop(event);
      });

      // The new node (last in array) should be selected
      const newNode = mockNodes[mockNodes.length - 1];
      expect(newNode.selected).toBe(true);
      expect(newNode.data.plugin).toBe('action:test');
      
      // Existing nodes should be deselected
      expect(mockNodes[0].selected).toBe(false);
    });

    it('should deselect existing edges when dropping a node', () => {
      mockEdges[0].selected = true;
      const { result } = renderUseDragAndDrop();
      const event = createMockDragEvent({
        'application/reactflow': 'element',
        'application/plugin': 'action:test',
        'component': JSON.stringify({ label: 'Test Action', type: 'element' }),
      });

      act(() => {
        result.current.onDrop(event);
      });

      // The previously selected edge should be deselected
      expect(mockEdges[0].selected).toBe(false);
    });

    it('should not drop when type or plugin is missing', () => {
      const { result } = renderUseDragAndDrop();
      const event = createMockDragEvent({
        'component': JSON.stringify({ label: 'Test' }),
      });

      act(() => {
        result.current.onDrop(event);
      });

      expect(mockSetNodes).not.toHaveBeenCalled();
    });
  });

  describe('onDragOver', () => {
    it('should prevent default and set drop effect', () => {
      const { result } = renderUseDragAndDrop();
      const event = {
        preventDefault: jest.fn(),
        clientX: 300,
        clientY: 200,
        dataTransfer: {
          dropEffect: '',
        },
      } as unknown as React.DragEvent;

      act(() => {
        result.current.onDragOver(event);
      });

      expect(event.preventDefault).toHaveBeenCalled();
      expect(event.dataTransfer.dropEffect).toBe('move');
    });
  });

  describe('condition drop on edge', () => {
    const createMockDragEvent = (data: Record<string, string>) => {
      return {
        preventDefault: jest.fn(),
        clientX: 350,
        clientY: 150, // Near the midpoint of edge-1
        dataTransfer: {
          getData: jest.fn((key) => data[key] || ''),
        },
      } as unknown as React.DragEvent;
    };

    it('should attach condition to nearest edge when dropping link type (condition)', () => {
      const { result } = renderUseDragAndDrop();

      const event = createMockDragEvent({
        'application/reactflow': 'link',
        'application/plugin': 'condition:is_new',
        'component': JSON.stringify({ label: 'Is New', type: 'link' }),
      });

      act(() => {
        result.current.onDrop(event);
      });

      // Should update edge (and deselect nodes), not create new node
      expect(mockSetEdges).toHaveBeenCalled();
      // setNodes is called to deselect existing nodes, but no new node is added
      expect(mockNodes.length).toBe(2);
      expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);
    });

    it('should attach condition to nearest edge when dropping link type (decision)', () => {
      const { result } = renderUseDragAndDrop();

      const event = createMockDragEvent({
        'application/reactflow': 'link',
        'application/plugin': 'decision:check',
        'component': JSON.stringify({ label: 'Check Value', type: 'link' }),
      });

      act(() => {
        result.current.onDrop(event);
      });

      expect(mockSetEdges).toHaveBeenCalled();
      expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);
    });

    it('should show alert when dropping condition far from any edge', () => {
      const alertSpy = jest.spyOn(window, 'alert').mockImplementation(() => {});

      const { result } = renderUseDragAndDrop();

      const event = {
        preventDefault: jest.fn(),
        clientX: 1000,
        clientY: 1000, // Far from any edge
        dataTransfer: {
          getData: jest.fn((key: string) => {
            if (key === 'application/reactflow') return 'link';
            if (key === 'application/plugin') return 'condition:test';
            if (key === 'component') return JSON.stringify({ label: 'Test', type: 'link' });
            return '';
          }),
        },
      } as unknown as React.DragEvent;

      act(() => {
        result.current.onDrop(event);
      });

      expect(alertSpy).toHaveBeenCalledWith(expect.stringContaining('Decision components can only be attached'));
      expect(mockSetEdges).not.toHaveBeenCalled();

      alertSpy.mockRestore();
    });

    it('should set edge type to condition when adding condition', () => {
      const { result } = renderUseDragAndDrop();

      const event = createMockDragEvent({
        'application/reactflow': 'link',
        'application/plugin': 'condition:is_new',
        'component': JSON.stringify({ label: 'Is New', type: 'link' }),
      });

      act(() => {
        result.current.onDrop(event);
      });

      const edgesCallback = mockSetEdges.mock.calls[0][0];
      const updatedEdges = typeof edgesCallback === 'function' ? edgesCallback(mockEdges) : edgesCallback;
      const updatedEdge = updatedEdges.find((e: any) => e.id === 'edge-1');

      expect(updatedEdge.type).toBe('condition');
      expect(updatedEdge.data.condition).toBe('condition:is_new');
      expect(updatedEdge.data.conditionLabel).toBe('Is New');
    });

    it('should set type to condition when dropping condition on edge with annotation', () => {
      mockEdges = [
        {
          id: 'edge-1',
          source: 'node-1',
          target: 'node-2',
          data: { annotation: 'Some note' },
        },
      ];

      const { result } = renderUseDragAndDrop();

      const event = createMockDragEvent({
        'application/reactflow': 'link',
        'application/plugin': 'condition:test',
        'component': JSON.stringify({ label: 'Test', type: 'link' }),
      });

      act(() => {
        result.current.onDrop(event);
      });

      const edgesCallback = mockSetEdges.mock.calls[0][0];
      const updatedEdges = typeof edgesCallback === 'function' ? edgesCallback(mockEdges) : edgesCallback;
      const updatedEdge = updatedEdges.find((e: any) => e.id === 'edge-1');

      expect(updatedEdge.type).toBe('condition');
    });

    it('should mark the target edge as selected when dropping condition', () => {
      const { result } = renderUseDragAndDrop();

      const event = createMockDragEvent({
        'application/reactflow': 'link',
        'application/plugin': 'condition:is_new',
        'component': JSON.stringify({ label: 'Is New', type: 'link' }),
      });

      act(() => {
        result.current.onDrop(event);
      });

      const edgesCallback = mockSetEdges.mock.calls[0][0];
      const updatedEdges = typeof edgesCallback === 'function' ? edgesCallback(mockEdges) : edgesCallback;
      const targetEdge = updatedEdges.find((e: any) => e.id === 'edge-1');
      expect(targetEdge.selected).toBe(true);
    });

    it('should deselect all nodes when dropping condition on edge', () => {
      // Pre-select a node
      mockNodes[0].selected = true;
      const { result } = renderUseDragAndDrop();

      const event = createMockDragEvent({
        'application/reactflow': 'link',
        'application/plugin': 'condition:is_new',
        'component': JSON.stringify({ label: 'Is New', type: 'link' }),
      });

      act(() => {
        result.current.onDrop(event);
      });

      // Nodes should be deselected (setNodes called to deselect)
      expect(mockNodes[0].selected).toBe(false);
    });

    it('should deselect other edges when dropping condition on edge', () => {
      // Add a second edge that is pre-selected
      mockEdges.push({
        id: 'edge-2',
        source: 'node-2',
        target: 'node-1',
        data: {},
        selected: true,
      });

      const { result } = renderUseDragAndDrop();

      const event = createMockDragEvent({
        'application/reactflow': 'link',
        'application/plugin': 'condition:is_new',
        'component': JSON.stringify({ label: 'Is New', type: 'link' }),
      });

      act(() => {
        result.current.onDrop(event);
      });

      const edgesCallback = mockSetEdges.mock.calls[0][0];
      const updatedEdges = typeof edgesCallback === 'function' ? edgesCallback(mockEdges) : edgesCallback;
      // Target edge should be selected
      const targetEdge = updatedEdges.find((e: any) => e.id === 'edge-1');
      expect(targetEdge.selected).toBe(true);
      // Other edge should be deselected
      const otherEdge = updatedEdges.find((e: any) => e.id === 'edge-2');
      expect(otherEdge.selected).toBe(false);
    });
  });

  describe('onDragOver', () => {
    it('should not check edges when not dragging a condition', () => {
      const { result } = renderUseDragAndDrop();

      const event = {
        preventDefault: jest.fn(),
        clientX: 350,
        clientY: 150,
        dataTransfer: { dropEffect: '' },
      } as unknown as React.DragEvent;

      act(() => {
        result.current.onDragOver(event);
      });

      // hoveredDropEdge should remain null since we're not in condition drag mode
      expect(result.current.hoveredDropEdge).toBeNull();
    });
  });

  describe('findNearestEdge edge cases', () => {
    it('should handle edges with missing source node', () => {
      mockEdges = [
        {
          id: 'edge-orphan',
          source: 'missing-node',
          target: 'node-2',
        },
      ];

      const { result } = renderUseDragAndDrop();

      const nearestEdge = result.current.findNearestEdge({ x: 350, y: 150 });
      expect(nearestEdge).toBeNull();
    });

    it('should handle edges with missing target node', () => {
      mockEdges = [
        {
          id: 'edge-orphan',
          source: 'node-1',
          target: 'missing-node',
        },
      ];

      const { result } = renderUseDragAndDrop();

      const nearestEdge = result.current.findNearestEdge({ x: 350, y: 150 });
      expect(nearestEdge).toBeNull();
    });
  });

  describe('label extraction from plugin', () => {
    it('should extract label from plugin when no label provided', () => {
      const { result } = renderUseDragAndDrop();
      const event = {
        preventDefault: jest.fn(),
        clientX: 300,
        clientY: 200,
        dataTransfer: {
          getData: jest.fn((key: string) => {
            if (key === 'application/reactflow') return 'element';
            if (key === 'application/plugin') return 'eca_base.action_test';
            if (key === 'component') return JSON.stringify({ type: 'element' }); // No label
            return '';
          }),
        },
      } as unknown as React.DragEvent;

      act(() => {
        result.current.onDrop(event);
      });

      expect(mockSetNodes).toHaveBeenCalled();
    });

    it('should use plugin as fallback label', () => {
      const { result } = renderUseDragAndDrop();
      const event = {
        preventDefault: jest.fn(),
        clientX: 300,
        clientY: 200,
        dataTransfer: {
          getData: jest.fn((key: string) => {
            if (key === 'application/reactflow') return 'element';
            if (key === 'application/plugin') return 'simple_plugin';
            if (key === 'component') return JSON.stringify({ type: 'element' });
            return '';
          }),
        },
      } as unknown as React.DragEvent;

      act(() => {
        result.current.onDrop(event);
      });

      expect(mockSetNodes).toHaveBeenCalled();
    });
  });
});
