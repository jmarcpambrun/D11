/**
 * NodePropertiesPanel - Renders the property panel content for a single
 * selected node. Shows the label input, annotation textarea, description,
 * and configuration form.
 */
import React, { useCallback } from 'react';
import ConfigurationForm from './ConfigurationForm';
import type { StoreNode as Node } from '../types/settings';
import { t } from '../utils/translation';
import { useDebouncedField } from '../hooks/useDebouncedField';
import { useTokenDragPrevention } from '../hooks/useTokenDragPrevention';

interface NodePropertiesPanelProps {
  node: Node;
  configurationForm: any;
  onConfigurationChange?: (nodeId: string, configuration: Record<string, any>) => void;
  onNodeUpdate?: (nodeId: string, data: any) => void;
  isLocked: boolean;
  nodeLabelField: ReturnType<typeof useDebouncedField>;
  nodeAnnotationField: ReturnType<typeof useDebouncedField>;
}

const NodePropertiesPanel: React.FC<NodePropertiesPanelProps> = ({
  node,
  configurationForm,
  onConfigurationChange,
  onNodeUpdate: _onNodeUpdate,
  isLocked,
  nodeLabelField,
  nodeAnnotationField,
}) => {
  const { isTokenDragging, handleNativeFieldDragOver, handleNativeFieldDrop } = useTokenDragPrevention();

  const handleConfigurationChange = useCallback((newConfiguration: Record<string, any>) => {
    if (node && onConfigurationChange) {
      onConfigurationChange(node.id, newConfiguration);
    }
  }, [node, onConfigurationChange]);

  return (
    <div className="panel-content">
      <div className={`property-item modeler-native-field ${isTokenDragging ? 'token-drop-disabled' : ''}`}>
        <label htmlFor="modeler-component-label">{t('Label')}</label>
        <div className="property-value editable">
          <input
            id="modeler-component-label"
            name="modeler-component-label"
            type="text"
            value={nodeLabelField.value}
            disabled={isLocked}
            onChange={nodeLabelField.onChange}
            onBlur={nodeLabelField.onBlur}
            onDragOver={handleNativeFieldDragOver}
            onDrop={handleNativeFieldDrop}
          />
        </div>
      </div>

      <div className={`property-item modeler-native-field ${isTokenDragging ? 'token-drop-disabled' : ''}`}>
        <label htmlFor="modeler-node-annotation">{t('Annotation')}</label>
        <div className="property-value editable">
          <textarea
            id="modeler-node-annotation"
            name="modeler-node-annotation"
            value={nodeAnnotationField.value}
            disabled={isLocked}
            placeholder={t('Add a note or annotation for this node...')}
            rows={3}
            onChange={nodeAnnotationField.onChange}
            onBlur={nodeAnnotationField.onBlur}
            onDragOver={handleNativeFieldDragOver}
            onDrop={handleNativeFieldDrop}
          />
        </div>
      </div>

      {node.data?.description && (
        <div className="component-description">
          {node.data.description}
        </div>
      )}

      {configurationForm && (
        <div className="configuration-section">
          <ConfigurationForm
            key={`node-${node.id}-${node.data?.plugin}`}
            form={configurationForm}
            configuration={node.data?.configuration || {}}
            onChange={handleConfigurationChange}
            disabled={isLocked}
          />
        </div>
      )}
    </div>
  );
};

export default NodePropertiesPanel;
