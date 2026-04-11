/**
 * Tests for ReplayDataRenderer component
 */

import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { ReplayDataRenderer, StepDataContainer, GlobalTokensContainer, TemplateTokensContainer } from '../ReplayDataRenderer';

// Mock react-icons
jest.mock('react-icons/fi', () => ({
  FiChevronDown: () => <span data-testid="fi-chevron-down" />,
  FiChevronRight: () => <span data-testid="fi-chevron-right" />,
  FiMoreVertical: () => <span data-testid="fi-more-vertical" />,
}));

// Mock the Zustand store
const mockSetTokenDragging = jest.fn();
jest.mock('../../store/useFilterStore', () => ({
  useFilterStore: jest.fn((selector: any) => {
    const state = {
      setTokenDragging: mockSetTokenDragging,
    };
    if (typeof selector === 'function') return selector(state);
    return state;
  }),
}));

describe('ReplayDataRenderer', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('primitive values', () => {
    it('should render null value', () => {
      render(<ReplayDataRenderer data={null} />);
      expect(screen.getByText('null')).toBeInTheDocument();
    });

    it('should render undefined value', () => {
      render(<ReplayDataRenderer data={undefined} />);
      expect(screen.getByText('undefined')).toBeInTheDocument();
    });

    it('should render boolean true', () => {
      render(<ReplayDataRenderer data={true} />);
      expect(screen.getByText('true')).toBeInTheDocument();
    });

    it('should render boolean false', () => {
      render(<ReplayDataRenderer data={false} />);
      expect(screen.getByText('false')).toBeInTheDocument();
    });

    it('should render number', () => {
      render(<ReplayDataRenderer data={42} />);
      expect(screen.getByText('42')).toBeInTheDocument();
    });

    it('should render string without quotes', () => {
      render(<ReplayDataRenderer data="hello" />);
      expect(screen.getByText('hello')).toBeInTheDocument();
    });

    it('should display long strings without truncation', () => {
      const longString = 'a'.repeat(150);
      render(<ReplayDataRenderer data={longString} />);
      expect(screen.getByText('a'.repeat(150))).toBeInTheDocument();
    });
  });

  describe('token data structure', () => {
    it('should render token data with label', () => {
      const tokenData = { label: 'Test Token' };
      render(<ReplayDataRenderer data={tokenData} />);
      expect(screen.getByText('Test Token')).toBeInTheDocument();
    });

    it('should render token data with label and value', () => {
      const tokenData = { label: 'My Value', value: 'test value' };
      render(<ReplayDataRenderer data={tokenData} />);
      expect(screen.getByText('My Value')).toBeInTheDocument();
      expect(screen.getByText('test value')).toBeInTheDocument();
    });

    it('should make draggable tokens when token property exists', () => {
      const tokenData = { label: 'Draggable', token: '[token:value]' };
      render(<ReplayDataRenderer data={tokenData} />);
      const label = screen.getByText('Draggable');
      expect(label).toHaveClass('draggable');
      expect(label).toHaveAttribute('draggable', 'true');
    });

    it('should not make non-token items draggable', () => {
      const tokenData = { label: 'Not Draggable' };
      render(<ReplayDataRenderer data={tokenData} />);
      const label = screen.getByText('Not Draggable');
      expect(label).not.toHaveClass('draggable');
    });

    it('should render grip icon for draggable tokens', () => {
      const tokenData = { label: 'Draggable', token: '[token:value]' };
      render(<ReplayDataRenderer data={tokenData} />);
      expect(screen.getByTestId('fi-more-vertical')).toBeInTheDocument();
    });

    it('should not render grip icon for non-draggable items', () => {
      const tokenData = { label: 'Not Draggable' };
      render(<ReplayDataRenderer data={tokenData} />);
      expect(screen.queryByTestId('fi-more-vertical')).toBeNull();
    });

    it('should call setTokenDragging(true) on drag start', () => {
      const tokenData = { label: 'Token', token: '[token:val]' };
      render(<ReplayDataRenderer data={tokenData} />);
      const label = screen.getByText('Token');

      fireEvent.dragStart(label, {
        dataTransfer: { setData: jest.fn() },
      });

      expect(mockSetTokenDragging).toHaveBeenCalledWith(true);
    });

    it('should call setTokenDragging(false) on drag end', () => {
      const tokenData = { label: 'Token', token: '[token:val]' };
      render(<ReplayDataRenderer data={tokenData} />);
      const label = screen.getByText('Token');

      fireEvent.dragEnd(label);

      expect(mockSetTokenDragging).toHaveBeenCalledWith(false);
    });

    it('should render grip icon for draggable tokens with value', () => {
      const tokenData = { label: 'Token With Value', token: '[token:val]', value: 'some value' };
      render(<ReplayDataRenderer data={tokenData} />);
      expect(screen.getByTestId('fi-more-vertical')).toBeInTheDocument();
    });
  });

  describe('collapsible sections', () => {
    it('should render collapsed array initially', () => {
      const data = [1, 2, 3];
      render(<ReplayDataRenderer data={data} />);
      expect(screen.getByText(/Array \(3\)/)).toBeInTheDocument();
      expect(screen.queryByText('[0]:')).not.toBeInTheDocument();
    });

    it('should expand array on click', () => {
      const data = [1, 2, 3];
      render(<ReplayDataRenderer data={data} />);
      
      fireEvent.click(screen.getByText(/Array \(3\)/));
      
      expect(screen.getByText('[0]:')).toBeInTheDocument();
      expect(screen.getByText('[1]:')).toBeInTheDocument();
      expect(screen.getByText('[2]:')).toBeInTheDocument();
    });

    it('should render collapsed object initially', () => {
      const data = { a: 1, b: 2 };
      render(<ReplayDataRenderer data={data} />);
      expect(screen.getByText(/Object \(2\)/)).toBeInTheDocument();
      expect(screen.queryByText('a:')).not.toBeInTheDocument();
    });

    it('should expand object on click', () => {
      const data = { a: 1, b: 2 };
      render(<ReplayDataRenderer data={data} />);
      
      fireEvent.click(screen.getByText(/Object \(2\)/));
      
      expect(screen.getByText('a:')).toBeInTheDocument();
      expect(screen.getByText('b:')).toBeInTheDocument();
    });

    it('should collapse expanded section on second click', () => {
      const data = [1, 2, 3];
      render(<ReplayDataRenderer data={data} />);
      
      // Expand
      fireEvent.click(screen.getByText(/Array \(3\)/));
      expect(screen.getByText('[0]:')).toBeInTheDocument();
      
      // Collapse
      fireEvent.click(screen.getByText(/Array \(3\)/));
      expect(screen.queryByText('[0]:')).not.toBeInTheDocument();
    });
  });

  describe('nested token data', () => {
    it('should render token data with nested data property', () => {
      const tokenData = {
        label: 'Parent',
        data: {
          child1: { label: 'Child 1', value: 'value1' },
          child2: { label: 'Child 2', value: 'value2' },
        }
      };
      render(<ReplayDataRenderer data={tokenData} />);
      
      // Parent should be visible
      expect(screen.getByText('Parent')).toBeInTheDocument();
      expect(screen.getByText('(2)')).toBeInTheDocument(); // Item count
      
      // Children should be hidden initially
      expect(screen.queryByText('Child 1')).not.toBeInTheDocument();
    });

    it('should expand nested token data on click', () => {
      const tokenData = {
        label: 'Parent',
        data: {
          child1: { label: 'Child 1', value: 'value1' },
        }
      };
      render(<ReplayDataRenderer data={tokenData} />);
      
      fireEvent.click(screen.getByText('Parent'));
      
      expect(screen.getByText('Child 1')).toBeInTheDocument();
      expect(screen.getByText('value1')).toBeInTheDocument();
    });
  });

  describe('empty collections', () => {
    it('should render empty array as []', () => {
      render(<ReplayDataRenderer data={[]} />);
      expect(screen.getByText('[]')).toBeInTheDocument();
    });

    it('should render empty object as {}', () => {
      render(<ReplayDataRenderer data={{}} />);
      expect(screen.getByText('{}')).toBeInTheDocument();
    });
  });

  describe('large collections', () => {
    it('should display all items in large arrays without truncation', () => {
      const data = Array.from({ length: 15 }, (_, i) => i);
      render(<ReplayDataRenderer data={data} />);
      
      fireEvent.click(screen.getByText(/Array \(15\)/));
      
      // All 15 items should be visible, no truncation message
      expect(screen.getByText('[14]:')).toBeInTheDocument();
      expect(screen.queryByText(/\.\.\.and .* more/)).toBeNull();
    });

    it('should display all entries in large objects without truncation', () => {
      const data: Record<string, number> = {};
      for (let i = 0; i < 15; i++) {
        data[`key${i}`] = i;
      }
      render(<ReplayDataRenderer data={data} />);
      
      fireEvent.click(screen.getByText(/Object \(15\)/));
      
      // All 15 keys should be visible, no truncation message
      expect(screen.getByText('key14:')).toBeInTheDocument();
      expect(screen.queryByText(/\.\.\.and .* more/)).toBeNull();
    });
  });
});

describe('StepDataContainer', () => {
  it('should render step data entries', () => {
    const stepData = {
      token1: { label: 'Token 1', value: 'value1' },
      token2: { label: 'Token 2', value: 'value2' },
    };
    render(<StepDataContainer stepData={stepData} />);
    
    expect(screen.getByText('Token 1')).toBeInTheDocument();
    expect(screen.getByText('Token 2')).toBeInTheDocument();
  });

  it('should add label from key when not present', () => {
    const stepData = {
      myKey: { value: 'test value' },
    };
    render(<StepDataContainer stepData={stepData} />);
    
    expect(screen.getByText('myKey')).toBeInTheDocument();
    expect(screen.getByText('test value')).toBeInTheDocument();
  });

  it('should handle primitive values', () => {
    const stepData = {
      primitiveValue: 42,
    };
    render(<StepDataContainer stepData={stepData} />);
    
    expect(screen.getByText('primitiveValue')).toBeInTheDocument();
    expect(screen.getByText('42')).toBeInTheDocument();
  });
});

describe('GlobalTokensContainer', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('should render global token entries with their names as labels', () => {
    const globalTokens = {
      '[current-date:custom:?]': {
        name: 'Custom format',
        description: 'A custom date format.',
        dynamic: true,
        'raw token': '[current-date:custom:?]',
        token: 'custom:?',
        value: '2026-02-13',
      },
    };
    render(<GlobalTokensContainer globalTokens={globalTokens} />);
    expect(screen.getByText('Custom format')).toBeInTheDocument();
  });

  it('should make global tokens draggable with raw token value', () => {
    const globalTokens = {
      '[site:name]': {
        name: 'Site name',
        'raw token': '[site:name]',
        token: 'name',
        value: 'My Site',
      },
    };
    render(<GlobalTokensContainer globalTokens={globalTokens} />);
    const label = screen.getByText('Site name');
    expect(label).toHaveClass('draggable');
    expect(label).toHaveAttribute('draggable', 'true');
  });

  it('should render token value', () => {
    const globalTokens = {
      '[site:name]': {
        name: 'Site name',
        'raw token': '[site:name]',
        token: 'name',
        value: 'My Drupal Site',
      },
    };
    render(<GlobalTokensContainer globalTokens={globalTokens} />);
    expect(screen.getByText('My Drupal Site')).toBeInTheDocument();
  });

  it('should render children as nested collapsible group', () => {
    const globalTokens = {
      '[current-page:content-language]': {
        name: 'Content language',
        description: 'The active content language.',
        type: 'language',
        'raw token': '[current-page:content-language]',
        token: 'content-language',
        value: 'en',
        children: {
          '[current-page:content-language:direction]': {
            name: 'Direction',
            description: 'Whether the language is written left-to-right or right-to-left.',
            'raw token': '[current-page:content-language:direction]',
            token: 'content-language:direction',
            parent: '[current-page:content-language]',
            value: 'ltr',
          },
          '[current-page:content-language:domain]': {
            name: 'Domain',
            description: 'The domain name to use for the language.',
            'raw token': '[current-page:content-language:domain]',
            token: 'content-language:domain',
            parent: '[current-page:content-language]',
            value: 'example.com',
          },
        },
      },
    };
    render(<GlobalTokensContainer globalTokens={globalTokens} />);

    // Parent should be visible with count
    expect(screen.getByText('Content language')).toBeInTheDocument();
    expect(screen.getByText('(2)')).toBeInTheDocument();

    // Children should be hidden initially
    expect(screen.queryByText('Direction')).not.toBeInTheDocument();
    expect(screen.queryByText('Domain')).not.toBeInTheDocument();
  });

  it('should expand children when parent is clicked', () => {
    const globalTokens = {
      '[current-page:content-language]': {
        name: 'Content language',
        'raw token': '[current-page:content-language]',
        token: 'content-language',
        value: 'en',
        children: {
          '[current-page:content-language:direction]': {
            name: 'Direction',
            'raw token': '[current-page:content-language:direction]',
            token: 'content-language:direction',
            value: 'ltr',
          },
        },
      },
    };
    render(<GlobalTokensContainer globalTokens={globalTokens} />);

    fireEvent.click(screen.getByText('Content language'));

    expect(screen.getByText('Direction')).toBeInTheDocument();
    expect(screen.getByText('ltr')).toBeInTheDocument();
  });

  it('should render multiple top-level global tokens', () => {
    const globalTokens = {
      '[site:name]': {
        name: 'Site name',
        'raw token': '[site:name]',
        token: 'name',
        value: 'My Site',
      },
      '[site:url]': {
        name: 'URL',
        'raw token': '[site:url]',
        token: 'url',
        value: 'https://example.com',
      },
    };
    render(<GlobalTokensContainer globalTokens={globalTokens} />);

    expect(screen.getByText('Site name')).toBeInTheDocument();
    expect(screen.getByText('URL')).toBeInTheDocument();
  });

  it('should render grip icon for draggable global tokens', () => {
    const globalTokens = {
      '[site:name]': {
        name: 'Site name',
        'raw token': '[site:name]',
        token: 'name',
        value: 'My Site',
      },
    };
    render(<GlobalTokensContainer globalTokens={globalTokens} />);
    expect(screen.getByTestId('fi-more-vertical')).toBeInTheDocument();
  });

  it('should set token dragging state on drag start', () => {
    const globalTokens = {
      '[site:name]': {
        name: 'Site name',
        'raw token': '[site:name]',
        token: 'name',
        value: 'My Site',
      },
    };
    render(<GlobalTokensContainer globalTokens={globalTokens} />);

    fireEvent.dragStart(screen.getByText('Site name'), {
      dataTransfer: { setData: jest.fn() },
    });

    expect(mockSetTokenDragging).toHaveBeenCalledWith(true);
  });

  it('should render token without value (value is optional)', () => {
    const globalTokens = {
      '[site:name]': {
        name: 'Site name',
        'raw token': '[site:name]',
        token: 'name',
      },
    };
    render(<GlobalTokensContainer globalTokens={globalTokens} />);
    expect(screen.getByText('Site name')).toBeInTheDocument();
    expect(screen.getByText('Site name')).toHaveClass('draggable');
    // No value should be rendered
    expect(document.querySelector('.token-value')).toBeNull();
  });

  it('should render parent with children but no value', () => {
    const globalTokens = {
      '[current-page:content-language]': {
        name: 'Content language',
        'raw token': '[current-page:content-language]',
        token: 'content-language',
        children: {
          '[current-page:content-language:direction]': {
            name: 'Direction',
            'raw token': '[current-page:content-language:direction]',
            token: 'content-language:direction',
          },
        },
      },
    };
    render(<GlobalTokensContainer globalTokens={globalTokens} />);

    // Parent should render with child count
    expect(screen.getByText('Content language')).toBeInTheDocument();
    expect(screen.getByText('(1)')).toBeInTheDocument();

    // Expand and verify child renders without value
    fireEvent.click(screen.getByText('Content language'));
    expect(screen.getByText('Direction')).toBeInTheDocument();
    expect(document.querySelectorAll('.token-value').length).toBe(0);
  });
});

describe('TemplateTokensContainer', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('should render template token entries with their names as labels', () => {
    const templateTokens = {
      '[template:author]': {
        name: 'Author',
        'raw token': '[template:author]',
        token: 'author',
        value: 'Jane Doe',
      },
    };
    render(<TemplateTokensContainer templateTokens={templateTokens} />);
    expect(screen.getByText('Author')).toBeInTheDocument();
  });

  it('should make template tokens draggable with raw token value', () => {
    const templateTokens = {
      '[template:version]': {
        name: 'Version',
        'raw token': '[template:version]',
        token: 'version',
        value: '1.0.0',
      },
    };
    render(<TemplateTokensContainer templateTokens={templateTokens} />);
    const label = screen.getByText('Version');
    expect(label).toHaveClass('draggable');
    expect(label).toHaveAttribute('draggable', 'true');
  });

  it('should render token value', () => {
    const templateTokens = {
      '[template:author]': {
        name: 'Author',
        'raw token': '[template:author]',
        token: 'author',
        value: 'Jane Doe',
      },
    };
    render(<TemplateTokensContainer templateTokens={templateTokens} />);
    expect(screen.getByText('Jane Doe')).toBeInTheDocument();
  });

  it('should render children as nested collapsible group', () => {
    const templateTokens = {
      '[template:config]': {
        name: 'Config',
        'raw token': '[template:config]',
        token: 'config',
        children: {
          '[template:config:timeout]': {
            name: 'Timeout',
            'raw token': '[template:config:timeout]',
            token: 'config:timeout',
            parent: '[template:config]',
            value: '30',
          },
        },
      },
    };
    render(<TemplateTokensContainer templateTokens={templateTokens} />);

    // Parent should be visible with count
    expect(screen.getByText('Config')).toBeInTheDocument();
    expect(screen.getByText('(1)')).toBeInTheDocument();

    // Child should be hidden initially
    expect(screen.queryByText('Timeout')).not.toBeInTheDocument();

    // Expand and verify child
    fireEvent.click(screen.getByText('Config'));
    expect(screen.getByText('Timeout')).toBeInTheDocument();
    expect(screen.getByText('30')).toBeInTheDocument();
  });

  it('should render multiple top-level template tokens', () => {
    const templateTokens = {
      '[template:author]': {
        name: 'Author',
        'raw token': '[template:author]',
        token: 'author',
        value: 'Jane Doe',
      },
      '[template:version]': {
        name: 'Version',
        'raw token': '[template:version]',
        token: 'version',
        value: '1.0.0',
      },
    };
    render(<TemplateTokensContainer templateTokens={templateTokens} />);
    expect(screen.getByText('Author')).toBeInTheDocument();
    expect(screen.getByText('Version')).toBeInTheDocument();
  });

  it('should set token dragging state on drag start', () => {
    const templateTokens = {
      '[template:author]': {
        name: 'Author',
        'raw token': '[template:author]',
        token: 'author',
        value: 'Jane Doe',
      },
    };
    render(<TemplateTokensContainer templateTokens={templateTokens} />);

    fireEvent.dragStart(screen.getByText('Author'), {
      dataTransfer: { setData: jest.fn() },
    });

    expect(mockSetTokenDragging).toHaveBeenCalledWith(true);
  });
});
