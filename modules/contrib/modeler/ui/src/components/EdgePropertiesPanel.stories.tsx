import type { Meta, StoryObj } from '@storybook/react';
import { fn } from '@storybook/test';
import EdgePropertiesPanel from './EdgePropertiesPanel';

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
    annotation: 'Checks administrator role.',
    isAnnotationVisible: false,
  },
};

const sampleConfigForm = [
  { key: 'role', type: 'select', title: 'Role', description: 'The role to check for', required: true, options: { administrator: 'Administrator', editor: 'Editor', authenticated: 'Authenticated' }, default_value: 'administrator' },
  { key: 'negate', type: 'checkbox', title: 'Negate', description: 'Negate the condition result', default_value: false },
];

const mockDebouncedField = {
  value: 'Is Admin',
  onChange: fn(),
  flush: fn(),
};

const mockAnnotationField = {
  value: 'Checks administrator role.',
  onChange: fn(),
  flush: fn(),
};

const meta: Meta<typeof EdgePropertiesPanel> = {
  title: 'Components/EdgePropertiesPanel',
  component: EdgePropertiesPanel,
  parameters: {
    layout: 'centered',
  },
  decorators: [
    (Story: React.ComponentType) => (
      <div style={{ width: 320, padding: 16, border: '1px solid #e0e0e0', borderRadius: 8 }}>
        <Story />
      </div>
    ),
  ],
  args: {
    edge: sampleEdge,
    configurationForm: sampleConfigForm,
    onEdgeConfigurationChange: fn(),
    onEdgeUpdate: fn(),
    isLocked: false,
    edgeLabelField: mockDebouncedField as any,
    edgeAnnotationField: mockAnnotationField as any,
  },
  argTypes: {
    isLocked: {
      control: 'boolean',
      description: 'Whether the panel fields are locked',
    },
  },
};

export default meta;
type Story = StoryObj<typeof EdgePropertiesPanel>;

/**
 * Default edge properties panel with condition configuration
 */
export const Default: Story = {};

/**
 * Locked panel (fields disabled)
 */
export const Locked: Story = {
  args: {
    isLocked: true,
  },
};

/**
 * Panel without configuration form
 */
export const NoConfigForm: Story = {
  args: {
    configurationForm: null,
  },
};

/**
 * Default edge (no condition)
 */
export const DefaultEdge: Story = {
  args: {
    edge: {
      id: 'edge_2',
      source: 'node_1',
      target: 'node_2',
      type: 'default',
      data: { locked: false },
    },
    configurationForm: null,
    edgeLabelField: {
      value: '',
      onChange: fn(),
      flush: fn(),
    } as any,
    edgeAnnotationField: {
      value: '',
      onChange: fn(),
      flush: fn(),
    } as any,
  },
};
