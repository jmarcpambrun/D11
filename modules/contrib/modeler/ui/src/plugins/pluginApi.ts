/**
 * Public Plugin API
 *
 * Creates the API object that external plugin panels use to interact with
 * the modeler state.  All returned data is deep-cloned so plugins cannot
 * mutate internal state.
 *
 * The API is created once when the modeler mounts and torn down on unmount.
 *
 * ## Query methods
 * Return deep-cloned snapshots of the current state.
 *
 * ## Mutation methods
 * Modify the graph (add/update/remove nodes and edges).  They are no-ops
 * in read-only mode and automatically integrate with undo/redo history
 * and unsaved-changes tracking via the callback hooks registered by
 * Flow.tsx.
 *
 * ## Event subscriptions
 * Return an `Unsubscribe` function; call it to stop receiving updates.
 */

import { useGraphStore } from '../store/useGraphStore';
import { useSelectionStore } from '../store/useSelectionStore';
import { useModelStore } from '../store/useModelStore';
import { useUISettingsStore } from '../store/useUISettingsStore';
import { useViewportStore } from '../store/useViewportStore';
import { useComponentStore } from '../store/useComponentStore';
import { useContextStore } from '../store/useContextStore';
import { useFilterStore } from '../store/useFilterStore';
import { useLabelStore } from '../store/useLabelStore';
import { useHistoryStore } from '../store/useHistoryStore';
import { useErrorStore } from '../store/useErrorStore';
import type { StoreNode as Node, StoreEdge as Edge } from '../types/settings';
import type {
  ModelerPluginApi,
  PluginNode,
  PluginEdge,
  PluginModelData,
  PluginComponent,
  PluginComponentLabels,
  PluginContext,
  PluginHistoryState,
  PluginError,
  AddNodeDescriptor,
  UpdateNodeDescriptor,
  UpdateEdgeDescriptor,
  SetConditionDescriptor,
  SelectionChangeCallback,
  NodesChangeCallback,
  EdgesChangeCallback,
  ModelDataChangeCallback,
  DarkModeChangeCallback,
  ContextChangeCallback,
  ComponentsChangeCallback,
  ReadOnlyChangeCallback,
  Unsubscribe,
} from '../types/pluginApi';
import { resolveNodeType } from '../utils/componentUtils';
import { generateNodeId, generateEdgeId } from '../utils/clipboardUtils';
import { LAYOUT, NODE_DIMENSIONS } from '../constants/dimensions';
import { findFreePosition } from '../utils/positionUtils';

// ── Helpers ───────────────────────────────────────────────────────────

/**
 * Deep-clone a value using structured clone (falls back to JSON round-trip).
 */
function deepClone<T>(value: T): T {
  if (value === null || value === undefined) return value;
  try {
    return structuredClone(value);
  } catch {
    return JSON.parse(JSON.stringify(value));
  }
}

/**
 * Project an internal node to the public PluginNode shape.
 */
function toPluginNode(node: Node): PluginNode {
  return deepClone({
    id: node.id,
    type: node.type,
    data: node.data as Record<string, unknown>,
    position: node.position,
  });
}

/**
 * Project an internal edge to the public PluginEdge shape.
 */
function toPluginEdge(edge: Edge): PluginEdge {
  return deepClone({
    id: edge.id,
    source: edge.source,
    target: edge.target,
    type: edge.type,
    data: edge.data as Record<string, unknown> | undefined,
  });
}

// ── Read-only flag management ─────────────────────────────────────────

let currentReadOnly = false;

/** Listeners notified when the read-only state changes. */
const readOnlyChangeListeners = new Set<ReadOnlyChangeCallback>();

/**
 * Called by the React app to update the read-only state that the API exposes.
 */
export function setApiReadOnly(readOnly: boolean): void {
  const prev = currentReadOnly;
  currentReadOnly = readOnly;
  if (prev !== readOnly) {
    readOnlyChangeListeners.forEach((fn) => {
      try {
        fn(readOnly);
      } catch (err) {
        console.error('Plugin onReadOnlyChange callback error:', err);
      }
    });
  }
}

// ── Mutation hooks ────────────────────────────────────────────────────
// Flow.tsx registers callbacks so that mutations performed via the plugin
// API trigger undo/redo history snapshots and mark the model as having
// unsaved changes.

type MutationHook = () => void;
let saveHistoryHook: MutationHook | null = null;
let markUnsavedHook: MutationHook | null = null;
let autoLayoutHook: MutationHook | null = null;

/**
 * Register callbacks that integrate mutations with undo/redo and
 * unsaved-changes tracking.  Called once by Flow.tsx on mount.
 */
export function setMutationHooks(hooks: {
  saveHistory: MutationHook;
  markUnsaved: MutationHook;
  autoLayout: MutationHook;
}): void {
  saveHistoryHook = hooks.saveHistory;
  markUnsavedHook = hooks.markUnsaved;
  autoLayoutHook = hooks.autoLayout;
}

/**
 * Clear mutation hooks (called on unmount).
 */
export function clearMutationHooks(): void {
  saveHistoryHook = null;
  markUnsavedHook = null;
  autoLayoutHook = null;
}

/**
 * Save a history snapshot before a mutation.
 */
function beforeMutation(): void {
  if (saveHistoryHook) saveHistoryHook();
}

/**
 * Mark the model as having unsaved changes after a mutation.
 */
function afterMutation(): void {
  if (markUnsavedHook) markUnsavedHook();
}

// ── API factory ───────────────────────────────────────────────────────

/**
 * Create the public API instance.
 *
 * The returned object is stable — callers can hold a reference indefinitely.
 * It reads from the Zustand store at call time, so values are always current.
 */
export function createPluginApi(): ModelerPluginApi {
  const api: ModelerPluginApi = {
    // ── Getters ─────────────────────────────────────────────────────
    getNodes(): PluginNode[] {
      return useGraphStore.getState().nodes.map(toPluginNode);
    },

    getEdges(): PluginEdge[] {
      return useGraphStore.getState().edges.map(toPluginEdge);
    },

    getNodeById(nodeId: string): PluginNode | null {
      const node = useGraphStore.getState().nodes.find((n) => n.id === nodeId);
      return node ? toPluginNode(node) : null;
    },

    getEdgeById(edgeId: string): PluginEdge | null {
      const edge = useGraphStore.getState().edges.find((e) => e.id === edgeId);
      return edge ? toPluginEdge(edge) : null;
    },

    getSelectedNode(): PluginNode | null {
      const node = useSelectionStore.getState().selectedNode;
      return node ? toPluginNode(node) : null;
    },

    getSelectedEdge(): PluginEdge | null {
      const edge = useSelectionStore.getState().selectedEdge;
      return edge ? toPluginEdge(edge) : null;
    },

    getModelData(): PluginModelData | null {
      const data = useModelStore.getState().modelData;
      if (!data) return null;
      return deepClone({
        id: data.id,
        version: data.version,
        metadata: data.metadata,
      });
    },

    isReadOnly(): boolean {
      return currentReadOnly;
    },

    isDarkMode(): boolean {
      return useUISettingsStore.getState().darkMode;
    },

    getComponents(): PluginComponent[] {
      return deepClone(
        useComponentStore.getState().components.map((c) => ({
          plugin: c.plugin,
          label: c.label,
          type: c.type,
          provider: c.provider,
          description: c.description,
          documentationUrl: c.documentationUrl,
          componentType: c.componentType,
        })),
      );
    },

    getComponentLabels(): PluginComponentLabels {
      return deepClone(useLabelStore.getState().componentLabels);
    },

    getContexts(): PluginContext[] {
      return deepClone(
        useContextStore.getState().contexts.map((ctx) => ({
          id: ctx.id,
          topic: ctx.topic,
          model_owner: ctx.model_owner,
        })),
      );
    },

    getSelectedContextId(): string | null {
      return useContextStore.getState().selectedContextId;
    },

    getFilteredNodeIds(): string[] | null {
      const ids = useFilterStore.getState().visibleStartNodeIds;
      return ids ? [...ids] : null;
    },

    getHistoryState(): PluginHistoryState {
      const history = useHistoryStore.getState();
      return {
        canUndo: history.canUndo(),
        canRedo: history.canRedo(),
      };
    },

    getErrors(): PluginError[] {
      return useErrorStore.getState().errorLog.map((r) => ({
        id: r.id,
        message: r.message,
        dismissed: !!r.dismissed,
      }));
    },

    // ── Event subscriptions ─────────────────────────────────────────
    onSelectionChange(callback: SelectionChangeCallback): Unsubscribe {
      let prevNodeId: string | null = null;
      let prevEdgeId: string | null = null;

      return useSelectionStore.subscribe((state) => {
        const nodeId = state.selectedNode?.id ?? null;
        const edgeId = state.selectedEdge?.id ?? null;

        if (nodeId !== prevNodeId || edgeId !== prevEdgeId) {
          prevNodeId = nodeId;
          prevEdgeId = edgeId;
          try {
            callback(
              state.selectedNode ? toPluginNode(state.selectedNode) : null,
              state.selectedEdge ? toPluginEdge(state.selectedEdge) : null,
            );
          } catch (err) {
            console.error('Plugin onSelectionChange callback error:', err);
          }
        }
      });
    },

    onNodesChange(callback: NodesChangeCallback): Unsubscribe {
      let prevLength = -1;
      let prevFirstId: string | undefined;

      return useGraphStore.subscribe((state) => {
        const len = state.nodes.length;
        const firstId = state.nodes[0]?.id;
        if (len !== prevLength || firstId !== prevFirstId) {
          prevLength = len;
          prevFirstId = firstId;
          try {
            callback(state.nodes.map(toPluginNode));
          } catch (err) {
            console.error('Plugin onNodesChange callback error:', err);
          }
        }
      });
    },

    onEdgesChange(callback: EdgesChangeCallback): Unsubscribe {
      let prevLength = -1;
      let prevFirstId: string | undefined;

      return useGraphStore.subscribe((state) => {
        const len = state.edges.length;
        const firstId = state.edges[0]?.id;
        if (len !== prevLength || firstId !== prevFirstId) {
          prevLength = len;
          prevFirstId = firstId;
          try {
            callback(state.edges.map(toPluginEdge));
          } catch (err) {
            console.error('Plugin onEdgesChange callback error:', err);
          }
        }
      });
    },

    onModelDataChange(callback: ModelDataChangeCallback): Unsubscribe {
      let prevId: string | undefined;
      let prevVersion: string | undefined;

      return useModelStore.subscribe((state) => {
        const id = state.modelData?.id;
        const version = state.modelData?.version;
        if (id !== prevId || version !== prevVersion) {
          prevId = id;
          prevVersion = version;
          try {
            callback(api.getModelData());
          } catch (err) {
            console.error('Plugin onModelDataChange callback error:', err);
          }
        }
      });
    },

    onDarkModeChange(callback: DarkModeChangeCallback): Unsubscribe {
      let prevDark: boolean | undefined;

      return useUISettingsStore.subscribe((state) => {
        if (state.darkMode !== prevDark) {
          prevDark = state.darkMode;
          try {
            callback(state.darkMode);
          } catch (err) {
            console.error('Plugin onDarkModeChange callback error:', err);
          }
        }
      });
    },

    onContextChange(callback: ContextChangeCallback): Unsubscribe {
      let prevContextId: string | null | undefined;

      return useContextStore.subscribe((state) => {
        if (state.selectedContextId !== prevContextId) {
          prevContextId = state.selectedContextId;
          try {
            callback(state.selectedContextId);
          } catch (err) {
            console.error('Plugin onContextChange callback error:', err);
          }
        }
      });
    },

    onComponentsChange(callback: ComponentsChangeCallback): Unsubscribe {
      let prevLength = -1;

      return useComponentStore.subscribe((state) => {
        if (state.components.length !== prevLength) {
          prevLength = state.components.length;
          try {
            callback(api.getComponents());
          } catch (err) {
            console.error('Plugin onComponentsChange callback error:', err);
          }
        }
      });
    },

    onReadOnlyChange(callback: ReadOnlyChangeCallback): Unsubscribe {
      readOnlyChangeListeners.add(callback);
      return () => {
        readOnlyChangeListeners.delete(callback);
      };
    },

    // ── Selection & viewport actions ────────────────────────────────
    selectNode(nodeId: string | null): void {
      if (currentReadOnly) return;
      if (nodeId === null) {
        useSelectionStore.getState().clearSelection();
        return;
      }
      const node = useGraphStore.getState().nodes.find((n) => n.id === nodeId);
      if (node) {
        useSelectionStore.getState().selectNode(node);
      }
    },

    selectEdge(edgeId: string | null): void {
      if (currentReadOnly) return;
      if (edgeId === null) {
        useSelectionStore.getState().clearSelection();
        return;
      }
      const edge = useGraphStore.getState().edges.find((e) => e.id === edgeId);
      if (edge) {
        useSelectionStore.getState().selectEdge(edge);
      }
    },

    clearSelection(): void {
      useSelectionStore.getState().clearSelection();
    },

    focusNode(nodeId: string): void {
      const node = useGraphStore.getState().nodes.find((n) => n.id === nodeId);
      if (node) {
        useViewportStore.getState().setViewportTarget({
          type: 'center',
          nodeId,
          options: { duration: 800 },
        });
      }
    },

    fitView(): void {
      useViewportStore.getState().setViewportTarget({
        type: 'fit',
        options: { padding: 0.1, duration: 800 },
      });
    },

    // ── Node mutations ──────────────────────────────────────────────
    addNode(descriptor: AddNodeDescriptor): string | null {
      if (currentReadOnly) return null;

      beforeMutation();

      const nodeType = resolveNodeType(descriptor.componentType);
      const label = descriptor.label || descriptor.plugin.split('.').pop() || 'New Node';
      const newNodeId = generateNodeId(label, nodeType);

      // Determine position: use provided or find an automatic one.
      let position = descriptor.position;
      if (!position) {
        const nodes = useGraphStore.getState().nodes;
        let candidateX: number = LAYOUT.DEFAULT_POSITION_X;
        let candidateY: number = LAYOUT.DEFAULT_POSITION_Y;

        if (nodes.length > 0) {
          const maxX = Math.max(...nodes.map((n) => n.position.x));
          const minY = Math.min(...nodes.map((n) => n.position.y));
          candidateX = maxX + LAYOUT.NODE_SPACING_X;
          candidateY = minY;
        }

        position = findFreePosition(
          { x: candidateX, y: candidateY },
          nodes,
          NODE_DIMENSIONS.DEFAULT_WIDTH,
          NODE_DIMENSIONS.DEFAULT_HEIGHT,
        );
      }

      const newNode: Node = {
        id: newNodeId,
        type: nodeType,
        position,
        data: {
          plugin: descriptor.plugin,
          label,
          componentType: descriptor.componentType,
          configuration: descriptor.configuration || {},
          description: descriptor.description,
          documentationUrl: descriptor.documentationUrl,
        },
      };

      useGraphStore.getState().addNode(newNode);
      afterMutation();
      return newNodeId;
    },

    updateNode(nodeId: string, updates: UpdateNodeDescriptor): boolean {
      if (currentReadOnly) return false;

      const { nodes } = useGraphStore.getState();
      const node = nodes.find((n) => n.id === nodeId);
      if (!node) return false;

      beforeMutation();

      // Build the partial update for the store.
      const storeUpdates: Partial<Node> = {};
      const dataUpdates: Record<string, unknown> = {};

      if (updates.position) {
        storeUpdates.position = { ...updates.position };
      }

      if (updates.label !== undefined) {
        dataUpdates.label = updates.label;
      }
      if (updates.configuration !== undefined) {
        // Shallow merge with existing configuration.
        dataUpdates.configuration = {
          ...(node.data.configuration || {}),
          ...updates.configuration,
        };
      }
      if (updates.annotation !== undefined) {
        dataUpdates.annotation = updates.annotation;
      }

      if (Object.keys(dataUpdates).length > 0) {
        storeUpdates.data = { ...node.data, ...dataUpdates };
      }

      useGraphStore.getState().updateNode(nodeId, storeUpdates);
      afterMutation();
      return true;
    },

    removeNode(nodeId: string): boolean {
      if (currentReadOnly) return false;

      const { nodes } = useGraphStore.getState();
      if (!nodes.find((n) => n.id === nodeId)) return false;

      beforeMutation();

      // Clear selection if the removed node was selected.
      const selection = useSelectionStore.getState();
      if (selection.selectedNode?.id === nodeId) {
        useSelectionStore.getState().clearSelection();
      }

      useGraphStore.getState().removeNode(nodeId);
      afterMutation();
      return true;
    },

    // ── Edge mutations ──────────────────────────────────────────────
    addEdge(sourceNodeId: string, targetNodeId: string): string | null {
      if (currentReadOnly) return null;

      const { nodes } = useGraphStore.getState();
      const sourceExists = nodes.some((n) => n.id === sourceNodeId);
      const targetExists = nodes.some((n) => n.id === targetNodeId);
      if (!sourceExists || !targetExists) return null;

      beforeMutation();

      const newEdgeId = generateEdgeId(sourceNodeId, targetNodeId);
      const newEdge: Edge = {
        id: newEdgeId,
        source: sourceNodeId,
        target: targetNodeId,
        type: 'default',
        data: {},
      };

      useGraphStore.getState().addEdge(newEdge);
      afterMutation();
      return newEdgeId;
    },

    updateEdge(edgeId: string, updates: UpdateEdgeDescriptor): boolean {
      if (currentReadOnly) return false;

      const { edges } = useGraphStore.getState();
      const edge = edges.find((e) => e.id === edgeId);
      if (!edge) return false;

      beforeMutation();

      const dataUpdates: Record<string, unknown> = {};
      if (updates.annotation !== undefined) {
        dataUpdates.annotation = updates.annotation;
      }

      const storeUpdates: Partial<Edge> = {};
      if (Object.keys(dataUpdates).length > 0) {
        storeUpdates.data = { ...(edge.data || {}), ...dataUpdates };
      }

      useGraphStore.getState().updateEdge(edgeId, storeUpdates);
      afterMutation();
      return true;
    },

    removeEdge(edgeId: string): boolean {
      if (currentReadOnly) return false;

      const { edges } = useGraphStore.getState();
      if (!edges.find((e) => e.id === edgeId)) return false;

      beforeMutation();

      // Clear selection if the removed edge was selected.
      const selection = useSelectionStore.getState();
      if (selection.selectedEdge?.id === edgeId) {
        useSelectionStore.getState().clearSelection();
      }

      useGraphStore.getState().removeEdge(edgeId);
      afterMutation();
      return true;
    },

    setCondition(edgeId: string, condition: SetConditionDescriptor): boolean {
      if (currentReadOnly) return false;

      const { edges } = useGraphStore.getState();
      const edge = edges.find((e) => e.id === edgeId);
      if (!edge) return false;

      beforeMutation();

      const conditionLabel = condition.label || condition.plugin.split('.').pop() || condition.plugin;

      useGraphStore.getState().updateEdge(edgeId, {
        type: 'condition',
        label: conditionLabel,
        data: {
          ...(edge.data || {}),
          condition: condition.plugin,
          conditionLabel,
          conditionConfiguration: condition.configuration || {},
        },
      });

      afterMutation();
      return true;
    },

    removeCondition(edgeId: string): boolean {
      if (currentReadOnly) return false;

      const { edges } = useGraphStore.getState();
      const edge = edges.find((e) => e.id === edgeId);
      if (!edge) return false;

      beforeMutation();

      useGraphStore.getState().updateEdge(edgeId, {
        type: 'default',
        label: '',
        data: {
          ...(edge.data || {}),
          condition: null,
          conditionLabel: null,
          conditionConfiguration: null,
          annotation: null,
        },
      });

      afterMutation();
      return true;
    },

    // ── Canvas actions ──────────────────────────────────────────────
    autoLayout(): void {
      if (currentReadOnly) return;
      if (autoLayoutHook) autoLayoutHook();
    },

    setDarkMode(enabled: boolean): void {
      const store = useUISettingsStore.getState();
      if (store.darkMode !== enabled) {
        store.toggleDarkMode();
      }
    },

    setFlowFilter(startNodeIds: string[] | null): void {
      useFilterStore.getState().setVisibleStartNodeIds(
        startNodeIds ? [...startNodeIds] : null,
      );
    },
  };

  return api;
}
