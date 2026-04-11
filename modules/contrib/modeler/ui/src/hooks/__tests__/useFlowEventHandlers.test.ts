import React from 'react';
import { renderHook, act } from '@testing-library/react';
import { useFlowEventHandlers } from '../useFlowEventHandlers';

// Mock store state
let mockNodes: any[] = [];
let mockEdges: any[] = [];
let mockSelectedNode: any = null;
let mockSelectedEdge: any = null;

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

const mockApplyNodeChanges = jest.fn();
const mockApplyEdgeChanges = jest.fn();
const mockRemoveNode = jest.fn((nodeId: string) => {
  mockEdges = mockEdges.filter(e => e.source !== nodeId && e.target !== nodeId);
  mockNodes = mockNodes.filter(n => n.id !== nodeId);
  if (mockSelectedNode?.id === nodeId) {
    mockSelectedNode = null;
  }
});
const mockRemoveEdge = jest.fn((edgeId: string) => {
  mockEdges = mockEdges.filter(e => e.id !== edgeId);
});
const mockSetSelectedNode = jest.fn((node) => { mockSelectedNode = node; });
const mockSetSelectedEdge = jest.fn((edge) => { mockSelectedEdge = edge; });
const mockSetSelectedNodes = jest.fn();
const mockSetSelectedEdges = jest.fn();

jest.mock('../../store/useGraphStore', () => ({
  useGraphStore: jest.fn((selector) => {
    const state = {
      nodes: mockNodes,
      edges: mockEdges,
      setNodes: mockSetNodes,
      setEdges: mockSetEdges,
      removeNode: mockRemoveNode,
      removeEdge: mockRemoveEdge,
      applyNodeChanges: mockApplyNodeChanges,
      applyEdgeChanges: mockApplyEdgeChanges,
    };
    return selector(state);
  }),
}));

jest.mock('../../store/useSelectionStore', () => ({
  useSelectionStore: jest.fn((selector) => {
    const state = {
      selectedNode: mockSelectedNode,
      selectedEdge: mockSelectedEdge,
      setSelectedNode: mockSetSelectedNode,
      setSelectedEdge: mockSetSelectedEdge,
      setSelectedNodes: mockSetSelectedNodes,
      setSelectedEdges: mockSetSelectedEdges,
    };
    return selector(state);
  }),
}));

// Mock utility functions
jest.mock('../../utils/clipboardUtils', () => ({
  generateUniqueEdgeId: jest.fn(() => 'edge-new-123'),
}));

describe('useFlowEventHandlers', () => {
  let mockHandleCanvasNodeClick: jest.Mock;
  let mockHandleCanvasEdgeClick: jest.Mock;
  let mockSetHasUnsavedChanges: jest.Mock;
  let mockAutoSyncToReplay: jest.Mock;

  beforeEach(() => {
    jest.clearAllMocks();
    mockHandleCanvasNodeClick = jest.fn();
    mockHandleCanvasEdgeClick = jest.fn();
    mockSetHasUnsavedChanges = jest.fn();
    mockAutoSyncToReplay = jest.fn();

    mockNodes = [
      { id: 'node-1', position: { x: 100, y: 100 }, data: { label: 'Node 1' }, selected: false },
      { id: 'node-2', position: { x: 300, y: 100 }, data: { label: 'Node 2' }, selected: false },
    ];
    mockEdges = [
      { id: 'edge-1', source: 'node-1', target: 'node-2', selected: false },
    ];
    mockSelectedNode = null;
    mockSelectedEdge = null;
  });

  const createRef = (value: boolean) => ({ current: value });

  const renderUseFlowEventHandlers = (overrides: Record<string, unknown> = {}) => {
    const isSyncing = (overrides.isSyncing as boolean | undefined) ?? false;
    const { isSyncing: _, isReplaySyncingRef: __, ...rest } = overrides;
    return renderHook(() =>
      useFlowEventHandlers({
        handleCanvasNodeClick: mockHandleCanvasNodeClick,
        handleCanvasEdgeClick: mockHandleCanvasEdgeClick,
        setHasUnsavedChanges: mockSetHasUnsavedChanges,
        isSyncing,
        isReplaySyncingRef: (overrides.isReplaySyncingRef ?? createRef(false)) as React.RefObject<boolean>,
        hasReplayData: false,
        isReplayMode: false,
        currentReplayStep: -1,
        autoSyncToReplay: mockAutoSyncToReplay,
        ...rest,
      })
    );
  };

  describe('return values', () => {
    it('should return all required handlers', () => {
      const { result } = renderUseFlowEventHandlers();

      expect(typeof result.current.onNodesChange).toBe('function');
      expect(typeof result.current.onEdgesChange).toBe('function');
      expect(typeof result.current.onSelectionChange).toBe('function');
      expect(typeof result.current.onNodeClick).toBe('function');
      expect(typeof result.current.onEdgeClick).toBe('function');
      expect(typeof result.current.onDeleteNode).toBe('function');
      expect(typeof result.current.handleDeleteSelected).toBe('function');
      expect(typeof result.current.onConnect).toBe('function');
      expect(typeof result.current.onPaneClick).toBe('function');
      expect(typeof result.current.onNodeDragStart).toBe('function');
      expect(typeof result.current.onNodeDragStop).toBe('function');
    });
  });

  describe('onNodesChange', () => {
    it('should apply node changes to store', () => {
      const { result } = renderUseFlowEventHandlers();
      const changes = [{ id: 'node-1', type: 'position', position: { x: 200, y: 200 } }];

      act(() => {
        result.current.onNodesChange(changes);
      });

      expect(mockApplyNodeChanges).toHaveBeenCalledWith(changes);
    });

    it('should not mark unsaved changes for any node changes (handled by drag events)', () => {
      const { result } = renderUseFlowEventHandlers();
      const changes = [
        { id: 'node-1', type: 'position', position: { x: 200, y: 200 }, dragging: false },
        { id: 'node-1', type: 'select', selected: true },
      ];

      act(() => {
        result.current.onNodesChange(changes);
      });

      expect(mockSetHasUnsavedChanges).not.toHaveBeenCalled();
    });
  });

  describe('onNodeDragStart and onNodeDragStop', () => {
    it('should mark unsaved changes when a node is dragged to a new position', () => {
      const { result } = renderUseFlowEventHandlers();
      const event = {} as React.MouseEvent;
      const nodeAtStart = { id: 'node-1', position: { x: 100, y: 100 }, data: {} };
      const nodeAtEnd = { id: 'node-1', position: { x: 200, y: 200 }, data: {} };

      act(() => {
        result.current.onNodeDragStart(event, nodeAtStart);
      });
      expect(mockSetHasUnsavedChanges).not.toHaveBeenCalled();

      act(() => {
        result.current.onNodeDragStop(event, nodeAtEnd);
      });
      expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);
    });

    it('should not mark unsaved changes when a node is clicked without moving (zero-movement drag)', () => {
      const { result } = renderUseFlowEventHandlers();
      const event = {} as React.MouseEvent;
      const node = { id: 'node-1', position: { x: 100, y: 100 }, data: {} };

      act(() => {
        result.current.onNodeDragStart(event, node);
      });
      act(() => {
        result.current.onNodeDragStop(event, node);
      });

      expect(mockSetHasUnsavedChanges).not.toHaveBeenCalled();
    });

    it('should not mark unsaved changes if only one axis moved to the same value', () => {
      const { result } = renderUseFlowEventHandlers();
      const event = {} as React.MouseEvent;
      const nodeAtStart = { id: 'node-1', position: { x: 100, y: 100 }, data: {} };
      const nodeAtEnd = { id: 'node-1', position: { x: 100, y: 200 }, data: {} };

      act(() => {
        result.current.onNodeDragStart(event, nodeAtStart);
      });
      act(() => {
        result.current.onNodeDragStop(event, nodeAtEnd);
      });

      expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);
    });
  });

  describe('onEdgesChange', () => {
    it('should apply edge changes to store', () => {
      const { result } = renderUseFlowEventHandlers();
      const changes = [{ id: 'edge-1', type: 'remove' }];

      act(() => {
        result.current.onEdgesChange(changes);
      });

      expect(mockApplyEdgeChanges).toHaveBeenCalledWith(changes);
    });
  });

  describe('onSelectionChange', () => {
    it('should set selected node and edge', () => {
      const { result } = renderUseFlowEventHandlers();
      const selectedNodes = [{ id: 'node-1', position: { x: 0, y: 0 }, data: {} }];
      const selectedEdges: any[] = [];

      act(() => {
        result.current.onSelectionChange({ nodes: selectedNodes, edges: selectedEdges });
      });

      expect(mockSetSelectedNode).toHaveBeenCalledWith(selectedNodes[0]);
      expect(mockSetSelectedEdge).toHaveBeenCalledWith(null);
    });

    it('should set selected nodes and edges arrays', () => {
      const { result } = renderUseFlowEventHandlers();
      const selectedNodes = [
        { id: 'node-1', position: { x: 0, y: 0 }, data: {} },
        { id: 'node-2', position: { x: 100, y: 0 }, data: {} }
      ];
      const selectedEdges = [{ id: 'edge-1', source: 'node-1', target: 'node-2' }];

      act(() => {
        result.current.onSelectionChange({ nodes: selectedNodes, edges: selectedEdges });
      });

      expect(mockSetSelectedNodes).toHaveBeenCalledWith(['node-1', 'node-2']);
      expect(mockSetSelectedEdges).toHaveBeenCalledWith(['edge-1']);
    });

    it('should auto-sync to replay when conditions are met', () => {
      const { result } = renderUseFlowEventHandlers({
        hasReplayData: true,
        isReplayMode: false,
        isSyncing: false,
      });
      const selectedNodes = [{ id: 'node-1', position: { x: 0, y: 0 }, data: {} }];

      act(() => {
        result.current.onSelectionChange({ nodes: selectedNodes, edges: [] });
      });

      expect(mockAutoSyncToReplay).toHaveBeenCalledWith(selectedNodes[0]);
    });

    it('should not auto-sync when isSyncing is true', () => {
      const { result } = renderUseFlowEventHandlers({
        hasReplayData: true,
        isSyncing: true,
      });

      act(() => {
        result.current.onSelectionChange({ nodes: [{ id: 'node-1', position: { x: 0, y: 0 }, data: {} }], edges: [] });
      });

      expect(mockAutoSyncToReplay).not.toHaveBeenCalled();
    });

    it('should not auto-sync when no replay data', () => {
      const { result } = renderUseFlowEventHandlers({
        hasReplayData: false,
      });

      act(() => {
        result.current.onSelectionChange({ nodes: [{ id: 'node-1', position: { x: 0, y: 0 }, data: {} }], edges: [] });
      });

      expect(mockAutoSyncToReplay).not.toHaveBeenCalled();
    });

    it('should not auto-sync when isReplayMode is true and currentReplayStep is not -1', () => {
      const { result } = renderUseFlowEventHandlers({
        hasReplayData: true,
        isReplayMode: true,
        currentReplayStep: 5,
        isSyncing: false,
      });

      act(() => {
        result.current.onSelectionChange({ nodes: [{ id: 'node-1', position: { x: 0, y: 0 }, data: {} }], edges: [] });
      });

      expect(mockAutoSyncToReplay).not.toHaveBeenCalled();
    });

    it('should auto-sync when isReplayMode is true but currentReplayStep is -1', () => {
      const { result } = renderUseFlowEventHandlers({
        hasReplayData: true,
        isReplayMode: true,
        currentReplayStep: -1,
        isSyncing: false,
      });

      act(() => {
        result.current.onSelectionChange({ nodes: [{ id: 'node-1', position: { x: 0, y: 0 }, data: {} }], edges: [] });
      });

      expect(mockAutoSyncToReplay).toHaveBeenCalled();
    });

    it('should handle empty selection', () => {
      const { result } = renderUseFlowEventHandlers();

      act(() => {
        result.current.onSelectionChange({ nodes: [], edges: [] });
      });

      expect(mockSetSelectedNode).toHaveBeenCalledWith(null);
      expect(mockSetSelectedEdge).toHaveBeenCalledWith(null);
      expect(mockSetSelectedNodes).toHaveBeenCalledWith([]);
      expect(mockSetSelectedEdges).toHaveBeenCalledWith([]);
    });

    it('should select edge when only edge is selected', () => {
      const { result } = renderUseFlowEventHandlers();
      const selectedEdge = { id: 'edge-1', source: 'node-1', target: 'node-2' };

      act(() => {
        result.current.onSelectionChange({ nodes: [], edges: [selectedEdge] });
      });

      expect(mockSetSelectedNode).toHaveBeenCalledWith(null);
      expect(mockSetSelectedEdge).toHaveBeenCalledWith(selectedEdge);
    });

    it('should skip onSelectionChange entirely when isReplaySyncingRef is true', () => {
      const replaySyncingRef = createRef(true);
      const { result } = renderUseFlowEventHandlers({
        isReplaySyncingRef: replaySyncingRef,
      });

      act(() => {
        result.current.onSelectionChange({
          nodes: [{ id: 'node-1', position: { x: 0, y: 0 }, data: {} }],
          edges: [{ id: 'edge-1', source: 'node-1', target: 'node-2' }],
        });
      });

      // All store updates should be skipped during replay-to-canvas sync
      expect(mockSetSelectedNode).not.toHaveBeenCalled();
      expect(mockSetSelectedEdge).not.toHaveBeenCalled();
      expect(mockSetSelectedNodes).not.toHaveBeenCalled();
      expect(mockSetSelectedEdges).not.toHaveBeenCalled();
      expect(mockAutoSyncToReplay).not.toHaveBeenCalled();
    });

    it('should allow onSelectionChange when isReplaySyncingRef is false even if isSyncing is true', () => {
      const replaySyncingRef = createRef(false);
      const { result } = renderUseFlowEventHandlers({
        isSyncing: true,
        isReplaySyncingRef: replaySyncingRef,
      });

      act(() => {
        result.current.onSelectionChange({
          nodes: [{ id: 'node-1', position: { x: 0, y: 0 }, data: {} }],
          edges: [],
        });
      });

      // Selection should proceed (isReplaySyncingRef is false)
      expect(mockSetSelectedNode).toHaveBeenCalledWith(expect.objectContaining({ id: 'node-1' }));
      // But autoSyncToReplay should be blocked by the general isSyncing flag
      expect(mockAutoSyncToReplay).not.toHaveBeenCalled();
    });
  });

  describe('onNodeClick', () => {
    it('should call handleCanvasNodeClick with node', () => {
      const { result } = renderUseFlowEventHandlers();
      const node = { id: 'node-1', position: { x: 0, y: 0 }, data: {} };
      const event = {} as React.MouseEvent;

      act(() => {
        result.current.onNodeClick(event, node);
      });

      expect(mockHandleCanvasNodeClick).toHaveBeenCalledWith(node);
    });
  });

  describe('onEdgeClick', () => {
    it('should call handleCanvasEdgeClick with edge', () => {
      const { result } = renderUseFlowEventHandlers();
      const edge = { id: 'edge-1', source: 'node-1', target: 'node-2' };
      const event = {} as React.MouseEvent;

      act(() => {
        result.current.onEdgeClick(event, edge);
      });

      expect(mockHandleCanvasEdgeClick).toHaveBeenCalledWith(edge);
    });
  });

  describe('onDeleteNode', () => {
    it('should delegate to useGraphStore.removeNode', () => {
      const { result } = renderUseFlowEventHandlers();

      act(() => {
        result.current.onDeleteNode('node-1');
      });

      expect(mockRemoveNode).toHaveBeenCalledWith('node-1');
    });

    it('should remove node and connected edges via removeNode', () => {
      const { result } = renderUseFlowEventHandlers();

      act(() => {
        result.current.onDeleteNode('node-1');
      });

      expect(mockNodes.find(n => n.id === 'node-1')).toBeUndefined();
      expect(mockEdges.find(e => e.source === 'node-1' || e.target === 'node-1')).toBeUndefined();
    });

    it('should mark unsaved changes', () => {
      const { result } = renderUseFlowEventHandlers();

      act(() => {
        result.current.onDeleteNode('node-1');
      });

      expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);
    });

    it('should announce deletion to screen readers', () => {
      const mockAnnounce = jest.fn();
      const { result } = renderUseFlowEventHandlers({ announce: mockAnnounce });

      act(() => {
        result.current.onDeleteNode('node-1');
      });

      expect(mockAnnounce).toHaveBeenCalledWith('Element deleted.');
    });

    it('should not fail when announce is not provided', () => {
      const { result } = renderUseFlowEventHandlers();

      expect(() => {
        act(() => {
          result.current.onDeleteNode('node-1');
        });
      }).not.toThrow();
    });
  });

  describe('handleDeleteSelected', () => {
    it('should delegate to removeNode for selected unlocked nodes', () => {
      mockNodes = [
        { id: 'node-1', selected: true, data: {} },
        { id: 'node-2', selected: false, data: {} },
      ];
      mockEdges = [];
      const { result } = renderUseFlowEventHandlers();

      act(() => {
        result.current.handleDeleteSelected();
      });

      expect(mockRemoveNode).toHaveBeenCalledWith('node-1');
      expect(mockRemoveNode).not.toHaveBeenCalledWith('node-2');
      expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);
    });

    it('should delegate to removeEdge for selected unlocked edges', () => {
      mockNodes = [];
      mockEdges = [
        { id: 'edge-1', source: 'a', target: 'b', selected: true },
        { id: 'edge-2', source: 'b', target: 'c', selected: false },
      ];
      const { result } = renderUseFlowEventHandlers();

      act(() => {
        result.current.handleDeleteSelected();
      });

      expect(mockRemoveEdge).toHaveBeenCalledWith('edge-1');
      expect(mockRemoveEdge).not.toHaveBeenCalledWith('edge-2');
    });

    it('should not double-remove edges already removed by node deletion', () => {
      mockNodes = [
        { id: 'node-1', selected: true, data: {} },
        { id: 'node-2', selected: false, data: {} },
      ];
      mockEdges = [
        { id: 'edge-1', source: 'node-1', target: 'node-2', selected: true },
      ];
      const { result } = renderUseFlowEventHandlers();

      act(() => {
        result.current.handleDeleteSelected();
      });

      // edge-1 is connected to node-1 so it's implicitly removed by removeNode;
      // removeEdge should NOT be called for it again.
      expect(mockRemoveNode).toHaveBeenCalledWith('node-1');
      expect(mockRemoveEdge).not.toHaveBeenCalledWith('edge-1');
    });

    it('should do nothing when nothing is selected', () => {
      mockNodes = [{ id: 'node-1', selected: false }];
      mockEdges = [{ id: 'edge-1', selected: false }];
      const { result } = renderUseFlowEventHandlers();

      act(() => {
        result.current.handleDeleteSelected();
      });

      expect(mockRemoveNode).not.toHaveBeenCalled();
      expect(mockRemoveEdge).not.toHaveBeenCalled();
      expect(mockSetHasUnsavedChanges).not.toHaveBeenCalled();
    });

    it('should announce deletion count to screen readers', () => {
      mockNodes = [
        { id: 'node-1', selected: true, data: {} },
        { id: 'node-2', selected: true, data: {} },
      ];
      mockEdges = [
        { id: 'edge-1', source: 'node-1', target: 'node-2', selected: true },
      ];
      const mockAnnounce = jest.fn();
      const { result } = renderUseFlowEventHandlers({ announce: mockAnnounce });

      act(() => {
        result.current.handleDeleteSelected();
      });

      expect(mockAnnounce).toHaveBeenCalledWith('3 elements deleted.');
    });

    it('should not announce when nothing is deleted', () => {
      mockNodes = [{ id: 'node-1', selected: false }];
      mockEdges = [];
      const mockAnnounce = jest.fn();
      const { result } = renderUseFlowEventHandlers({ announce: mockAnnounce });

      act(() => {
        result.current.handleDeleteSelected();
      });

      expect(mockAnnounce).not.toHaveBeenCalled();
    });
  });

  describe('onConnect', () => {
    it('should create new edge between nodes', () => {
      const { result } = renderUseFlowEventHandlers();
      const connection = { source: 'node-1', target: 'node-2' };

      act(() => {
        result.current.onConnect(connection);
      });

      expect(mockSetEdges).toHaveBeenCalled();
      expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);
    });

    it('should use default edge type', () => {
      mockEdges = [];
      const { result } = renderUseFlowEventHandlers();

      act(() => {
        result.current.onConnect({ source: 'node-1', target: 'node-2' });
      });

      const newEdge = mockEdges[0];
      expect(newEdge.type).toBe('default');
      expect(newEdge.id).toBe('edge-new-123');
    });

    it('should not create edge when source is null', () => {
      const { result } = renderUseFlowEventHandlers();

      act(() => {
        result.current.onConnect({ source: null, target: 'node-2' });
      });

      expect(mockSetEdges).not.toHaveBeenCalled();
    });

    it('should not create edge when target is null', () => {
      const { result } = renderUseFlowEventHandlers();

      act(() => {
        result.current.onConnect({ source: 'node-1', target: null });
      });

      expect(mockSetEdges).not.toHaveBeenCalled();
    });
  });

  describe('onPaneClick', () => {
    it('should clear node and edge selection', () => {
      const { result } = renderUseFlowEventHandlers();

      act(() => {
        result.current.onPaneClick();
      });

      expect(mockSetSelectedNode).toHaveBeenCalledWith(null);
      expect(mockSetSelectedEdge).toHaveBeenCalledWith(null);
    });

    it('should cause onSelectionChange to ignore stale non-empty events', () => {
      const { result } = renderUseFlowEventHandlers();
      const staleNode = { id: 'node-1', position: { x: 0, y: 0 }, data: {} };

      // Simulate: pane click clears selection, then a stale onSelectionChange
      // fires with the previously selected node still present.
      act(() => {
        result.current.onPaneClick();
      });

      mockSetSelectedNode.mockClear();
      mockSetSelectedEdge.mockClear();
      mockSetSelectedNodes.mockClear();
      mockSetSelectedEdges.mockClear();

      act(() => {
        result.current.onSelectionChange({ nodes: [staleNode], edges: [] });
      });

      // The stale event should be ignored — no store updates.
      expect(mockSetSelectedNode).not.toHaveBeenCalled();
      expect(mockSetSelectedEdge).not.toHaveBeenCalled();
      expect(mockSetSelectedNodes).not.toHaveBeenCalled();
      expect(mockSetSelectedEdges).not.toHaveBeenCalled();
    });

    it('should allow the confirming empty onSelectionChange after pane click', () => {
      const { result } = renderUseFlowEventHandlers();

      act(() => {
        result.current.onPaneClick();
      });

      mockSetSelectedNode.mockClear();
      mockSetSelectedEdge.mockClear();
      mockSetSelectedNodes.mockClear();
      mockSetSelectedEdges.mockClear();

      // The confirming empty selection event should proceed normally.
      act(() => {
        result.current.onSelectionChange({ nodes: [], edges: [] });
      });

      expect(mockSetSelectedNode).toHaveBeenCalledWith(null);
      expect(mockSetSelectedEdge).toHaveBeenCalledWith(null);
      expect(mockSetSelectedNodes).toHaveBeenCalledWith([]);
      expect(mockSetSelectedEdges).toHaveBeenCalledWith([]);
    });

    it('should not interfere with normal onSelectionChange without prior pane click', () => {
      const { result } = renderUseFlowEventHandlers();
      const selectedNode = { id: 'node-1', position: { x: 0, y: 0 }, data: {} };

      // Normal selection without pane click should work as usual.
      act(() => {
        result.current.onSelectionChange({ nodes: [selectedNode], edges: [] });
      });

      expect(mockSetSelectedNode).toHaveBeenCalledWith(selectedNode);
    });
  });

});
