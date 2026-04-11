import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import EdgePropertiesPanel from '../EdgePropertiesPanel';

// Mock ConfigurationForm - capture onChange for testing
let capturedConfigFormOnChange: ((config: Record<string, unknown>) => void) | null = null;
jest.mock('../ConfigurationForm', () => {
  return function MockConfigurationForm(props: any) {
    capturedConfigFormOnChange = props.onChange;
    return <div data-testid="configuration-form" data-disabled={props.disabled} />;
  };
});

// Mock react-icons
jest.mock('react-icons/fi', () => ({
  FiTrash2: () => <span data-testid="fi-trash" />,
}));

// Mock the Zustand store
let mockIsTokenDragging = false;
jest.mock('../../store/useFilterStore', () => ({
  useFilterStore: jest.fn((selector: any) => {
    const state = {
      isTokenDragging: mockIsTokenDragging,
    };
    if (typeof selector === 'function') return selector(state);
    return state;
  }),
}));

describe('EdgePropertiesPanel', () => {
  const mockOnEdgeConfigurationChange = jest.fn();
  const mockOnEdgeUpdate = jest.fn();

  const createMockField = (value: string = '') => ({
    value,
    setValue: jest.fn(),
    onChange: jest.fn(),
    onBlur: jest.fn(),
    flush: jest.fn(),
  });

  const conditionEdge = {
    id: 'edge-1',
    source: 'node-1',
    target: 'node-2',
    data: {
      condition: 'test.condition.test',
      conditionLabel: 'Check Value',
      conditionConfiguration: { field: 'value' },
      annotation: 'A note about this edge',
    },
  };

  const plainEdge = {
    id: 'edge-2',
    source: 'node-1',
    target: 'node-2',
    data: {},
  };

  const defaultProps = {
    edge: conditionEdge as any,
    configurationForm: null,
    onEdgeConfigurationChange: mockOnEdgeConfigurationChange,
    onEdgeUpdate: mockOnEdgeUpdate,
    isLocked: false,
    edgeLabelField: createMockField('Check Value'),
    edgeAnnotationField: createMockField('A note about this edge'),
  };

  beforeEach(() => {
    jest.clearAllMocks();
    mockIsTokenDragging = false;
  });

  describe('condition edge', () => {
    it('should render condition label input', () => {
      render(<EdgePropertiesPanel {...defaultProps} />);
      const input = screen.getByLabelText('Condition Label');
      expect(input).toBeTruthy();
      expect((input as HTMLInputElement).value).toBe('Check Value');
    });

    it('should show delete condition button when not locked', () => {
      render(<EdgePropertiesPanel {...defaultProps} />);
      const deleteBtn = screen.getByTitle('Remove condition');
      expect(deleteBtn).toBeTruthy();
    });

    it('should not show delete button when globally locked', () => {
      render(<EdgePropertiesPanel {...defaultProps} isLocked={true} />);
      expect(screen.queryByTitle('Remove condition')).toBeNull();
    });

    it('should call onEdgeConfigurationChange with null when deleting condition', () => {
      render(<EdgePropertiesPanel {...defaultProps} />);
      const deleteBtn = screen.getByTitle('Remove condition');
      fireEvent.click(deleteBtn);
      expect(mockOnEdgeConfigurationChange).toHaveBeenCalledWith('edge-1', null);
    });

    it('should disable label input when locked', () => {
      render(<EdgePropertiesPanel {...defaultProps} isLocked={true} />);
      const input = screen.getByLabelText('Condition Label');
      expect(input).toBeDisabled();
    });
  });

  describe('plain edge (no condition)', () => {
    it('should show message about no conditions', () => {
      render(
        <EdgePropertiesPanel
          {...defaultProps}
          edge={plainEdge as any}
          edgeLabelField={createMockField('')}
          edgeAnnotationField={createMockField('')}
        />
      );
      expect(screen.getByText('This connection has no conditions configured')).toBeTruthy();
    });
  });

  describe('configuration form', () => {
    it('should render configuration form when provided', () => {
      render(<EdgePropertiesPanel {...defaultProps} configurationForm={{ fields: [] }} />);
      expect(screen.getByTestId('configuration-form')).toBeTruthy();
    });

    it('should call onEdgeConfigurationChange when form config changes', () => {
      capturedConfigFormOnChange = null;
      render(
        <EdgePropertiesPanel {...defaultProps} configurationForm={{ fields: [] }} />
      );

      capturedConfigFormOnChange!({ newField: 'newValue' });

      expect(mockOnEdgeConfigurationChange).toHaveBeenCalledWith('edge-1', {
        _conditionLabel: 'Check Value',
        newField: 'newValue',
      });
    });

    it('should not call onEdgeConfigurationChange when locked', () => {
      capturedConfigFormOnChange = null;
      render(
        <EdgePropertiesPanel {...defaultProps} configurationForm={{ fields: [] }} isLocked={true} />
      );

      capturedConfigFormOnChange!({ newField: 'newValue' });

      expect(mockOnEdgeConfigurationChange).not.toHaveBeenCalled();
    });

  });

  describe('annotation field', () => {
    it('should render annotation textarea directly (not in metadata)', () => {
      render(<EdgePropertiesPanel {...defaultProps} />);
      const annotation = screen.getByLabelText('Annotation');
      expect(annotation).toBeTruthy();
      expect(annotation.tagName).toBe('TEXTAREA');
    });

    it('should show annotation value', () => {
      render(<EdgePropertiesPanel {...defaultProps} />);
      const annotation = screen.getByLabelText('Annotation');
      expect(annotation).toHaveValue('A note about this edge');
    });

    it('should disable annotation when locked', () => {
      render(<EdgePropertiesPanel {...defaultProps} isLocked={true} />);
      const annotation = screen.getByLabelText('Annotation');
      expect(annotation).toBeDisabled();
    });
  });

  describe('token drag state', () => {
    it('should add token-drop-disabled class to native fields when token is being dragged', () => {
      mockIsTokenDragging = true;
      const { container } = render(<EdgePropertiesPanel {...defaultProps} />);
      const nativeFields = container.querySelectorAll('.modeler-native-field.token-drop-disabled');
      expect(nativeFields.length).toBe(2); // label + annotation
    });

    it('should not add token-drop-disabled class when no token is being dragged', () => {
      mockIsTokenDragging = false;
      const { container } = render(<EdgePropertiesPanel {...defaultProps} />);
      expect(container.querySelector('.modeler-native-field.token-drop-disabled')).toBeNull();
    });

    it('should prevent drop on condition label input during token drag', () => {
      mockIsTokenDragging = true;
      render(<EdgePropertiesPanel {...defaultProps} />);
      const labelInput = screen.getByLabelText('Condition Label');

      const dropEvent = new Event('drop', { bubbles: true, cancelable: true });
      const prevented = !labelInput.dispatchEvent(dropEvent);
      expect(prevented).toBe(true);
    });

    it('should prevent drop on annotation textarea during token drag', () => {
      mockIsTokenDragging = true;
      render(<EdgePropertiesPanel {...defaultProps} />);
      const annotation = screen.getByLabelText('Annotation');

      const dropEvent = new Event('drop', { bubbles: true, cancelable: true });
      const prevented = !annotation.dispatchEvent(dropEvent);
      expect(prevented).toBe(true);
    });
  });
});
