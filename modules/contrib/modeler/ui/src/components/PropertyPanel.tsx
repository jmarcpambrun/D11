import React, { Profiler, useCallback, useEffect, useMemo, useState } from 'react';
import { FiChevronRight, FiChevronLeft, FiGitBranch, FiInfo, FiRefreshCw } from 'react-icons/fi';
import DocumentationButton from './DocumentationButton';
import InfoPopup from './InfoPopup';
import type { InfoItem } from './InfoPopup';
import MultiSelectionPanel from './MultiSelectionPanel';
import NodePropertiesPanel from './NodePropertiesPanel';
import EdgePropertiesPanel from './EdgePropertiesPanel';
import { usePanelStore } from '../store/usePanelStore';
import { useComponentStore } from '../store/useComponentStore';
import type { StoreNode as Node, StoreEdge as Edge } from '../types/settings';
import { PANEL_DIMENSIONS } from '../constants/dimensions';
import { t } from '../utils/translation';
import { getComponentIcon, getComponentLabel, getComponentTypeName } from '../utils/componentUtils';
import { useConfigurationLoader } from '../hooks/useConfigurationLoader';
import { useReplayLoader } from '../hooks/useReplayLoader';
import type { ReplayEntry } from '../hooks/useReplayLoader';
import { usePanelResize } from '../hooks/usePanelResize';
import { useDebouncedField } from '../hooks/useDebouncedField';
import type { Settings } from '../types/settings';
import { hasPermission } from '../utils/permissions';
import { onRenderCallback } from '../utils/profiling';

interface PropertyPanelProps {
  node?: Node | null;
  edge?: Edge | null;
  selectedNodes?: Node[];
  selectedEdges?: Edge[];
  onConfigurationChange?: (nodeId: string, configuration: Record<string, any>) => void;
  onEdgeConfigurationChange?: (edgeId: string, configuration: Record<string, any> | null) => void;
  onNodeUpdate?: (nodeId: string, data: any) => void;
  onEdgeUpdate?: (edgeId: string, data: any) => void;
  onDeleteSelected?: () => void;
  isLocked?: boolean;
  settings?: Settings;
  isReplayMode?: boolean;
  onReplayEntriesLoaded?: (entries: ReplayEntry[]) => void;
}

const PropertyPanel: React.FC<PropertyPanelProps> = ({
  node,
  edge,
  selectedNodes = [],
  selectedEdges = [],
  onConfigurationChange,
  onEdgeConfigurationChange,
  onNodeUpdate,
  onEdgeUpdate,
  onDeleteSelected,
  isLocked = false,
  settings = {},
  isReplayMode = false,
  onReplayEntriesLoaded
}) => {
  const panelWidth = usePanelStore(state => state.panelWidth);
  const panelIsResizing = usePanelStore(state => state.panelIsResizing);
  const setPanelWidth = usePanelStore(state => state.setPanelWidth);
  const setPanelResizing = usePanelStore(state => state.setPanelResizing);
  const propertyPanelCollapsed = usePanelStore(state => state.propertyPanelCollapsed);
  const togglePropertyPanelCollapse = usePanelStore(state => state.togglePropertyPanelCollapse);
  const components = useComponentStore(state => state.components);

  // Look up the documentation URL from the component definition
  const documentationUrl = useMemo(() => {
    if (!node?.data?.plugin || !components) return null;
    const component = components.find(c => c.plugin === node.data.plugin);
    return component?.documentationUrl || node.data?.documentationUrl || null;
  }, [node?.data?.plugin, node?.data?.documentationUrl, components]);
  
  // Use extracted hooks for cleaner architecture
  const { configurationForm, loading } = useConfigurationLoader({
    node,
    edge,
    settings,
    isReplayMode,
  });

  // Replay loader for event nodes — callback fires inside the hook, no useEffect bridge needed
  const { loading: replayLoading, error: replayError, loadReplayData } = useReplayLoader({
    settings,
    onEntriesLoaded: onReplayEntriesLoaded,
  });
  const isStartNode = node?.type === 'start';
  const isNewModel = !!settings.modeler_api?.isNew;
  const canReplay = hasPermission(settings, 'replay');
  const hasReplayUrl = !isNewModel && !!settings.modeler_api?.replay_url && canReplay;

  const handleLoadReplay = useCallback(() => {
    if (node && isStartNode && hasReplayUrl) {
      loadReplayData(node.id);
    }
  }, [node, isStartNode, hasReplayUrl, loadReplayData]);

  const { startResize } = usePanelResize({
    panelWidth,
    setPanelWidth,
    setPanelResizing: setPanelResizing,
    direction: 'left',
  });

  // Check if we have multiple selections
  const hasMultipleSelection: boolean = selectedNodes.length > 1 || selectedEdges.length > 1 ||
                               (selectedNodes.length > 0 && selectedEdges.length > 0);

  // Debounced field handlers for node label
  const handleNodeLabelChange = useCallback((value: string) => {
    if (node && onConfigurationChange && !isLocked) {
      onConfigurationChange(node.id, { _componentLabel: value });
    }
  }, [node, onConfigurationChange, isLocked]);

  const nodeLabelField = useDebouncedField({
    initialValue: node?.data?.label || '',
    onDebouncedChange: handleNodeLabelChange,
    disabled: isLocked,
  });

  // Debounced field handlers for node annotation
  const handleNodeAnnotationChange = useCallback((value: string) => {
    if (node && onNodeUpdate && !isLocked) {
      onNodeUpdate(node.id, { ...node.data, annotation: value });
    }
  }, [node, onNodeUpdate, isLocked]);

  const nodeAnnotationField = useDebouncedField({
    initialValue: node?.data?.annotation || '',
    onDebouncedChange: handleNodeAnnotationChange,
    disabled: isLocked,
  });

  // Debounced field handlers for edge condition label
  const edgeRef = React.useRef(edge);
  edgeRef.current = edge;
  
  const handleEdgeLabelChange = useCallback((value: string) => {
    const currentEdge = edgeRef.current;
    if (currentEdge && onEdgeConfigurationChange && !isLocked) {
      onEdgeConfigurationChange(currentEdge.id, {
        _conditionLabel: value,
      });
    }
  }, [onEdgeConfigurationChange, isLocked]);

  const edgeLabelField = useDebouncedField({
    initialValue: edge?.data?.conditionLabel || '',
    onDebouncedChange: handleEdgeLabelChange,
    disabled: isLocked,
  });

  // Debounced field handlers for edge annotation
  const handleEdgeAnnotationChange = useCallback((value: string) => {
    if (edge && onEdgeUpdate && !isLocked) {
      onEdgeUpdate(edge.id, { ...edge.data, annotation: value });
    }
  }, [edge, onEdgeUpdate, isLocked]);

  const edgeAnnotationField = useDebouncedField({
    initialValue: edge?.data?.annotation || '',
    onDebouncedChange: handleEdgeAnnotationChange,
    disabled: isLocked,
  });

  // Reset field values when node/edge changes.
  // Flush any pending debounced changes first so edits to the *previous*
  // node/edge are not silently discarded when the selection switches.
  useEffect(() => {
    nodeLabelField.flush();
    nodeAnnotationField.flush();
    if (node) {
      nodeLabelField.setValue(node.data?.label || '');
      nodeAnnotationField.setValue(node.data?.annotation || '');
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [node?.id]);

  useEffect(() => {
    edgeLabelField.flush();
    edgeAnnotationField.flush();
    if (edge) {
      edgeLabelField.setValue(edge.data?.conditionLabel || '');
      edgeAnnotationField.setValue(edge.data?.annotation || '');
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [edge?.id]);

  // Handle click on collapsed panel to expand
  const handlePanelClick = useCallback((e: React.MouseEvent) => {
    if (propertyPanelCollapsed) {
      e.stopPropagation();
      togglePropertyPanelCollapse();
    }
  }, [propertyPanelCollapsed, togglePropertyPanelCollapse]);

  // Info popup state
  const [showInfoPopup, setShowInfoPopup] = useState(false);

  // Build metadata items for the info popup
  const infoItems: InfoItem[] = useMemo(() => {
    if (node) {
      return [
        { label: t('ID'), value: node.id, show: true },
        { label: t('Type'), value: node.type || '', show: true },
        { label: t('Plugin ID'), value: node.data?.plugin || '', show: !!node.data?.plugin },
      ];
    }
    if (edge) {
      return [
        { label: t('Connection Type'), value: t('Edge'), show: true },
        { label: t('Condition Plugin'), value: edge.data?.condition || '', show: !!edge.data?.condition },
        { label: t('Edge ID'), value: edge.id, show: true },
        { label: t('Source'), value: edge.source, show: true },
        { label: t('Target'), value: edge.target, show: true },
      ];
    }
    return [];
  }, [node, edge]);

  // Close info popup when selection changes
  useEffect(() => {
    setShowInfoPopup(false);
  }, [node?.id, edge?.id]);

  return (
    <Profiler id="PropertyPanel" onRender={onRenderCallback}>
    <div
      className={`workflow-property-panel ${panelIsResizing ? 'resizing' : ''} ${propertyPanelCollapsed ? 'collapsed' : ''}`}
      style={{ width: propertyPanelCollapsed ? `${PANEL_DIMENSIONS.PROPERTY_PANEL.COLLAPSED_WIDTH}px` : `${panelWidth}px` }}
      onClick={handlePanelClick}
      title={propertyPanelCollapsed ? t('Click to expand') : undefined}
    >
      <div
        className="resize-handle"
        onMouseDown={startResize}
        title={t('Drag to resize')}
      />
      <button
        className="panel-collapse-widget"
        onClick={togglePropertyPanelCollapse}
        title={propertyPanelCollapsed ? t('Expand panel') : t('Collapse panel')}
        aria-label={propertyPanelCollapsed ? t('Expand panel') : t('Collapse panel')}
      >
        <span className="collapse-icon">
          {propertyPanelCollapsed ? <FiChevronLeft /> : <FiChevronRight />}
        </span>
      </button>
      {propertyPanelCollapsed && (
        <div className="panel-collapsed-label">
          <span>{t('Properties')}</span>
        </div>
      )}
      <div className="panel-content">

      <div className="panel-header">
        {hasMultipleSelection ? (
          <h3>{t('Multiple Selection')}</h3>
        ) : node || edge ? (
          <div className="component-header">
            <div className="component-info">
              {node ? getComponentIcon(node.type || 'element') : <FiGitBranch />}
              <span className="component-type">
                {node ? getComponentTypeName(node.type || 'element') : getComponentLabel('link')}
              </span>
            </div>
            <div className="header-actions">
              {(loading || replayLoading) && <div className="loading-indicator">{t('Loading...')}</div>}
              {node && isStartNode && hasReplayUrl && (
                <button
                  className={`header-replay-btn ${replayLoading ? 'loading' : ''}`}
                  aria-label={t('Load replay data')}
                  onClick={handleLoadReplay}
                  disabled={replayLoading}
                  title={replayError || t('Load replay data')}
                >
                  <FiRefreshCw className={replayLoading ? 'spinning' : ''} />
                </button>
              )}
              {node && documentationUrl && (
                <DocumentationButton
                  url={documentationUrl}
                  title={node.data?.label || t('Component')}
                  className="header-documentation-btn"
                  size={16}
                />
              )}
              <button
                className="header-info-btn"
                aria-label={t('Show metadata')}
                onClick={() => setShowInfoPopup(prev => !prev)}
                title={t('Show metadata')}
              >
                <FiInfo />
              </button>
            </div>
          </div>
        ) : (
          <div className="empty-header"></div>
        )}
      </div>

      {showInfoPopup && infoItems.length > 0 && (
        <InfoPopup items={infoItems} onClose={() => setShowInfoPopup(false)} />
      )}

      {hasMultipleSelection ? (
        <MultiSelectionPanel
          selectedNodes={selectedNodes}
          selectedEdges={selectedEdges}
          onDeleteSelected={onDeleteSelected}
          isLocked={isLocked}
        />
      ) : !node && !edge ? (
        <div className="panel-content empty">
          <p>{t('Select a component or connection to view its properties')}</p>
        </div>
      ) : node ? (
        <NodePropertiesPanel
          node={node}
          configurationForm={configurationForm}
          onConfigurationChange={onConfigurationChange}
          onNodeUpdate={onNodeUpdate}
          isLocked={isLocked}
          nodeLabelField={nodeLabelField}
          nodeAnnotationField={nodeAnnotationField}
        />
      ) : edge ? (
        <EdgePropertiesPanel
          edge={edge}
          configurationForm={configurationForm}
          onEdgeConfigurationChange={onEdgeConfigurationChange}
          onEdgeUpdate={onEdgeUpdate}
          isLocked={isLocked}
          edgeLabelField={edgeLabelField}
          edgeAnnotationField={edgeAnnotationField}
        />
      ) : null}
      </div>
    </div>
    </Profiler>
  );
};

export default PropertyPanel;
