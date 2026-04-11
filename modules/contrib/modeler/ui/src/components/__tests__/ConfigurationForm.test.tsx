import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import ConfigurationForm from '../ConfigurationForm';

// Mock the timing constant for faster tests
jest.mock('../../constants/dimensions', () => ({
  TIMING: {
    DEBOUNCE_DELAY: 10, // Short delay for testing
  },
}));

// Mock the sanitize functions
jest.mock('../../utils/sanitize', () => ({
  sanitizeHtml: (html: string) => html,
  sanitizeTokenHtml: (html: string) => html,
  escapeHtml: (text: string) => text,
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

describe('ConfigurationForm', () => {
  const defaultProps = {
    form: null,
    configuration: null,
    onChange: jest.fn(),
    disabled: false,
  };

  beforeEach(() => {
    jest.clearAllMocks();
    jest.useFakeTimers();
    mockIsTokenDragging = false;
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  describe('rendering with no form', () => {
    it('should show no configuration message when form is null', () => {
      render(<ConfigurationForm {...defaultProps} />);
      expect(screen.getByText('No configuration available')).toBeInTheDocument();
    });

    it('should show no configuration message when form is undefined', () => {
      render(<ConfigurationForm {...defaultProps} form={undefined} />);
      expect(screen.getByText('No configuration available')).toBeInTheDocument();
    });

    it('should show no configuration message when form is not an array', () => {
      render(<ConfigurationForm {...defaultProps} form={'not-array' as any} />);
      expect(screen.getByText('No configuration available')).toBeInTheDocument();
    });
  });

  describe('rendering form fields', () => {
    it('should render textfield type', () => {
      const form = [{ key: 'name', type: 'textfield', title: 'Name' }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByText('Name')).toBeInTheDocument();
    });

    it('should render email type', () => {
      const form = [{ key: 'email', type: 'email', title: 'Email' }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByText('Email')).toBeInTheDocument();
    });

    it('should render url type', () => {
      const form = [{ key: 'website', type: 'url', title: 'Website' }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByText('Website')).toBeInTheDocument();
    });

    it('should render textarea type', () => {
      const form = [{ key: 'description', type: 'textarea', title: 'Description' }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByText('Description')).toBeInTheDocument();
    });

    it('should render number type', () => {
      const form = [{ key: 'count', type: 'number', title: 'Count' }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByText('Count')).toBeInTheDocument();
      expect(screen.getByRole('spinbutton')).toBeInTheDocument();
    });

    it('should render checkbox type', () => {
      const form = [{ key: 'enabled', type: 'checkbox', title: 'Enabled' }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByText('Enabled')).toBeInTheDocument();
      expect(screen.getByRole('checkbox')).toBeInTheDocument();
    });

    it('should render select type', () => {
      const form = [{
        key: 'choice',
        type: 'select',
        title: 'Choice',
        options: { a: 'Option A', b: 'Option B' },
      }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByText('Choice')).toBeInTheDocument();
      expect(screen.getByRole('combobox')).toBeInTheDocument();
      expect(screen.getByRole('option', { name: 'Option A' })).toBeInTheDocument();
      expect(screen.getByRole('option', { name: 'Option B' })).toBeInTheDocument();
    });

    it('should render radios type', () => {
      const form = [{
        key: 'radio_choice',
        type: 'radios',
        title: 'Radio Choice',
        options: { x: 'Option X', y: 'Option Y' },
      }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByText('Radio Choice')).toBeInTheDocument();
      expect(screen.getAllByRole('radio')).toHaveLength(2);
    });

    it('should render checkboxes type', () => {
      const form = [{
        key: 'multi',
        type: 'checkboxes',
        title: 'Multi Select',
        options: { one: 'One', two: 'Two' },
      }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByText('Multi Select')).toBeInTheDocument();
      expect(screen.getAllByRole('checkbox')).toHaveLength(2);
    });

    it('should render markup type', () => {
      const form = [{
        key: 'info',
        type: 'markup',
        title: 'Information',
        markup: '<p>Some info</p>',
      }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByText('Information')).toBeInTheDocument();
      expect(screen.getByText('Some info')).toBeInTheDocument();
    });

    it('should render default type as text input', () => {
      const form = [{ key: 'unknown', type: 'unknown-type', title: 'Unknown' }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByRole('textbox')).toBeInTheDocument();
    });
  });

  describe('field labels and descriptions', () => {
    it('should render field title as label', () => {
      const form = [{ key: 'field', type: 'textfield', title: 'Field Title' }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByText('Field Title')).toBeInTheDocument();
    });

    it('should render required indicator', () => {
      const form = [{ key: 'field', type: 'textfield', title: 'Required Field', required: true }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(document.querySelector('.required')).toBeInTheDocument();
    });

    it('should render field description', () => {
      const form = [{
        key: 'field',
        type: 'textfield',
        title: 'Field',
        description: 'This is a description',
      }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByText('This is a description')).toBeInTheDocument();
    });
  });

  describe('initial values', () => {
    it('should use configuration values', () => {
      const form = [{ key: 'name', type: 'number', title: 'Name' }];
      const configuration = { name: 42 };
      render(<ConfigurationForm {...defaultProps} form={form} configuration={configuration} />);
      expect(screen.getByRole('spinbutton')).toHaveValue(42);
    });

    it('should use default value when no configuration', () => {
      const form = [{ key: 'count', type: 'number', title: 'Count', default_value: 10 }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByRole('spinbutton')).toHaveValue(10);
    });

    it('should prefer configuration over default value', () => {
      const form = [{ key: 'count', type: 'number', title: 'Count', default_value: 10 }];
      const configuration = { count: 20 };
      render(<ConfigurationForm {...defaultProps} form={form} configuration={configuration} />);
      expect(screen.getByRole('spinbutton')).toHaveValue(20);
    });
  });

  describe('number field constraints', () => {
    it('should apply min constraint', () => {
      const form = [{ key: 'count', type: 'number', title: 'Count', min: 0 }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByRole('spinbutton')).toHaveAttribute('min', '0');
    });

    it('should apply max constraint', () => {
      const form = [{ key: 'count', type: 'number', title: 'Count', max: 100 }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByRole('spinbutton')).toHaveAttribute('max', '100');
    });

    it('should apply step constraint', () => {
      const form = [{ key: 'count', type: 'number', title: 'Count', step: 5 }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByRole('spinbutton')).toHaveAttribute('step', '5');
    });
  });

  describe('number field with token support', () => {
    it('should render ContentEditableField when token_support is enabled', () => {
      const form = [{ key: 'value', type: 'number', title: 'Value', token_support: true }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      // Should NOT render a native number input (spinbutton)
      expect(screen.queryByRole('spinbutton')).toBeNull();
      // Should render a ContentEditableField (contenteditable div)
      expect(document.querySelector('[contenteditable]')).toBeInTheDocument();
    });

    it('should render ContentEditableField when replace_tokens is checked', () => {
      const form = [
        { key: 'replace_tokens', type: 'checkbox', title: 'Replace tokens' },
        { key: 'value', type: 'number', title: 'Value' },
      ];
      const configuration = { replace_tokens: true };
      render(<ConfigurationForm {...defaultProps} form={form} configuration={configuration} />);
      // Number field should use ContentEditableField
      expect(screen.queryByRole('spinbutton')).toBeNull();
      expect(document.querySelector('[contenteditable]')).toBeInTheDocument();
    });

    it('should render ContentEditableField when value contains a token pattern', () => {
      const form = [{ key: 'value', type: 'number', title: 'Value' }];
      const configuration = { value: '[node:field_length:value]' };
      render(<ConfigurationForm {...defaultProps} form={form} configuration={configuration} />);
      // Should switch to ContentEditableField to display the token
      expect(screen.queryByRole('spinbutton')).toBeNull();
      expect(document.querySelector('[contenteditable]')).toBeInTheDocument();
    });

    it('should render native number input when no token support and numeric value', () => {
      const form = [{ key: 'count', type: 'number', title: 'Count' }];
      const configuration = { count: 42 };
      render(<ConfigurationForm {...defaultProps} form={form} configuration={configuration} />);
      // Should render native number input
      expect(screen.getByRole('spinbutton')).toBeInTheDocument();
      expect(screen.getByRole('spinbutton')).toHaveValue(42);
    });

    it('should apply token-drop-enabled class to number field with token_support when dragging', () => {
      mockIsTokenDragging = true;
      const form = [{ key: 'value', type: 'number', title: 'Value', token_support: true }];
      const { container } = render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(container.querySelector('.form-field.token-drop-enabled')).toBeInTheDocument();
    });
  });

  describe('onChange behavior', () => {
    it('should call onChange for number field changes', () => {
      const onChange = jest.fn();
      const form = [{ key: 'count', type: 'number', title: 'Count' }];
      render(<ConfigurationForm {...defaultProps} form={form} onChange={onChange} />);

      fireEvent.change(screen.getByRole('spinbutton'), { target: { value: '42' } });

      expect(onChange).toHaveBeenCalledWith({ count: '42' });
    });

    it('should call onChange for checkbox toggle', () => {
      const onChange = jest.fn();
      const form = [{ key: 'enabled', type: 'checkbox', title: 'Enabled' }];
      render(<ConfigurationForm {...defaultProps} form={form} onChange={onChange} />);

      fireEvent.click(screen.getByRole('checkbox'));

      expect(onChange).toHaveBeenCalledWith({ enabled: true });
    });

    it('should call onChange for select change', () => {
      const onChange = jest.fn();
      const form = [{
        key: 'choice',
        type: 'select',
        title: 'Choice',
        options: { a: 'A', b: 'B' },
      }];
      render(<ConfigurationForm {...defaultProps} form={form} onChange={onChange} />);

      fireEvent.change(screen.getByRole('combobox'), { target: { value: 'b' } });

      expect(onChange).toHaveBeenCalledWith({ choice: 'b' });
    });

    it('should call onChange for radio change', () => {
      const onChange = jest.fn();
      const form = [{
        key: 'radio',
        type: 'radios',
        title: 'Radio',
        options: { x: 'X', y: 'Y' },
      }];
      render(<ConfigurationForm {...defaultProps} form={form} onChange={onChange} />);

      const radios = screen.getAllByRole('radio');
      fireEvent.click(radios[1]);

      expect(onChange).toHaveBeenCalledWith({ radio: 'y' });
    });

    it('should call onChange for checkboxes change (add)', () => {
      const onChange = jest.fn();
      const form = [{
        key: 'multi',
        type: 'checkboxes',
        title: 'Multi',
        options: { a: 'A', b: 'B' },
      }];
      render(<ConfigurationForm {...defaultProps} form={form} onChange={onChange} />);

      const checkboxes = screen.getAllByRole('checkbox');
      fireEvent.click(checkboxes[0]);

      expect(onChange).toHaveBeenCalledWith({ multi: ['a'] });
    });

    it('should call onChange for checkboxes change (remove)', () => {
      const onChange = jest.fn();
      const form = [{
        key: 'multi',
        type: 'checkboxes',
        title: 'Multi',
        options: { a: 'A', b: 'B' },
      }];
      const configuration = { multi: ['a', 'b'] };
      render(<ConfigurationForm {...defaultProps} form={form} configuration={configuration} onChange={onChange} />);

      const checkboxes = screen.getAllByRole('checkbox');
      fireEvent.click(checkboxes[0]); // Uncheck 'a'

      expect(onChange).toHaveBeenCalledWith({ multi: ['b'] });
    });
  });

  describe('disabled state', () => {
    it('should disable number input when disabled', () => {
      const form = [{ key: 'count', type: 'number', title: 'Count' }];
      render(<ConfigurationForm {...defaultProps} form={form} disabled={true} />);
      expect(screen.getByRole('spinbutton')).toBeDisabled();
    });

    it('should disable checkbox when disabled', () => {
      const form = [{ key: 'enabled', type: 'checkbox', title: 'Enabled' }];
      render(<ConfigurationForm {...defaultProps} form={form} disabled={true} />);
      expect(screen.getByRole('checkbox')).toBeDisabled();
    });

    it('should disable select when disabled', () => {
      const form = [{
        key: 'choice',
        type: 'select',
        title: 'Choice',
        options: { a: 'A' },
      }];
      render(<ConfigurationForm {...defaultProps} form={form} disabled={true} />);
      expect(screen.getByRole('combobox')).toBeDisabled();
    });

    it('should disable radio buttons when disabled', () => {
      const form = [{
        key: 'radio',
        type: 'radios',
        title: 'Radio',
        options: { a: 'A', b: 'B' },
      }];
      render(<ConfigurationForm {...defaultProps} form={form} disabled={true} />);
      const radios = screen.getAllByRole('radio');
      radios.forEach(radio => expect(radio).toBeDisabled());
    });

    it('should disable checkboxes when disabled', () => {
      const form = [{
        key: 'multi',
        type: 'checkboxes',
        title: 'Multi',
        options: { a: 'A', b: 'B' },
      }];
      render(<ConfigurationForm {...defaultProps} form={form} disabled={true} />);
      const checkboxes = screen.getAllByRole('checkbox');
      checkboxes.forEach(checkbox => expect(checkbox).toBeDisabled());
    });
  });

  describe('select default option', () => {
    it('should include select placeholder option', () => {
      const form = [{
        key: 'choice',
        type: 'select',
        title: 'Choice',
        options: { a: 'A' },
      }];
      render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(screen.getByRole('option', { name: '- Select -' })).toBeInTheDocument();
    });
  });

  describe('multiple fields', () => {
    it('should render multiple fields', () => {
      const form = [
        { key: 'name', type: 'textfield', title: 'Name' },
        { key: 'count', type: 'number', title: 'Count' },
        { key: 'enabled', type: 'checkbox', title: 'Enabled' },
      ];
      render(<ConfigurationForm {...defaultProps} form={form} />);

      expect(screen.getByText('Name')).toBeInTheDocument();
      expect(screen.getByText('Count')).toBeInTheDocument();
      expect(screen.getByText('Enabled')).toBeInTheDocument();
    });

    it('should preserve values across field changes', () => {
      const onChange = jest.fn();
      const form = [
        { key: 'name', type: 'number', title: 'Name' },
        { key: 'count', type: 'number', title: 'Count' },
      ];
      const configuration = { name: 1, count: 2 };
      render(<ConfigurationForm {...defaultProps} form={form} configuration={configuration} onChange={onChange} />);

      const inputs = screen.getAllByRole('spinbutton');
      fireEvent.change(inputs[0], { target: { value: '10' } });

      expect(onChange).toHaveBeenCalledWith({ name: '10', count: 2 });
    });
  });

  describe('token drop zone indicators', () => {
    it('should apply token-drop-enabled class to fields with token_support when dragging', () => {
      mockIsTokenDragging = true;
      const form = [
        { key: 'value', type: 'textfield', title: 'Value', token_support: true },
      ];
      const { container } = render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(container.querySelector('.form-field.token-drop-enabled')).toBeInTheDocument();
    });

    it('should apply token-drop-disabled class to fields without token_support when dragging', () => {
      mockIsTokenDragging = true;
      const form = [
        { key: 'name', type: 'textfield', title: 'Name' },
      ];
      const { container } = render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(container.querySelector('.form-field.token-drop-disabled')).toBeInTheDocument();
    });

    it('should not apply token classes when not dragging', () => {
      mockIsTokenDragging = false;
      const form = [
        { key: 'value', type: 'textfield', title: 'Value', token_support: true },
        { key: 'name', type: 'textfield', title: 'Name' },
      ];
      const { container } = render(<ConfigurationForm {...defaultProps} form={form} />);
      expect(container.querySelector('.token-drop-enabled')).toBeNull();
      expect(container.querySelector('.token-drop-disabled')).toBeNull();
    });

    it('should enable all fields when replace_tokens checkbox is checked', () => {
      mockIsTokenDragging = true;
      const form = [
        { key: 'replace_tokens', type: 'checkbox', title: 'Replace tokens' },
        { key: 'name', type: 'textfield', title: 'Name' },
        { key: 'value', type: 'textfield', title: 'Value' },
      ];
      const configuration = { replace_tokens: true };
      const { container } = render(
        <ConfigurationForm {...defaultProps} form={form} configuration={configuration} />
      );
      const enabledFields = container.querySelectorAll('.form-field.token-drop-enabled');
      // All 3 fields should be enabled (including the checkbox itself)
      expect(enabledFields.length).toBe(3);
      expect(container.querySelector('.form-field.token-drop-disabled')).toBeNull();
    });

    it('should not enable all fields when replace_tokens is unchecked', () => {
      mockIsTokenDragging = true;
      const form = [
        { key: 'replace_tokens', type: 'checkbox', title: 'Replace tokens' },
        { key: 'name', type: 'textfield', title: 'Name' },
        { key: 'value', type: 'textfield', title: 'Value', token_support: true },
      ];
      const configuration = { replace_tokens: false };
      const { container } = render(
        <ConfigurationForm {...defaultProps} form={form} configuration={configuration} />
      );
      // Only the field with token_support should be enabled
      expect(container.querySelectorAll('.form-field.token-drop-enabled').length).toBe(1);
      expect(container.querySelectorAll('.form-field.token-drop-disabled').length).toBe(2);
    });

    it('should toggle all fields when replace_tokens checkbox is toggled', () => {
      mockIsTokenDragging = true;
      const onChange = jest.fn();
      const form = [
        { key: 'replace_tokens', type: 'checkbox', title: 'Replace tokens' },
        { key: 'name', type: 'textfield', title: 'Name' },
      ];
      const { container } = render(
        <ConfigurationForm {...defaultProps} form={form} onChange={onChange} />
      );

      // Initially, name field should be disabled (no token_support, replace_tokens unchecked)
      expect(container.querySelectorAll('.form-field.token-drop-disabled').length).toBe(2);

      // Check the replace_tokens checkbox
      const checkbox = screen.getByRole('checkbox');
      fireEvent.click(checkbox);

      // Now all fields should be enabled
      expect(container.querySelectorAll('.form-field.token-drop-enabled').length).toBe(2);
      expect(container.querySelector('.form-field.token-drop-disabled')).toBeNull();
    });
  });

  describe('use_yaml / validate_yaml integration', () => {
    const yamlForm = [
      { key: 'config_value', type: 'textarea', title: 'Config value' },
      { key: 'use_yaml', type: 'checkbox', title: 'Use YAML', yaml_field: 'config_value' },
      { key: 'validate_yaml', type: 'checkbox', title: 'Validate YAML', yaml_field: 'config_value' },
    ];

    it('should render a plain textarea when use_yaml is unchecked', () => {
      const { container } = render(
        <ConfigurationForm {...defaultProps} form={yamlForm} configuration={{ use_yaml: false }} />
      );
      // No yaml-editor class on the textarea wrapper
      expect(container.querySelector('.yaml-editor')).toBeNull();
    });

    it('should render the YAML editor when use_yaml is checked', () => {
      const { container } = render(
        <ConfigurationForm {...defaultProps} form={yamlForm} configuration={{ use_yaml: true }} />
      );
      expect(container.querySelector('.yaml-editor')).toBeInTheDocument();
    });

    it('should switch from plain textarea to YAML editor when use_yaml is toggled on', () => {
      const onChange = jest.fn();
      const { container } = render(
        <ConfigurationForm {...defaultProps} form={yamlForm} onChange={onChange} />
      );
      // Initially no YAML editor
      expect(container.querySelector('.yaml-editor')).toBeNull();

      // Check the use_yaml checkbox (second checkbox in the form)
      const checkboxes = screen.getAllByRole('checkbox');
      // use_yaml is the first checkbox rendered
      fireEvent.click(checkboxes[0]);

      // YAML editor should now appear
      expect(container.querySelector('.yaml-editor')).toBeInTheDocument();
    });

    it('should hide validate_yaml checkbox when use_yaml is unchecked', () => {
      const { container } = render(
        <ConfigurationForm {...defaultProps} form={yamlForm} configuration={{ use_yaml: false }} />
      );
      // The validate_yaml field should have display: none
      const fields = container.querySelectorAll('.form-field');
      // Find the validate_yaml field (third field)
      const validateField = fields[2];
      expect(validateField).toHaveStyle('display: none');
    });

    it('should show validate_yaml checkbox when use_yaml is checked', () => {
      const { container } = render(
        <ConfigurationForm {...defaultProps} form={yamlForm} configuration={{ use_yaml: true }} />
      );
      const fields = container.querySelectorAll('.form-field');
      const validateField = fields[2];
      expect(validateField).not.toHaveStyle('display: none');
    });

    it('should render schema-less YAML editor without Editor/YAML mode toolbar', () => {
      const { container } = render(
        <ConfigurationForm
          {...defaultProps}
          form={yamlForm}
          configuration={{ use_yaml: true, config_value: 'key: value' }}
        />
      );
      // The schema-less editor has the yaml-editor-schemaless class
      expect(container.querySelector('.yaml-editor-schemaless')).toBeInTheDocument();
      // No toolbar buttons (Editor / YAML tabs)
      expect(container.querySelector('.yaml-editor-toolbar')).toBeNull();
    });

    it('should not show YAML validation errors when validate_yaml is unchecked', () => {
      const { container } = render(
        <ConfigurationForm
          {...defaultProps}
          form={yamlForm}
          configuration={{ use_yaml: true, validate_yaml: false, config_value: 'invalid: yaml: :::' }}
        />
      );
      expect(container.querySelector('.yaml-editor-error')).toBeNull();
    });

    it('should show YAML validation errors when validate_yaml is checked and YAML is invalid', () => {
      const { container } = render(
        <ConfigurationForm
          {...defaultProps}
          form={yamlForm}
          configuration={{ use_yaml: true, validate_yaml: true, config_value: 'invalid: yaml: :::' }}
        />
      );
      expect(container.querySelector('.yaml-editor-error')).toBeInTheDocument();
    });

    it('should not show validation errors when validate_yaml is checked and YAML is valid', () => {
      const { container } = render(
        <ConfigurationForm
          {...defaultProps}
          form={yamlForm}
          configuration={{ use_yaml: true, validate_yaml: true, config_value: 'key: value' }}
        />
      );
      expect(container.querySelector('.yaml-editor-error')).toBeNull();
    });
  });
});
