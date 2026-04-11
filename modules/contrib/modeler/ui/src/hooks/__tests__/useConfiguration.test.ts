import { renderHook, act } from '@testing-library/react';
import { useConfiguration } from '../useConfiguration';

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

const mockSetSelectedNode = jest.fn((node) => {
  mockSelectedNode = node;
});

const mockSetSelectedEdge = jest.fn((edge) => {
  mockSelectedEdge = edge;
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

jest.mock('../../store/useSelectionStore', () => ({
  useSelectionStore: jest.fn((selector) => {
    const state = {
      selectedNode: mockSelectedNode,
      setSelectedNode: mockSetSelectedNode,
      selectedEdge: mockSelectedEdge,
      setSelectedEdge: mockSetSelectedEdge,
    };
    return selector(state);
  }),
}));

// Mock autoLayout
jest.mock('../../utils/modelUtils', () => ({
  autoLayout: jest.fn((nodes) => nodes.map((n: any) => ({ ...n, position: { x: 0, y: 0 } }))),
}));

describe('useConfiguration', () => {
  let mockSetHasUnsavedChanges: jest.Mock;

  beforeEach(() => {
    jest.clearAllMocks();
    mockSetHasUnsavedChanges = jest.fn();
    mockNodes = [
      {
        id: 'node-1',
        position: { x: 100, y: 100 },
        data: { label: 'Node 1', configuration: { field1: 'value1' } },
      },
      {
        id: 'node-2',
        position: { x: 300, y: 100 },
        data: { label: 'Node 2' },
      },
    ];
    mockEdges = [
      {
        id: 'edge-1',
        source: 'node-1',
        target: 'node-2',
        type: 'default',
        data: {},
      },
    ];
    mockSelectedNode = null;
    mockSelectedEdge = null;
  });

  const renderUseConfiguration = () => {
    return renderHook(() =>
      useConfiguration({
        setHasUnsavedChanges: mockSetHasUnsavedChanges,
      })
    );
  };

  describe('onConfigurationChange', () => {
    it('should update node configuration', () => {
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onConfigurationChange('node-1', { field2: 'value2' });
      });

      expect(mockSetNodes).toHaveBeenCalled();
      expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);
    });

    it('should merge new configuration with existing', () => {
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onConfigurationChange('node-1', { field2: 'value2' });
      });

      const updatedNodes = mockNodes;
      const node1 = updatedNodes.find((n: any) => n.id === 'node-1');
      expect(node1.data.configuration.field1).toBe('value1');
      expect(node1.data.configuration.field2).toBe('value2');
    });

    it('should update label when _componentLabel is provided', () => {
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onConfigurationChange('node-1', { _componentLabel: 'New Label' });
      });

      const updatedNodes = mockNodes;
      const node1 = updatedNodes.find((n: any) => n.id === 'node-1');
      expect(node1.data.label).toBe('New Label');
    });

    it('should not fail for non-existent node', () => {
      const { result } = renderUseConfiguration();

      expect(() => {
        act(() => {
          result.current.onConfigurationChange('non-existent', { field: 'value' });
        });
      }).not.toThrow();
    });

    it('should handle node with undefined data', () => {
      mockNodes = [
        { id: 'node-1', position: { x: 0, y: 0 } },
      ];
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onConfigurationChange('node-1', { field: 'value' });
      });

      const updatedNodes = mockNodes;
      const node1 = updatedNodes.find((n: any) => n.id === 'node-1');
      expect(node1.data.configuration.field).toBe('value');
    });
  });

  describe('onEdgeConfigurationChange', () => {
    it('should update edge configuration', () => {
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onEdgeConfigurationChange('edge-1', { conditionLabel: 'Yes' });
      });

      expect(mockSetEdges).toHaveBeenCalled();
      expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);
    });

    it('should change edge type to condition when condition is added', () => {
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onEdgeConfigurationChange('edge-1', { conditionLabel: 'Yes' });
      });

      const updatedEdges = mockEdges;
      const edge1 = updatedEdges.find((e: any) => e.id === 'edge-1');
      expect(edge1.type).toBe('condition');
    });

    it('should handle callback pattern', () => {
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onEdgeConfigurationChange('edge-1', (edge) => ({
          conditionLabel: `Condition for ${edge.id}`,
        }));
      });

      const updatedEdges = mockEdges;
      const edge1 = updatedEdges.find((e: any) => e.id === 'edge-1');
      // conditionLabel should be stored at data level, not in conditionConfiguration
      expect(edge1.data.conditionLabel).toBe('Condition for edge-1');
      // Also check that it's set as the edge label for ReactFlow display
      expect(edge1.label).toBe('Condition for edge-1');
    });

    it('should handle null configuration', () => {
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onEdgeConfigurationChange('edge-1', null);
      });

      const updatedEdges = mockEdges;
      const edge1 = updatedEdges.find((e: any) => e.id === 'edge-1');
      expect(edge1.data.conditionConfiguration).toBeNull();
    });

    it('should clear annotation when condition is deleted', () => {
      mockEdges = [
        {
          id: 'edge-1',
          source: 'node-1',
          target: 'node-2',
          type: 'condition',
          data: { condition: 'some_condition', annotation: 'Test annotation', isAnnotationVisible: true },
        },
      ];
      const { result } = renderUseConfiguration();

      // Deleting condition (null) should also clear annotation
      act(() => {
        result.current.onEdgeConfigurationChange('edge-1', null);
      });

      const updatedEdges = mockEdges;
      const edge1 = updatedEdges.find((e: any) => e.id === 'edge-1');
      expect(edge1.data.condition).toBeNull();
      expect(edge1.data.conditionConfiguration).toBeNull();
      expect(edge1.data.annotation).toBeNull();
      expect(edge1.data.isAnnotationVisible).toBe(false);
      expect(edge1.type).toBe('default');
    });

    it('should clear conditionLabel and label when condition is deleted', () => {
      // Regression: conditionLabel and top-level label were not cleared on deletion.
      mockEdges = [
        {
          id: 'edge-1',
          source: 'node-1',
          target: 'node-2',
          type: 'condition',
          label: 'My Condition',
          data: {
            condition: 'some_plugin',
            conditionLabel: 'My Condition',
            conditionConfiguration: { key: 'value' },
          },
        },
      ];
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onEdgeConfigurationChange('edge-1', null);
      });

      const edge1 = mockEdges.find((e: any) => e.id === 'edge-1');
      expect(edge1.data.condition).toBeNull();
      expect(edge1.data.conditionLabel).toBeNull();
      expect(edge1.data.conditionConfiguration).toBeNull();
      expect(edge1.label).toBe('');
      expect(edge1.type).toBe('default');
    });

    it('should revert edge type to default when condition is deleted from edge with empty conditionConfiguration', () => {
      // Regression: conditionConfiguration: {} is truthy in JS, so the edge
      // type was not reverting to 'default' after condition deletion.
      mockEdges = [
        {
          id: 'edge-1',
          source: 'node-1',
          target: 'node-2',
          type: 'condition',
          data: {
            condition: 'some_plugin',
            conditionLabel: 'Yes',
            conditionConfiguration: {},
          },
        },
      ];
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onEdgeConfigurationChange('edge-1', null);
      });

      const edge1 = mockEdges.find((e: any) => e.id === 'edge-1');
      expect(edge1.type).toBe('default');
      expect(edge1.data.condition).toBeNull();
    });

    it('should clear annotation even when conditionConfiguration was empty', () => {
      // Regression: annotation survived condition deletion, creating an orphaned
      // annotation with no way to edit or remove it.
      mockEdges = [
        {
          id: 'edge-1',
          source: 'node-1',
          target: 'node-2',
          type: 'condition',
          data: {
            condition: 'some_plugin',
            conditionLabel: 'Check',
            conditionConfiguration: {},
            annotation: 'Important note',
            isAnnotationVisible: true,
          },
        },
      ];
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onEdgeConfigurationChange('edge-1', null);
      });

      const edge1 = mockEdges.find((e: any) => e.id === 'edge-1');
      expect(edge1.data.annotation).toBeNull();
      expect(edge1.data.isAnnotationVisible).toBe(false);
      expect(edge1.type).toBe('default');
    });
  });

  describe('onNodeUpdate', () => {
    it('should update node data', () => {
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onNodeUpdate('node-1', { description: 'custom value' });
      });

      expect(mockSetNodes).toHaveBeenCalled();
      expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);
    });

    it('should merge with existing data', () => {
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onNodeUpdate('node-1', { description: 'new value' });
      });

      const updatedNodes = mockNodes;
      const node1 = updatedNodes.find((n: Record<string, unknown>) => n.id === 'node-1');
      expect((node1 as any).data.label).toBe('Node 1');
      expect((node1 as any).data.description).toBe('new value');
    });
  });

  describe('onEdgeUpdate', () => {
    it('should update edge data', () => {
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onEdgeUpdate('edge-1', { annotation: 'custom value' });
      });

      expect(mockSetEdges).toHaveBeenCalled();
      expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);
    });

    it('should merge with existing data', () => {
      mockEdges = [
        {
          id: 'edge-1',
          source: 'node-1',
          target: 'node-2',
          type: 'default',
          data: { existingField: 'existing' },
        },
      ];
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onEdgeUpdate('edge-1', { annotation: 'new' });
      });

      const updatedEdges = mockEdges;
      const edge1 = updatedEdges.find((e: Record<string, unknown>) => e.id === 'edge-1');
      expect((edge1 as any).data.existingField).toBe('existing');
      expect((edge1 as any).data.annotation).toBe('new');
    });

    it('should update edge type based on merged data', () => {
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onEdgeUpdate('edge-1', { condition: 'some_condition' });
      });

      const updatedEdges = mockEdges;
      const edge1 = updatedEdges.find((e: any) => e.id === 'edge-1');
      expect(edge1.type).toBe('condition');
    });
  });

  describe('handleAutoLayout', () => {
    it('should call autoLayout with nodes and edges', () => {
      const { autoLayout } = require('../../utils/modelUtils');
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.handleAutoLayout();
      });

      // Verify autoLayout was called with arrays of nodes and edges
      expect(autoLayout).toHaveBeenCalled();
      const callArgs = autoLayout.mock.calls[0];
      expect(Array.isArray(callArgs[0])).toBe(true);
      expect(Array.isArray(callArgs[1])).toBe(true);
      expect(callArgs[0].length).toBe(2); // 2 nodes
      expect(callArgs[1].length).toBe(1); // 1 edge
    });

    it('should update nodes with layouted result', () => {
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.handleAutoLayout();
      });

      expect(mockSetNodes).toHaveBeenCalled();
    });

    it('should mark as unsaved changes', () => {
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.handleAutoLayout();
      });

      expect(mockSetHasUnsavedChanges).toHaveBeenCalledWith(true);
    });

    it('should not update when autoLayout returns null', () => {
      const { autoLayout } = require('../../utils/modelUtils');
      autoLayout.mockReturnValueOnce(null);

      const { result } = renderUseConfiguration();

      act(() => {
        result.current.handleAutoLayout();
      });

      expect(mockSetNodes).not.toHaveBeenCalled();
    });
  });

  describe('selected node update on configuration change', () => {
    it('should update selectedNode when matching node is changed', async () => {
      mockSelectedNode = { id: 'node-1', position: { x: 100, y: 100 }, data: { label: 'Node 1' } };
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onConfigurationChange('node-1', { field: 'value' });
      });

      // Wait for the setTimeout
      await act(async () => {
        await new Promise(resolve => setTimeout(resolve, 20));
      });

      expect(mockSetNodes).toHaveBeenCalled();
    });
  });

  describe('selected edge update on configuration change', () => {
    it('should update selectedEdge when matching edge is changed', async () => {
      mockSelectedEdge = { id: 'edge-1', source: 'node-1', target: 'node-2' };
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onEdgeConfigurationChange('edge-1', { conditionLabel: 'Test' });
      });

      // Wait for the setTimeout
      await act(async () => {
        await new Promise(resolve => setTimeout(resolve, 20));
      });

      expect(mockSetEdges).toHaveBeenCalled();
    });
  });

  describe('edge type determination with both condition and annotation', () => {
    it('should set type to condition when both condition and annotation exist', () => {
      mockEdges = [
        {
          id: 'edge-1',
          source: 'node-1',
          target: 'node-2',
          type: 'default',
          data: { annotation: 'A note', condition: 'some_condition' },
        },
      ];
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onEdgeConfigurationChange('edge-1', { conditionLabel: 'Yes' });
      });

      const updatedEdges = mockEdges;
      const edge1 = updatedEdges.find((e: any) => e.id === 'edge-1');
      expect(edge1.type).toBe('condition');
    });

    it('should stay default type when only annotation exists via onEdgeUpdate', () => {
      mockEdges = [
        {
          id: 'edge-1',
          source: 'node-1',
          target: 'node-2',
          type: 'default',
          data: {},
        },
      ];
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onEdgeUpdate('edge-1', { annotation: 'Just a note' });
      });

      const updatedEdges = mockEdges;
      const edge1 = updatedEdges.find((e: any) => e.id === 'edge-1');
      // Annotations belong to conditions; an edge without a condition stays 'default'
      expect(edge1.type).toBe('default');
    });

    it('should set type to condition when both condition and annotation exist via onEdgeUpdate', () => {
      mockEdges = [
        {
          id: 'edge-1',
          source: 'node-1',
          target: 'node-2',
          type: 'default',
          data: { annotation: 'A note' },
        },
      ];
      const { result } = renderUseConfiguration();

      act(() => {
        result.current.onEdgeUpdate('edge-1', { condition: 'some_condition' });
      });

      const updatedEdges = mockEdges;
      const edge1 = updatedEdges.find((e: any) => e.id === 'edge-1');
      expect(edge1.type).toBe('condition');
    });
  });
});
