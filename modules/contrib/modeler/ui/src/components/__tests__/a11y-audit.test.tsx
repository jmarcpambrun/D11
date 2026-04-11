/**
 * Comprehensive accessibility audit for all visual components.
 *
 * Uses jest-axe (axe-core) to detect WCAG violations.
 * Run with: npm test -- a11y-audit
 */
import React from 'react';
import { render } from '@testing-library/react';
import { axe, toHaveNoViolations } from 'jest-axe';

expect.extend(toHaveNoViolations);

// ─── Helpers ─────────────────────────────────────────────────────────────────

/** Render into a container and return axe results. */
async function audit(ui: React.ReactElement) {
  const { container } = render(ui);
  return axe(container);
}

// Mock ReactFlow hooks/components used by many components
jest.mock('reactflow', () => ({
  Handle: ({ type, position, id, ...rest }: any) => (
    <div data-testid={`handle-${type}-${id || position}`} {...rest} />
  ),
  Position: { Top: 'top', Bottom: 'bottom', Left: 'left', Right: 'right' },
  EdgeLabelRenderer: ({ children }: any) => <div>{children}</div>,
  getBezierPath: () => ['M0,0 C50,50 100,100 150,150', 75, 75],
  useReactFlow: () => ({
    fitView: jest.fn(),
    setCenter: jest.fn(),
    setViewport: jest.fn(),
    getViewport: () => ({ x: 0, y: 0, zoom: 1 }),
  }),
  ReactFlowProvider: ({ children }: any) => <div>{children}</div>,
}));

// Mock domain stores
jest.mock('../../store/useGraphStore', () => {
  const state = {
    nodes: [],
    edges: [],
  };
  return {
    useGraphStore: (selector?: (s: any) => any) => (selector ? selector(state) : state),
  };
});

jest.mock('../../store/useSelectionStore', () => {
  const state = {
    selectedNode: null,
    selectedEdge: null,
    selectedNodes: [],
    selectedEdges: [],
  };
  return {
    useSelectionStore: (selector?: (s: any) => any) => (selector ? selector(state) : state),
  };
});

jest.mock('../../store/useComponentStore', () => {
  const state = {
    components: [],
    favoriteComponents: {},
  };
  return {
    useComponentStore: (selector?: (s: any) => any) => (selector ? selector(state) : state),
  };
});

jest.mock('../../store/usePanelStore', () => {
  const state = {
    panelWidth: 320,
    panelIsResizing: false,
    propertyPanelCollapsed: false,
    replayPanelWidth: 300,
    replayPanelIsResizing: false,
    replayPanelCollapsed: false,
    setPanelWidth: jest.fn(),
    setPanelResizing: jest.fn(),
    togglePropertyPanelCollapse: jest.fn(),
    setReplayPanelWidth: jest.fn(),
    setReplayPanelResizing: jest.fn(),
    toggleReplayPanelCollapse: jest.fn(),
  };
  return {
    usePanelStore: (selector?: (s: any) => any) => (selector ? selector(state) : state),
  };
});

jest.mock('../../store/useFilterStore', () => {
  const state = {
    visibleStartNodeIds: null,
    setVisibleStartNodeIds: jest.fn(),
  };
  return {
    useFilterStore: (selector?: (s: any) => any) => (selector ? selector(state) : state),
  };
});

jest.mock('../../store/useContextStore', () => {
  const state = {
    selectedContextId: null,
    contexts: [],
    dependencies: [],
  };
  return {
    useContextStore: (selector?: (s: any) => any) => (selector ? selector(state) : state),
  };
});

// Mock createPortal so portal-based components render inline
jest.mock('react-dom', () => ({
  ...jest.requireActual('react-dom'),
  createPortal: (node: React.ReactNode) => node,
}));

// Mock DOMPurify
jest.mock('dompurify', () => ({
  __esModule: true,
  default: { sanitize: (html: string) => html },
}));

// ─── Node Components ─────────────────────────────────────────────────────────

import CustomNode from '../nodes/CustomNode';
import StartNode from '../nodes/StartNode';
import GatewayNode from '../nodes/GatewayNode';
import SubprocessNode from '../nodes/SubprocessNode';

const nodeProps = (data: any) => ({
  id: 'test_node',
  type: 'custom',
  selected: false,
  dragging: false,
  isConnectable: true,
  xPos: 0,
  yPos: 0,
  zIndex: 0,
  data: {
    label: 'Test Node',
    onDelete: jest.fn(),
    onToggleAnnotation: jest.fn(),
    ...data,
  },
});

describe('A11y Audit: Node Components', () => {
  test('CustomNode has no a11y violations', async () => {
    const results = await audit(<CustomNode {...nodeProps({ plugin: 'test:action' })} />);
    expect(results).toHaveNoViolations();
  });

  test('StartNode has no a11y violations', async () => {
    const results = await audit(<StartNode {...nodeProps({ plugin: 'test:event' })} />);
    expect(results).toHaveNoViolations();
  });

  test('GatewayNode has no a11y violations', async () => {
    const results = await audit(<GatewayNode {...nodeProps({})} />);
    expect(results).toHaveNoViolations();
  });

  test('SubprocessNode has no a11y violations', async () => {
    const results = await audit(<SubprocessNode {...nodeProps({ plugin: 'test:subprocess', subflowCount: 3 })} />);
    expect(results).toHaveNoViolations();
  });

  test('CustomNode (selected + read-only) has no a11y violations', async () => {
    const results = await audit(
      <CustomNode {...nodeProps({ plugin: 'test:action', isLocked: true })} selected={true} />
    );
    expect(results).toHaveNoViolations();
  });

  test('CustomNode with visible annotation has no a11y violations', async () => {
    const results = await audit(
      <CustomNode
        {...nodeProps({
          plugin: 'test:action',
          annotation: 'Important note',
          isAnnotationVisible: true,
        })}
      />
    );
    expect(results).toHaveNoViolations();
  });
});

// ─── Edge Components ─────────────────────────────────────────────────────────

import EdgeOrderBadge from '../edges/EdgeOrderBadge';

describe('A11y Audit: Edge Components', () => {
  test('EdgeOrderBadge has no a11y violations', async () => {
    const results = await audit(
      <EdgeOrderBadge
        edgeId="edge_1"
        isLocked={false}
        onReorderEdge={jest.fn()}
        edgeOrderInfo={{ pathX: 100, pathY: 100, order: 1, totalEdges: 3, sourceNodeId: 'node_1' }}
      />
    );
    expect(results).toHaveNoViolations();
  });
});

// ─── Panel Components ────────────────────────────────────────────────────────

import MultiSelectionPanel from '../MultiSelectionPanel';

describe('A11y Audit: Panel Components', () => {
  test('MultiSelectionPanel has no a11y violations', async () => {
    const results = await audit(
      <MultiSelectionPanel
        selectedNodes={[
          { id: 'n1', type: 'element', position: { x: 0, y: 0 }, data: { label: 'Node 1' } },
          { id: 'n2', type: 'gateway', position: { x: 100, y: 0 }, data: { label: 'Node 2' } },
        ]}
        selectedEdges={[
          { id: 'e1', source: 'n1', target: 'n2', type: 'default', data: {} },
        ]}
        isLocked={false}
      />
    );
    expect(results).toHaveNoViolations();
  });
});

// ─── Form / Input Components ─────────────────────────────────────────────────

import ConfigurationForm from '../ConfigurationForm';
import ContentEditableField from '../ContentEditableField';
import ConfirmDialog from '../ConfirmDialog';
import MetadataModal from '../MetadataModal';

describe('A11y Audit: Form & Input Components', () => {
  test('ConfigurationForm with text fields has no a11y violations', async () => {
    const results = await audit(
      <ConfigurationForm
        form={[
          { key: 'name', type: 'textfield', title: 'Name', required: true, default_value: '' },
          { key: 'email', type: 'email', title: 'Email', default_value: '' },
          { key: 'notes', type: 'textarea', title: 'Notes', default_value: '' },
        ]}
        configuration={{}}
        onChange={jest.fn()}
        disabled={false}
      />
    );
    expect(results).toHaveNoViolations();
  });

  test('ConfigurationForm with select/checkbox fields has no a11y violations', async () => {
    const results = await audit(
      <ConfigurationForm
        form={[
          { key: 'format', type: 'select', title: 'Format', options: { html: 'HTML', plain: 'Plain' }, default_value: 'html' },
          { key: 'enabled', type: 'checkbox', title: 'Enabled', default_value: true },
          { key: 'role', type: 'radios', title: 'Role', options: { admin: 'Admin', editor: 'Editor' }, default_value: 'admin' },
          { key: 'tags', type: 'checkboxes', title: 'Tags', options: { a: 'Tag A', b: 'Tag B' }, default_value: [] },
        ]}
        configuration={{}}
        onChange={jest.fn()}
        disabled={false}
      />
    );
    expect(results).toHaveNoViolations();
  });

  test('ConfigurationForm (disabled) has no a11y violations', async () => {
    const results = await audit(
      <ConfigurationForm
        form={[{ key: 'name', type: 'textfield', title: 'Name', default_value: '' }]}
        configuration={{ name: 'Test' }}
        onChange={jest.fn()}
        disabled={true}
      />
    );
    expect(results).toHaveNoViolations();
  });

  test('ContentEditableField has no a11y violations', async () => {
    const results = await audit(
      <ContentEditableField value="Hello" onChange={jest.fn()} placeholder="Type here..." />
    );
    expect(results).toHaveNoViolations();
  });

  test('ContentEditableField (disabled) has no a11y violations', async () => {
    const results = await audit(
      <ContentEditableField value="Read only" onChange={jest.fn()} disabled={true} />
    );
    expect(results).toHaveNoViolations();
  });

  test('ConfirmDialog (open) has no a11y violations', async () => {
    const results = await audit(
      <ConfirmDialog
        isOpen={true}
        onClose={jest.fn()}
        onSaveAndClose={jest.fn()}
        onCloseWithoutSave={jest.fn()}
      />
    );
    expect(results).toHaveNoViolations();
  });

  test('MetadataModal (open) has no a11y violations', async () => {
    const results = await audit(
      <MetadataModal
        isOpen={true}
        onClose={jest.fn()}
        onSave={jest.fn()}
        metadata={{
          label: 'Test Workflow',
          version: '1.0.0',
          executable: true,
          template: false,
          storage: 'default',
          documentation: '',
          tags: ['test'],
          changelog: '',
        }}
      />
    );
    expect(results).toHaveNoViolations();
  });
});

// ─── Replay Components ───────────────────────────────────────────────────────

import { ReplayDataRenderer, StepDataContainer } from '../ReplayDataRenderer';

describe('A11y Audit: Replay Components', () => {
  test('ReplayDataRenderer has no a11y violations', async () => {
    const results = await audit(
      <ReplayDataRenderer
        data={{
          entity: { title: 'Article', status: true },
          user: { name: 'admin', roles: ['administrator'] },
        }}
      />
    );
    expect(results).toHaveNoViolations();
  });

  test('StepDataContainer has no a11y violations', async () => {
    const results = await audit(
      <StepDataContainer stepData={{ entity: { title: 'Test' }, event: { label: 'Insert' } }} />
    );
    expect(results).toHaveNoViolations();
  });
});

// ─── Documentation Components ────────────────────────────────────────────────

import DocumentationButton from '../DocumentationButton';
import DocumentationPopup from '../DocumentationPopup';

describe('A11y Audit: Documentation Components', () => {
  test('DocumentationButton has no a11y violations', async () => {
    const results = await audit(
      <DocumentationButton url="https://example.com/docs" title="Test Docs" />
    );
    expect(results).toHaveNoViolations();
  });

  test('DocumentationButton (no URL) renders nothing', async () => {
    const results = await audit(
      <DocumentationButton url={null} title="No Docs" />
    );
    expect(results).toHaveNoViolations();
  });

  test('DocumentationPopup (open) has no a11y violations', async () => {
    const results = await audit(
      <DocumentationPopup
        url="https://example.com/docs"
        title="Test Documentation"
        isOpen={true}
        onClose={jest.fn()}
      />
    );
    expect(results).toHaveNoViolations();
  });
});

// ─── Quick-Add Components ────────────────────────────────────────────────────

import QuickAddButton from '../QuickAddButton';
import QuickAddConditionButton from '../QuickAddConditionButton';
import QuickAddEventButton from '../QuickAddEventButton';

describe('A11y Audit: Quick-Add Components', () => {
  test('QuickAddButton has no a11y violations', async () => {
    const results = await audit(<QuickAddButton onAddNode={jest.fn()} />);
    expect(results).toHaveNoViolations();
  });

  test('QuickAddConditionButton has no a11y violations', async () => {
    const results = await audit(
      <QuickAddConditionButton edgeId="edge_1" onAddCondition={jest.fn()} />
    );
    expect(results).toHaveNoViolations();
  });

  test('QuickAddEventButton has no a11y violations', async () => {
    const results = await audit(<QuickAddEventButton onAddEvent={jest.fn()} />);
    expect(results).toHaveNoViolations();
  });
});

// ─── Utility Components ──────────────────────────────────────────────────────

import PanelErrorBoundary from '../PanelErrorBoundary';
import SearchBar from '../SearchBar';
import Toolbar from '../Toolbar';

describe('A11y Audit: Utility Components', () => {
  test('PanelErrorBoundary (normal) has no a11y violations', async () => {
    const results = await audit(
      <PanelErrorBoundary panelName="Test Panel">
        <div>Content</div>
      </PanelErrorBoundary>
    );
    expect(results).toHaveNoViolations();
  });

  test('SearchBar has no a11y violations', async () => {
    const results = await audit(
      <SearchBar
        onHighlight={jest.fn()}
        onFocus={jest.fn()}
      />
    );
    expect(results).toHaveNoViolations();
  });

  test('Toolbar has no a11y violations', async () => {
    const results = await audit(
      <Toolbar
        onSave={jest.fn()}
        onOpenMetadata={jest.fn()}
        onToggleMessages={jest.fn()}
        onClearMessages={jest.fn()}
        onClose={jest.fn()}
        onSearchHighlight={jest.fn()}
        onSearchFocus={jest.fn()}
        isLocked={false}
        hasMessages={false}
        messagesVisible={false}
        modelName="Test Model"
        hasUnsavedChanges={false}
        settings={{}}
      />
    );
    expect(results).toHaveNoViolations();
  });
});
