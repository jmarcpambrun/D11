import React, { Profiler, useCallback, useEffect, useMemo, useState, useRef } from 'react';
import { useReactFlow } from 'reactflow';
import { useGraphStore } from '../store/useGraphStore';
import { useSelectionStore } from '../store/useSelectionStore';
import { useModelStore } from '../store/useModelStore';
import { useViewportStore } from '../store/useViewportStore';
import { usePanelStore } from '../store/usePanelStore';
import { useFilterStore } from '../store/useFilterStore';
import { useContextStore } from '../store/useContextStore';
import { useComponentStore } from '../store/useComponentStore';
import type { StoreNode as Node, StoreEdge as Edge, StoreComponent } from '../types/settings';
import { useSimpleReplaySync, ReplayStep } from '../hooks/useSimpleReplaySync';
import { useKeyboardShortcuts } from '../hooks/useKeyboardShortcuts';
import { useClipboard } from '../hooks/useClipboard';
import { useReplayCoordination } from '../hooks/useReplayCoordination';
import { useViewportEffects } from '../hooks/useViewportEffects';
import { useDragAndDrop } from '../hooks/useDragAndDrop';
import { useFlowEventHandlers } from '../hooks/useFlowEventHandlers';
import { useReplayIndicators } from '../hooks/useReplayIndicators';
import { useModalState } from '../hooks/useModalState';
import { useSearch } from '../hooks/useSearch';
import { useModelDataLoader } from '../hooks/useModelDataLoader';
import { useConfiguration } from '../hooks/useConfiguration';
import { useCloseHandler } from '../hooks/useCloseHandler';
import { useMessagesContainer } from '../hooks/useMessagesContainer';
import { useQuickAdd } from '../hooks/useQuickAdd';
import { useEdgeStyling } from '../hooks/useEdgeStyling';
import { useNodeEdgeActions } from '../hooks/useNodeEdgeActions';
import { useSelectionSync } from '../hooks/useSelectionSync';
import { useStatusAnnouncer } from '../hooks/useStatusAnnouncer';
import { useTestRunner } from '../hooks/useTestRunner';
import { useExport } from '../hooks/useExport';
import type { ExportFormat } from '../hooks/useExport';
import { useLazyTokens } from '../hooks/useLazyTokens';
import { useViewMode } from '../hooks/useViewMode';
import { useHistory } from '../hooks/useHistory';
import { exportModelData } from '../utils/modelUtils';
import { t } from '../utils/translation';
import { getComponentLabel, getComponentLabelPlural } from '../utils/componentUtils';
import type { ReplayEntry } from '../hooks/useReplayLoader';
import type { Settings, DrupalAjax, ModelConstraints, ComponentLabels } from '../types/settings';
import { hasPermission } from '../utils/permissions';

// Component imports
import FlowCanvas from './FlowCanvas';
import PropertyPanel from './PropertyPanel';
import ReplayPanel from './ReplayPanel';
import Modals from './Modals';
import Toolbar from './Toolbar';
import CanvasToolbar from './CanvasToolbar';
import PanelErrorBoundary from './PanelErrorBoundary';
import PluginPanelSlot from './PluginPanelContainer';
import { onRenderCallback } from '../utils/profiling';
import { usePluginPanels, usePluginWidgets } from '../hooks/usePluginPanels';
import { createPluginApi, setApiReadOnly, setMutationHooks, clearMutationHooks } from '../plugins/pluginApi';
import { markReady, markUnready } from '../plugins/pluginRegistry';
import type { ModelerPluginApi } from '../types/pluginApi';
import type { ModelerContext } from '../types/settings';

/** Stable empty arrays used as default prop values to avoid creating new
 *  references on every render (which would defeat React.memo). */
const EMPTY_CONTEXTS: ModelerContext[] = [];

interface FlowProps {
  settings: Settings;
  drupal: DrupalAjax;
}

function FlowInner({ settings, drupal }: FlowProps) {
  // ReactFlow hooks
  const { fitView, setCenter, setViewport: _setViewport, getViewport } = useReactFlow();

  const modelerRef = useRef<HTMLDivElement>(null);

  // Messages container hook
  const {
    messagesContainerRef,
    messagesVisible,
    hasMessages,
    handleToggleMessages,
    handleClearMessages
  } = useMessagesContainer();

  // Context selection from store
  const contexts = useContextStore(state => state.contexts);
  const selectedContextId = useContextStore(state => state.selectedContextId);
  const setSelectedContextId = useContextStore(state => state.setSelectedContextId);

  // Store state - single source of truth
  const nodes = useGraphStore(state => state.nodes);
  const edges = useGraphStore(state => state.edges);
  const setNodes = useGraphStore(state => state.setNodes);
  const setEdges = useGraphStore(state => state.setEdges);

  // Refs for pre-save validation — keeps a stable callback identity while
  // always reflecting the current nodes/edges arrays (via ref).
  const validationNodesRef = useRef(nodes);
  validationNodesRef.current = nodes;
  const validationEdgesRef = useRef(edges);
  validationEdgesRef.current = edges;
  const modelConstraints = settings?.modeler_api?.model_constraints;
  const modelConstraintsRef = useRef<ModelConstraints | undefined>(modelConstraints);

  // Global and template tokens are loaded on demand from modeler_api endpoints
  // to avoid blocking the initial page render.  Falls back to inline
  // drupalSettings when the URL is not provided (older modeler_api versions).
  const globalTokens = useLazyTokens(
    settings.modeler_api?.global_tokens_url,
    settings.modeler_api?.global_tokens,
  );
  const templateTokens = useLazyTokens(
    settings.modeler_api?.template_tokens_url,
    settings.modeler_api?.template_tokens,
  );
  modelConstraintsRef.current = modelConstraints;
  const validateBeforeSave = useCallback((): string | null => {
    const currentNodes = validationNodesRef.current;
    const currentEdges = validationEdgesRef.current;
    const placeholders = currentNodes.filter(n => n.type === 'placeholder');
    if (placeholders.length > 0) {
      const count = placeholders.length;
      return t(
        'Cannot save: @count placeholder node(s) still need an action or gateway assigned. Please replace all placeholder nodes before saving.',
        { '@count': String(count) },
      );
    }
    // Validate model-level cardinality constraints.
    const constraints = modelConstraintsRef.current;
    if (constraints) {
      const typeCounts: Record<string, number> = {};
      for (const node of currentNodes) {
        if (node.type && node.type !== 'placeholder') {
          typeCounts[node.type] = (typeCounts[node.type] ?? 0) + 1;
        }
      }
      const errors: string[] = [];
      for (const [typeName, constraint] of Object.entries(constraints)) {
        const count = typeCounts[typeName] ?? 0;
        const label = getComponentLabel(typeName as keyof ComponentLabels);
        const labelPlural = getComponentLabelPlural(typeName as keyof ComponentLabels);
        if (constraint.min !== undefined && count < constraint.min) {
          errors.push(constraint.min === 1
            ? t('A model requires at least one @label.', { '@label': label })
            : t('A model requires at least @min @label_plural.', { '@min': String(constraint.min), '@label_plural': labelPlural }),
          );
        }
        if (constraint.max !== undefined && count > constraint.max) {
          errors.push(constraint.max === 1
            ? t('A model allows at most one @label.', { '@label': label })
            : t('A model allows at most @max @label_plural.', { '@max': String(constraint.max), '@label_plural': labelPlural }),
          );
        }
        // Validate successor cardinality per node.
        if (constraint.successors) {
          const sConstraint = constraint.successors;
          for (const node of currentNodes) {
            if (node.type !== typeName) continue;
            const outgoing = currentEdges.filter(e => e.source === node.id).length;
            if (sConstraint.min !== undefined && outgoing < sConstraint.min) {
              errors.push(t('@label "@name" requires at least @min successor(s).', {
                '@label': label,
                '@name': node.data?.label ?? node.id,
                '@min': String(sConstraint.min),
              }));
            }
            if (sConstraint.max !== undefined && outgoing > sConstraint.max) {
              errors.push(sConstraint.max === 0
                ? t('@label "@name" must not have any successors.', {
                  '@label': label,
                  '@name': node.data?.label ?? node.id,
                })
                : t('@label "@name" allows at most @max successor(s).', {
                  '@label': label,
                  '@name': node.data?.label ?? node.id,
                  '@max': String(sConstraint.max),
                }),
              );
            }
          }
        }
      }
      if (errors.length > 0) {
        return t('Cannot save: ') + errors.join(' ');
      }
    }
    return null;
  }, []);
  const selectedNode = useSelectionStore(state => state.selectedNode);
  const setSelectedNode = useSelectionStore(state => state.setSelectedNode);
  const selectedEdge = useSelectionStore(state => state.selectedEdge);
  const setSelectedEdge = useSelectionStore(state => state.setSelectedEdge);
  const selectedNodes = useSelectionStore(state => state.selectedNodes);
  const selectedEdges = useSelectionStore(state => state.selectedEdges);
  const setSelectedNodes = useSelectionStore(state => state.setSelectedNodes);
  const setSelectedEdges = useSelectionStore(state => state.setSelectedEdges);
  const modelData = useModelStore(state => state.modelData);
  const viewportTarget = useViewportStore(state => state.viewportTarget);
  const setViewportTarget = useViewportStore(state => state.setViewportTarget);
  const setReplayPanelCollapsed = usePanelStore(state => state.setReplayPanelCollapsed);
  const setPropertyPanelCollapsed = usePanelStore(state => state.setPropertyPanelCollapsed);

  // Use viewport effects to handle viewport changes
  useViewportEffects({
    viewportTarget,
    nodes,
    setCenter,
    fitView,
    onViewportChange: () => {
      setViewportTarget(null);
    }
  });

  // Read-only mode: no modifications are allowed when:
  //  1. explicitly set via drupalSettings.modeler_api.readOnly, OR
  //  2. the model is an existing template and the user lacks the
  //     "edit template" permission, OR
  //  3. running in standalone viewer mode (no Drupal backend).
  const isStandalone = !!settings.modeler?.standalone;

  // View mode: fullscreen (default Drupal) or restored (default standalone)
  const {
    viewMode,
    toggleViewMode,
    startDrag,
    startResize,
    isDragging,
    isResizing,
  } = useViewMode({ isStandalone, modelerRef });

  const isTemplateEditBlocked =
    !settings.modeler_api?.isNew &&
    !!settings.modeler_api?.metadata?.template &&
    !hasPermission(settings, 'edit template'); // eslint-disable-line i18n/no-untranslated-strings
  const isReadOnly = !!settings.modeler_api?.readOnly || isTemplateEditBlocked || isStandalone;

  // ── Plugin panel integration ─────────────────────────────────────────
  // Create the public API once per mount and update the read-only flag.
  const pluginApiRef = useRef<ModelerPluginApi | null>(null);
  if (!pluginApiRef.current) {
    pluginApiRef.current = createPluginApi();
  }
  const pluginApi = pluginApiRef.current;

  // Keep the API's read-only state in sync.
  useEffect(() => {
    setApiReadOnly(isReadOnly);
  }, [isReadOnly]);

  // Signal ready/unready to the plugin registry.
  useEffect(() => {
    // Expose the API on the global WorkflowModeler object if available.
    if (window.WorkflowModeler) {
      window.WorkflowModeler.api = pluginApi;
    }
    markReady();
    return () => {
      markUnready();
      clearMutationHooks();
      if (window.WorkflowModeler) {
        window.WorkflowModeler.api = null;
      }
    };
  }, [pluginApi]);

  // Retrieve registered plugin panels for each slot position.
  const leftPluginPanels = usePluginPanels('left');
  const rightPluginPanels = usePluginPanels('right');
  const bottomPluginPanels = usePluginPanels('bottom');

  // Retrieve registered toolbar widgets for each toolbar position.
  const leftPluginWidgets = usePluginWidgets('left');
  const rightPluginWidgets = usePluginWidgets('right');

  // Local state for UI interactions
  const [isReplayMode, setIsReplayMode] = useState(false);
  const [currentReplayStep, setCurrentReplayStep] = useState(-1);
  const [showEdgeOrderNumbers] = useState(true);
  const [showAllAnnotations] = useState(false);
  const [isShiftPressed, setIsShiftPressed] = useState(false);
  const [isCtrlPressed, setIsCtrlPressed] = useState(false);
  const [isAltPressed, setIsAltPressed] = useState(false);
  const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);

  // History (undo/redo) - disabled in read-only mode
  const { saveHistory, undo, redo, canUndo, canRedo } = useHistory({ enabled: !isReadOnly, setHasUnsavedChanges });

  // Canvas is locked when in read-only mode
  const isLocked = isReadOnly;
  const [isEventPopupOpen, setIsEventPopupOpen] = useState(false);

  // Dynamically loaded replay entries from backend
  const [loadedReplayEntries, setLoadedReplayEntries] = useState<ReplayEntry[]>([]);
  const [selectedReplayEntryIndex, setSelectedReplayEntryIndex] = useState(-1);

  // Status announcer for screen readers (aria-live region)
  const { message: statusMessage, announce } = useStatusAnnouncer();

  // Search functionality
  const {
    searchTerm,
    highlightedSearchResult,
    onSearchHighlight,
    onSearchFocus,
    clearSearch,
  } = useSearch();
  
  // Drag and drop functionality
  const {
    onDrop,
    onDragOver,
    isDraggingCondition,
    hoveredDropEdge,
  } = useDragAndDrop({
    isLocked,
    setHasUnsavedChanges,
    saveHistory
  });

  // Modal state management
  const {
    showMetadataModal,
    showConfirmDialog,
    confirmDialogTitle,
    confirmDialogMessage,
    confirmDialogType,
    confirmDialogLoading,
    confirmDialogPrimaryLabel,
    confirmDialogSecondaryLabel,
    confirmDialogCancelLabel,
    confirmDialogPrimaryVariant,
    onMetadataSubmit,
    showConfirmationDialog,
    handleConfirmDialog,
    handleCancelDialog,
    handleCloseWithoutSave,
    openMetadataModal,
    closeMetadataModal
  } = useModalState({ setHasUnsavedChanges });

  // Close handler
  const {
    handleClose,
    handleSaveComplete,
    saveButtonRef
  } = useCloseHandler({
    settings,
    hasUnsavedChanges,
    showConfirmationDialog
  });

  // Handle initial state based on whether this is a new model
  useEffect(() => {
    const isNew = settings?.modeler_api?.isNew;
    
    if (isNew) {
      const timer = setTimeout(() => {
        openMetadataModal();
      }, 100);
      return () => clearTimeout(timer);
    }
  }, [settings?.modeler_api?.isNew, openMetadataModal]);

  // collapsePanels: start all panels collapsed, then auto-expand/collapse
  // the property panel based on whether something is selected.
  const collapsePanels = !!settings.modeler?.collapsePanels;

  // Set initial collapse state when collapsePanels is requested
  useEffect(() => {
    if (collapsePanels) {
      setPropertyPanelCollapsed(true);
      setReplayPanelCollapsed(true);
    }
  // Run once on mount — intentionally not reacting to further changes.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Auto-expand property panel when a node or edge is selected,
  // auto-collapse when selection is cleared AND replay is not active.
  // During replay, intermediate null-selection states (between steps) should
  // not collapse the panel — it stays open until replay is exited.
  useEffect(() => {
    if (!collapsePanels) return;
    const hasSelection = !!selectedNode || !!selectedEdge;
    if (hasSelection) {
      setPropertyPanelCollapsed(false);
    } else if (!isReplayMode) {
      setPropertyPanelCollapsed(true);
    }
  }, [collapsePanels, selectedNode, selectedEdge, isReplayMode, setPropertyPanelCollapsed]);

  // Wrapper for closing metadata modal - opens event popup for new models
  const handleCloseMetadataModal = useCallback(() => {
    closeMetadataModal();
    if (settings?.modeler_api?.isNew) {
      setTimeout(() => {
        setIsEventPopupOpen(true);
      }, 150);
    }
  }, [closeMetadataModal, settings?.modeler_api?.isNew]);

  // Model data loading and management
  const { replayData: initialReplayData } = useModelDataLoader({ settings, setViewportTarget });

  // Active replay data: use selected entry's data if available, otherwise use initial data.
  // Memoized to keep a stable reference — many hooks depend on replayData identity.
  const replayData: ReplayStep[] = useMemo(() => {
    if (
      selectedReplayEntryIndex >= 0 &&
      selectedReplayEntryIndex < loadedReplayEntries.length
    ) {
      return (loadedReplayEntries[selectedReplayEntryIndex].history || []) as ReplayStep[];
    }
    return initialReplayData;
  }, [selectedReplayEntryIndex, loadedReplayEntries, initialReplayData]);
  
  // Configuration management
  const {
    onConfigurationChange,
    onEdgeConfigurationChange,
    onNodeUpdate,
    onEdgeUpdate,
    handleAutoLayout
  } = useConfiguration({ setHasUnsavedChanges, saveHistory });

  // Register mutation hooks so that plugin API mutations integrate with
  // undo/redo history and unsaved-changes tracking.
  useEffect(() => {
    setMutationHooks({
      saveHistory: () => saveHistory(),
      markUnsaved: () => setHasUnsavedChanges(true),
      autoLayout: () => handleAutoLayout(),
    });
  }, [saveHistory, setHasUnsavedChanges, handleAutoLayout]);

  // Quick add functionality
  const { addSuccessorNode, addConditionWithPlaceholder } = useQuickAdd({ setHasUnsavedChanges, saveHistory });

  // Node and edge action handlers (add condition, add event)
  const { handleAddCondition, handleAddEvent } = useNodeEdgeActions({ setHasUnsavedChanges, saveHistory });

  // Combined quick-add handler: routes condition selections to the
  // condition-with-placeholder flow, everything else to addSuccessorNode.
  const handleQuickAdd = useCallback((component: StoreComponent, sourceNodeId: string) => {
    if (component.type === 'link') {
      addConditionWithPlaceholder(component, sourceNodeId);
    } else {
      addSuccessorNode(component, sourceNodeId);
    }
  }, [addSuccessorNode, addConditionWithPlaceholder]);

  // Replace a placeholder node with a real action/gateway component.
  const handleReplacePlaceholder = useCallback((nodeId: string, component: StoreComponent) => {
    if (saveHistory) saveHistory();
    const nodeType = component.type === 'gateway' ? 'gateway' : 'element';
    const label = component.label || component.plugin?.split('.').pop() || t('New Node');

    setNodes(prev => prev.map(n => {
      if (n.id !== nodeId) return n;
      return {
        ...n,
        type: nodeType,
        data: {
          ...n.data,
          plugin: component.plugin,
          label,
          componentType: component.componentType,
          description: component.description,
          documentationUrl: component.documentationUrl,
        },
      };
    }));
    setHasUnsavedChanges(true);
  }, [setNodes, setHasUnsavedChanges, saveHistory]);

  // Sync selected objects when nodes/edges change
  useSelectionSync();

  // Start flow filter from store
  const visibleStartNodeIds = useFilterStore(state => state.visibleStartNodeIds);
  const setVisibleStartNodeIds = useFilterStore(state => state.setVisibleStartNodeIds);

  // Track previous start node IDs so we can detect additions/removals.
  const prevStartNodeIdsRef = useRef<Set<string>>(new Set());

  // Keep the flow filter in sync when start nodes are added or removed.
  //  - Removed start nodes are pruned from the selection (revert to "All" if none remain).
  //  - Newly added start nodes are auto-included so they are immediately visible.
  //
  // The ref starts empty.  On the first run (initial model load) we only
  // populate it — we must NOT treat every loaded node as "newly added",
  // because that would immediately override a filter that was set during
  // model loading (e.g. via selectComponentId).
  useEffect(() => {
    const currentStartIds = new Set(
      nodes.filter(n => n.type === 'start').map(n => n.id),
    );
    const prevIds = prevStartNodeIdsRef.current;

    // First meaningful population — just record and bail.
    if (prevIds.size === 0 && currentStartIds.size > 0) {
      prevStartNodeIdsRef.current = currentStartIds;
      return;
    }

    prevStartNodeIdsRef.current = currentStartIds;

    if (visibleStartNodeIds === null) return;

    // Detect start nodes that were just added to the canvas.
    const added: string[] = [];
    currentStartIds.forEach(id => {
      if (!prevIds.has(id)) added.push(id);
    });

    // Remove IDs that no longer exist on the canvas.
    let next = visibleStartNodeIds.filter(id => currentStartIds.has(id));

    // Auto-include any newly added start nodes in the selection.
    for (const id of added) {
      if (!next.includes(id)) {
        next = [...next, id];
      }
    }

    // If every start node is now selected (or none remain), revert to "All".
    if (next.length === 0 || next.length === currentStartIds.size) {
      if (visibleStartNodeIds !== null) {
        setVisibleStartNodeIds(null);
      }
    } else if (
      next.length !== visibleStartNodeIds.length ||
      next.some((id, i) => id !== visibleStartNodeIds[i])
    ) {
      setVisibleStartNodeIds(next);
    }
  }, [nodes, visibleStartNodeIds, setVisibleStartNodeIds]);

  // Compute the set of visible node IDs based on the start flow filter.
  // Uses BFS from each visible start node to discover all reachable nodes.
  const visibleNodeIds = useMemo<Set<string> | null>(() => {
    if (visibleStartNodeIds === null) return null; // All visible

    const reachable = new Set<string>();
    const adjacency = new Map<string, string[]>();

    // Build adjacency from edges
    for (const edge of edges) {
      if (!adjacency.has(edge.source)) adjacency.set(edge.source, []);
      adjacency.get(edge.source)!.push(edge.target);
    }

    // BFS from each selected start node
    const queue: string[] = [...visibleStartNodeIds];
    for (const id of queue) reachable.add(id);

    while (queue.length > 0) {
      const current = queue.shift()!;
      const neighbors = adjacency.get(current);
      if (neighbors) {
        for (const neighbor of neighbors) {
          if (!reachable.has(neighbor)) {
            reachable.add(neighbor);
            queue.push(neighbor);
          }
        }
      }
    }

    return reachable;
  }, [visibleStartNodeIds, edges]);

  // Apply the flow filter: set `hidden` on nodes and edges not in the visible set.
  const filteredNodes = useMemo(() => {
    if (visibleNodeIds === null) {
      // Remove any stale hidden flags
      return nodes.map(n => n.hidden ? { ...n, hidden: false } : n);
    }
    return nodes.map(n => ({
      ...n,
      hidden: !visibleNodeIds.has(n.id),
    }));
  }, [nodes, visibleNodeIds]);

  const filteredEdges = useMemo(() => {
    if (visibleNodeIds === null) {
      return edges.map(e => e.hidden ? { ...e, hidden: false } : e);
    }
    return edges.map(e => ({
      ...e,
      hidden: !visibleNodeIds.has(e.source) || !visibleNodeIds.has(e.target),
    }));
  }, [edges, visibleNodeIds]);

  // Edge styling for condition drag-and-drop
  const styledEdges = useEdgeStyling({ edges: filteredEdges, isDraggingCondition, hoveredDropEdge });

  // Replay indicators
  const { replayIndicators } = useReplayIndicators({
    isReplayMode,
    currentReplayStep,
    replayData,
    edges,
    nodes,
  });

  // Initialize custom hooks
  const { isSyncing, isReplaySyncingRef, handleCanvasNodeClick, handleCanvasEdgeClick, handleReplayStepSelect: baseHandleReplayStepSelect } = useSimpleReplaySync({
    replayData,
    nodes,
    edges,
    setNodes,
    setEdges,
    setSelectedNode,
    setSelectedEdge,
    currentStep: currentReplayStep,
    setCurrentStep: setCurrentReplayStep,
  });

  // Wrap handleReplayStepSelect to also activate replay mode
  const handleReplayStepSelect = useCallback((stepIndex: number) => {
    baseHandleReplayStepSelect(stepIndex);
    if (stepIndex >= 0 && !isReplayMode) {
      setIsReplayMode(true);
    }
  }, [baseHandleReplayStepSelect, isReplayMode, setIsReplayMode]);

  const { handleCopy, handlePaste, canCopy, canPaste } = useClipboard({
    selectedNode,
    selectedEdge,
    selectedNodeIds: selectedNodes,
    selectedEdgeIds: selectedEdges,
    nodes,
    edges,
    setNodes,
    setEdges,
    setSelectedNode,
    setSelectedEdge,
    setSelectedNodes,
    setSelectedEdges,
    setHasUnsavedChanges,
    announce,
    saveHistory,
  });

  const canDeleteSelected = useCallback(
    (): boolean => selectedNode !== null || selectedEdge !== null,
    [selectedNode, selectedEdge],
  );

  const { autoSyncToReplay, toggleReplayMode, hasReplayData } = useReplayCoordination({
    replayData,
    nodes,
    edges,
    isReplayMode,
    currentReplayStep,
    setIsReplayMode,
    setCurrentReplayStep,
    selectedNode,
    selectedEdge,
    isSyncing,
    handleReplayStepSelect,
  });

  // Derive the selected start node ID (or auto-detect if there's only one start node)
  const selectedStartNodeId = useMemo(() => {
    if (selectedNode?.type === 'start') return selectedNode.id;
    const startNodes = nodes.filter(n => n.type === 'start');
    if (startNodes.length === 1) return startNodes[0].id;
    return null;
  }, [selectedNode, nodes]);

  const isNewModel = !!settings.modeler_api?.isNew;

  // Granular permission checks (string keys are permission identifiers, not UI text)
  /* eslint-disable i18n/no-untranslated-strings */
  const canEditMetadata = hasPermission(settings, 'edit metadata');
  const canSwitchContext = hasPermission(settings, 'switch context');
  const canEditTemplate = hasPermission(settings, 'edit template');
  const canCreateTemplate = hasPermission(settings, 'create template');
  const canTest = hasPermission(settings, 'test');
  const canReplay = hasPermission(settings, 'replay');
  /* eslint-enable i18n/no-untranslated-strings */

  // Note: "edit template" restriction is now enforced via isReadOnly
  // (computed earlier) — when the model is an existing template and the
  // user lacks the permission, the entire modeler enters read-only mode.

  // Testing implies replay — hide the test button when replay is denied.
  // In standalone mode test/replay URLs are not available but data may
  // have been embedded in the JSON export.
  const hasTestUrl = !isStandalone && !isNewModel && !!settings.modeler_api?.test_url && canTest && canReplay;
  const hasReplayUrl = !isStandalone && !isNewModel && !!settings.modeler_api?.replay_url && canReplay;
  const hasAnyReplayCapability = hasTestUrl || hasReplayUrl || initialReplayData.length > 0;

  // Event handlers from extracted hook
  const {
    onNodesChange,
    onEdgesChange,
    onSelectionChange,
    onNodeClick,
    onEdgeClick,
    onDeleteNode,
    handleDeleteSelected,
    onConnect,
    onPaneClick,
    onNodeDragStart,
    onNodeDragStop,
  } = useFlowEventHandlers({
    handleCanvasNodeClick,
    handleCanvasEdgeClick,
    setHasUnsavedChanges,
    isSyncing,
    isReplaySyncingRef,
    hasReplayData,
    isReplayMode,
    currentReplayStep,
    autoSyncToReplay,
    announce,
    saveHistory
  });

  // Handle delete with confirmation for multi-selection panel
  const handleDeleteSelectedWithConfirm = useCallback(() => {
    const totalItems = selectedNodes.length + selectedEdges.length;
    if (totalItems === 0) return;

    showConfirmationDialog(
      t('Delete Selected Items'),
      t('Are you sure you want to delete @count selected items? This action cannot be undone.', { '@count': String(totalItems) }),
      'danger',
      () => { handleDeleteSelected(); },
      undefined,
      {
        primaryLabel: t('Delete'),
        secondaryLabel: false,
        cancelLabel: t('Cancel'),
        primaryVariant: 'danger',
      }
    );
  }, [selectedNodes.length, selectedEdges.length, showConfirmationDialog, handleDeleteSelected]);

  // Keyboard shortcuts (copy/paste/delete disabled in read-only mode)
  useKeyboardShortcuts({
    callbacks: {
      onDelete: handleDeleteSelected,
      onCopy: handleCopy,
      onPaste: handlePaste,
      onToggleSearch: () => {
        // The search bar is always visible inline; Ctrl+F focuses the input.
        // WCAG 2.2 SC 2.1.1 — keyboard-accessible search.
        const searchInput = document.querySelector<HTMLInputElement>('.search-input');
        if (searchInput) {
          searchInput.focus();
        }
      },
      onEscape: () => {
        clearSearch();
        // Blur the search input so focus returns to the canvas
        const searchInput = document.querySelector<HTMLInputElement>('.search-input');
        if (searchInput && document.activeElement === searchInput) {
          searchInput.blur();
        }
      },
      onUndo: undo,
      onRedo: redo,
    },
    modifiers: {
      isShiftPressed,
      setIsShiftPressed,
      isCtrlPressed,
      setIsCtrlPressed,
      isAltPressed,
      setIsAltPressed,
    },
    capabilities: {
      canDelete: !isReadOnly && canDeleteSelected(),
      canCopy: !isReadOnly && canCopy,
      canPaste: !isReadOnly && canPaste,
      canSearch: true,
      canEscape: true,
      canUndo: !isReadOnly && canUndo(),
      canRedo: !isReadOnly && canRedo(),
    },
    isModelerFocused: true,
    enabled: true,
  });

  // Handle dynamically loaded replay entries from PropertyPanel
  const handleReplayEntriesLoaded = useCallback((entries: ReplayEntry[]) => {
    setLoadedReplayEntries(entries);
    // If there are entries, auto-select the first one and open replay
    if (entries.length > 0) {
      setSelectedReplayEntryIndex(0);
      const firstEntryData = (entries[0].history || []) as ReplayStep[];
      if (firstEntryData.length > 0) {
        setIsReplayMode(true);
        setCurrentReplayStep(-1);
      }
      // Auto-expand the replay panel if it's collapsed
      setReplayPanelCollapsed(false);
      announce(t('@count replay entries loaded', { '@count': String(entries.length) }));
    } else {
      setSelectedReplayEntryIndex(-1);
    }
  }, [setIsReplayMode, setCurrentReplayStep, announce, setReplayPanelCollapsed]);

  // Handle selecting a replay entry from the dropdown
  const handleSelectReplayEntry = useCallback((index: number) => {
    setSelectedReplayEntryIndex(index);
    // Reset replay step when switching entries
    setCurrentReplayStep(-1);
    if (index >= 0 && index < loadedReplayEntries.length) {
      const entryData = (loadedReplayEntries[index].history || []) as ReplayStep[];
      if (entryData.length > 0) {
        setIsReplayMode(true);
      }
    }
  }, [loadedReplayEntries, setCurrentReplayStep, setIsReplayMode]);

  // Handle replay data received from the test runner
  const handleTestReplayDataReceived = useCallback((data: any[]) => {
    // Wrap the raw replay steps into a ReplayEntry-like structure
    const entry: ReplayEntry = {
      model_id: settings.modeler?.modelId || '',
      component_id: selectedStartNodeId || '',
      history: data,
      timestamp: new Date().toISOString(),
      user: 'test',
      ip: '',
      url: '',
    };
    handleReplayEntriesLoaded([entry]);
    announce(t('Test completed — replay data received'));
  }, [settings.modeler?.modelId, selectedStartNodeId, handleReplayEntriesLoaded, announce]);

  // Test runner hook
  const {
    isTestRunning,
    isTestInitiating,
    testError,
    startTest,
    cancelTest,
    notifySaveComplete,
  } = useTestRunner({
    settings,
    hasUnsavedChanges,
    showConfirmationDialog,
    saveButtonRef,
    onReplayDataReceived: handleTestReplayDataReceived,
    validateBeforeSave,
  });

  // Components from store (needed for export to derive required modules)
  const components = useComponentStore(state => state.components);

  // Export functionality
  const [showExportDialog, setShowExportDialog] = useState(false);
  const [isExporting, setIsExporting] = useState(false);
  const pendingExportAfterSaveRef = useRef(false);

  const {
    canExport,
    availableFormats,
    hasReplayData: exportHasReplayData,
    executeExport,
    getRequiredModules,
  } = useExport({
    settings,
    nodes,
    edges,
    components,
    modelData,
    replayData,
    announce,
  });

  // Handle the export button click: check unsaved changes, then open dialog
  // In standalone mode there is nothing to save — go straight to dialog.
  const handleExportClick = useCallback(() => {
    if (hasUnsavedChanges && !isStandalone) {
      showConfirmationDialog(
        t('Unsaved Changes'),
        t('You have unsaved changes. Please save your model before exporting.'),
        'warning',
        () => {
          // Mark that we want to open the export dialog after save completes
          pendingExportAfterSaveRef.current = true;
          saveButtonRef.current?.click();
        },
        undefined,
        {
          primaryLabel: t('Save Now'),
          secondaryLabel: false,
          cancelLabel: t('Cancel'),
          primaryVariant: 'primary',
        },
      );
      return;
    }
    setShowExportDialog(true);
  }, [hasUnsavedChanges, isStandalone, showConfirmationDialog, saveButtonRef]);

  // Handle format selection from the export dialog
  const handleExportFormat = useCallback(async (format: ExportFormat, includeReplayData?: boolean) => {
    setIsExporting(true);
    try {
      await executeExport(format, includeReplayData);
      setShowExportDialog(false);
    } catch (_error) {
      // Error is already announced via the hook
    } finally {
      setIsExporting(false);
    }
  }, [executeExport]);

  const handleCloseExportDialog = useCallback(() => {
    setShowExportDialog(false);
  }, []);

  // Auto-collapse replay panel when there's no data and no test running.
  // Auto-expand when the Test button becomes available so users can access it.
  // When collapsePanels is active, skip the auto-expand — the user can still
  // expand manually by clicking the collapsed panel tab.
  const showTestButton = hasTestUrl && !!selectedStartNodeId;
  useEffect(() => {
    if (collapsePanels) return;
    const hasData = replayData && replayData.length > 0;
    if (!hasData && !isTestRunning && !isTestInitiating && !showTestButton) {
      setReplayPanelCollapsed(true);
    } else if (showTestButton || hasData || isTestRunning || isTestInitiating) {
      setReplayPanelCollapsed(false);
    }
  }, [replayData, isTestRunning, isTestInitiating, showTestButton, setReplayPanelCollapsed, collapsePanels]);

  // Placeholder handlers for other features
  const onConnectStart = useCallback(() => {}, []);
  const onConnectEnd = useCallback(() => {}, []);
  const onDragEnter = useCallback(() => {}, []);
  const onDragLeave = useCallback(() => {}, []);
  const setReactFlowReady = useViewportStore(state => state.setReactFlowReady);
  const onInit = useCallback(() => {
    setReactFlowReady(true);
  }, [setReactFlowReady]);

  // ── Stable Toolbar callbacks ──────────────────────────────────────
  // Using functional updaters (prev => ...) so these callbacks have no
  // dependencies and keep a stable identity across renders.  This is
  // critical for React.memo on Toolbar to work.


  // Refs for values captured by onSave/onSaveComplete to avoid dependency
  // churn while keeping the callbacks stable.
  const nodesRef = useRef(nodes);
  nodesRef.current = nodes;
  const edgesRef = useRef(edges);
  edgesRef.current = edges;
  const modelDataRef = useRef(modelData);
  modelDataRef.current = modelData;

  const handleSaveData = useCallback(() =>
    exportModelData(nodesRef.current, edgesRef.current, {
      id: modelDataRef.current?.id,
      version: modelDataRef.current?.version,
      ...modelDataRef.current?.metadata,
    }),
  []);

  const handleSaveCompleteToolbar = useCallback(() => {
    handleSaveComplete(setHasUnsavedChanges);
    notifySaveComplete();
    if (pendingExportAfterSaveRef.current) {
      pendingExportAfterSaveRef.current = false;
      setShowExportDialog(true);
    }
  }, [handleSaveComplete, notifySaveComplete]);

  // Stable contexts prop for CanvasToolbar
  const toolbarContexts = canSwitchContext && !isReadOnly ? contexts : EMPTY_CONTEXTS;

  return (
    <Profiler id="Flow" onRender={onRenderCallback}>
    <div
      className={[
        'workflow-modeler',
        isStandalone ? 'standalone' : '',
        viewMode,
        isDragging ? 'is-dragging' : '',
        isResizing ? 'is-resizing' : '',
      ].filter(Boolean).join(' ')}
      ref={modelerRef}
    >
      <PanelErrorBoundary panelName={t('Toolbar')} className="toolbar-error">
        <Toolbar
          modelName={modelData?.metadata?.label || t('Untitled Workflow')}
          hasUnsavedChanges={hasUnsavedChanges}
          onSave={handleSaveData}
          onSaveComplete={handleSaveCompleteToolbar}
          saveButtonRef={saveButtonRef}
          onOpenMetadata={openMetadataModal}
          onToggleMessages={handleToggleMessages}
          onClearMessages={handleClearMessages}
          isLocked={isLocked}
          isReadOnly={isReadOnly}
          hasMessages={hasMessages}
          messagesVisible={messagesVisible}
          onSearchHighlight={onSearchHighlight}
          onSearchFocus={onSearchFocus}
          settings={settings}
          drupal={drupal}
          onClose={handleClose}
          announce={announce}
          validateBeforeSave={validateBeforeSave}

          onAddEvent={handleAddEvent}
          isEventPopupOpen={isEventPopupOpen}
          onEventPopupOpenChange={setIsEventPopupOpen}
          onExport={handleExportClick}
          canExport={canExport}
          viewMode={viewMode}
          onToggleViewMode={toggleViewMode}
          onStartDrag={startDrag}
          pluginWidgetsLeft={leftPluginWidgets}
          pluginWidgetsRight={rightPluginWidgets}
          pluginApi={pluginApi}
        />
      </PanelErrorBoundary>

      {/* Messages container - positioned at top, fades out */}
      <div
        ref={messagesContainerRef}
        role="log"
        aria-label={t('Workflow messages')}
        aria-live="polite"
        aria-relevant="additions removals"
        className={`workflow-messages-container ${messagesVisible ? 'visible' : 'hidden'}`}
      />

      <div className="workflow-modeler-content">
        {/* Plugin panels - Left slot */}
        {leftPluginPanels.length > 0 && (
          <PluginPanelSlot panels={leftPluginPanels} api={pluginApi} position="left" />
        )}

        {/* Main Canvas */}
        <div className={`workflow-modeler-canvas ${isDraggingCondition ? 'condition-drag-active' : ''}`}>
          <CanvasToolbar
            isLocked={isLocked}
            isReadOnly={isReadOnly}
            onCopy={handleCopy}
            onPaste={handlePaste}
            onUndo={undo}
            onRedo={redo}
            hasSelection={!!selectedNode || !!selectedEdge}
            canPaste={canPaste}
            canUndo={canUndo()}
            canRedo={canRedo()}
            onAutoLayout={handleAutoLayout}
            contexts={toolbarContexts}
            selectedContextId={selectedContextId}
            onContextChange={setSelectedContextId}
          />
          <PanelErrorBoundary panelName={t('Canvas')} className="canvas-error">
            <FlowCanvas
              nodes={filteredNodes}
              edges={styledEdges}
              eventHandlers={{
                onNodesChange,
                onEdgesChange,
                onConnect,
                onSelectionChange,
                onConnectStart,
                onConnectEnd,
                onDrop,
                onDragOver,
                onDragEnter,
                onDragLeave,
                onNodeClick,
                onEdgeClick,
                onPaneClick,
                onNodeDragStart,
                onNodeDragStop,
                onInit,
              }}
              elementCallbacks={{
                onEdgeUpdate,
                onNodeUpdate,
                onDeleteNode,
                onEdgeConfigurationChange,
              }}
              viewport={getViewport()}
              modifierKeys={{
                isShiftPressed,
                isCtrlPressed,
                isAltPressed,
              }}
              uiState={{
                isDragActive: false,
                isLocked,
                showEdgeOrderNumbers,
                showAllAnnotations,
              }}
              search={{
                searchTerm,
                highlightedSearchResult,
              }}
              replay={{
                replayData,
                currentReplayStep,
                isReplayMode,
                replayIndicators,
              }}
              setEdges={setEdges}
              setHasUnsavedChanges={setHasUnsavedChanges}
              quickAdd={{
                onQuickAdd: handleQuickAdd,
                onAddCondition: handleAddCondition,
                onReplacePlaceholder: handleReplacePlaceholder,
              }}
              modelConstraints={modelConstraints}
            />
          </PanelErrorBoundary>
        </div>

        {/* Replay Panel - Hidden when no test_url/replay_url and no initial data */}
        {hasAnyReplayCapability && (
          <PanelErrorBoundary panelName={t('Replay')} className="replay-panel-error">
            <ReplayPanel
              replayData={replayData}
              currentStep={currentReplayStep}
              onSelectStep={handleReplayStepSelect}
              onToggleReplay={toggleReplayMode}
              isReplayMode={isReplayMode}
              edges={edges}
              nodes={nodes}
              isVisible={true}
              stepData={currentReplayStep >= 0 && currentReplayStep < replayData.length ? replayData[currentReplayStep].data || null : null}
              stepInfo={currentReplayStep >= 0 && currentReplayStep < replayData.length ? {
                type: replayData[currentReplayStep].type,
                id: replayData[currentReplayStep].id,
                successorId: replayData[currentReplayStep].successorId,
                conditionId: replayData[currentReplayStep].conditionId,
                object: replayData[currentReplayStep].object,
                exception: replayData[currentReplayStep].exception
              } : null}
              replayEntries={loadedReplayEntries}
              selectedEntryIndex={selectedReplayEntryIndex}
              onSelectReplayEntry={handleSelectReplayEntry}
              selectedStartNodeId={selectedStartNodeId}
              hasReplayUrl={hasReplayUrl}
              hasTestUrl={hasTestUrl}
              isTestRunning={isTestRunning}
              isTestInitiating={isTestInitiating}
              testError={testError}
              onStartTest={startTest}
              onCancelTest={cancelTest}
              globalTokens={globalTokens}
              templateTokens={templateTokens}
              isTemplate={!!settings.modeler_api?.metadata?.template}
            />
          </PanelErrorBoundary>
        )}

        {/* Property Panel - Far right */}
        <PanelErrorBoundary panelName={t('Properties')} className="property-panel-error">
          <PropertyPanel
            node={selectedNode}
            edge={selectedEdge}
            selectedNodes={selectedNodes.map(id => nodes.find(n => n.id === id)).filter((n): n is Node => n !== undefined)}
            selectedEdges={selectedEdges.map(id => edges.find(e => e.id === id)).filter((e): e is Edge => e !== undefined)}
            onConfigurationChange={onConfigurationChange}
            onEdgeConfigurationChange={onEdgeConfigurationChange}
            onNodeUpdate={onNodeUpdate}
            onEdgeUpdate={onEdgeUpdate}
            onDeleteSelected={handleDeleteSelectedWithConfirm}
            settings={settings}
            isLocked={isLocked}
            onReplayEntriesLoaded={handleReplayEntriesLoaded}
          />
        </PanelErrorBoundary>

        {/* Plugin panels - Right slot */}
        {rightPluginPanels.length > 0 && (
          <PluginPanelSlot panels={rightPluginPanels} api={pluginApi} position="right" />
        )}
      </div>

      {/* Plugin panels - Bottom slot */}
      {bottomPluginPanels.length > 0 && (
        <PluginPanelSlot panels={bottomPluginPanels} api={pluginApi} position="bottom" />
      )}

      <PanelErrorBoundary panelName={t('Modals')} className="modals-error">
        <Modals
          showMetadataModal={showMetadataModal}
          onCloseMetadataModal={handleCloseMetadataModal}
          onMetadataSubmit={onMetadataSubmit}
          modelMetadata={modelData?.metadata || { label: '', documentation: '', tags: [], changelog: '' }}
          modelId={modelData?.id}
          isNewModel={settings?.modeler_api?.isNew || false}
          canEditMetadata={canEditMetadata && !isReadOnly}
          canCreateTemplate={canCreateTemplate && canEditTemplate && !isReadOnly}
          showConfirmDialog={showConfirmDialog}
          confirmDialogTitle={confirmDialogTitle}
          confirmDialogMessage={confirmDialogMessage}
          confirmDialogType={confirmDialogType}
          onConfirmDialog={handleConfirmDialog}
          onCancelDialog={handleCancelDialog}
          onCloseWithoutSave={handleCloseWithoutSave}
          confirmDialogLoading={confirmDialogLoading}
          confirmDialogPrimaryLabel={confirmDialogPrimaryLabel}
          confirmDialogSecondaryLabel={confirmDialogSecondaryLabel}
          confirmDialogCancelLabel={confirmDialogCancelLabel}
          confirmDialogPrimaryVariant={confirmDialogPrimaryVariant}
          showExportDialog={showExportDialog}
          onCloseExportDialog={handleCloseExportDialog}
          exportAvailableFormats={availableFormats}
          exportHasReplayData={exportHasReplayData}
          exportRequiredModules={getRequiredModules()}
          onExport={handleExportFormat}
          isExporting={isExporting}
        />
      </PanelErrorBoundary>

      {/* Resize handle — only in Drupal restored (windowed) mode */}
      {viewMode === 'restored' && !isStandalone && (
        <div
          className="modeler-resize-handle"
          onMouseDown={startResize}
          aria-hidden="true"
        />
      )}

      {/* Hidden aria-live region for screen reader announcements */}
      <div
        role="status"
        aria-live="polite"
        aria-atomic="true"
        className="sr-only"
      >
        {statusMessage}
      </div>
    </div>
    </Profiler>
  );
}

// Main Flow component wrapper
function Flow(props: FlowProps) {
  return <FlowInner {...props} />;
}

export default Flow;
