/**
 * ReplayDataRenderer - Component for rendering token and step data in replay panel
 * 
 * Handles rendering of hierarchical token data with collapsible sections,
 * drag-and-drop token support, and various data type visualizations.
 */

import React, { useState, useCallback } from 'react';
import { FiChevronDown, FiChevronRight, FiMoreVertical } from 'react-icons/fi';
import { useFilterStore } from '../store/useFilterStore';
import { t } from '../utils/translation';

const MAX_DEPTH = Infinity;
const MAX_ITEMS_TO_SHOW = Infinity;

interface ReplayDataRendererProps {
  /** The data to render (can be token data or plain values) */
  data: any;
  /** Base path for section expansion tracking */
  basePath?: string;
}

/**
 * Component for rendering replay/token data with collapsible sections
 */
export const ReplayDataRenderer: React.FC<ReplayDataRendererProps> = ({
  data,
  basePath = '',
}) => {
  const setTokenDragging = useFilterStore(s => s.setTokenDragging);
  const [expandedSections, setExpandedSections] = useState(new Set<string>());

  const toggleSection = useCallback((sectionId: string) => {
    setExpandedSections(prev => {
      const newExpanded = new Set(prev);
      if (newExpanded.has(sectionId)) {
        newExpanded.delete(sectionId);
      } else {
        newExpanded.add(sectionId);
      }
      return newExpanded;
    });
  }, []);

  // Render a primitive value
  const renderPrimitiveValue = (value: any): React.ReactNode => {
    if (value === null) {
      return <span className="data-null">null</span>;
    }

    if (value === undefined) {
      return <span className="data-undefined">undefined</span>;
    }

    if (typeof value === 'boolean') {
      return <span className="data-boolean">{value ? 'true' : 'false'}</span>;
    }

    if (typeof value === 'number') {
      return <span className="data-number">{value}</span>;
    }

    if (typeof value === 'string') {
      return <span className="data-string">{value}</span>;
    }

    return <span className="data-unknown">{String(value)}</span>;
  };

  // Render any value (recursive)
  const renderDataValue = (value: any, path = '', depth = 0): React.ReactNode => {
    if (depth >= MAX_DEPTH) {
      return <span className="data-truncated">...</span>;
    }

    // Handle primitives
    if (value === null || value === undefined || typeof value !== 'object') {
      return renderPrimitiveValue(value);
    }

    // Handle arrays
    if (Array.isArray(value)) {
      if (value.length === 0) {
        return <span className="data-array">[]</span>;
      }

      const arrayPath = `${path}[]`;
      const isExpanded = expandedSections.has(arrayPath);

      return (
        <div className="data-array">
          <button className="data-header" onClick={() => toggleSection(arrayPath)} aria-expanded={isExpanded}>
            {isExpanded ? <FiChevronDown /> : <FiChevronRight />}
            <span>{t('Array (@count)', { '@count': value.length })}</span>
          </button>
          {isExpanded && (
            <div className="data-content nested">
              {value.slice(0, MAX_ITEMS_TO_SHOW).map((item, index) => (
                <div key={index} className="data-item">
                  <span className="data-key">[{index}]:</span>
                  {renderDataValue(item, `${arrayPath}[${index}]`, depth + 1)}
                </div>
              ))}
              {value.length > MAX_ITEMS_TO_SHOW && (
                <div className="data-truncated">
                  {t('...and @count more', { '@count': value.length - MAX_ITEMS_TO_SHOW })}
                </div>
              )}
            </div>
          )}
        </div>
      );
    }

    // Handle objects
    const keys = Object.keys(value);
    if (keys.length === 0) {
      return <span className="data-object">{'{}'}</span>;
    }

    const objectPath = `${path}{}`;
    const isExpanded = expandedSections.has(objectPath);

    return (
      <div className="data-object">
        <button className="data-header" onClick={() => toggleSection(objectPath)} aria-expanded={isExpanded}>
          {isExpanded ? <FiChevronDown /> : <FiChevronRight />}
          <span>{t('Object (@count)', { '@count': keys.length })}</span>
        </button>
        {isExpanded && (
          <div className="data-content nested">
            {keys.slice(0, MAX_ITEMS_TO_SHOW).map((key) => (
              <div key={key} className="data-item">
                <span className="data-key">{key}:</span>
                {renderDataValue(value[key], `${objectPath}.${key}`, depth + 1)}
              </div>
            ))}
            {keys.length > MAX_ITEMS_TO_SHOW && (
              <div className="data-truncated">
                {t('...and @count more', { '@count': keys.length - MAX_ITEMS_TO_SHOW })}
              </div>
            )}
          </div>
        )}
      </div>
    );
  };

  // Handle drag start for tokens
  const handleTokenDragStart = (e: React.DragEvent, tokenData: { label: string; token: string }) => {
    e.dataTransfer.setData('text/plain', tokenData.token);
    e.dataTransfer.setData('application/token', JSON.stringify(tokenData));
    setTokenDragging(true);
  };

  // Handle drag end for tokens
  const handleTokenDragEnd = useCallback(() => {
    setTokenDragging(false);
  }, [setTokenDragging]);

  // Render token data (normalized structure with label/token/value/data)
  const renderTokenData = (tokenData: any, path = '', depth = 0): React.ReactNode => {
    if (depth >= MAX_DEPTH) {
      return <span className="data-truncated">...</span>;
    }

    // Check if this is the token data structure with label property
    if (tokenData && typeof tokenData === 'object' && 'label' in tokenData) {
      const tokenPath = `${path}${tokenData.label || 'item'}`;
      const hasValue = 'value' in tokenData;
      const hasData = tokenData.data && typeof tokenData.data === 'object';

      // Handle items that have both value AND data
      if (hasValue || hasData) {
        const isExpanded = expandedSections.has(tokenPath);
        const itemCount = hasData 
          ? (Array.isArray(tokenData.data) ? tokenData.data.length : Object.keys(tokenData.data).length) 
          : 0;

        return (
          <div className="token-group">
            <div className="token-header">
              <div className="token-label-group">
                {hasData ? (
                  <button 
                    className="token-expand-btn"
                    onClick={() => toggleSection(tokenPath)} 
                    aria-expanded={isExpanded}
                  >
                    {isExpanded ? <FiChevronDown /> : <FiChevronRight />}
                    <span
                      className="token-label"
                      title={isExpanded 
                        ? t('Click to collapse @label', { '@label': tokenData.label }) 
                        : t('Click to expand @label', { '@label': tokenData.label })}
                    >
                      {tokenData.label}
                    </span>
                    {tokenData.token && (
                      <span className="token-string" style={{ display: 'none' }}>{tokenData.token}</span>
                    )}
                    {hasData && (
                      <span className="token-count">({itemCount})</span>
                    )}
                  </button>
                ) : (
                  <>
                    {tokenData.token ? (
                      <span className="token-drag-icon" aria-hidden="true"><FiMoreVertical /></span>
                    ) : (
                      <span className="token-label-spacer"></span>
                    )}
                    <span
                      className={`token-label ${tokenData.token ? 'draggable' : ''}`}
                      draggable={!!tokenData.token}
                      onDragStart={(e) => {
                        if (tokenData.token) {
                          handleTokenDragStart(e, { label: tokenData.label, token: tokenData.token });
                        }
                      }}
                      onDragEnd={handleTokenDragEnd}
                      title={tokenData.token ? t('Drag to insert token: @token', { '@token': tokenData.token }) : ''}
                    >
                      {tokenData.label}
                    </span>
                    {tokenData.token && (
                      <span className="token-string" style={{ display: 'none' }}>{tokenData.token}</span>
                    )}
                  </>
                )}
              </div>
              {hasValue && (
                <span className="token-value">{renderDataValue(tokenData.value, `${tokenPath}.value`, depth + 1)}</span>
              )}
            </div>
            {hasData && isExpanded && (
              <div className="token-content nested">
                {Array.isArray(tokenData.data) ? (
                  // Handle array data
                  tokenData.data.map((item: any, index: number) => (
                    <div key={index} className="token-array-item">
                      {renderTokenData(item, `${tokenPath}[${index}]`, depth + 1)}
                    </div>
                  ))
                ) : (
                  // Handle object data
                  Object.entries(tokenData.data).map(([key, item]) => (
                    <div key={key} className="token-object-item">
                      {renderTokenData(item, `${tokenPath}.${key}`, depth + 1)}
                    </div>
                  ))
                )}
              </div>
            )}
          </div>
        );
      }

      // Fallback for items with label but no value or data
      return (
        <div className="token-item">
          <div className="token-header">
            <div className="token-label-group">
              {tokenData.token ? (
                <span className="token-drag-icon" aria-hidden="true"><FiMoreVertical /></span>
              ) : (
                <span className="token-label-spacer"></span>
              )}
              <span
                className={`token-label ${tokenData.token ? 'draggable' : ''}`}
                draggable={!!tokenData.token}
                onDragStart={(e) => {
                  if (tokenData.token) {
                    handleTokenDragStart(e, { label: tokenData.label, token: tokenData.token });
                  }
                }}
                onDragEnd={handleTokenDragEnd}
                title={tokenData.token ? t('Drag to insert token: @token', { '@token': tokenData.token }) : ''}
              >
                {tokenData.label}
              </span>
              {tokenData.token && (
                <span className="token-string" style={{ display: 'none' }}>{tokenData.token}</span>
              )}
            </div>
          </div>
        </div>
      );
    }

    // For non-token data structures, fall back to regular rendering
    return renderDataValue(tokenData, path, depth);
  };

  return <>{renderTokenData(data, basePath)}</>;
};

/**
 * Container component for rendering step data from replay
 */
interface StepDataContainerProps {
  /** Step data object to render */
  stepData: Record<string, any>;
}

export const StepDataContainer: React.FC<StepDataContainerProps> = ({ stepData }) => {
  return (
    <div className="token-data-container">
      {Object.entries(stepData).map(([key, value]) => {
        // If value doesn't have a label, add the key as the label
        const dataWithLabel = (value && typeof value === 'object' && 'label' in value)
          ? value
          : { label: key, ...(typeof value === 'object' && value !== null ? value : { value }) };
        return (
          <div key={key}>
            <ReplayDataRenderer data={dataWithLabel} basePath={`tokenData.${key}`} />
          </div>
        );
      })}
    </div>
  );
};

/**
 * Transform a Drupal global token entry into the ReplayDataRenderer format.
 *
 * Drupal sends: { name, description, dynamic, "raw token", token, value, children? }
 * Renderer expects: { label, token, value, data? }
 */
function transformGlobalToken(entry: Record<string, any>): Record<string, any> {
  const result: Record<string, any> = {
    label: entry.name || entry.token || '',
    // eslint-disable-next-line i18n/no-untranslated-strings -- property key, not user-facing
    token: entry['raw token'] || '',
  };
  if ('value' in entry) {
    result.value = entry.value;
  }
  if (entry.children && typeof entry.children === 'object') {
    const childData: Record<string, any> = {};
    for (const [childKey, childEntry] of Object.entries(entry.children)) {
      childData[childKey] = transformGlobalToken(childEntry as Record<string, any>);
    }
    result.data = childData;
  }
  return result;
}

/**
 * Container component for rendering global tokens from drupalSettings.
 */
interface GlobalTokensContainerProps {
  /** Global tokens object from drupalSettings.modeler_api.global_tokens */
  globalTokens: Record<string, any>;
}

export const GlobalTokensContainer: React.FC<GlobalTokensContainerProps> = ({ globalTokens }) => {
  return (
    <div className="token-data-container">
      {Object.entries(globalTokens).map(([key, entry]) => {
        const transformed = transformGlobalToken(entry as Record<string, any>);
        return (
          <div key={key}>
            <ReplayDataRenderer data={transformed} basePath={`globalTokens.${key}`} />
          </div>
        );
      })}
    </div>
  );
};

/**
 * Container component for rendering template tokens from drupalSettings.
 * Uses the same GlobalToken structure and transformation as global tokens.
 */
interface TemplateTokensContainerProps {
  /** Template tokens object from drupalSettings.modeler_api.template_tokens */
  templateTokens: Record<string, any>;
}

export const TemplateTokensContainer: React.FC<TemplateTokensContainerProps> = ({ templateTokens }) => {
  return (
    <div className="token-data-container">
      {Object.entries(templateTokens).map(([key, entry]) => {
        const transformed = transformGlobalToken(entry as Record<string, any>);
        return (
          <div key={key}>
            <ReplayDataRenderer data={transformed} basePath={`templateTokens.${key}`} />
          </div>
        );
      })}
    </div>
  );
};

export default ReplayDataRenderer;
