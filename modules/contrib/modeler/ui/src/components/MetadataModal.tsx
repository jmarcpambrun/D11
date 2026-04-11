import React, { useState, useEffect, useCallback, useRef } from 'react';
import { FiX, FiLink } from 'react-icons/fi';
import { t } from '../utils/translation';
import { useFocusTrap } from '../hooks/useFocusTrap';
import { labelToSnakeCase } from '../utils/modelUtils';
import HelpTooltip from './HelpTooltip';

interface MetadataFormData {
  id?: string;
  label: string;
  version: string;
  executable: boolean;
  template: boolean;
  storage: string;
  documentation: string;
  tags: string[];
  changelog: string;
}

interface MetadataModalProps {
  isOpen: boolean;
  onClose: () => void;
  metadata?: Partial<MetadataFormData>;
  onSave: (data: MetadataFormData) => void;
  isNew?: boolean;
  modelId?: string;
  /** When false and not a new model, all fields are read-only. */
  canEditMetadata?: boolean;
  /** When false, the Template checkbox is disabled. */
  canCreateTemplate?: boolean;
}

// Default label for new models
const DEFAULT_MODEL_LABEL = 'New Model';

const MetadataModal: React.FC<MetadataModalProps> = ({ isOpen, onClose, metadata, onSave, isNew = false, modelId, canEditMetadata = true, canCreateTemplate = true }) => {
  const labelInputRef = useRef<HTMLInputElement>(null);
  const dialogRef = useRef<HTMLDivElement>(null);

  // Focus trap: keeps Tab inside the modal, Escape closes, restores focus on close
  useFocusTrap({
    isActive: isOpen,
    onClose,
    containerRef: dialogRef,
    autoFocus: false, // We handle auto-focus on the label input ourselves
  });

  // Metadata fields are read-only when the permission is denied AND the model is not new.
  const fieldsReadOnly = !isNew && !canEditMetadata;

  // Determine if label is still the default
  const isDefaultLabel = (label: string) => !label || label === DEFAULT_MODEL_LABEL;

  // Track if auto-generation of ID from label is active
  const [autoGenerateId, setAutoGenerateId] = useState(true);

  const [formData, setFormData] = useState<MetadataFormData>(() => {
    const label = metadata?.label || '';
    // If label is default, don't pre-populate the ID
    const id = isDefaultLabel(label) ? '' : (modelId || '');
    return {
      id,
      label,
      version: metadata?.version || '1.0.0',
      executable: metadata?.executable !== false,
      template: metadata?.template || false,
      storage: metadata?.storage || '',
      documentation: metadata?.documentation || '',
      tags: Array.isArray(metadata?.tags) ? metadata.tags : (metadata?.tags ? [metadata.tags] : []),
      changelog: metadata?.changelog || '',
    };
  });

  useEffect(() => {
    // Always update form data when modal opens or metadata changes
    if (isOpen) {
      const label = metadata?.label || '';
      // If label is default, don't pre-populate the ID and enable auto-generation
      const id = isDefaultLabel(label) ? '' : (modelId || '');
      setFormData({
        id,
        label,
        version: metadata?.version || '1.0.0',
        executable: metadata?.executable !== false,
        template: metadata?.template || false,
        storage: metadata?.storage || '',
        documentation: metadata?.documentation || '',
        tags: Array.isArray(metadata?.tags) ? metadata.tags : (metadata?.tags ? [metadata.tags] : []),
        changelog: metadata?.changelog || '',
      });
      // Enable auto-generation if ID is empty (default label case)
      setAutoGenerateId(!id);
    }
  }, [metadata, modelId, isOpen]);

  // Focus the label input when modal opens
  useEffect(() => {
    if (isOpen && labelInputRef.current) {
      // Small delay to ensure the modal is fully rendered
      const timeoutId = setTimeout(() => {
        labelInputRef.current?.focus();
        // Select all text if it's the default label
        if (isDefaultLabel(formData.label)) {
          labelInputRef.current?.select();
        }
      }, 50);
      return () => clearTimeout(timeoutId);
    }
  }, [isOpen, formData.label]);

  // Note: Escape key handling is provided by useFocusTrap above

  const handleChange = useCallback((field: keyof MetadataFormData, value: string | boolean | string[]) => {
    // Handle ID field changes - track auto-generation state
    if (field === 'id' && typeof value === 'string') {
      // If user clears the ID, re-enable auto-generation
      if (value === '') {
        setAutoGenerateId(true);
      } else {
        // User manually entered an ID, disable auto-generation
        setAutoGenerateId(false);
      }
    }

    setFormData(prev => {
      const newData = {
        ...prev,
        [field]: value
      };

      // Auto-derive ID from label when label changes (only if auto-generation is active and isNew)
      if (field === 'label' && isNew && autoGenerateId && typeof value === 'string') {
        newData.id = labelToSnakeCase(value);
      }

      return newData;
    });
  }, [isNew, autoGenerateId]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    // Destructure to separate id from other fields
    const { id: _id, ...formDataWithoutId } = formData;
    const processedData: MetadataFormData = {
      ...formDataWithoutId,
      tags: formData.tags,
    };
    // Include ID only for new models
    if (isNew) {
      processedData.id = formData.id || labelToSnakeCase(formData.label);
    }
    onSave(processedData);
    onClose();
  };

  if (!isOpen) return null;

  return (
    <div className="metadata-modal-overlay">
      <div className="metadata-modal" ref={dialogRef} role="dialog" aria-modal="true" aria-labelledby="metadata-modal-title">
        <div className="metadata-modal-header">
          <h2 id="metadata-modal-title">{t('Model Information')}</h2>
          <button type="button" className="close-btn" onClick={onClose} aria-label={t('Close')}>
            <FiX />
          </button>
        </div>
        
        <form onSubmit={handleSubmit} className="metadata-form">
          <div className="form-group">
            <label htmlFor="label">{t('Label')} *</label>
            <input
              ref={labelInputRef}
              type="text"
              id="label"
              value={formData.label}
              onChange={(e) => handleChange('label', e.target.value)}
              required
              readOnly={fieldsReadOnly}
            />
          </div>

          {isNew ? (
            <div className="form-group">
              <label htmlFor="model-id">
                <FiLink size={14} style={{ marginRight: '4px', verticalAlign: 'middle' }} />
                {t('Machine Name')}
              </label>
              <input
                type="text"
                id="model-id"
                value={formData.id || ''}
                onChange={(e) => handleChange('id', e.target.value)}
                placeholder={t('Auto-generated from label')}
                pattern="[a-z0-9_]+"
                title={t('Only lowercase letters, numbers, and underscores allowed')}
              />
              <small>
                {formData.id
                  ? t('Machine name (only lowercase letters, numbers, and underscores)')
                  : t('Will be auto-generated from label, or enter a custom value.')}
              </small>
            </div>
          ) : modelId && (
            <div className="form-group">
              <label>
                <FiLink size={14} style={{ marginRight: '4px', verticalAlign: 'middle' }} />
                {t('Machine Name')}
              </label>
              <div className="form-value-display">{modelId}</div>
            </div>
          )}

          <div className="form-group">
            <label htmlFor="version">{t('Version')}</label>
            <input
              type="text"
              id="version"
              value={formData.version}
              onChange={(e) => handleChange('version', e.target.value)}
              readOnly={fieldsReadOnly}
            />
          </div>

          <div className="form-group checkbox-group">
            <label>
              <input
                type="checkbox"
                checked={formData.executable}
                onChange={(e) => handleChange('executable', e.target.checked)}
                disabled={fieldsReadOnly}
              />
              <span className="checkmark"></span>
              {t('Enabled')}
            </label>
          </div>

          <div className="form-group checkbox-group">
            <label>
              <input
                type="checkbox"
                checked={formData.template}
                onChange={(e) => handleChange('template', e.target.checked)}
                disabled={fieldsReadOnly || !canCreateTemplate}
              />
              <span className="checkmark"></span>
              {t('Template')}
              <small>{t('If checked, the model will be used as a template for new models.')}</small>
            </label>
          </div>

          <div className="form-group">
            <label htmlFor="storage">
              {t('Storage of raw data')}
              <HelpTooltip text={t('Controls if and how the modeler\'s raw data (canvas layout and positioning) is being stored. This has no impact on the functionality of the current model. If the modeler\'s raw data is not stored, the canvas layout will be laid out automatically next time it gets loaded. If the raw data is stored with config, it makes that config entity slightly bigger, but everything is self-contained. Alternatively, the raw data can be stored in a separate config entity to keep the functional config small but keep the canvas layout around. The default uses the system setting for raw modeler data.')} />
            </label>
            <select
              id="storage"
              value={formData.storage}
              onChange={(e) => handleChange('storage', e.target.value)}
              disabled={fieldsReadOnly}
            >
              <option value="">{t('Default')}</option>
              <option value="none">{t('Do not store raw model data')}</option>
              <option value="separate">{t('Store raw data in separate config entity')}</option>
              <option value="third-party">{t('Store raw data with config as third-party setting')}</option>
            </select>
          </div>

          <div className="form-group">
            <label htmlFor="documentation">{t('Documentation')}</label>
            <textarea
              id="documentation"
              rows={4}
              value={formData.documentation}
              onChange={(e) => handleChange('documentation', e.target.value)}
              readOnly={fieldsReadOnly}
            />
          </div>

          <div className="form-group">
            <label htmlFor="tags">{t('Tags')}</label>
            <input
              type="text"
              id="tags"
              value={formData.tags.join(', ')}
              onChange={(e) => handleChange('tags', e.target.value.split(',').map(tag => tag.trim()).filter(tag => tag))}
              placeholder={t('Comma-separated list of tags')}
              readOnly={fieldsReadOnly}
            />
            <small>{t('Comma-separated list of tags.')}</small>
          </div>

          {!isNew && (
            <div className="form-group">
              <label htmlFor="changelog">{t('Changelog')}</label>
              <textarea
                id="changelog"
                rows={4}
                value={formData.changelog}
                onChange={(e) => handleChange('changelog', e.target.value)}
                readOnly={fieldsReadOnly}
              />
            </div>
          )}

          <div className="form-actions">
            <button type="button" onClick={onClose} className="btn btn-secondary">
              {fieldsReadOnly ? t('Close') : t('Cancel')}
            </button>
            {!fieldsReadOnly && (
              <button type="submit" className="btn btn-primary">
                {t('Save')}
              </button>
            )}
          </div>
        </form>
      </div>
    </div>
  );
};

export default MetadataModal;