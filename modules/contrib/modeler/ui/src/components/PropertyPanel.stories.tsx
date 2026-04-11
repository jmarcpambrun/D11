import type { Meta, StoryObj } from '@storybook/react';
import { fn } from '@storybook/test';
import PropertyPanel from './PropertyPanel';
import { withStore } from '../../.storybook/decorators';

const sampleNode = {
  id: 'node_1',
  type: 'element',
  position: { x: 200, y: 100 },
  data: {
    label: 'Send Email',
    plugin: 'eca_mail:send_mail',
    locked: false,
    annotation: '',
    isAnnotationVisible: false,
  },
};

const sampleEventNode = {
  id: 'event_1',
  type: 'start',
  position: { x: 0, y: 0 },
  data: {
    label: 'Content Created',
    plugin: 'content_entity:insert',
    locked: false,
    annotation: 'Triggers when a new content entity is created',
    isAnnotationVisible: false,
  },
};

const sampleEdge = {
  id: 'edge_1',
  source: 'node_1',
  target: 'node_2',
  type: 'condition',
  label: 'Is Admin',
  data: {
    condition: 'user_has_role',
    conditionLabel: 'Is Admin',
    locked: false,
    annotation: '',
    isAnnotationVisible: false,
  },
};

const meta: Meta<typeof PropertyPanel> = {
  title: 'Components/PropertyPanel',
  component: PropertyPanel,
  decorators: [
    withStore({
      initialState: {
        nodes: [sampleNode],
        edges: [sampleEdge],
      },
    }),
    (Story: React.ComponentType) => (
      <div style={{ width: 350, height: 600, border: '1px solid #e0e0e0', borderRadius: 8 }}>
        <Story />
      </div>
    ),
  ],
  parameters: {
    layout: 'centered',
  },
  args: {
    node: sampleNode,
    edge: null,
    selectedNodes: [],
    selectedEdges: [],
    onConfigurationChange: fn(),
    onEdgeConfigurationChange: fn(),
    onNodeUpdate: fn(),
    onEdgeUpdate: fn(),
    isLocked: false,
    settings: {},
    isReplayMode: false,
  },
  argTypes: {
    isLocked: {
      control: 'boolean',
      description: 'Whether editing is locked',
    },
    isReplayMode: {
      control: 'boolean',
      description: 'Whether replay mode is active',
    },
  },
};

export default meta;
type Story = StoryObj<typeof PropertyPanel>;

/**
 * Property panel with a selected node
 */
export const WithNode: Story = {};

/**
 * Property panel with a selected edge
 */
export const WithEdge: Story = {
  tags: ['!test'],
  args: {
    node: null,
    edge: sampleEdge,
  },
};

/**
 * Property panel with no selection
 */
export const NoSelection: Story = {
  args: {
    node: null,
    edge: null,
  },
};

/**
 * Property panel in locked mode
 */
export const Locked: Story = {
  tags: ['!test'],
  args: {
    isLocked: true,
  },
};

/**
 * Property panel in replay mode
 */
export const ReplayMode: Story = {
  tags: ['!test'],
  args: {
    isReplayMode: true,
  },
};

/**
 * Multi-selection mode
 */
export const MultiSelection: Story = {
  args: {
    node: null,
    edge: null,
    selectedNodes: [
      sampleNode,
      { id: 'node_2', type: 'element', position: { x: 400, y: 100 }, data: { label: 'Log Message' } },
    ],
    selectedEdges: [sampleEdge],
    onDeleteSelected: fn(),
  },
};

/**
 * Event node with replay URL configured — shows the replay load button in header
 */
export const EventNodeWithReplayUrl: Story = {
  decorators: [
    withStore({
      initialState: {
        nodes: [sampleEventNode],
        edges: [],
      },
    }),
    (Story: React.ComponentType) => (
      <div style={{ width: 350, height: 600, border: '1px solid #e0e0e0', borderRadius: 8 }}>
        <Story />
      </div>
    ),
  ],
  args: {
    node: sampleEventNode,
    edge: null,
    selectedNodes: [],
    selectedEdges: [],
    settings: {
      modeler_api: {
        replay_url: '/api/modeler/replay',
        config_url: '/api/modeler/config',
        token_url: '/api/modeler/token',
      },
      modeler: {
        modelId: 'model_abc123',
      },
    },
    onReplayEntriesLoaded: fn(),
  },
};

/**
 * Event node without replay URL — no replay button shown
 */
export const EventNodeWithoutReplayUrl: Story = {
  decorators: [
    withStore({
      initialState: {
        nodes: [sampleEventNode],
        edges: [],
      },
    }),
    (Story: React.ComponentType) => (
      <div style={{ width: 350, height: 600, border: '1px solid #e0e0e0', borderRadius: 8 }}>
        <Story />
      </div>
    ),
  ],
  args: {
    node: sampleEventNode,
    edge: null,
    selectedNodes: [],
    selectedEdges: [],
    settings: {
      modeler_api: {
        config_url: '/api/modeler/config',
        token_url: '/api/modeler/token',
      },
    },
  },
};

/**
 * Non-event node with replay URL — replay button should NOT appear (only for event nodes)
 */
export const ActionNodeWithReplayUrl: Story = {
  args: {
    settings: {
      modeler_api: {
        replay_url: '/api/modeler/replay',
        config_url: '/api/modeler/config',
        token_url: '/api/modeler/token',
      },
      modeler: {
        modelId: 'model_abc123',
      },
    },
  },
};
