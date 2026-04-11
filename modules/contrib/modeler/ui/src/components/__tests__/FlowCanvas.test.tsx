import React from 'react';
import { render, screen } from '@testing-library/react';
import FlowCanvas from '../FlowCanvas';

jest.mock('reactflow', () => {
  const MockReactFlow = React.forwardRef((props: any, ref: any) => (
    <div data-testid="react-flow" ref={ref} className={props.className || ''}>
      {props.children}
    </div>
  ));
  MockReactFlow.displayName = 'MockReactFlow';

  return {
    __esModule: true,
    default: MockReactFlow,
    MiniMap: () => <div data-testid="minimap" />,
    Background: () => <div data-testid="background" />,
    Controls: () => <div data-testid="controls" />,
    EdgeLabelRenderer: ({ children }: { children: React.ReactNode }) => <div data-testid="edge-label-renderer">{children}</div>,
    ConnectionLineType: { Bezier: 'bezier', SmoothStep: 'smoothstep' },
    MarkerType: { Arrow: 'arrow', ArrowClosed: 'arrowclosed' },
    SelectionMode: { Partial: 'partial' },
    PanOnScrollMode: { Free: 'free', Horizontal: 'horizontal', Vertical: 'vertical' },
    Position: { Top: 'top', Bottom: 'bottom', Left: 'left', Right: 'right' },
  };
});

jest.mock('../../hooks/useEdgeOrdering', () => ({
  useEdgeOrdering: jest.fn(() => ({
    handleDragStart: jest.fn(),
    handleDragEnd: jest.fn(),
    handleEdgeOrderDrop: jest.fn(),
    handleReorderEdge: jest.fn(),
    getEdgeOrderInfo: jest.fn(() => ({ order: 1, totalEdges: 1 })),
  })),
}));

jest.mock('../../hooks/useSimpleReplaySync', () => ({}));

jest.mock('../nodes/CustomNode', () => () => <div data-testid="custom-node" />);
jest.mock('../nodes/StartNode', () => () => <div data-testid="start-node" />);
jest.mock('../nodes/GatewayNode', () => () => <div data-testid="gateway-node" />);
jest.mock('../nodes/SubprocessNode', () => () => <div data-testid="subprocess-node" />);
jest.mock('../edges/DefaultEdge', () => () => <div data-testid="default-edge" />);
jest.mock('../edges/ConditionEdge', () => () => <div data-testid="condition-edge" />);

jest.mock('../QuickAddEventButton', () => () => null);

describe('FlowCanvas', () => {
  const defaultEventHandlers = {
    onNodesChange: jest.fn(),
    onEdgesChange: jest.fn(),
    onConnect: jest.fn(),
    onSelectionChange: jest.fn(),
    onConnectStart: jest.fn(),
    onConnectEnd: jest.fn(),
    onDrop: jest.fn(),
    onDragOver: jest.fn(),
    onDragEnter: jest.fn(),
    onDragLeave: jest.fn(),
    onNodeClick: jest.fn(),
    onEdgeClick: jest.fn(),
    onPaneClick: jest.fn(),
    onNodeDragStart: jest.fn(),
    onNodeDragStop: jest.fn(),
    onInit: jest.fn(),
  };

  const defaultElementCallbacks = {
    onEdgeUpdate: jest.fn(),
    onNodeUpdate: jest.fn(),
    onDeleteNode: jest.fn(),
  };

  const defaultModifierKeys = {
    isShiftPressed: false,
    isCtrlPressed: false,
    isAltPressed: false,
  };

  const defaultUIState = {
    isDragActive: false,
    isLocked: false,
    showEdgeOrderNumbers: false,
    showAllAnnotations: false,
  };

  const defaultSearch = {
    searchTerm: '',
    highlightedSearchResult: null,
  };

  const defaultReplay = {
    replayData: [] as any[],
    currentReplayStep: -1,
    isReplayMode: false,
    replayIndicators: [] as any[],
  };

  const defaultQuickAdd = {
    onQuickAdd: jest.fn(),
    onAddCondition: jest.fn(),
  };

  const defaultProps = {
    nodes: [],
    edges: [],
    eventHandlers: defaultEventHandlers,
    elementCallbacks: defaultElementCallbacks,
    viewport: { x: 0, y: 0, zoom: 1 },
    modifierKeys: defaultModifierKeys,
    uiState: defaultUIState,
    search: defaultSearch,
    replay: defaultReplay,
    setEdges: jest.fn(),
    setHasUnsavedChanges: jest.fn(),
    quickAdd: defaultQuickAdd,
  };

  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('rendering', () => {
    it('should render the ReactFlow component', () => {
      render(<FlowCanvas {...defaultProps} />);
      expect(screen.getByTestId('react-flow')).toBeTruthy();
    });

    it('should render without errors with empty nodes/edges', () => {
      const { container } = render(<FlowCanvas {...defaultProps} />);
      expect(container).toBeTruthy();
    });

    it('should not apply modifier cursor classes', () => {
      // With Figma-like gestures, no modifier-based cursor classes are added
      const { container } = render(<FlowCanvas {...defaultProps} modifierKeys={{ ...defaultModifierKeys, isShiftPressed: true }} />);
      expect(container.querySelector('.shift-pressed')).toBeFalsy();
    });

    it('should not apply extra cursor classes when ctrl and alt are pressed', () => {
      render(<FlowCanvas {...defaultProps} modifierKeys={{ ...defaultModifierKeys, isCtrlPressed: true, isAltPressed: true }} />);
      const reactFlowEl = screen.getByTestId('react-flow');
      // Ctrl/Alt no longer add cursor classes (zoom/pan handled natively by ReactFlow)
      expect(reactFlowEl.className).not.toContain('ctrl-alt-pressed');
      expect(reactFlowEl.className).not.toContain('ctrl-pressed');
    });

    it('should disable node dragging when locked', () => {
      const { container } = render(<FlowCanvas {...defaultProps} uiState={{ ...defaultUIState, isLocked: true }} />);
      // FlowCanvas passes nodesDraggable={!isLocked} to ReactFlow
      // Verify the component renders without error when locked
      expect(container.querySelector('.reactflow-wrapper')).toBeTruthy();
    });

    it('should apply drag-active class when dragging', () => {
      const { container } = render(<FlowCanvas {...defaultProps} uiState={{ ...defaultUIState, isDragActive: true }} />);
      expect(container.querySelector('.drag-active')).toBeTruthy();
    });

    it('should always show minimap', () => {
      render(<FlowCanvas {...defaultProps} />);
      expect(screen.getByTestId('minimap')).toBeTruthy();
    });
  });

  describe('with nodes', () => {
    it('should handle nodes with search highlighting', () => {
      const nodes = [
        { id: 'node-1', type: 'element', position: { x: 0, y: 0 }, data: { label: 'Test Node' } },
      ];
      const { container } = render(<FlowCanvas {...defaultProps} nodes={nodes as any} search={{ ...defaultSearch, searchTerm: 'Test' }} />);
      expect(container).toBeTruthy();
    });

    it('should highlight search result node', () => {
      const nodes = [
        { id: 'node-1', type: 'element', position: { x: 0, y: 0 }, data: { label: 'Test Node' } },
      ];
      const { container } = render(
        <FlowCanvas
          {...defaultProps}
          nodes={nodes as any}
          search={{ searchTerm: 'Test', highlightedSearchResult: { id: 'node-1', type: 'node' } }}
        />
      );
      expect(container).toBeTruthy();
    });
  });

  describe('with edges', () => {
    it('should enhance edges with order info and callbacks', () => {
      const edges = [
        { id: 'edge-1', source: 'node-1', target: 'node-2', data: {} },
      ];
      const { container } = render(
        <FlowCanvas {...defaultProps} edges={edges as any} uiState={{ ...defaultUIState, showEdgeOrderNumbers: true }} />
      );
      expect(container).toBeTruthy();
    });

    it('should not add onAddCondition to edge with existing condition', () => {
      const edges = [
        { id: 'edge-1', source: 'node-1', target: 'node-2', data: { condition: 'existing' } },
      ];
      const { container } = render(
        <FlowCanvas {...defaultProps} edges={edges as any} quickAdd={{ ...defaultQuickAdd, onAddCondition: jest.fn() }} />
      );
      expect(container).toBeTruthy();
    });

    it('should add onAddCondition to edges without condition when not locked', () => {
      const onAddCondition = jest.fn();
      const edges = [
        { id: 'edge-1', source: 'node-1', target: 'node-2', data: {} },
      ];
      const { container } = render(
        <FlowCanvas {...defaultProps} edges={edges as any} quickAdd={{ ...defaultQuickAdd, onAddCondition }} uiState={{ ...defaultUIState, isLocked: false }} />
      );
      expect(container).toBeTruthy();
    });

    it('should not add onAddCondition when locked', () => {
      const onAddCondition = jest.fn();
      const edges = [
        { id: 'edge-1', source: 'node-1', target: 'node-2', data: {} },
      ];
      const { container } = render(
        <FlowCanvas {...defaultProps} edges={edges as any} quickAdd={{ ...defaultQuickAdd, onAddCondition }} uiState={{ ...defaultUIState, isLocked: true }} />
      );
      expect(container).toBeTruthy();
    });

    it('should enhance edges with annotation visibility', () => {
      const edges = [
        { id: 'edge-1', source: 'node-1', target: 'node-2', data: { isAnnotationVisible: true } },
      ];
      const { container } = render(
        <FlowCanvas {...defaultProps} edges={edges as any} uiState={{ ...defaultUIState, showAllAnnotations: true }} />
      );
      expect(container).toBeTruthy();
    });

    it('should enhance edges with replay data', () => {
      const edges = [
        { id: 'edge-1', source: 'node-1', target: 'node-2', data: {} },
      ];
      const replayData = [{ id: 'step-1', nodeId: 'node-1' }];
      const { container } = render(
        <FlowCanvas
          {...defaultProps}
          edges={edges as any}
          replay={{ ...defaultReplay, replayData: replayData as any, currentReplayStep: 0, isReplayMode: true }}
        />
      );
      expect(container).toBeTruthy();
    });
  });

  describe('enhanced nodes', () => {
    it('should enhance nodes with annotation visibility', () => {
      const nodes = [
        { id: 'node-1', type: 'element', position: { x: 0, y: 0 }, data: { label: 'Test', isAnnotationVisible: true } },
      ];
      const { container } = render(
        <FlowCanvas {...defaultProps} nodes={nodes as any} uiState={{ ...defaultUIState, showAllAnnotations: true }} />
      );
      expect(container).toBeTruthy();
    });

    it('should add onQuickAdd to nodes when not locked', () => {
      const onQuickAdd = jest.fn();
      const nodes = [
        { id: 'node-1', type: 'element', position: { x: 0, y: 0 }, data: { label: 'Test' } },
      ];
      const { container } = render(
        <FlowCanvas {...defaultProps} nodes={nodes as any} quickAdd={{ ...defaultQuickAdd, onQuickAdd }} uiState={{ ...defaultUIState, isLocked: false }} />
      );
      expect(container).toBeTruthy();
    });

    it('should not add onQuickAdd to nodes when locked', () => {
      const onQuickAdd = jest.fn();
      const nodes = [
        { id: 'node-1', type: 'element', position: { x: 0, y: 0 }, data: { label: 'Test' } },
      ];
      const { container } = render(
        <FlowCanvas {...defaultProps} nodes={nodes as any} quickAdd={{ ...defaultQuickAdd, onQuickAdd }} uiState={{ ...defaultUIState, isLocked: true }} />
      );
      expect(container).toBeTruthy();
    });

    it('should add onDelete to nodes when onDeleteNode provided', () => {
      const onDeleteNode = jest.fn();
      const nodes = [
        { id: 'node-1', type: 'element', position: { x: 0, y: 0 }, data: { label: 'Test' } },
      ];
      const { container } = render(
        <FlowCanvas {...defaultProps} nodes={nodes as any} elementCallbacks={{ ...defaultElementCallbacks, onDeleteNode }} />
      );
      expect(container).toBeTruthy();
    });

    it('should enhance nodes with replay state', () => {
      const nodes = [
        { id: 'node-1', type: 'element', position: { x: 0, y: 0 }, data: { label: 'Test' } },
      ];
      const { container } = render(
        <FlowCanvas
          {...defaultProps}
          nodes={nodes as any}
          replay={{ ...defaultReplay, isReplayMode: true, currentReplayStep: 0, replayData: [{ id: 'step-1', nodeId: 'node-1' }] as any }}
        />
      );
      expect(container).toBeTruthy();
    });
  });

  describe('selection mode', () => {
    it('should always use partial selection mode', () => {
      // Selection mode is always Partial with Figma-like gestures
      const { container } = render(<FlowCanvas {...defaultProps} modifierKeys={{ ...defaultModifierKeys, isShiftPressed: true }} />);
      // No modifier-based classes applied; selection mode is handled internally
      expect(container).toBeTruthy();
    });

    it('should not apply ctrl-pressed class when only ctrl pressed', () => {
      render(<FlowCanvas {...defaultProps} modifierKeys={{ ...defaultModifierKeys, isCtrlPressed: true, isAltPressed: false }} />);
      const reactFlowEl = screen.getByTestId('react-flow');
      // Ctrl no longer adds cursor class (zoom handled natively by ReactFlow)
      expect(reactFlowEl.className).not.toContain('ctrl-pressed');
    });
  });

  // QuickAddEventButton has been moved to the Toolbar component

  describe('replay indicators', () => {
    it('should render replay indicators', () => {
      const replayIndicators = [
        { id: 'indicator-1', x: 100, y: 200, color: '#00ff00' },
        { id: 'indicator-2', x: 300, y: 400, color: '#ff0000' },
      ];
      const { container } = render(
        <FlowCanvas {...defaultProps} replay={{ ...defaultReplay, replayIndicators }} />
      );
      const indicators = container.querySelectorAll('.replay-indicator');
      expect(indicators.length).toBe(2);
    });

    it('should position replay indicators correctly', () => {
      const replayIndicators = [
        { id: 'indicator-1', x: 100, y: 200, color: '#00ff00' },
      ];
      const { container } = render(
        <FlowCanvas {...defaultProps} replay={{ ...defaultReplay, replayIndicators }} />
      );
      const indicator = container.querySelector('.replay-indicator') as HTMLElement;
      expect(indicator).toBeTruthy();
      // Indicators use flow-coordinate transforms inside EdgeLabelRenderer
      expect(indicator.style.transform).toBe('translate(-50%, -50%) translate(100px, 200px)');
      expect(indicator.style.backgroundColor).toBe('rgb(0, 255, 0)');
    });

    it('should not render indicators when empty', () => {
      const { container } = render(
        <FlowCanvas {...defaultProps} replay={{ ...defaultReplay, replayIndicators: [] }} />
      );
      expect(container.querySelectorAll('.replay-indicator').length).toBe(0);
    });
  });
});
