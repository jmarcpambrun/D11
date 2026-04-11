import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import PropertyPanel from '../PropertyPanel';

jest.mock('react-icons/fi', () => ({
  FiChevronDown: () => <span data-testid="fi-chevron-down" />,
  FiChevronRight: () => <span data-testid="fi-chevron-right" />,
  FiChevronLeft: () => <span data-testid="fi-chevron-left" />,
  FiActivity: () => <span data-testid="fi-activity" />,
  FiZap: () => <span data-testid="fi-zap" />,
  FiGitBranch: () => <span data-testid="fi-git-branch" />,
  FiBox: () => <span data-testid="fi-box" />,
  FiInfo: () => <span data-testid="fi-info" />,
  FiRefreshCw: (props: any) => <span data-testid="fi-refresh" className={props.className} />,
}));

jest.mock('../DocumentationButton', () => () => <span data-testid="doc-btn" />);
jest.mock('../InfoPopup', () => {
  const MockInfoPopup = (props: any) => <div data-testid="info-popup">{props.items?.map((item: any, i: number) => <span key={i}>{item.label}: {item.value}</span>)}</div>;
  MockInfoPopup.displayName = 'MockInfoPopup';
  return MockInfoPopup;
});
jest.mock('../MultiSelectionPanel', () => (_props: any) => <div data-testid="multi-selection-panel" />);
jest.mock('../NodePropertiesPanel', () => (props: any) => <div data-testid="node-properties-panel" data-node-id={props.node?.id} />);
jest.mock('../EdgePropertiesPanel', () => (props: any) => <div data-testid="edge-properties-panel" data-edge-id={props.edge?.id} />);

const mockTogglePropertyPanelCollapse = jest.fn();
let mockPanelState: any = {
  panelWidth: 300,
  panelIsResizing: false,
  setPanelWidth: jest.fn(),
  setPanelResizing: jest.fn(),
  propertyPanelCollapsed: false,
  togglePropertyPanelCollapse: mockTogglePropertyPanelCollapse,
};
let mockComponentState: any = {
  components: [],
};

jest.mock('../../store/usePanelStore', () => ({
  usePanelStore: jest.fn((selector: any) => {
    if (typeof selector === 'function') return selector(mockPanelState);
    return mockPanelState;
  }),
}));

jest.mock('../../store/useComponentStore', () => ({
  useComponentStore: jest.fn((selector: any) => {
    if (typeof selector === 'function') return selector(mockComponentState);
    return mockComponentState;
  }),
}));

jest.mock('../../hooks/useConfigurationLoader', () => ({
  useConfigurationLoader: jest.fn(() => ({
    configurationForm: null,
    loading: false,
  })),
}));

const mockLoadReplayData = jest.fn();
jest.mock('../../hooks/useReplayLoader', () => ({
  useReplayLoader: jest.fn(() => ({
    loading: false,
    error: null,
    loadReplayData: mockLoadReplayData,
  })),
}));

jest.mock('../../hooks/usePanelResize', () => ({
  usePanelResize: jest.fn(() => ({
    startResize: jest.fn(),
  })),
}));

const debouncedFieldCallbacks: Array<{ onDebouncedChange: (value: string) => void; disabled?: boolean }> = [];
jest.mock('../../hooks/useDebouncedField', () => ({
  useDebouncedField: jest.fn(({ initialValue, onDebouncedChange, disabled }) => {
    debouncedFieldCallbacks.push({ onDebouncedChange, disabled });
    return {
      value: initialValue || '',
      setValue: jest.fn(),
      onChange: jest.fn(),
      onBlur: jest.fn(),
      flush: jest.fn(),
    };
  }),
}));

describe('PropertyPanel', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    debouncedFieldCallbacks.length = 0;
    mockPanelState = {
      panelWidth: 300,
      panelIsResizing: false,
      setPanelWidth: jest.fn(),
      setPanelResizing: jest.fn(),
      propertyPanelCollapsed: false,
      togglePropertyPanelCollapse: mockTogglePropertyPanelCollapse,
    };
    mockComponentState = {
      components: [],
    };
  });

  describe('empty state', () => {
    it('should show empty message when no node or edge selected', () => {
      render(<PropertyPanel />);
      expect(screen.getByText('Select a component or connection to view its properties')).toBeTruthy();
    });
  });

  describe('single node selected', () => {
    it('should render NodePropertiesPanel', () => {
      const node = { id: 'node-1', type: 'element', data: { label: 'Test', plugin: 'test' }, position: { x: 0, y: 0 } };
      render(<PropertyPanel node={node as any} />);
      expect(screen.getByTestId('node-properties-panel')).toBeTruthy();
    });

    it('should show component type for element nodes', () => {
      const node = { id: 'node-1', type: 'element', data: { label: 'Test', plugin: 'test' }, position: { x: 0, y: 0 } };
      render(<PropertyPanel node={node as any} />);
      expect(screen.getByText('Element')).toBeTruthy();
    });

    it('should show Start type for start nodes', () => {
      const node = { id: 'node-1', type: 'start', data: { label: 'Test', plugin: 'test' }, position: { x: 0, y: 0 } };
      render(<PropertyPanel node={node as any} />);
      expect(screen.getByText('Start')).toBeTruthy();
    });

    it('should show Gateway type for gateway nodes', () => {
      const node = { id: 'node-1', type: 'gateway', data: { label: 'Test', plugin: 'test' }, position: { x: 0, y: 0 } };
      render(<PropertyPanel node={node as any} />);
      expect(screen.getByText('Gateway')).toBeTruthy();
    });
  });

  describe('single edge selected', () => {
    it('should render EdgePropertiesPanel', () => {
      const edge = { id: 'edge-1', source: 'a', target: 'b', data: { condition: 'test' } };
      render(<PropertyPanel edge={edge as any} />);
      expect(screen.getByTestId('edge-properties-panel')).toBeTruthy();
    });

    it('should show Link type', () => {
      const edge = { id: 'edge-1', source: 'a', target: 'b', data: { condition: 'test' } };
      render(<PropertyPanel edge={edge as any} />);
      expect(screen.getByText('Link')).toBeTruthy();
    });
  });

  describe('multi-selection', () => {
    it('should render MultiSelectionPanel when multiple nodes selected', () => {
      const nodes = [
        { id: 'n1', type: 'element', data: { label: 'A' }, position: { x: 0, y: 0 } },
        { id: 'n2', type: 'element', data: { label: 'B' }, position: { x: 0, y: 0 } },
      ];
      render(<PropertyPanel selectedNodes={nodes as any} />);
      expect(screen.getByTestId('multi-selection-panel')).toBeTruthy();
      expect(screen.getByText('Multiple Selection')).toBeTruthy();
    });
  });

  describe('subprocess type', () => {
    it('should show Subprocess type for subprocess nodes', () => {
      const node = { id: 'node-1', type: 'subprocess', data: { label: 'Test', plugin: 'test' }, position: { x: 0, y: 0 } };
      render(<PropertyPanel node={node as any} />);
      expect(screen.getByText('Subprocess')).toBeTruthy();
    });
  });

  describe('loading indicator', () => {
    it('should show loading indicator when configuration is loading', () => {
      const { useConfigurationLoader } = require('../../hooks/useConfigurationLoader');
      useConfigurationLoader.mockReturnValue({ configurationForm: null, loading: true });
      
      const node = { id: 'node-1', type: 'element', data: { label: 'Test', plugin: 'test' }, position: { x: 0, y: 0 } };
      render(<PropertyPanel node={node as any} />);
      expect(screen.getByText('Loading...')).toBeTruthy();
    });
  });

  describe('documentation button', () => {
    it('should show documentation button when component has documentationUrl', () => {
      mockComponentState.components = [{ plugin: 'test_plugin', documentationUrl: 'https://example.com/docs' }];
      const node = { id: 'node-1', type: 'element', data: { label: 'Test', plugin: 'test_plugin' }, position: { x: 0, y: 0 } };
      render(<PropertyPanel node={node as any} />);
      expect(screen.getByTestId('doc-btn')).toBeTruthy();
    });

    it('should show documentation button from node data documentationUrl', () => {
      const node = { id: 'node-1', type: 'element', data: { label: 'Test', plugin: 'test_plugin', documentationUrl: 'https://example.com' }, position: { x: 0, y: 0 } };
      render(<PropertyPanel node={node as any} />);
      expect(screen.getByTestId('doc-btn')).toBeTruthy();
    });

    it('should not show documentation button when no URL available', () => {
      const node = { id: 'node-1', type: 'element', data: { label: 'Test', plugin: 'no_docs' }, position: { x: 0, y: 0 } };
      render(<PropertyPanel node={node as any} />);
      expect(screen.queryByTestId('doc-btn')).toBeFalsy();
    });
  });

  describe('collapsed state', () => {
    it('should show collapsed label when collapsed', () => {
      mockPanelState.propertyPanelCollapsed = true;
      render(<PropertyPanel />);
      expect(screen.getByText('Properties')).toBeTruthy();
    });

    it('should expand panel when collapsed panel clicked', () => {
      mockPanelState.propertyPanelCollapsed = true;
      render(<PropertyPanel />);
      const panel = document.querySelector('.workflow-property-panel');
      fireEvent.click(panel!);
      expect(mockTogglePropertyPanelCollapse).toHaveBeenCalled();
    });

    it('should not toggle collapse when clicking expanded panel', () => {
      mockPanelState.propertyPanelCollapsed = false;
      render(<PropertyPanel />);
      const panel = document.querySelector('.workflow-property-panel');
      fireEvent.click(panel!);
      expect(mockTogglePropertyPanelCollapse).not.toHaveBeenCalled();
    });

    it('should toggle collapse when collapse widget clicked', () => {
      render(<PropertyPanel />);
      const collapseWidget = document.querySelector('.panel-collapse-widget');
      fireEvent.click(collapseWidget!);
      expect(mockTogglePropertyPanelCollapse).toHaveBeenCalled();
    });
  });

  describe('resizing state', () => {
    it('should apply resizing class when panel is resizing', () => {
      mockPanelState.panelIsResizing = true;
      render(<PropertyPanel />);
      const panel = document.querySelector('.workflow-property-panel');
      expect(panel?.className).toContain('resizing');
    });
  });

  describe('debounced field handlers', () => {
    it('should call onConfigurationChange when node label changes', () => {
      const onConfigurationChange = jest.fn();
      const node = { id: 'node-1', type: 'element', data: { label: 'Test', plugin: 'test' }, position: { x: 0, y: 0 } };
      render(<PropertyPanel node={node as any} onConfigurationChange={onConfigurationChange} />);

      // The first debounced field callback is for node label (order: nodeLabel, nodeAnnotation, edgeLabel, edgeAnnotation)
      const nodeLabelCallback = debouncedFieldCallbacks[0];
      nodeLabelCallback.onDebouncedChange('New Label');

      expect(onConfigurationChange).toHaveBeenCalledWith('node-1', { _componentLabel: 'New Label' });
    });

    it('should not call onConfigurationChange for node label when locked', () => {
      const onConfigurationChange = jest.fn();
      const node = { id: 'node-1', type: 'element', data: { label: 'Test', plugin: 'test' }, position: { x: 0, y: 0 } };
      render(<PropertyPanel node={node as any} onConfigurationChange={onConfigurationChange} isLocked={true} />);

      const nodeLabelCallback = debouncedFieldCallbacks[0];
      nodeLabelCallback.onDebouncedChange('New Label');

      expect(onConfigurationChange).not.toHaveBeenCalled();
    });

    it('should call onNodeUpdate when node annotation changes', () => {
      const onNodeUpdate = jest.fn();
      const node = { id: 'node-1', type: 'element', data: { label: 'Test', plugin: 'test', annotation: '' }, position: { x: 0, y: 0 } };
      render(<PropertyPanel node={node as any} onNodeUpdate={onNodeUpdate} />);

      // The second debounced field callback is for node annotation
      const nodeAnnotationCallback = debouncedFieldCallbacks[1];
      nodeAnnotationCallback.onDebouncedChange('New annotation');

      expect(onNodeUpdate).toHaveBeenCalledWith('node-1', expect.objectContaining({ annotation: 'New annotation' }));
    });

    it('should not call onNodeUpdate for annotation when locked', () => {
      const onNodeUpdate = jest.fn();
      const node = { id: 'node-1', type: 'element', data: { label: 'Test', plugin: 'test' }, position: { x: 0, y: 0 } };
      render(<PropertyPanel node={node as any} onNodeUpdate={onNodeUpdate} isLocked={true} />);

      const nodeAnnotationCallback = debouncedFieldCallbacks[1];
      nodeAnnotationCallback.onDebouncedChange('New annotation');

      expect(onNodeUpdate).not.toHaveBeenCalled();
    });

    it('should call onEdgeConfigurationChange when edge label changes', () => {
      const onEdgeConfigurationChange = jest.fn();
      const edge = { id: 'edge-1', source: 'a', target: 'b', data: { conditionLabel: 'Old' } };
      render(<PropertyPanel edge={edge as any} onEdgeConfigurationChange={onEdgeConfigurationChange} />);

      // Third callback is for edge label
      const edgeLabelCallback = debouncedFieldCallbacks[2];
      edgeLabelCallback.onDebouncedChange('New Condition');

      expect(onEdgeConfigurationChange).toHaveBeenCalledWith('edge-1', { _conditionLabel: 'New Condition' });
    });

    it('should not call onEdgeConfigurationChange for edge label when locked', () => {
      const onEdgeConfigurationChange = jest.fn();
      const edge = { id: 'edge-1', source: 'a', target: 'b', data: { conditionLabel: 'Old' } };
      render(<PropertyPanel edge={edge as any} onEdgeConfigurationChange={onEdgeConfigurationChange} isLocked={true} />);

      const edgeLabelCallback = debouncedFieldCallbacks[2];
      edgeLabelCallback.onDebouncedChange('New Condition');

      expect(onEdgeConfigurationChange).not.toHaveBeenCalled();
    });

    it('should call onEdgeUpdate when edge annotation changes', () => {
      const onEdgeUpdate = jest.fn();
      const edge = { id: 'edge-1', source: 'a', target: 'b', data: { annotation: '' } };
      render(<PropertyPanel edge={edge as any} onEdgeUpdate={onEdgeUpdate} />);

      // Fourth callback is for edge annotation
      const edgeAnnotationCallback = debouncedFieldCallbacks[3];
      edgeAnnotationCallback.onDebouncedChange('Edge note');

      expect(onEdgeUpdate).toHaveBeenCalledWith('edge-1', expect.objectContaining({ annotation: 'Edge note' }));
    });

    it('should not call onEdgeUpdate for annotation when locked', () => {
      const onEdgeUpdate = jest.fn();
      const edge = { id: 'edge-1', source: 'a', target: 'b', data: { annotation: '' } };
      render(<PropertyPanel edge={edge as any} onEdgeUpdate={onEdgeUpdate} isLocked={true} />);

      const edgeAnnotationCallback = debouncedFieldCallbacks[3];
      edgeAnnotationCallback.onDebouncedChange('Edge note');

      expect(onEdgeUpdate).not.toHaveBeenCalled();
    });
  });

  describe('info popup', () => {
    it('should toggle info popup when info button clicked', () => {
      const node = { id: 'node-1', type: 'element', data: { label: 'Test', plugin: 'test' }, position: { x: 0, y: 0 } };
      render(<PropertyPanel node={node as any} />);

      const infoBtn = screen.getByTitle('Show metadata');
      fireEvent.click(infoBtn);

      expect(screen.getByTestId('info-popup')).toBeTruthy();
    });

    it('should close info popup when toggled again', () => {
      const node = { id: 'node-1', type: 'element', data: { label: 'Test', plugin: 'test' }, position: { x: 0, y: 0 } };
      render(<PropertyPanel node={node as any} />);

      const infoBtn = screen.getByTitle('Show metadata');
      fireEvent.click(infoBtn);
      expect(screen.getByTestId('info-popup')).toBeTruthy();

      fireEvent.click(infoBtn);
      expect(screen.queryByTestId('info-popup')).toBeFalsy();
    });

    it('should show edge metadata in info popup', () => {
      const edge = { id: 'edge-1', source: 'a', target: 'b', data: { condition: 'test.condition' } };
      render(<PropertyPanel edge={edge as any} />);

      const infoBtn = screen.getByTitle('Show metadata');
      fireEvent.click(infoBtn);

      expect(screen.getByTestId('info-popup')).toBeTruthy();
    });
  });

  describe('replay button', () => {
    it('should show replay button for start node with replay URL', () => {
      const node = { id: 'node-1', type: 'start', data: { label: 'Event', plugin: 'test' }, position: { x: 0, y: 0 } };
      const settings = { modeler_api: { replay_url: '/api/replay', isNew: false, permissions: ['replay'] } };
      render(<PropertyPanel node={node as any} settings={settings as any} />);

      const replayBtn = screen.getByLabelText('Load replay data');
      expect(replayBtn).toBeTruthy();
    });

    it('should not show replay button for non-start nodes', () => {
      const node = { id: 'node-1', type: 'element', data: { label: 'Action', plugin: 'test' }, position: { x: 0, y: 0 } };
      const settings = { modeler_api: { replay_url: '/api/replay', isNew: false, permissions: ['replay'] } };
      render(<PropertyPanel node={node as any} settings={settings as any} />);

      expect(screen.queryByLabelText('Load replay data')).toBeFalsy();
    });
  });

  describe('multi-selection with edges', () => {
    it('should render MultiSelectionPanel when multiple edges selected', () => {
      const edges = [
        { id: 'e1', source: 'a', target: 'b', data: {} },
        { id: 'e2', source: 'b', target: 'c', data: {} },
      ];
      render(<PropertyPanel selectedEdges={edges as any} />);
      expect(screen.getByTestId('multi-selection-panel')).toBeTruthy();
    });

    it('should render MultiSelectionPanel when nodes and edges both selected', () => {
      const nodes = [
        { id: 'n1', type: 'element', data: { label: 'A' }, position: { x: 0, y: 0 } },
      ];
      const edges = [
        { id: 'e1', source: 'a', target: 'b', data: {} },
      ];
      render(<PropertyPanel selectedNodes={nodes as any} selectedEdges={edges as any} />);
      expect(screen.getByTestId('multi-selection-panel')).toBeTruthy();
    });
  });
});
