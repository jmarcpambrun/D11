import React from 'react';
import type { Meta, StoryObj } from '@storybook/react';
import { fn } from '@storybook/test';
import { ReactFlowProvider } from 'reactflow';
import DefaultEdge from './DefaultEdge';

/**
 * Wrapper that provides the SVG context edges need to render.
 */
const EdgeSvgWrapper = (Story: React.ComponentType) => (
  <ReactFlowProvider>
    <div style={{ width: 500, height: 300, position: 'relative', background: '#f5f5f5', borderRadius: 8 }}>
      <svg width="500" height="300" style={{ position: 'absolute', top: 0, left: 0 }}>
        <Story />
      </svg>
    </div>
  </ReactFlowProvider>
);

const meta: Meta<typeof DefaultEdge> = {
  title: 'Components/Edges/DefaultEdge',
  component: DefaultEdge,
  decorators: [EdgeSvgWrapper],
  parameters: {
    layout: 'centered',
  },
  args: {
    id: 'edge_1',
    source: 'node_1',
    target: 'node_2',
    sourceX: 50,
    sourceY: 50,
    targetX: 400,
    targetY: 200,
    sourcePosition: 'bottom' as any,
    targetPosition: 'top' as any,
    selected: false,
    data: {
      isLocked: false,
      edgeOrdersVisible: false,
      onEdgeUpdate: fn(),
      onReorderEdge: fn(),
      onAddCondition: fn(),
    },
  },
  argTypes: {
    selected: {
      control: 'boolean',
      description: 'Whether the edge is selected',
    },
    'data.isLocked': {
      control: 'boolean',
      description: 'Whether the canvas is in read-only mode',
    },
    'data.edgeOrdersVisible': {
      control: 'boolean',
      description: 'Whether edge order badges are visible',
    },
  },
};

export default meta;
type Story = StoryObj<typeof DefaultEdge>;

/**
 * Default edge connecting two nodes
 */
export const Default: Story = {};

/**
 * Selected edge
 */
export const Selected: Story = {
  args: {
    selected: true,
  },
};

/**
 * Read-only edge (canvas locked)
 */
export const ReadOnly: Story = {
  args: {
    data: {
      isLocked: true,
      onEdgeUpdate: fn(),
      onReorderEdge: fn(),
      onAddCondition: fn(),
    },
  },
};

/**
 * Edge with order badge visible
 */
export const WithOrderBadge: Story = {
  args: {
    data: {
      edgeOrdersVisible: true,
      edgeOrderInfo: {
        pathX: 225,
        pathY: 125,
        order: 1,
        totalEdges: 3,
        sourceNodeId: 'node_1',
      },
      onEdgeUpdate: fn(),
      onReorderEdge: fn(),
      onAddCondition: fn(),
    },
  },
};

/**
 * Edge with replay highlight
 */
export const ReplayHighlighted: Story = {
  args: {
    data: {
      replayHighlight: '#4caf50',
      onEdgeUpdate: fn(),
      onReorderEdge: fn(),
      onAddCondition: fn(),
    },
  },
};
