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
import { sanitizeHtml } from '../utils/sanitize';
import { t } from '../utils/translation';
import ContentEditableField from './ContentEditableField';
import YamlEditor from './YamlEditor';
import type { YamlSchema } from './YamlEditor';

/**
 * A single normalized Drupal #states condition.
 *
 * Mirrors the structure emitted by the backend FormToJsonConverter: the
 * selector ":input[name=\"KEY\"]" is simplified to the bare field key, and the
 * common Drupal condition keys (value / checked / empty) are carried verbatim.
 */
interface StateCondition {
  /** The (flat) field key this condition observes. */
  field: string;
  /**
   * Match when the observed field equals this value. When an array is given,
   * match when the observed field equals ANY listed value (Drupal's
   * "equals any" semantics). The backend normally expands array values into
   * OR groups, but the array form is accepted here for robustness.
   */
  value?: (string | number | boolean) | (string | number | boolean)[];
  /** Match when the observed field's checked state equals this. */
  checked?: boolean;
  /** Match when the observed field's empty state equals this. */
  empty?: boolean;
}

/**
 * A group of conditions combined with logical AND. All conditions in a group
 * must hold for the group to match.
 */
type StateGroup = StateCondition[];

/**
 * Normalized Drupal #states, keyed by state type. Each value is a list of
 * OR groups: conditions within a group combine with logical AND, and groups
 * combine with logical OR (the state holds when ANY group fully matches).
 */
interface FieldStates {
  visible?: StateGroup[];
  invisible?: StateGroup[];
  required?: StateGroup[];
  optional?: StateGroup[];
}

interface FormField {
  key: string;
  type: string;
  /**
   * Widget format derived from the field's config-schema contract (e.g. a Json
   * constraint → 'json'), independent of the Drupal form element type. Lets the
   * modeler pick a specialized editor without hard-coding vendor type names.
   */
  format?: string;
  title?: string;
  description?: string;
  placeholder?: string;
  required?: boolean;
  default_value?: unknown;
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
  /**
   * Normalized Drupal #states driving conditional visibility / required
   * behavior. Evaluated against the flat `values` map by the states engine.
   */
  states?: FieldStates;
  /**
   * Child fields for a `group` (details / fieldset / container) field. Child
   * values flow through the SAME flat `values` map keyed by their own field
   * key, mirroring Drupal's flat form-value structure.
   */
  children?: FormField[];
  /** For `details` groups: whether the group starts expanded (default true). */
  open?: boolean;
  /**
   * Empty/placeholder option for a `select`, decided and labeled server-side
   * (PHP owns the empty-option rule). When present, the UI renders it as the
   * first option; when absent, no empty option is rendered. The label is
   * already translated server-side and is rendered verbatim.
   */
  empty_option?: { value: string; label: string };
}

interface ConfigurationFormProps {
  form?: FormField[] | null;
  configuration?: Record<string, unknown> | null;
  onChange?: (values: Record<string, unknown>) => void;
  disabled?: boolean;
}

/**
 * Determine whether a single #states condition currently holds, given the
 * flat form values.
 */
function evaluateCondition(condition: StateCondition, values: Record<string, unknown>): boolean {
  const current = values[condition.field];
  if (condition.value !== undefined) {
    // An array value means "equals any listed value" (Drupal's match-any).
    if (Array.isArray(condition.value)) {
      return condition.value.some((v) => String(current ?? '') === String(v));
    }
    return String(current ?? '') === String(condition.value);
  }
  if (condition.checked !== undefined) {
    return Boolean(current) === condition.checked;
  }
  if (condition.empty !== undefined) {
    // A value is "empty" when it is null/undefined, an empty string, or an
    // empty array (mirroring Drupal's notion of empty form values).
    const isEmpty =
      current === null ||
      current === undefined ||
      current === '' ||
      (Array.isArray(current) && current.length === 0);
    return isEmpty === condition.empty;
  }
  // An unsupported / empty condition object never matches.
  return false;
}

/**
 * Evaluate a single group with logical AND: every condition in the group must
 * hold. An empty group trivially holds.
 */
function evaluateGroup(group: StateGroup, values: Record<string, unknown>): boolean {
  return group.every((condition) => evaluateCondition(condition, values));
}

/**
 * Evaluate a list of OR groups: the state holds when ANY group fully matches.
 * An empty or missing list is treated as "not applicable" and trivially holds,
 * so callers can guard on the presence of the state type.
 */
function evaluateGroups(groups: StateGroup[] | undefined, values: Record<string, unknown>): boolean {
  if (!groups || groups.length === 0) {
    return true;
  }
  return groups.some((group) => evaluateGroup(group, values));
}

/**
 * The resolved presentation state for a field after applying its #states.
 */
interface ResolvedFieldState {
  hidden: boolean;
  required: boolean;
}

/**
 * Resolve a field's visibility and required state from its #states against the
 * current flat form values.
 */
function resolveFieldState(field: FormField, values: Record<string, unknown>): ResolvedFieldState {
  let hidden = false;
  let required = !!field.required;

  const states = field.states;
  if (states) {
    if (states.visible) {
      // Field is shown only while ANY visible group holds.
      hidden = hidden || !evaluateGroups(states.visible, values);
    }
    if (states.invisible) {
      // Field is hidden while ANY invisible group holds.
      hidden = hidden || evaluateGroups(states.invisible, values);
    }
    if (states.required) {
      required = evaluateGroups(states.required, values);
    }
    if (states.optional) {
      // Optional is the inverse of required.
      required = !evaluateGroups(states.optional, values);
    }
  }

  return { hidden, required };
}

/**
 * Render a single form field based on its type
 */
const FormFieldRenderer: React.FC<{
  field: FormField;
  value: unknown;
  onChange: (value: unknown) => void;
  disabled: boolean;
  acceptsTokens: boolean;
  /** Resolved required state (after applying #states). Drives the input's required attribute. */
  required: boolean;
  /** When true, the textarea should switch to the YAML editor (no schema). */
  useYaml?: boolean;
  /** When true (and useYaml is true), validate YAML syntax while typing. */
  validateYaml?: boolean;
}> = ({ field, value, onChange, disabled, acceptsTokens, required, useYaml, validateYaml }) => {
  const currentValue = value ?? field.default_value ?? '';
  // String-coerced view of the current value for string-based widgets
  // (ContentEditableField, native text/number/select inputs).
  const stringValue = typeof currentValue === 'string' ? currentValue : String(currentValue ?? '');

  // Schema-derived format wins over the raw element type: a field whose config
  // schema declares a Json constraint gets the JSON editor regardless of which
  // Drupal form element produced it. The JSON editor reuses the schema-less
  // YamlEditor in JSON mode (textarea + inline validation + Format action).
  if (field.format === 'json') {
    return (
      <YamlEditor
        value={typeof currentValue === 'string' ? currentValue : ''}
        onChange={onChange}
        disabled={disabled}
        format="json"
        validate
      />
    );
  }

  switch (field.type) {
    case 'textfield':
    case 'email':
    case 'url':
      return (
        <ContentEditableField
          value={stringValue}
          onChange={onChange}
          className="form-control"
          placeholder={field.placeholder || t('Enter @field...', { '@field': field.title || field.type })}
          disabled={disabled}
          multiline={false}
          acceptsTokens={acceptsTokens}
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
          value={stringValue}
          onChange={onChange}
          className="form-control"
          placeholder={field.placeholder || t('Enter @field...', { '@field': field.title || t('text') })}
          disabled={disabled}
          multiline={true}
          acceptsTokens={acceptsTokens}
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
            value={stringValue}
            onChange={onChange}
            className="form-control"
            placeholder={field.placeholder || t('Enter @field...', { '@field': field.title || field.type })}
            disabled={disabled}
            multiline={false}
            acceptsTokens={acceptsTokens}
          />
        );
      }
      return (
        <input
          id={`config-field-${field.key}`}
          type="number"
          value={stringValue}
          onChange={(e) => onChange(e.target.value)}
          className="form-control"
          min={field.min}
          max={field.max}
          step={field.step}
          required={required}
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
            required={required}
            disabled={disabled}
          />
          <span className="checkbox-label">{field.title}</span>
        </label>
      );

    case 'select':
      return (
        <select
          id={`config-field-${field.key}`}
          value={stringValue}
          onChange={(e) => onChange(e.target.value)}
          className="form-control"
          required={required}
          disabled={disabled}
        >
          {field.empty_option && (
            <option value={field.empty_option.value}>{field.empty_option.label}</option>
          )}
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
                checked={stringValue === optionKey}
                onChange={(e) => onChange(e.target.value)}
                required={required}
                disabled={disabled}
              />
              <span className="radio-label">{optionLabel}</span>
            </label>
          ))}
        </div>
      );

    case 'checkboxes': {
      const checkboxValues: string[] = Array.isArray(currentValue) ? (currentValue as string[]) : [];
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
          value={stringValue}
          onChange={(e) => onChange(e.target.value)}
          className="form-control"
          required={required}
          disabled={disabled}
        />
      );
  }
};

/**
 * Collapsible/static container wrapping a group's child fields.
 *
 * `details` groups render as native <details>/<summary> (collapsible,
 * honoring the backend `open` flag); `fieldset`/`container` groups render as a
 * static titled container.
 */
const FieldGroup: React.FC<{
  field: FormField;
  children: React.ReactNode;
}> = ({ field, children }) => {
  if (field.type === 'group' && field.open !== undefined) {
    // Details-style group: collapsible via native disclosure widget.
    return (
      <details className="form-group form-group-details" open={field.open}>
        {field.title && <summary className="form-group-title">{field.title}</summary>}
        <div className="form-group-body">{children}</div>
      </details>
    );
  }
  // Fieldset / container style: static titled container.
  return (
    <div className="form-group">
      {field.title && <div className="form-group-title">{field.title}</div>}
      <div className="form-group-body">{children}</div>
    </div>
  );
};

const ConfigurationForm: React.FC<ConfigurationFormProps> = ({
  form,
  configuration,
  onChange,
  disabled = false
}) => {
  // Initialize state with configuration
  const [values, setValues] = useState<Record<string, unknown>>(configuration || {});

  const handleFieldChange = useCallback((fieldKey: string, value: unknown) => {
    const newValues = { ...values, [fieldKey]: value };
    setValues(newValues);
    if (onChange) {
      onChange(newValues);
    }
  }, [values, onChange]);

  // Determine whether all fields accept tokens (when replace_tokens checkbox is checked)
  const replaceTokensEnabled = useMemo(() => {
    if (!form || !Array.isArray(form)) return false;
    // Look through nested groups too, since replace_tokens may live in a group.
    const hasReplaceTokens = (fields: FormField[]): boolean =>
      fields.some(
        (f) =>
          (f.key === 'replace_tokens' && f.type === 'checkbox') ||
          (f.children ? hasReplaceTokens(f.children) : false)
      );
    if (!hasReplaceTokens(form)) return false;
    return !!values.replace_tokens;
  }, [form, values.replace_tokens]);

  // Build a lookup: for each textarea key, find whether a use_yaml and/or
  // validate_yaml checkbox targets it via the yaml_field annotation. Walks
  // nested group children so linked checkboxes can live inside a group.
  const yamlFieldMap = useMemo(() => {
    const map: Record<string, { useYamlKey: string; validateYamlKey: string }> = {};
    const walk = (fields: FormField[]): void => {
      for (const f of fields) {
        if (f.key === 'use_yaml' && f.yaml_field) {
          if (!map[f.yaml_field]) map[f.yaml_field] = { useYamlKey: '', validateYamlKey: '' };
          map[f.yaml_field].useYamlKey = f.key;
        }
        if (f.key === 'validate_yaml' && f.yaml_field) {
          if (!map[f.yaml_field]) map[f.yaml_field] = { useYamlKey: '', validateYamlKey: '' };
          map[f.yaml_field].validateYamlKey = f.key;
        }
        if (f.children) walk(f.children);
      }
    };
    if (form && Array.isArray(form)) walk(form);
    return map;
  }, [form]);

  /**
   * Recursively render a single field (or group of fields). Group children
   * reuse this same path, so #states visibility/required and token support
   * apply to nested children too. All values stay flat, keyed by field key.
   */
  const renderField = useCallback((field: FormField): React.ReactNode => {
    // A field accepts tokens if replace_tokens is checked globally,
    // or if the field has token_support set to true.
    const fieldAcceptsTokens = replaceTokensEnabled || !!field.token_support;

    // Resolve visibility / required from the generic #states engine.
    const { hidden: stateHidden, required } = resolveFieldState(field, values);

    // Minimal fallback for ECA's validate_yaml checkbox: when the backend
    // does NOT supply #states for it, keep the legacy behavior of hiding it
    // until use_yaml is checked. When the backend DOES supply states, the
    // generic engine above already handles it.
    const yamlFallbackHidden =
      !field.states &&
      field.key === 'validate_yaml' &&
      !!field.yaml_field &&
      !values.use_yaml;

    const hideField = stateHidden || yamlFallbackHidden;

    // Group field: render the container + recurse into children.
    if (field.type === 'group' && field.children) {
      return (
        <div
          key={field.key}
          className="form-field form-field-group"
          style={hideField ? { display: 'none' } : undefined}
        >
          <FieldGroup field={field}>
            {field.children.map((child) => renderField(child))}
          </FieldGroup>
        </div>
      );
    }

    // Determine use_yaml / validate_yaml state for textarea fields.
    const yamlLink = field.type === 'textarea' ? yamlFieldMap[field.key] : undefined;
    const useYaml = yamlLink ? !!values[yamlLink.useYamlKey] : false;
    const validateYaml = yamlLink ? !!values[yamlLink.validateYamlKey] : false;

    return (
      <div
        key={field.key}
        className="form-field"
        style={hideField ? { display: 'none' } : undefined}
      >
        {field.type !== 'checkbox' && field.type !== 'markup' && field.title && (
          <label className="field-label" htmlFor={`config-field-${field.key}`}>
            {field.title}
            {required && <span className="required">*</span>}
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
            required={required}
            useYaml={useYaml}
            validateYaml={validateYaml}
          />
        </div>

        {field.description && (
          <div className="field-description" dangerouslySetInnerHTML={{ __html: sanitizeHtml(field.description) }} />
        )}
      </div>
    );
  }, [values, disabled, replaceTokensEnabled, yamlFieldMap, handleFieldChange]);

  if (!form || !Array.isArray(form)) {
    return <div className="no-configuration">{t('No configuration available')}</div>;
  }

  return (
    <div className="configuration-form">
      {form.map((field) => renderField(field))}
    </div>
  );
};

export default ConfigurationForm;
