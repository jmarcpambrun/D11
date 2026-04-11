import type { Meta, StoryObj } from '@storybook/react';
import { ReplayDataRenderer, StepDataContainer, GlobalTokensContainer, TemplateTokensContainer } from './ReplayDataRenderer';

const meta: Meta<typeof ReplayDataRenderer> = {
  title: 'Components/ReplayDataRenderer',
  component: ReplayDataRenderer,
  parameters: {
    layout: 'centered',
  },
  decorators: [
    (Story: React.ComponentType) => (
      <div style={{ width: 400, padding: 16, border: '1px solid #e0e0e0', borderRadius: 8, fontFamily: 'monospace', fontSize: 13 }}>
        <Story />
      </div>
    ),
  ],
  args: {
    data: {
      entity: {
        type: 'node',
        bundle: 'article',
        title: 'Test Article',
        status: true,
      },
      user: {
        uid: 1,
        name: 'admin',
        roles: ['authenticated', 'administrator'],
      },
    },
    basePath: '',
  },
};

export default meta;
type Story = StoryObj<typeof ReplayDataRenderer>;

/**
 * Default hierarchical data display
 */
export const Default: Story = {};

/**
 * Flat key-value data
 */
export const FlatData: Story = {
  args: {
    data: {
      name: 'John Doe',
      email: 'john@example.com',
      role: 'administrator',
      active: true,
      loginCount: 42,
    },
  },
};

/**
 * Deeply nested data structure
 */
export const DeeplyNested: Story = {
  args: {
    data: {
      level1: {
        level2: {
          level3: {
            level4: {
              level5: {
                value: 'deep value',
              },
            },
          },
        },
      },
    },
  },
};

/**
 * Array data
 */
export const ArrayData: Story = {
  args: {
    data: {
      items: ['First', 'Second', 'Third', 'Fourth', 'Fifth'],
      tags: ['drupal', 'workflow', 'automation'],
      nested: [
        { id: 1, name: 'Item One' },
        { id: 2, name: 'Item Two' },
      ],
    },
  },
};

/**
 * Empty data
 */
export const EmptyData: Story = {
  args: {
    data: {},
  },
};

/**
 * Primitive value
 */
export const PrimitiveValue: Story = {
  args: {
    data: 'Simple string value',
  },
};

/**
 * StepDataContainer with full step data
 */
export const StepContainer: StoryObj<typeof StepDataContainer> = {
  render: () => (
    <StepDataContainer
      stepData={{
        entity: {
          type: 'node',
          bundle: 'article',
          title: 'Published Article',
          uid: 1,
          status: true,
        },
        event: {
          machine_name: 'content:entity_insert',
          label: 'Content: After inserting a new entity',
        },
      }}
    />
  ),
};

/**
 * Token data structure with label/token/value hierarchy
 */
export const TokenDataStructure: Story = {
  args: {
    data: {
      label: 'Entity',
      token: '[entity:title]',
      value: 'Test Article',
      data: {
        title: {
          label: 'Title',
          token: '[entity:title]',
          value: 'Test Article',
        },
        status: {
          label: 'Published',
          token: '[entity:status]',
          value: true,
        },
        author: {
          label: 'Author',
          token: '[entity:author:name]',
          value: 'admin',
          data: {
            name: {
              label: 'Name',
              token: '[entity:author:name]',
              value: 'admin',
            },
          },
        },
      },
    },
    basePath: 'token.',
  },
};

/**
 * GlobalTokensContainer showing global tokens from drupalSettings
 */
export const GlobalTokens: StoryObj<typeof GlobalTokensContainer> = {
  render: () => (
    <GlobalTokensContainer
      globalTokens={{
        'current-user': {
          name: 'Current user',
          token: 'current-user',
          'raw token': '[current-user:account-name]',
          value: 'admin',
          children: {
            'account-name': {
              name: 'Account name',
              token: 'account-name',
              'raw token': '[current-user:account-name]',
              value: 'admin',
            },
            'mail': {
              name: 'Email',
              token: 'mail',
              'raw token': '[current-user:mail]',
              value: 'admin@example.com',
            },
          },
        },
        'site': {
          name: 'Site information',
          token: 'site',
          'raw token': '[site:name]',
          value: 'My Drupal Site',
          children: {
            'name': {
              name: 'Name',
              token: 'name',
              'raw token': '[site:name]',
              value: 'My Drupal Site',
            },
            'slogan': {
              name: 'Slogan',
              token: 'slogan',
              'raw token': '[site:slogan]',
              value: 'Building great things',
            },
          },
        },
      }}
    />
  ),
};

/**
 * TemplateTokensContainer showing template-defined tokens
 */
export const TemplateTokens: StoryObj<typeof TemplateTokensContainer> = {
  render: () => (
    <TemplateTokensContainer
      templateTokens={{
        'template-author': {
          name: 'Author',
          token: 'author',
          'raw token': '[template:author]',
          value: 'Jane Doe',
        },
        'template-config': {
          name: 'Configuration',
          token: 'config',
          'raw token': '[template:config]',
          children: {
            'timeout': {
              name: 'Timeout',
              token: 'config:timeout',
              'raw token': '[template:config:timeout]',
              value: '30',
            },
            'retries': {
              name: 'Retries',
              token: 'config:retries',
              'raw token': '[template:config:retries]',
              value: '3',
            },
          },
        },
        'template-version': {
          name: 'Version',
          token: 'version',
          'raw token': '[template:version]',
          value: '1.0.0',
        },
      }}
    />
  ),
};
