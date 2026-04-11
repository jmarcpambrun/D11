/**
 * ConfigurationForm - Dynamic form component for workflow element configuration
 * 
 * Renders form fields based on a schema provided by the backend.
 * Supports various field types including text, textarea, select, checkboxes,
 * and rich text fields with token support.
 *
 * Textarea fields that include an inline `yaml_schema` (discovered
 * automatically from Drupal config schema) are rendered with a structured
 * YAML editor widget instead of a plain content-editable area.
 */

import React, { useState, useCallback, useMemo } from 'react';
import yaml from 'js-yaml';
import { useFilterStore } from '../store/useFilterStore';
import { sanitizeHtml } from '../utils/sanitize';
import { t } from '../utils/translation';
import ContentEditableField from './ContentEditableField';
import YamlEditor from './YamlEditor';
import type { YamlSchema } from './YamlEditor';

interface FormField {
  key: string;
  type: string;
  title?: string;
  description?: string;
  placeholder?: string;
  required?: boolean;
  default_value?: any;
  min?: number;
  max?: number;
  step?: number;
  options?: Record<string, string>;
  markup?: string;
  token_support?: boolean;
  /**
   * Inline YAML schema discovered from Drupal config schema.
   * When present on a textarea field, the structured YAML editor is rendered.
   * The backend auto-discovers this from a config schema definition at
   * "yaml.{plugin_schema_key}.{field_key}".
   */
  yaml_schema?: YamlSchema;
  /**
   * For use_yaml / validate_yaml checkboxes: the key of the textarea field
   * they control.  Set by the backend when it detects ECA's
   * FormFieldYamlTrait pattern.
   */
  yaml_field?: string;
}

interface ConfigurationFormProps {
  form?: FormField[] | null;
  configuration?: Record<string, any> | null;
  onChange?: (values: Record<string, any>) => void;
  disabled?: boolean;
}

/**
 * Render a single form field based on its type
 */
const FormFieldRenderer: React.FC<{
  field: FormField;
  value: any;
  onChange: (value: any) => void;
  disabled: boolean;
  acceptsTokens: boolean;
  isTokenDragging: boolean;
  /** When true, the textarea should switch to the YAML editor (no schema). */
  useYaml?: boolean;
  /** When true (and useYaml is true), validate YAML syntax while typing. */
  validateYaml?: boolean;
}> = ({ field, value, onChange, disabled, acceptsTokens, isTokenDragging, useYaml, validateYaml }) => {
  const currentValue = value ?? field.default_value ?? '';

  switch (field.type) {
    case 'textfield':
    case 'email':
    case 'url':
      return (
        <ContentEditableField
          value={currentValue}
          onChange={onChange}
          className="form-control"
          placeholder={field.placeholder || t('Enter @field...', { '@field': field.title || field.type })}
          disabled={disabled}
          multiline={false}
          acceptsTokens={acceptsTokens}
          isTokenDragging={isTokenDragging}
        />
      );

    case 'textarea': {
      // Coerce non-string values to YAML strings for the YAML editor.
      const coerceToYaml = (val: unknown): string => {
        if (typeof val === 'string') return val;
        if (val === null || val === undefined) return '';
        try {
          return yaml.dump(val, {
            indent: 2,
            lineWidth: -1,
            noRefs: true,
            sortKeys: false,
          }).replace(/\n$/, '');
        } catch {
          return String(val);
        }
      };

      // If the backend provided an inline YAML schema, render the
      // structured editor. The schema is discovered automatically from
      // Drupal config schema at "yaml.{plugin_schema_key}.{field_key}".
      if (field.yaml_schema) {
        return (
          <YamlEditor
            value={coerceToYaml(currentValue)}
            onChange={onChange}
            schema={field.yaml_schema}
            disabled={disabled}
          />
        );
      }

      // When the use_yaml checkbox is checked for this textarea, render
      // a schema-less YAML editor (raw YAML mode only, with optional
      // syntax validation controlled by validate_yaml).
      if (useYaml) {
        return (
          <YamlEditor
            value={coerceToYaml(currentValue)}
            onChange={onChange}
            disabled={disabled}
            validate={validateYaml}
          />
        );
      }

      return (
        <ContentEditableField
          value={currentValue}
          onChange={onChange}
          className="form-control"
          placeholder={field.placeholder || t('Enter @field...', { '@field': field.title || t('text') })}
          disabled={disabled}
          multiline={true}
          acceptsTokens={acceptsTokens}
          isTokenDragging={isTokenDragging}
        />
      );
    }

    case 'number': {
      // Use ContentEditableField when tokens are accepted, or when the
      // current value already contains a token pattern (to prevent data
      // loss when loading models with token values in number fields).
      const hasTokenValue = typeof currentValue === 'string' && /\[.+:.+\]/.test(currentValue);
      if (acceptsTokens || hasTokenValue) {
        return (
          <ContentEditableField
            value={currentValue}
            onChange={onChange}
            className="form-control"
            placeholder={field.placeholder || t('Enter @field...', { '@field': field.title || field.type })}
            disabled={disabled}
            multiline={false}
            acceptsTokens={acceptsTokens}
            isTokenDragging={isTokenDragging}
          />
        );
      }
      return (
        <input
          id={`config-field-${field.key}`}
          type="number"
          value={currentValue}
          onChange={(e) => onChange(e.target.value)}
          className="form-control"
          min={field.min}
          max={field.max}
          step={field.step}
          required={field.required}
          disabled={disabled}
        />
      );
    }

    case 'checkbox':
      return (
        <label className="checkbox-wrapper">
          <input
            type="checkbox"
            checked={!!currentValue}
            onChange={(e) => onChange(e.target.checked)}
            required={field.required}
            disabled={disabled}
          />
          <span className="checkbox-label">{field.title}</span>
        </label>
      );

    case 'select':
      return (
        <select
          id={`config-field-${field.key}`}
          value={currentValue}
          onChange={(e) => onChange(e.target.value)}
          className="form-control"
          required={field.required}
          disabled={disabled}
        >
          <option value="">{t('- Select -')}</option>
          {field.options && Object.entries(field.options).map(([optionKey, optionLabel]) => (
            <option key={optionKey} value={optionKey}>
              {optionLabel}
            </option>
          ))}
        </select>
      );

    case 'radios':
      return (
        <div className="radio-group">
          {field.options && Object.entries(field.options).map(([optionKey, optionLabel]) => (
            <label key={optionKey} className="radio-wrapper">
              <input
                type="radio"
                name={field.key}
                value={optionKey}
                checked={currentValue === optionKey}
                onChange={(e) => onChange(e.target.value)}
                required={field.required}
                disabled={disabled}
              />
              <span className="radio-label">{optionLabel}</span>
            </label>
          ))}
        </div>
      );

    case 'checkboxes': {
      const checkboxValues = Array.isArray(currentValue) ? currentValue : [];
      return (
        <div className="checkbox-group">
          {field.options && Object.entries(field.options).map(([optionKey, optionLabel]) => (
            <label key={optionKey} className="checkbox-wrapper">
              <input
                type="checkbox"
                value={optionKey}
                checked={checkboxValues.includes(optionKey)}
                onChange={(e) => {
                  const newValues = e.target.checked
                    ? [...checkboxValues, optionKey]
                    : checkboxValues.filter((v: string) => v !== optionKey);
                  onChange(newValues);
                }}
                disabled={disabled}
              />
              <span className="checkbox-label">{optionLabel}</span>
            </label>
          ))}
        </div>
      );
    }

    case 'markup':
      return (
        <div className="markup-content" dangerouslySetInnerHTML={{ __html: sanitizeHtml(field.markup) }} />
      );

    default:
      return (
        <input
          id={`config-field-${field.key}`}
          type="text"
          value={currentValue}
          onChange={(e) => onChange(e.target.value)}
          className="form-control"
          required={field.required}
          disabled={disabled}
        />
      );
  }
};

const ConfigurationForm: React.FC<ConfigurationFormProps> = ({
  form,
  configuration,
  onChange,
  disabled = false
}) => {
  // Initialize state with configuration
  const [values, setValues] = useState<Record<string, any>>(configuration || {});
  const isTokenDragging = useFilterStore(s => s.isTokenDragging);

  const handleFieldChange = useCallback((fieldKey: string, value: any) => {
    const newValues = { ...values, [fieldKey]: value };
    setValues(newValues);
    if (onChange) {
      onChange(newValues);
    }
  }, [values, onChange]);

  // Determine whether all fields accept tokens (when replace_tokens checkbox is checked)
  const replaceTokensEnabled = useMemo(() => {
    if (!form || !Array.isArray(form)) return false;
    const replaceTokensField = form.find(
      (f) => f.key === 'replace_tokens' && f.type === 'checkbox'
    );
    if (!replaceTokensField) return false;
    return !!values.replace_tokens;
  }, [form, values.replace_tokens]);

  // Build a lookup: for each textarea key, find whether a use_yaml and/or
  // validate_yaml checkbox targets it via the yaml_field annotation.
  const yamlFieldMap = useMemo(() => {
    const map: Record<string, { useYamlKey: string; validateYamlKey: string }> = {};
    if (!form || !Array.isArray(form)) return map;
    for (const f of form) {
      if (f.key === 'use_yaml' && f.yaml_field) {
        if (!map[f.yaml_field]) map[f.yaml_field] = { useYamlKey: '', validateYamlKey: '' };
        map[f.yaml_field].useYamlKey = f.key;
      }
      if (f.key === 'validate_yaml' && f.yaml_field) {
        if (!map[f.yaml_field]) map[f.yaml_field] = { useYamlKey: '', validateYamlKey: '' };
        map[f.yaml_field].validateYamlKey = f.key;
      }
    }
    return map;
  }, [form]);

  if (!form || !Array.isArray(form)) {
    return <div className="no-configuration">{t('No configuration available')}</div>;
  }

  return (
    <div className="configuration-form">
      {form.map((field) => {
        // A field accepts tokens if replace_tokens is checked globally,
        // or if the field has token_support set to true
        const fieldAcceptsTokens = replaceTokensEnabled || !!field.token_support;

        // Determine use_yaml / validate_yaml state for textarea fields.
        const yamlLink = field.type === 'textarea' ? yamlFieldMap[field.key] : undefined;
        const useYaml = yamlLink ? !!values[yamlLink.useYamlKey] : false;
        const validateYaml = yamlLink ? !!values[yamlLink.validateYamlKey] : false;

        // Hide use_yaml / validate_yaml checkboxes — they are shown inline
        // via the YAML editor toggle, not as standalone form fields.
        // Actually, we keep them visible so the user can toggle the behavior.
        // The validate_yaml checkbox should only be visible when use_yaml
        // is checked, mirroring the Drupal #states behavior.
        const hideField =
          (field.key === 'validate_yaml' && field.yaml_field && !values.use_yaml);

        return (
          <div
            key={field.key}
            className={`form-field ${isTokenDragging && !fieldAcceptsTokens ? 'token-drop-disabled' : ''} ${isTokenDragging && fieldAcceptsTokens ? 'token-drop-enabled' : ''}`}
            style={hideField ? { display: 'none' } : undefined}
          >
            {field.type !== 'checkbox' && field.type !== 'markup' && field.title && (
              <label className="field-label" htmlFor={`config-field-${field.key}`}>
                {field.title}
                {field.required && <span className="required">*</span>}
              </label>
            )}

            {field.type === 'markup' && field.title && (
              <h4 className="markup-title">{field.title}</h4>
            )}

            <div className="field-input">
              <FormFieldRenderer
                field={field}
                value={values[field.key]}
                onChange={(value) => handleFieldChange(field.key, value)}
                disabled={disabled}
                acceptsTokens={fieldAcceptsTokens}
                isTokenDragging={isTokenDragging}
                useYaml={useYaml}
                validateYaml={validateYaml}
              />
            </div>

            {field.description && (
              <div className="field-description" dangerouslySetInnerHTML={{ __html: sanitizeHtml(field.description) }} />
            )}
          </div>
        );
      })}
    </div>
  );
};

export default ConfigurationForm;
