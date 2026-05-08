import React from 'react';
import type { Meta, StoryObj } from '@storybook/react';
import { fn } from '@storybook/test';
import { ReactFlowProvider } from 'reactflow';
import ConditionEdge from './ConditionEdge';
import { withStore } from '../../../.storybook/decorators';

const EdgeSvgWrapper = (Story: React.ComponentType) => (
  <ReactFlowProvider>
    <div style={{ width: 500, height: 300, position: 'relative', background: '#f5f5f5', borderRadius: 8 }}>
      <svg width="500" height="300" style={{ position: 'absolute', top: 0, left: 0 }}>
        <Story />
      </svg>
    </div>
  </ReactFlowProvider>
);

// Mock components for store — needed so QuickAddEdgeButton renders (not null)
const mockEdgeComponents = [
  { plugin: 'condition:entity_is_new', label: 'Entity is New', type: 'link', componentType: 5 },
  { plugin: 'condition:user_role', label: 'User Has Role', type: 'link', componentType: 5 },
  { plugin: 'action:save_entity', label: 'Save Entity', type: 'element', componentType: 4 },
  { plugin: 'action:publish', label: 'Publish Content', type: 'element', componentType: 4 },
  { plugin: 'gateway:split', label: 'Split Flow', type: 'gateway', componentType: 6 },
];

const meta: Meta<typeof ConditionEdge> = {
  title: 'Components/Edges/ConditionEdge',
  component: ConditionEdge,
  decorators: [
    EdgeSvgWrapper,
    withStore({
      initialState: {
        nodes: [],
        edges: [],
        components: mockEdgeComponents,
      },
    }),
  ],
  parameters: {
    layout: 'centered',
  },
  args: {
    id: 'condition_edge_1',
    source: 'node_1',
    target: 'node_2',
    sourceX: 50,
    sourceY: 50,
    targetX: 400,
    targetY: 200,
    sourcePosition: 'bottom' as any,
    targetPosition: 'top' as any,
    selected: false,
    label: 'Is Admin',
    data: {
      condition: 'user_has_role',
      isLocked: false,
      edgeOrdersVisible: false,
      annotation: '',
      onDeleteCondition: fn(),
      onEdgeUpdate: fn(),
      onReorderEdge: fn(),
    },
  },
  argTypes: {
    selected: {
      control: 'boolean',
      description: 'Whether the edge is selected',
    },
    label: {
      control: 'text',
      description: 'Condition label displayed on the edge',
    },
    'data.condition': {
      control: 'text',
      description: 'Condition plugin identifier',
    },
    'data.isLocked': {
      control: 'boolean',
      description: 'Whether the canvas is in read-only mode',
    },
    'data.annotation': {
      control: 'text',
      description: 'Annotation text',
    },
  
  },
};

export default meta;
type Story = StoryObj<typeof ConditionEdge>;

/**
 * Default condition edge with label
 */
export const Default: Story = {};

/**
 * Selected condition edge
 */
export const Selected: Story = {
  args: {
    selected: true,
  },
};

/**
 * Read-only condition edge (canvas locked)
 */
export const ReadOnly: Story = {
  args: {
    data: {
      condition: 'user_has_role',
      isLocked: true,
      onDeleteCondition: fn(),
      onEdgeUpdate: fn(),
      onReorderEdge: fn(),
    },
  },
};

/**
 * Condition edge with visible annotation
 */
export const WithAnnotation: Story = {
  args: {
    data: {
      condition: 'content_is_published',
      annotation: 'Only proceeds if the content entity is in a published state.',
      onDeleteCondition: fn(),
      onEdgeUpdate: fn(),
      onReorderEdge: fn(),
    },
  },
};

/**
 * Condition edge with replay highlight
 */
export const ReplayHighlighted: Story = {
  args: {
    data: {
      condition: 'user_has_role',
      replayHighlight: '#4caf50',
      onDeleteCondition: fn(),
      onEdgeUpdate: fn(),
      onReorderEdge: fn(),
    },
  },
};

/**
 * Condition edge with edge order badge
 */
export const WithOrderBadge: Story = {
  args: {
    data: {
      condition: 'user_has_role',
      edgeOrdersVisible: true,
      edgeOrderInfo: {
        pathX: 225,
        pathY: 125,
        order: 2,
        totalEdges: 4,
        sourceNodeId: 'node_1',
      },
      onDeleteCondition: fn(),
      onEdgeUpdate: fn(),
      onReorderEdge: fn(),
    },
  },
};

/**
 * Condition edge with quick-add buttons before and after the condition
 */
export const WithQuickAddButtons: Story = {
  args: {
    data: {
      condition: 'user_has_role',
      onDeleteCondition: fn(),
      onEdgeUpdate: fn(),
      onReorderEdge: fn(),
      onInsertBeforeCondition: fn(),
      onInsertAfterCondition: fn(),
    },
  },
};
