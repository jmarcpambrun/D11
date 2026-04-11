import React from 'react';
import { render, screen, act } from '@testing-library/react';
import Flow from '../Flow';

// Mock ReactFlow
jest.mock('reactflow', () => ({
  useReactFlow: () => ({
    fitView: jest.fn(),
    setCenter: jest.fn(),
    setViewport: jest.fn(),
    getViewport: jest.fn(() => ({ x: 0, y: 0, zoom: 1 })),
  }),
  useStore: (selector: any) => {
    if (typeof selector === 'function') {
      // Provide a minimal ReactFlow state with transform
      return selector({ transform: [0, 0, 1] });
    }
    return 1;
  },
}));

// Mock all domain stores
jest.mock('../../store/useGraphStore', () => ({
  useGraphStore: jest.fn((selector) => {
    const state = {
      nodes: [],
      edges: [],
      setNodes: jest.fn(),
      setEdges: jest.fn(),
    };
    if (typeof selector === 'function') return selector(state);
    return state;
  }),
}));

jest.mock('../../store/useSelectionStore', () => ({
  useSelectionStore: jest.fn((selector) => {
    const state = {
      selectedNode: null,
      setSelectedNode: jest.fn(),
      selectedEdge: null,
      setSelectedEdge: jest.fn(),
      selectedNodes: [],
      setSelectedNodes: jest.fn(),
      selectedEdges: [],
      setSelectedEdges: jest.fn(),
    };
    if (typeof selector === 'function') return selector(state);
    return state;
  }),
}));

jest.mock('../../store/useModelStore', () => ({
  useModelStore: jest.fn((selector) => {
    const state = {
      modelData: { metadata: { label: 'Test Model' } },
    };
    if (typeof selector === 'function') return selector(state);
    return state;
  }),
}));

jest.mock('../../store/useViewportStore', () => ({
  useViewportStore: jest.fn((selector) => {
    const state = {
      viewportTarget: null,
      setViewportTarget: jest.fn(),
      reactFlowReady: false,
      setReactFlowReady: jest.fn(),
    };
    if (typeof selector === 'function') return selector(state);
    return state;
  }),
}));

jest.mock('../../store/usePanelStore', () => ({
  usePanelStore: jest.fn((selector) => {
    const state = {
      replayPanelCollapsed: false,
      toggleReplayPanelCollapse: jest.fn(),
      setReplayPanelCollapsed: jest.fn(),
      setPropertyPanelCollapsed: jest.fn(),
    };
    if (typeof selector === 'function') return selector(state);
    return state;
  }),
}));

jest.mock('../../store/useFilterStore', () => ({
  useFilterStore: jest.fn((selector) => {
    const state = {
      visibleStartNodeIds: null,
      setVisibleStartNodeIds: jest.fn(),
    };
    if (typeof selector === 'function') return selector(state);
    return state;
  }),
}));

jest.mock('../../store/useContextStore', () => ({
  useContextStore: jest.fn((selector) => {
    const state = {
      contexts: [],
      selectedContextId: null,
      setSelectedContextId: jest.fn(),
    };
    if (typeof selector === 'function') return selector(state);
    return state;
  }),
}));

jest.mock('../../store/useComponentStore', () => ({
  useComponentStore: jest.fn((selector) => {
    const state = {
      components: [],
    };
    if (typeof selector === 'function') return selector(state);
    return state;
  }),
}));

jest.mock('../../hooks/useSimpleReplaySync', () => ({
  useSimpleReplaySync: jest.fn(() => ({
    isSyncing: false,
    handleCanvasNodeClick: jest.fn(),
    handleCanvasEdgeClick: jest.fn(),
    handleReplayStepSelect: jest.fn(),
  })),
}));

jest.mock('../../hooks/useKeyboardShortcuts', () => ({
  useKeyboardShortcuts: jest.fn(),
}));

jest.mock('../../hooks/useClipboard', () => ({
  useClipboard: jest.fn(() => ({
    handleCopy: jest.fn(),
    handlePaste: jest.fn(),
    canCopy: false,
    canPaste: false,
  })),
}));

jest.mock('../../hooks/useReplayCoordination', () => ({
  useReplayCoordination: jest.fn(() => ({
    autoSyncToReplay: jest.fn(),
    toggleReplayMode: jest.fn(),
    hasReplayData: false,
  })),
}));

jest.mock('../../hooks/useViewportEffects', () => ({
  useViewportEffects: jest.fn(),
}));

jest.mock('../../hooks/useDragAndDrop', () => ({
  useDragAndDrop: jest.fn(() => ({
    onDrop: jest.fn(),
    onDragOver: jest.fn(),
    isDraggingCondition: false,
    hoveredDropEdge: null,
  })),
}));

jest.mock('../../hooks/useFlowEventHandlers', () => ({
  useFlowEventHandlers: jest.fn(() => ({
    onNodesChange: jest.fn(),
    onEdgesChange: jest.fn(),
    onSelectionChange: jest.fn(),
    onNodeClick: jest.fn(),
    onEdgeClick: jest.fn(),
    onDeleteNode: jest.fn(),
    handleDeleteSelected: jest.fn(),
    onConnect: jest.fn(),
    onPaneClick: jest.fn(),
  })),
}));

jest.mock('../../hooks/useReplayIndicators', () => ({
  useReplayIndicators: jest.fn(() => ({ replayIndicators: [] })),
}));

jest.mock('../../hooks/useModalState', () => ({
  useModalState: jest.fn(() => ({
    showMetadataModal: false,
    showConfirmDialog: false,
    confirmDialogTitle: '',
    confirmDialogMessage: '',
    confirmDialogType: 'info',
    confirmDialogLoading: false,
    onMetadataSubmit: jest.fn(),
    showConfirmationDialog: jest.fn(),
    handleConfirmDialog: jest.fn(),
    handleCancelDialog: jest.fn(),
    handleCloseWithoutSave: jest.fn(),
    openMetadataModal: jest.fn(),
    closeMetadataModal: jest.fn(),
  })),
}));

jest.mock('../../hooks/useSearch', () => ({
  useSearch: jest.fn(() => ({
    searchTerm: '',
    highlightedSearchResult: null,
    onSearchHighlight: jest.fn(),
    onSearchFocus: jest.fn(),
    clearSearch: jest.fn(),
  })),
}));

jest.mock('../../hooks/useModelDataLoader', () => ({
  useModelDataLoader: jest.fn(() => ({ replayData: [] })),
}));

jest.mock('../../hooks/useConfiguration', () => ({
  useConfiguration: jest.fn(() => ({
    onConfigurationChange: jest.fn(),
    onEdgeConfigurationChange: jest.fn(),
    onNodeUpdate: jest.fn(),
    onEdgeUpdate: jest.fn(),
    handleAutoLayout: jest.fn(),
  })),
}));

jest.mock('../../hooks/useCloseHandler', () => ({
  useCloseHandler: jest.fn(() => ({
    handleClose: jest.fn(),
    handleSaveComplete: jest.fn(),
    saveButtonRef: { current: null },
  })),
}));

jest.mock('../../hooks/useTestRunner', () => ({
  useTestRunner: jest.fn(() => ({
    isTestRunning: false,
    isTestInitiating: false,
    testError: null,
    startTest: jest.fn(),
    cancelTest: jest.fn(),
    notifySaveComplete: jest.fn(),
  })),
}));

jest.mock('../../hooks/useMessagesContainer', () => ({
  useMessagesContainer: jest.fn(() => ({
    messagesContainerRef: { current: null },
    messagesVisible: false,
    hasMessages: false,
    handleToggleMessages: jest.fn(),
    handleClearMessages: jest.fn(),
  })),
}));

jest.mock('../../hooks/useQuickAdd', () => ({
  useQuickAdd: jest.fn(() => ({
    addSuccessorNode: jest.fn(),
  })),
}));

jest.mock('../../hooks/useEdgeStyling', () => ({
  useEdgeStyling: jest.fn(({ edges }) => edges),
}));

jest.mock('../../hooks/useNodeEdgeActions', () => ({
  useNodeEdgeActions: jest.fn(() => ({
    handleAddCondition: jest.fn(),
    handleAddEvent: jest.fn(),
  })),
}));

jest.mock('../../hooks/useSelectionSync', () => ({
  useSelectionSync: jest.fn(),
}));

jest.mock('../../utils/modelUtils', () => ({
  exportModelData: jest.fn(() => ({})),
  getFitViewport: jest.fn(() => ({ x: 0, y: 0, zoom: 1 })),
}));

jest.mock('../../hooks/useStatusAnnouncer', () => ({
  useStatusAnnouncer: jest.fn(() => ({
    message: '',
    announce: jest.fn(),
  })),
}));

jest.mock('../../hooks/useExport', () => ({
  useExport: jest.fn(() => ({
    canExport: false,
    availableFormats: [],
    hasReplayData: false,
    executeExport: jest.fn(),
    getRequiredModules: jest.fn(() => []),
  })),
}));

jest.mock('../../hooks/useViewMode', () => ({
  useViewMode: jest.fn(() => ({
    viewMode: 'fullscreen',
    toggleViewMode: jest.fn(),
    startDrag: jest.fn(),
    startResize: jest.fn(),
    isDragging: false,
    isResizing: false,
  })),
}));

jest.mock('../../hooks/useHistory', () => ({
  useHistory: jest.fn(() => ({
    saveHistory: jest.fn(),
    undo: jest.fn(),
    redo: jest.fn(),
    canUndo: jest.fn(() => false),
    canRedo: jest.fn(() => false),
  })),
}));

jest.mock('../../hooks/usePluginPanels', () => ({
  usePluginPanels: jest.fn(() => []),
  usePluginWidgets: jest.fn(() => []),
}));

jest.mock('../../plugins/pluginApi', () => ({
  createPluginApi: jest.fn(() => ({})),
  setApiReadOnly: jest.fn(),
  setMutationHooks: jest.fn(),
  clearMutationHooks: jest.fn(),
}));

jest.mock('../../plugins/pluginRegistry', () => ({
  markReady: jest.fn(),
  markUnready: jest.fn(),
}));

// Note: ../../utils/permissions is NOT mocked — the real hasPermission()
// reads from settings.modeler_api.permissions so template-edit-blocked
// tests work correctly.

// Capture props from mocked child components
let capturedToolbarProps: any = {};
let capturedModalsProps: any = {};
let capturedFlowCanvasProps: any = {};
let capturedReplayPanelProps: any = {};
let capturedPropertyPanelProps: any = {};
let capturedCanvasToolbarProps: any = {};

jest.mock('../FlowCanvas', () => (props: any) => { capturedFlowCanvasProps = props; return <div data-testid="flow-canvas" />; });
jest.mock('../PropertyPanel', () => (props: any) => { capturedPropertyPanelProps = props; return <div data-testid="property-panel" />; });
jest.mock('../ReplayPanel', () => (props: any) => { capturedReplayPanelProps = props; return <div data-testid="replay-panel" />; });
jest.mock('../Modals', () => (props: any) => { capturedModalsProps = props; return <div data-testid="modals" />; });
jest.mock('../Toolbar', () => (props: any) => { capturedToolbarProps = props; return <div data-testid="toolbar" data-model-name={props.modelName} />; });
jest.mock('../CanvasToolbar', () => (props: any) => { capturedCanvasToolbarProps = props; return <div data-testid="canvas-toolbar" />; });
jest.mock('../PanelErrorBoundary', () => ({ children }: any) => <>{children}</>);
jest.mock('../PluginPanelContainer', () => (_props: any) => null);

describe('Flow', () => {
  const defaultProps = {
    settings: {
      modeler: { modelId: 'test-1' },
      modeler_api: {},
    },
    drupal: {
      ajax: jest.fn(),
    },
  };

  beforeEach(() => {
    jest.clearAllMocks();
    capturedToolbarProps = {};
    capturedModalsProps = {};
    capturedFlowCanvasProps = {};
    capturedReplayPanelProps = {};
    capturedPropertyPanelProps = {};
    capturedCanvasToolbarProps = {};
  });

  describe('rendering', () => {
    it('should render the workflow modeler container', () => {
      const { container } = render(<Flow {...defaultProps} />);
      expect(container.querySelector('.workflow-modeler')).toBeTruthy();
    });

    it('should render toolbar', () => {
      render(<Flow {...defaultProps} />);
      expect(screen.getByTestId('toolbar')).toBeTruthy();
    });

    it('should pass model name to toolbar', () => {
      render(<Flow {...defaultProps} />);
      expect(screen.getByTestId('toolbar').getAttribute('data-model-name')).toBe('Test Model');
    });

    it('should render canvas', () => {
      render(<Flow {...defaultProps} />);
      expect(screen.getByTestId('flow-canvas')).toBeTruthy();
    });

    it('should render property panel', () => {
      render(<Flow {...defaultProps} />);
      expect(screen.getByTestId('property-panel')).toBeTruthy();
    });

    it('should render modals', () => {
      render(<Flow {...defaultProps} />);
      expect(screen.getByTestId('modals')).toBeTruthy();
    });

    it('should render content area', () => {
      const { container } = render(<Flow {...defaultProps} />);
      expect(container.querySelector('.workflow-modeler-content')).toBeTruthy();
    });

    it('should render messages container', () => {
      const { container } = render(<Flow {...defaultProps} />);
      expect(container.querySelector('.workflow-messages-container')).toBeTruthy();
    });
  });

  describe('toolbar callbacks', () => {
    it('should call exportModelData when onSave is invoked', () => {
      const { exportModelData } = require('../../utils/modelUtils');
      render(<Flow {...defaultProps} />);
      capturedToolbarProps.onSave();
      expect(exportModelData).toHaveBeenCalled();
    });

    it('should call handleSaveComplete when onSaveComplete is invoked', () => {
      const { useCloseHandler } = require('../../hooks/useCloseHandler');
      const mockHandleSaveComplete = jest.fn();
      useCloseHandler.mockReturnValue({
        handleClose: jest.fn(),
        handleSaveComplete: mockHandleSaveComplete,
        saveButtonRef: { current: null },
      });
      render(<Flow {...defaultProps} />);
      capturedToolbarProps.onSaveComplete();
      expect(mockHandleSaveComplete).toHaveBeenCalled();
    });

    it('should pass isLocked=false when not in read-only mode', () => {
      render(<Flow {...defaultProps} />);
      // isLocked is now derived from isReadOnly (no user toggle)
      expect(capturedToolbarProps.isLocked).toBe(false);
    });

    it('should show Untitled Workflow when modelData has no label', () => {
      const { useModelStore } = require('../../store/useModelStore');
      useModelStore.mockImplementation((selector: any) => {
        const state = {
          modelData: { metadata: {} },
        };
        return typeof selector === 'function' ? selector(state) : state;
      });
      render(<Flow {...defaultProps} />);
      expect(capturedToolbarProps.modelName).toBe('Untitled Workflow');
    });
  });

  describe('new model initial state', () => {
    it('should open metadata modal for new models', () => {
      jest.useFakeTimers();
      const { useModalState } = require('../../hooks/useModalState');
      const mockOpenMetadata = jest.fn();
      useModalState.mockReturnValue({
        showMetadataModal: false, showConfirmDialog: false,
        confirmDialogTitle: '', confirmDialogMessage: '',
        confirmDialogType: 'info', confirmDialogLoading: false,
        onMetadataSubmit: jest.fn(), showConfirmationDialog: jest.fn(),
        handleConfirmDialog: jest.fn(), handleCancelDialog: jest.fn(),
        handleCloseWithoutSave: jest.fn(),
        openMetadataModal: mockOpenMetadata, closeMetadataModal: jest.fn(),
      });

      render(<Flow settings={{ modeler: {}, modeler_api: { isNew: true } }} drupal={{ ajax: jest.fn() }} />);

      act(() => { jest.advanceTimersByTime(150); });
      expect(mockOpenMetadata).toHaveBeenCalled();
      jest.useRealTimers();
    });
  });

  describe('handleCloseMetadataModal', () => {
    it('should close metadata modal and open event popup for new models', () => {
      jest.useFakeTimers();
      const { useModalState } = require('../../hooks/useModalState');
      const mockCloseMetadata = jest.fn();
      useModalState.mockReturnValue({
        showMetadataModal: true, showConfirmDialog: false,
        confirmDialogTitle: '', confirmDialogMessage: '',
        confirmDialogType: 'info', confirmDialogLoading: false,
        onMetadataSubmit: jest.fn(), showConfirmationDialog: jest.fn(),
        handleConfirmDialog: jest.fn(), handleCancelDialog: jest.fn(),
        handleCloseWithoutSave: jest.fn(),
        openMetadataModal: jest.fn(), closeMetadataModal: mockCloseMetadata,
      });

      render(<Flow settings={{ modeler: {}, modeler_api: { isNew: true } }} drupal={{ ajax: jest.fn() }} />);
      
      act(() => { capturedModalsProps.onCloseMetadataModal(); });
      expect(mockCloseMetadata).toHaveBeenCalled();

      // Should open event popup after 150ms for new models
      act(() => { jest.advanceTimersByTime(200); });
      expect(capturedToolbarProps.isEventPopupOpen).toBe(true);
      jest.useRealTimers();
    });

    it('should close metadata modal without opening event popup for existing models', () => {
      jest.useFakeTimers();
      const { useModalState } = require('../../hooks/useModalState');
      const mockCloseMetadata = jest.fn();
      useModalState.mockReturnValue({
        showMetadataModal: true, showConfirmDialog: false,
        confirmDialogTitle: '', confirmDialogMessage: '',
        confirmDialogType: 'info', confirmDialogLoading: false,
        onMetadataSubmit: jest.fn(), showConfirmationDialog: jest.fn(),
        handleConfirmDialog: jest.fn(), handleCancelDialog: jest.fn(),
        handleCloseWithoutSave: jest.fn(),
        openMetadataModal: jest.fn(), closeMetadataModal: mockCloseMetadata,
      });

      render(<Flow settings={{ modeler: {}, modeler_api: { isNew: false } }} drupal={{ ajax: jest.fn() }} />);
      
      act(() => { capturedModalsProps.onCloseMetadataModal(); });
      expect(mockCloseMetadata).toHaveBeenCalled();

      act(() => { jest.advanceTimersByTime(200); });
      expect(capturedToolbarProps.isEventPopupOpen).toBe(false);
      jest.useRealTimers();
    });
  });

  describe('handleReplayStepSelect', () => {
    it('should activate replay mode when selecting a positive step', () => {
      const { useReplayCoordination } = require('../../hooks/useReplayCoordination');
      useReplayCoordination.mockReturnValue({
        autoSyncToReplay: jest.fn(),
        toggleReplayMode: jest.fn(),
        hasReplayData: true,
      });
      const { useModelDataLoader } = require('../../hooks/useModelDataLoader');
      useModelDataLoader.mockReturnValue({ replayData: [{ type: 'action', id: 'n1' }] });

      render(<Flow {...defaultProps} />);
      
      // The ReplayPanel receives onSelectStep which wraps handleReplayStepSelect
      act(() => { capturedReplayPanelProps.onSelectStep(0); });
      // Should have set replay mode to true
      expect(capturedReplayPanelProps.isReplayMode).toBe(true);
    });
  });

  describe('useViewportEffects onViewportChange callback', () => {
    it('should clear viewport target when viewport changes', () => {
      const { useViewportEffects } = require('../../hooks/useViewportEffects');
      let capturedOnViewportChange: any;
      useViewportEffects.mockImplementation((opts: any) => {
        capturedOnViewportChange = opts.onViewportChange;
      });

      const { useViewportStore } = require('../../store/useViewportStore');
      const mockSetViewportTarget = jest.fn();
      useViewportStore.mockImplementation((selector: any) => {
        const state = {
          viewportTarget: { nodeId: 'n1' }, setViewportTarget: mockSetViewportTarget,
          reactFlowReady: false, setReactFlowReady: jest.fn(),
        };
        return typeof selector === 'function' ? selector(state) : state;
      });

      render(<Flow {...defaultProps} />);
      capturedOnViewportChange();
      expect(mockSetViewportTarget).toHaveBeenCalledWith(null);
    });
  });

  describe('useKeyboardShortcuts callbacks', () => {
    it('should pass onToggleSearch callback that focuses search input', () => {
      const { useKeyboardShortcuts } = require('../../hooks/useKeyboardShortcuts');
      let capturedCallbacks: any;
      useKeyboardShortcuts.mockImplementation((opts: any) => {
        capturedCallbacks = opts.callbacks;
      });

      render(<Flow {...defaultProps} />);

      // Create a mock search input in the DOM
      const searchInput = document.createElement('input');
      searchInput.className = 'search-input';
      document.body.appendChild(searchInput);
      const focusSpy = jest.spyOn(searchInput, 'focus');

      capturedCallbacks.onToggleSearch();
      expect(focusSpy).toHaveBeenCalled();

      document.body.removeChild(searchInput);
    });

    it('should pass onEscape callback that clears search and blurs input', () => {
      const { useKeyboardShortcuts } = require('../../hooks/useKeyboardShortcuts');
      let capturedCallbacks: any;
      useKeyboardShortcuts.mockImplementation((opts: any) => {
        capturedCallbacks = opts.callbacks;
      });
      const { useSearch } = require('../../hooks/useSearch');
      const mockClearSearch = jest.fn();
      useSearch.mockReturnValue({
        searchTerm: 'test', highlightedSearchResult: null,
        onSearchHighlight: jest.fn(), onSearchFocus: jest.fn(),
        clearSearch: mockClearSearch,
      });

      render(<Flow {...defaultProps} />);

      // Create a mock search input and make it the active element
      const searchInput = document.createElement('input');
      searchInput.className = 'search-input';
      document.body.appendChild(searchInput);
      searchInput.focus();
      const blurSpy = jest.spyOn(searchInput, 'blur');

      capturedCallbacks.onEscape();
      expect(mockClearSearch).toHaveBeenCalled();
      expect(blurSpy).toHaveBeenCalled();

      document.body.removeChild(searchInput);
    });
  });

  describe('replay panel rendering', () => {
    it('should render replay panel when replay data exists', () => {
      const { useReplayCoordination } = require('../../hooks/useReplayCoordination');
      useReplayCoordination.mockReturnValue({
        autoSyncToReplay: jest.fn(),
        toggleReplayMode: jest.fn(),
        hasReplayData: true,
      });
      const { useModelDataLoader } = require('../../hooks/useModelDataLoader');
      useModelDataLoader.mockReturnValue({ replayData: [{ type: 'action', id: 'n1' }] });

      render(<Flow {...defaultProps} />);
      expect(screen.getByTestId('replay-panel')).toBeTruthy();
    });

    it('should not render replay panel when no replay data', () => {
      const { useReplayCoordination } = require('../../hooks/useReplayCoordination');
      useReplayCoordination.mockReturnValue({
        autoSyncToReplay: jest.fn(),
        toggleReplayMode: jest.fn(),
        hasReplayData: false,
      });
      const { useModelDataLoader } = require('../../hooks/useModelDataLoader');
      useModelDataLoader.mockReturnValue({ replayData: [] });
      render(<Flow {...defaultProps} />);
      expect(screen.queryByTestId('replay-panel')).toBeNull();
    });

    it('should pass step data when current step is valid', () => {
      const { useReplayCoordination } = require('../../hooks/useReplayCoordination');
      useReplayCoordination.mockReturnValue({
        autoSyncToReplay: jest.fn(),
        toggleReplayMode: jest.fn(),
        hasReplayData: true,
      });
      const replaySteps = [
        { type: 'action', id: 'n1', data: { key: 'val' }, successorId: 'n2', object: 'Obj' },
      ];
      const { useModelDataLoader } = require('../../hooks/useModelDataLoader');
      useModelDataLoader.mockReturnValue({ replayData: replaySteps });

      // Make useSimpleReplaySync actually set the step when handleReplayStepSelect is called
      const { useSimpleReplaySync } = require('../../hooks/useSimpleReplaySync');
      let _mockSetCurrentStep: ((step: number) => void) | null = null;
      useSimpleReplaySync.mockImplementation((opts: any) => {
        _mockSetCurrentStep = opts.setCurrentStep;
        return {
          isSyncing: false,
          handleCanvasNodeClick: jest.fn(),
          handleCanvasEdgeClick: jest.fn(),
          handleReplayStepSelect: (step: number) => {
            opts.setCurrentStep(step);
          },
        };
      });

      render(<Flow {...defaultProps} />);

      // Select step 0
      act(() => { capturedReplayPanelProps.onSelectStep(0); });

      // Now step data and info should be passed
      expect(capturedReplayPanelProps.stepData).toEqual({ key: 'val' });
      expect(capturedReplayPanelProps.stepInfo).toEqual(expect.objectContaining({ type: 'action', id: 'n1' }));
    });

    it('should pass null step data when no step selected', () => {
      const { useReplayCoordination } = require('../../hooks/useReplayCoordination');
      useReplayCoordination.mockReturnValue({
        autoSyncToReplay: jest.fn(),
        toggleReplayMode: jest.fn(),
        hasReplayData: true,
      });
      const { useModelDataLoader } = require('../../hooks/useModelDataLoader');
      useModelDataLoader.mockReturnValue({ replayData: [{ type: 'action', id: 'n1' }] });

      render(<Flow {...defaultProps} />);
      // currentReplayStep defaults to -1
      expect(capturedReplayPanelProps.stepData).toBeNull();
      expect(capturedReplayPanelProps.stepInfo).toBeNull();
    });
  });

  describe('property panel props', () => {
    it('should pass selectedNodes mapped from store IDs', () => {
      const { useGraphStore } = require('../../store/useGraphStore');
      useGraphStore.mockImplementation((selector: any) => {
        const state = {
          nodes: [{ id: 'n1', data: {}, position: { x: 0, y: 0 } }, { id: 'n2', data: {}, position: { x: 1, y: 1 } }],
          edges: [], setNodes: jest.fn(), setEdges: jest.fn(),
        };
        return typeof selector === 'function' ? selector(state) : state;
      });
      const { useSelectionStore } = require('../../store/useSelectionStore');
      useSelectionStore.mockImplementation((selector: any) => {
        const state = {
          selectedNode: null, setSelectedNode: jest.fn(),
          selectedEdge: null, setSelectedEdge: jest.fn(),
          selectedNodes: ['n1', 'n2'], selectedEdges: [],
          setSelectedNodes: jest.fn(), setSelectedEdges: jest.fn(),
        };
        return typeof selector === 'function' ? selector(state) : state;
      });

      render(<Flow {...defaultProps} />);
      expect(capturedPropertyPanelProps.selectedNodes).toHaveLength(2);
      expect(capturedPropertyPanelProps.selectedNodes[0].id).toBe('n1');
    });

    it('should filter out selectedNodes with missing IDs', () => {
      const { useGraphStore } = require('../../store/useGraphStore');
      useGraphStore.mockImplementation((selector: any) => {
        const state = {
          nodes: [{ id: 'n1', data: {}, position: { x: 0, y: 0 } }],
          edges: [], setNodes: jest.fn(), setEdges: jest.fn(),
        };
        return typeof selector === 'function' ? selector(state) : state;
      });
      const { useSelectionStore } = require('../../store/useSelectionStore');
      useSelectionStore.mockImplementation((selector: any) => {
        const state = {
          selectedNode: null, setSelectedNode: jest.fn(),
          selectedEdge: null, setSelectedEdge: jest.fn(),
          selectedNodes: ['n1', 'n_nonexistent'], selectedEdges: ['e_missing'],
          setSelectedNodes: jest.fn(), setSelectedEdges: jest.fn(),
        };
        return typeof selector === 'function' ? selector(state) : state;
      });

      render(<Flow {...defaultProps} />);
      expect(capturedPropertyPanelProps.selectedNodes).toHaveLength(1);
      expect(capturedPropertyPanelProps.selectedEdges).toHaveLength(0);
    });
  });

  describe('condition drag styling', () => {
    it('should add condition-drag-active class when dragging condition', () => {
      const { useDragAndDrop } = require('../../hooks/useDragAndDrop');
      useDragAndDrop.mockReturnValue({
        onDrop: jest.fn(), onDragOver: jest.fn(),
        isDraggingCondition: true, hoveredDropEdge: null,
      });

      const { container } = render(<Flow {...defaultProps} />);
      expect(container.querySelector('.condition-drag-active')).toBeTruthy();
    });
  });

  describe('messages container visibility', () => {
    it('should apply visible class when messages are visible', () => {
      const { useMessagesContainer } = require('../../hooks/useMessagesContainer');
      useMessagesContainer.mockReturnValue({
        messagesContainerRef: { current: null },
        messagesVisible: true,
        hasMessages: true,
        handleToggleMessages: jest.fn(),
        handleClearMessages: jest.fn(),
      });

      const { container } = render(<Flow {...defaultProps} />);
      expect(container.querySelector('.workflow-messages-container.visible')).toBeTruthy();
    });

    it('should apply hidden class when messages are not visible', () => {
      const { useMessagesContainer } = require('../../hooks/useMessagesContainer');
      useMessagesContainer.mockReturnValue({
        messagesContainerRef: { current: null },
        messagesVisible: false,
        hasMessages: false,
        handleToggleMessages: jest.fn(),
        handleClearMessages: jest.fn(),
      });
      const { container } = render(<Flow {...defaultProps} />);
      expect(container.querySelector('.workflow-messages-container.hidden')).toBeTruthy();
    });

    it('should have role="log" and aria-live attributes for screen readers', () => {
      const { container } = render(<Flow {...defaultProps} />);
      const messagesContainer = container.querySelector('.workflow-messages-container');
      expect(messagesContainer).toBeTruthy();
      expect(messagesContainer?.getAttribute('role')).toBe('log');
      expect(messagesContainer?.getAttribute('aria-label')).toBe('Workflow messages');
      expect(messagesContainer?.getAttribute('aria-live')).toBe('polite');
      expect(messagesContainer?.getAttribute('aria-relevant')).toBe('additions removals');
    });
  });

  describe('placeholder handlers', () => {
    it('should pass no-op handlers for onConnectStart, onConnectEnd, etc.', () => {
      render(<Flow {...defaultProps} />);
      // These are useCallback(() => {}, []) - now grouped in eventHandlers
      const eh = capturedFlowCanvasProps.eventHandlers;
      expect(typeof eh.onConnectStart).toBe('function');
      expect(typeof eh.onConnectEnd).toBe('function');
      expect(typeof eh.onDragEnter).toBe('function');
      expect(typeof eh.onDragLeave).toBe('function');
      expect(typeof eh.onInit).toBe('function');
      // Calling them should not throw
      eh.onConnectStart();
      eh.onConnectEnd();
      eh.onDragEnter();
      eh.onDragLeave();
      eh.onInit();
    });
  });

  describe('read-only mode', () => {
    const readOnlySettings = {
      modeler: { modelId: 'test-1' },
      modeler_api: { readOnly: true },
    };
    const readOnlyProps = {
      settings: readOnlySettings,
      drupal: { ajax: jest.fn() },
    };

    it('should pass isLocked=true to toolbar when readOnly is set', () => {
      render(<Flow {...readOnlyProps} />);
      expect(capturedToolbarProps.isLocked).toBe(true);
    });

    it('should pass isReadOnly=true to toolbar when readOnly is set', () => {
      render(<Flow {...readOnlyProps} />);
      expect(capturedToolbarProps.isReadOnly).toBe(true);
    });

    it('should not pass isReadOnly to toolbar when readOnly is not set', () => {
      render(<Flow {...defaultProps} />);
      expect(capturedToolbarProps.isReadOnly).toBe(false);
    });

    it('should pass isLocked=true to canvas when readOnly is set', () => {
      render(<Flow {...readOnlyProps} />);
      expect(capturedFlowCanvasProps.uiState.isLocked).toBe(true);
    });

    it('should pass isLocked=true to property panel when readOnly is set', () => {
      render(<Flow {...readOnlyProps} />);
      expect(capturedPropertyPanelProps.isLocked).toBe(true);
    });

    it('should pass empty contexts to canvas toolbar when readOnly is set', () => {
      render(
        <Flow
          settings={{
            modeler: { modelId: 'test-1' },
            modeler_api: {
              readOnly: true,
              contexts: [{ id: 'ctx_1', topic: 'Content', model_owner: 'test_owner', components: {} }],
            },
          }}
          drupal={{ ajax: jest.fn() }}
        />
      );
      expect(capturedCanvasToolbarProps.contexts).toEqual([]);
    });

    it('should pass canEditMetadata=false to modals when readOnly is set', () => {
      render(<Flow {...readOnlyProps} />);
      expect(capturedModalsProps.canEditMetadata).toBe(false);
    });

    it('should pass canCreateTemplate=false to modals when readOnly is set', () => {
      render(<Flow {...readOnlyProps} />);
      expect(capturedModalsProps.canCreateTemplate).toBe(false);
    });

    it('should disable keyboard shortcuts for delete/copy/paste when readOnly is set', () => {
      const { useKeyboardShortcuts } = require('../../hooks/useKeyboardShortcuts');
      let capturedCapabilities: any;
      useKeyboardShortcuts.mockImplementation((opts: any) => {
        capturedCapabilities = opts.capabilities;
      });

      render(<Flow {...readOnlyProps} />);
      expect(capturedCapabilities.canDelete).toBe(false);
      expect(capturedCapabilities.canCopy).toBe(false);
      expect(capturedCapabilities.canPaste).toBe(false);
    });

    it('should always have isLocked=true in read-only mode (no user toggle)', () => {
      render(<Flow {...readOnlyProps} />);
      // isLocked is derived directly from isReadOnly — no user toggle exists
      expect(capturedToolbarProps.isLocked).toBe(true);
    });
  });

  describe('template edit blocked → read-only fallback', () => {
    // When the model is an existing template and the user lacks the
    // "edit template" permission, the modeler falls back to read-only mode.
    const blockedSettings = {
      modeler: { modelId: 'template-1' },
      modeler_api: {
        metadata: { template: true },
        permissions: { 'edit template': false },
      },
    };
    const blockedProps = {
      settings: blockedSettings,
      drupal: { ajax: jest.fn() },
    };

    it('should enter read-only mode for existing template when edit template is denied', () => {
      render(<Flow {...blockedProps} />);
      expect(capturedToolbarProps.isReadOnly).toBe(true);
      expect(capturedToolbarProps.isLocked).toBe(true);
    });

    it('should NOT enter read-only mode for new template models', () => {
      render(
        <Flow
          settings={{
            modeler: { modelId: 'new-template' },
            modeler_api: {
              isNew: true,
              metadata: { template: true },
              permissions: { 'edit template': false },
            },
          }}
          drupal={{ ajax: jest.fn() }}
        />
      );
      expect(capturedToolbarProps.isReadOnly).toBe(false);
    });

    it('should NOT enter read-only mode when edit template permission is granted', () => {
      render(
        <Flow
          settings={{
            modeler: { modelId: 'template-ok' },
            modeler_api: {
              metadata: { template: true },
              permissions: { 'edit template': true },
            },
          }}
          drupal={{ ajax: jest.fn() }}
        />
      );
      expect(capturedToolbarProps.isReadOnly).toBe(false);
    });

    it('should NOT enter read-only mode for non-template models', () => {
      render(
        <Flow
          settings={{
            modeler: { modelId: 'non-template' },
            modeler_api: {
              permissions: { 'edit template': false },
            },
          }}
          drupal={{ ajax: jest.fn() }}
        />
      );
      expect(capturedToolbarProps.isReadOnly).toBe(false);
    });

    it('should pass canEditMetadata=false to modals when template edit is blocked', () => {
      render(<Flow {...blockedProps} />);
      expect(capturedModalsProps.canEditMetadata).toBe(false);
    });

    it('should pass canCreateTemplate=false to modals when template edit is blocked', () => {
      render(<Flow {...blockedProps} />);
      expect(capturedModalsProps.canCreateTemplate).toBe(false);
    });

    it('should pass empty contexts when template edit is blocked', () => {
      render(
        <Flow
          settings={{
            modeler: { modelId: 'template-ctx' },
            modeler_api: {
              metadata: { template: true },
              permissions: { 'edit template': false },
              contexts: [{ id: 'ctx_1', topic: 'Content', model_owner: 'test_owner', components: {} }],
            },
          }}
          drupal={{ ajax: jest.fn() }}
        />
      );
      expect(capturedCanvasToolbarProps.contexts).toEqual([]);
    });
  });
});
