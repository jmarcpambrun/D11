import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import MetadataModal from '../MetadataModal';

describe('MetadataModal', () => {
  const defaultProps = {
    isOpen: true,
    onClose: jest.fn(),
    onSave: jest.fn(),
    metadata: {},
    isNew: false,
  };

  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('rendering', () => {
    it('should render nothing when isOpen is false', () => {
      render(<MetadataModal {...defaultProps} isOpen={false} />);
      expect(screen.queryByText('Model Information')).not.toBeInTheDocument();
    });

    it('should render modal when isOpen is true', () => {
      render(<MetadataModal {...defaultProps} />);
      expect(screen.getByText('Model Information')).toBeInTheDocument();
    });

    it('should render label input', () => {
      render(<MetadataModal {...defaultProps} />);
      expect(screen.getByLabelText('Label *')).toBeInTheDocument();
    });

    it('should render version input', () => {
      render(<MetadataModal {...defaultProps} />);
      expect(screen.getByLabelText('Version')).toBeInTheDocument();
    });

    it('should render enabled checkbox', () => {
      render(<MetadataModal {...defaultProps} />);
      expect(screen.getByText('Enabled')).toBeInTheDocument();
    });

    it('should render template checkbox', () => {
      render(<MetadataModal {...defaultProps} />);
      expect(screen.getByText('Template')).toBeInTheDocument();
    });

    it('should render storage select', () => {
      render(<MetadataModal {...defaultProps} />);
      expect(screen.getByRole('combobox', { name: /storage of raw data/i })).toBeInTheDocument();
    });

    it('should render documentation textarea', () => {
      render(<MetadataModal {...defaultProps} />);
      expect(screen.getByLabelText('Documentation')).toBeInTheDocument();
    });

    it('should render tags input', () => {
      render(<MetadataModal {...defaultProps} />);
      expect(screen.getByLabelText('Tags')).toBeInTheDocument();
    });

    it('should render changelog textarea for existing models', () => {
      render(<MetadataModal {...defaultProps} isNew={false} />);
      expect(screen.getByLabelText('Changelog')).toBeInTheDocument();
    });

    it('should not render changelog textarea for new models', () => {
      render(<MetadataModal {...defaultProps} isNew={true} />);
      expect(screen.queryByLabelText('Changelog')).not.toBeInTheDocument();
    });

    it('should render close button', () => {
      render(<MetadataModal {...defaultProps} />);
      expect(document.querySelector('.close-btn')).toBeInTheDocument();
    });

    it('should render cancel button', () => {
      render(<MetadataModal {...defaultProps} />);
      expect(screen.getByText('Cancel')).toBeInTheDocument();
    });

    it('should render save button', () => {
      render(<MetadataModal {...defaultProps} />);
      expect(screen.getByText('Save')).toBeInTheDocument();
    });
  });

  describe('initial values', () => {
    it('should populate label from metadata', () => {
      render(<MetadataModal {...defaultProps} metadata={{ label: 'Test Label' }} />);
      expect(screen.getByLabelText('Label *')).toHaveValue('Test Label');
    });

    it('should populate version from metadata', () => {
      render(<MetadataModal {...defaultProps} metadata={{ version: '2.0.0' }} />);
      expect(screen.getByLabelText('Version')).toHaveValue('2.0.0');
    });

    it('should use default version when not provided', () => {
      render(<MetadataModal {...defaultProps} />);
      expect(screen.getByLabelText('Version')).toHaveValue('1.0.0');
    });

    it('should check enabled by default', () => {
      render(<MetadataModal {...defaultProps} />);
      const checkbox = screen.getByRole('checkbox', { name: /enabled/i });
      expect(checkbox).toBeChecked();
    });

    it('should not check enabled when executable is false', () => {
      render(<MetadataModal {...defaultProps} metadata={{ executable: false }} />);
      const checkbox = screen.getByRole('checkbox', { name: /enabled/i });
      expect(checkbox).not.toBeChecked();
    });

    it('should populate storage from metadata', () => {
      render(<MetadataModal {...defaultProps} metadata={{ storage: 'separate' }} />);
      expect(screen.getByRole('combobox', { name: /storage of raw data/i })).toHaveValue('separate');
    });

    it('should populate documentation from metadata', () => {
      render(<MetadataModal {...defaultProps} metadata={{ documentation: 'Some docs' }} />);
      expect(screen.getByLabelText('Documentation')).toHaveValue('Some docs');
    });

    it('should populate tags from metadata array', () => {
      render(<MetadataModal {...defaultProps} metadata={{ tags: ['tag1', 'tag2'] }} />);
      expect(screen.getByLabelText('Tags')).toHaveValue('tag1, tag2');
    });

    it('should handle tags as string', () => {
      render(<MetadataModal {...defaultProps} metadata={{ tags: 'single-tag' as any }} />);
      expect(screen.getByLabelText('Tags')).toHaveValue('single-tag');
    });

    it('should populate changelog from metadata', () => {
      render(<MetadataModal {...defaultProps} metadata={{ changelog: 'Change log text' }} />);
      expect(screen.getByLabelText('Changelog')).toHaveValue('Change log text');
    });
  });

  describe('form interactions', () => {
    it('should update label on change', () => {
      render(<MetadataModal {...defaultProps} />);
      const input = screen.getByLabelText('Label *');

      fireEvent.change(input, { target: { value: 'New Label' } });

      expect(input).toHaveValue('New Label');
    });

    it('should update version on change', () => {
      render(<MetadataModal {...defaultProps} />);
      const input = screen.getByLabelText('Version');

      fireEvent.change(input, { target: { value: '3.0.0' } });

      expect(input).toHaveValue('3.0.0');
    });

    it('should toggle enabled checkbox', () => {
      render(<MetadataModal {...defaultProps} />);
      const checkbox = screen.getByRole('checkbox', { name: /enabled/i });

      fireEvent.click(checkbox);

      expect(checkbox).not.toBeChecked();
    });

    it('should toggle template checkbox', () => {
      render(<MetadataModal {...defaultProps} />);
      const checkbox = screen.getByRole('checkbox', { name: /template/i });

      fireEvent.click(checkbox);

      expect(checkbox).toBeChecked();
    });

    it('should update storage on change', () => {
      render(<MetadataModal {...defaultProps} />);
      const select = screen.getByRole('combobox', { name: /storage of raw data/i });

      fireEvent.change(select, { target: { value: 'separate' } });

      expect(select).toHaveValue('separate');
    });

    it('should update documentation on change', () => {
      render(<MetadataModal {...defaultProps} />);
      const textarea = screen.getByLabelText('Documentation');

      fireEvent.change(textarea, { target: { value: 'New docs' } });

      expect(textarea).toHaveValue('New docs');
    });

    it('should update tags on change', () => {
      render(<MetadataModal {...defaultProps} />);
      const input = screen.getByLabelText('Tags');

      fireEvent.change(input, { target: { value: 'new, tags, here' } });

      expect(input).toHaveValue('new, tags, here');
    });

    it('should update changelog on change', () => {
      render(<MetadataModal {...defaultProps} />);
      const textarea = screen.getByLabelText('Changelog');

      fireEvent.change(textarea, { target: { value: 'New changelog' } });

      expect(textarea).toHaveValue('New changelog');
    });
  });

  describe('close behavior', () => {
    it('should call onClose when close button clicked', () => {
      const onClose = jest.fn();
      render(<MetadataModal {...defaultProps} onClose={onClose} />);

      fireEvent.click(document.querySelector('.close-btn')!);

      expect(onClose).toHaveBeenCalled();
    });

    it('should call onClose when cancel button clicked', () => {
      const onClose = jest.fn();
      render(<MetadataModal {...defaultProps} onClose={onClose} />);

      fireEvent.click(screen.getByText('Cancel'));

      expect(onClose).toHaveBeenCalled();
    });

    it('should call onClose when Escape key pressed', () => {
      const onClose = jest.fn();
      render(<MetadataModal {...defaultProps} onClose={onClose} />);

      fireEvent.keyDown(document, { key: 'Escape' });

      expect(onClose).toHaveBeenCalled();
    });
  });

  describe('save behavior', () => {
    it('should call onSave with form data on submit', () => {
      const onSave = jest.fn();
      render(<MetadataModal {...defaultProps} onSave={onSave} />);

      const labelInput = screen.getByLabelText('Label *');
      fireEvent.change(labelInput, { target: { value: 'Test Model' } });

      fireEvent.click(screen.getByText('Save'));

      expect(onSave).toHaveBeenCalledWith(
        expect.objectContaining({
          label: 'Test Model',
        })
      );
    });

    it('should include all form fields in save data', () => {
      const onSave = jest.fn();
      render(<MetadataModal {...defaultProps} onSave={onSave} />);

      fireEvent.change(screen.getByLabelText('Label *'), { target: { value: 'My Model' } });
      fireEvent.change(screen.getByLabelText('Version'), { target: { value: '2.0.0' } });
      fireEvent.change(screen.getByRole('combobox', { name: /storage of raw data/i }), { target: { value: 'separate' } });
      fireEvent.change(screen.getByLabelText('Documentation'), { target: { value: 'Docs' } });
      fireEvent.change(screen.getByLabelText('Tags'), { target: { value: 'tag1, tag2' } });
      fireEvent.change(screen.getByLabelText('Changelog'), { target: { value: 'Changes' } });

      fireEvent.click(screen.getByText('Save'));

      expect(onSave).toHaveBeenCalledWith({
        label: 'My Model',
        version: '2.0.0',
        executable: true,
        template: false,
        storage: 'separate',
        documentation: 'Docs',
        tags: ['tag1', 'tag2'],
        changelog: 'Changes',
      });
    });

    it('should close modal after save', () => {
      const onClose = jest.fn();
      render(<MetadataModal {...defaultProps} onClose={onClose} />);

      fireEvent.change(screen.getByLabelText('Label *'), { target: { value: 'Test' } });
      fireEvent.click(screen.getByText('Save'));

      expect(onClose).toHaveBeenCalled();
    });

    it('should parse tags into array', () => {
      const onSave = jest.fn();
      render(<MetadataModal {...defaultProps} onSave={onSave} />);

      fireEvent.change(screen.getByLabelText('Label *'), { target: { value: 'Test' } });
      fireEvent.change(screen.getByLabelText('Tags'), { target: { value: 'one, two, three' } });
      fireEvent.click(screen.getByText('Save'));

      expect(onSave).toHaveBeenCalledWith(
        expect.objectContaining({
          tags: ['one', 'two', 'three'],
        })
      );
    });

    it('should trim tag values', () => {
      const onSave = jest.fn();
      render(<MetadataModal {...defaultProps} onSave={onSave} />);

      fireEvent.change(screen.getByLabelText('Label *'), { target: { value: 'Test' } });
      fireEvent.change(screen.getByLabelText('Tags'), { target: { value: '  one  ,  two  ' } });
      fireEvent.click(screen.getByText('Save'));

      expect(onSave).toHaveBeenCalledWith(
        expect.objectContaining({
          tags: ['one', 'two'],
        })
      );
    });

    it('should filter empty tags', () => {
      const onSave = jest.fn();
      render(<MetadataModal {...defaultProps} onSave={onSave} />);

      fireEvent.change(screen.getByLabelText('Label *'), { target: { value: 'Test' } });
      fireEvent.change(screen.getByLabelText('Tags'), { target: { value: 'one, , two, , ' } });
      fireEvent.click(screen.getByText('Save'));

      expect(onSave).toHaveBeenCalledWith(
        expect.objectContaining({
          tags: ['one', 'two'],
        })
      );
    });
  });

  describe('form validation', () => {
    it('should require label field', () => {
      render(<MetadataModal {...defaultProps} />);
      const labelInput = screen.getByLabelText('Label *');
      expect(labelInput).toHaveAttribute('required');
    });
  });

  describe('form reset on metadata change', () => {
    it('should update form when metadata changes', () => {
      const { rerender } = render(<MetadataModal {...defaultProps} metadata={{ label: 'Old' }} />);

      expect(screen.getByLabelText('Label *')).toHaveValue('Old');

      rerender(<MetadataModal {...defaultProps} metadata={{ label: 'New' }} />);

      expect(screen.getByLabelText('Label *')).toHaveValue('New');
    });

    it('should update form when isOpen changes to true', () => {
      const { rerender } = render(
        <MetadataModal {...defaultProps} isOpen={false} metadata={{ label: 'Test' }} />
      );

      rerender(<MetadataModal {...defaultProps} isOpen={true} metadata={{ label: 'Test' }} />);

      expect(screen.getByLabelText('Label *')).toHaveValue('Test');
    });
  });

  describe('storage options', () => {
    it('should have default option', () => {
      render(<MetadataModal {...defaultProps} />);
      expect(screen.getByRole('option', { name: 'Default' })).toBeInTheDocument();
    });

    it('should have "do not store" option', () => {
      render(<MetadataModal {...defaultProps} />);
      expect(screen.getByRole('option', { name: 'Do not store raw model data' })).toBeInTheDocument();
    });

    it('should have "separate config" option', () => {
      render(<MetadataModal {...defaultProps} />);
      expect(screen.getByRole('option', { name: 'Store raw data in separate config entity' })).toBeInTheDocument();
    });

    it('should have "third-party" option', () => {
      render(<MetadataModal {...defaultProps} />);
      expect(screen.getByRole('option', { name: 'Store raw data with config as third-party setting' })).toBeInTheDocument();
    });

    it('should display a help icon next to the storage label', () => {
      render(<MetadataModal {...defaultProps} />);
      expect(screen.getByRole('button', { name: 'More information' })).toBeInTheDocument();
    });

    it('should show tooltip text when help icon is clicked', () => {
      render(<MetadataModal {...defaultProps} />);
      const helpBtn = screen.getByRole('button', { name: 'More information' });

      fireEvent.click(helpBtn);

      expect(screen.getByRole('tooltip')).toBeInTheDocument();
      expect(screen.getByText(/Controls if and how the modeler/)).toBeInTheDocument();
    });

    it('should hide tooltip when help icon is clicked again', () => {
      render(<MetadataModal {...defaultProps} />);
      const helpBtn = screen.getByRole('button', { name: 'More information' });

      fireEvent.click(helpBtn);
      expect(screen.getByRole('tooltip')).toBeInTheDocument();

      fireEvent.click(helpBtn);
      expect(screen.queryByRole('tooltip')).not.toBeInTheDocument();
    });
  });

  describe('model ID field for new models', () => {
    it('should not show ID field when isNew is false', () => {
      render(<MetadataModal {...defaultProps} isNew={false} />);
      expect(screen.queryByLabelText(/machine name/i)).not.toBeInTheDocument();
    });

    it('should show ID field when isNew is true', () => {
      render(<MetadataModal {...defaultProps} isNew={true} />);
      expect(screen.getByLabelText(/machine name/i)).toBeInTheDocument();
    });

    it('should auto-derive ID from label', () => {
      render(<MetadataModal {...defaultProps} isNew={true} />);

      fireEvent.change(screen.getByLabelText('Label *'), { target: { value: 'My Test Model' } });

      expect(screen.getByLabelText(/machine name/i)).toHaveValue('my_test_model');
    });

    it('should convert special characters to underscores', () => {
      render(<MetadataModal {...defaultProps} isNew={true} />);

      fireEvent.change(screen.getByLabelText('Label *'), { target: { value: 'Test-Model With Spaces' } });

      expect(screen.getByLabelText(/machine name/i)).toHaveValue('test_model_with_spaces');
    });

    it('should allow manual ID editing', () => {
      render(<MetadataModal {...defaultProps} isNew={true} />);

      const idInput = screen.getByLabelText(/machine name/i);
      fireEvent.change(idInput, { target: { value: 'custom_id' } });

      expect(idInput).toHaveValue('custom_id');
    });

    it('should not override manually edited ID when label changes', () => {
      render(<MetadataModal {...defaultProps} isNew={true} />);

      // First manually edit the ID
      const idInput = screen.getByLabelText(/machine name/i);
      fireEvent.change(idInput, { target: { value: 'custom_id' } });

      // Then change the label
      fireEvent.change(screen.getByLabelText('Label *'), { target: { value: 'New Label' } });

      // ID should remain custom (not overwritten because it's not empty)
      expect(idInput).toHaveValue('custom_id');
    });

    it('should include ID in save data for new models', () => {
      const onSave = jest.fn();
      render(<MetadataModal {...defaultProps} onSave={onSave} isNew={true} />);

      fireEvent.change(screen.getByLabelText('Label *'), { target: { value: 'Test Model' } });
      fireEvent.click(screen.getByText('Save'));

      expect(onSave).toHaveBeenCalledWith(
        expect.objectContaining({
          id: 'test_model',
        })
      );
    });

    it('should not include ID in save data for existing models', () => {
      const onSave = jest.fn();
      render(<MetadataModal {...defaultProps} onSave={onSave} isNew={false} />);

      fireEvent.change(screen.getByLabelText('Label *'), { target: { value: 'Test Model' } });
      fireEvent.click(screen.getByText('Save'));

      expect(onSave).toHaveBeenCalledWith(
        expect.not.objectContaining({
          id: expect.anything(),
        })
      );
    });

    it('should empty ID field when label is default (New Model)', () => {
      render(<MetadataModal {...defaultProps} isNew={true} modelId="some_id" metadata={{ label: 'New Model' }} />);

      // ID should be empty because label is the default
      expect(screen.getByLabelText(/machine name/i)).toHaveValue('');
    });

    it('should populate ID field from modelId when label is not default', () => {
      render(<MetadataModal {...defaultProps} isNew={true} modelId="existing_id" metadata={{ label: 'Custom Label' }} />);

      expect(screen.getByLabelText(/machine name/i)).toHaveValue('existing_id');
    });

    it('should auto-update ID only when ID field is empty', () => {
      render(<MetadataModal {...defaultProps} isNew={true} />);

      // Type in label - ID should auto-update since it's empty
      fireEvent.change(screen.getByLabelText('Label *'), { target: { value: 'First' } });
      expect(screen.getByLabelText(/machine name/i)).toHaveValue('first');

      // Clear the ID manually
      fireEvent.change(screen.getByLabelText(/machine name/i), { target: { value: '' } });

      // Type new label - ID should auto-update again since it's empty
      fireEvent.change(screen.getByLabelText('Label *'), { target: { value: 'Second' } });
      expect(screen.getByLabelText(/machine name/i)).toHaveValue('second');
    });
  });
});
