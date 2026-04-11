/**
 * EdgePropertiesPanel - Renders the property panel content for a single
 * selected edge. Shows condition label, annotation textarea, configuration
 * form, and delete button.
 */
import React, { useCallback } from 'react';
import { FiTrash2 } from 'react-icons/fi';
import ConfigurationForm from './ConfigurationForm';
import type { StoreEdge as Edge } from '../types/settings';
import { t } from '../utils/translation';
import { useDebouncedField } from '../hooks/useDebouncedField';
import { useTokenDragPrevention } from '../hooks/useTokenDragPrevention';

interface EdgePropertiesPanelProps {
  edge: Edge;
  configurationForm: any;
  onEdgeConfigurationChange?: (edgeId: string, configuration: Record<string, any> | null) => void;
  onEdgeUpdate?: (edgeId: string, data: any) => void;
  isLocked: boolean;
  edgeLabelField: ReturnType<typeof useDebouncedField>;
  edgeAnnotationField: ReturnType<typeof useDebouncedField>;
}

const EdgePropertiesPanel: React.FC<EdgePropertiesPanelProps> = ({
  edge,
  configurationForm,
  onEdgeConfigurationChange,
  onEdgeUpdate: _onEdgeUpdate,
  isLocked,
  edgeLabelField,
  edgeAnnotationField,
}) => {
  const { isTokenDragging, handleNativeFieldDragOver, handleNativeFieldDrop } = useTokenDragPrevention();

  const handleDeleteCondition = useCallback(() => {
    if (edge && onEdgeConfigurationChange) {
      onEdgeConfigurationChange(edge.id, null);
    }
  }, [edge, onEdgeConfigurationChange]);

  return (
    <div className="panel-content">
      {edge.data?.condition ? (
        <>
          <div className={`property-item modeler-native-field ${isTokenDragging ? 'token-drop-disabled' : ''}`}>
            <label htmlFor="modeler-condition-label">{t('Condition Label')}</label>
            <div className="property-value editable">
              <input
                id="modeler-condition-label"
                name="modeler-condition-label"
                type="text"
                value={edgeLabelField.value}
                disabled={isLocked}
                onChange={edgeLabelField.onChange}
                onBlur={edgeLabelField.onBlur}
                onDragOver={handleNativeFieldDragOver}
                onDrop={handleNativeFieldDrop}
              />
              {!isLocked && (
                <button
                  className="panel-delete-btn"
                  onClick={handleDeleteCondition}
                  title={t('Remove condition')}
                  aria-label={t('Remove condition')}
                >
                  <FiTrash2 />
                </button>
              )}
            </div>
          </div>

          <div className={`property-item modeler-native-field ${isTokenDragging ? 'token-drop-disabled' : ''}`}>
            <label htmlFor="modeler-edge-annotation">{t('Annotation')}</label>
            <div className="property-value editable">
              <textarea
                id="modeler-edge-annotation"
                name="modeler-edge-annotation"
                value={edgeAnnotationField.value}
                disabled={isLocked}
                placeholder={t('Add a note or annotation for this connection...')}
                rows={3}
                onChange={edgeAnnotationField.onChange}
                onBlur={edgeAnnotationField.onBlur}
                onDragOver={handleNativeFieldDragOver}
                onDrop={handleNativeFieldDrop}
              />
            </div>
          </div>

          {configurationForm && (
            <div className="configuration-section">
              <ConfigurationForm
                key={`edge-${edge.id}-${edge.data?.condition}`}
                form={configurationForm}
                configuration={edge.data.conditionConfiguration || {}}
                onChange={(newConfig: Record<string, any>) => {
                  if (onEdgeConfigurationChange && edge && !isLocked) {
                    onEdgeConfigurationChange(edge.id, {
                      _conditionLabel: edge.data?.conditionLabel || '',
                      ...newConfig,
                    });
                  }
                }}
                disabled={isLocked}
              />
            </div>
          )}
        </>
      ) : (
        <p>{t('This connection has no conditions configured')}</p>
      )}
    </div>
  );
};

export default EdgePropertiesPanel;
