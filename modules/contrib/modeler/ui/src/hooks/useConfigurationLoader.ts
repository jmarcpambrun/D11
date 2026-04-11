/**
 * useConfigurationLoader - Hook for loading component configuration forms from the API
 * 
 * Handles fetching configuration forms for nodes and edges, with proper abort handling
 * and caching to prevent duplicate requests.
 */

import { useState, useCallback, useEffect, useRef, useMemo } from 'react';
import { useContextStore } from '../store/useContextStore';
import { useGraphStore } from '../store/useGraphStore';
import type { StoreNode as Node, StoreEdge as Edge } from '../types/settings';
import type { Settings } from '../types/settings';
import { t } from '../utils/translation';
import { fetchValidatedCsrfToken, validateConfigurationResponse } from '../utils/validation';
import { showDrupalMessage } from '../utils/drupalMessage';

interface UseConfigurationLoaderProps {
  node?: Node | null;
  edge?: Edge | null;
  settings?: Settings;
  isReplayMode?: boolean;
}

interface UseConfigurationLoaderReturn {
  configurationForm: any;
  loading: boolean;
  loadConfiguration: () => Promise<void>;
}

export function useConfigurationLoader({
  node,
  edge,
  settings = {},
  isReplayMode = false,
}: UseConfigurationLoaderProps): UseConfigurationLoaderReturn {
  const [configurationForm, setConfigurationForm] = useState<any>(null);
  const [loading, setLoading] = useState<boolean>(false);
  
  const lastLoadedPluginRef = useRef<string | null>(null);
  const lastLoadedConditionRef = useRef<string | null>(null);
  const loadAbortController = useRef<AbortController | null>(null);

  // Context config: default values to apply to new component configurations
  const contextConfig = useContextStore(state => state.contextConfig);
  const setNodes = useGraphStore(state => state.setNodes);
  const setEdges = useGraphStore(state => state.setEdges);

  // Standalone mode: return pre-baked forms from settings instead of fetching
  const isStandalone = !!settings.modeler?.standalone;
  const configForms = settings.modeler?.configForms;

  const loadConfiguration = useCallback(async () => {
    // In standalone mode, look up the form from the pre-baked configForms map
    if (isStandalone) {
      if (configForms) {
        const pluginId = edge?.data?.condition || node?.data?.plugin;
        if (pluginId && configForms[pluginId]) {
          setConfigurationForm(configForms[pluginId]);
        } else {
          setConfigurationForm(null);
        }
      } else {
        setConfigurationForm(null);
      }
      return;
    }
    // Cancel any previous load operation
    if (loadAbortController.current) {
      loadAbortController.current.abort();
    }

    // Create new abort controller for this load
    const abortController = new AbortController();
    loadAbortController.current = abortController;

    // Determine if we're loading for a node or an edge with condition
    const isEdge = edge && edge.data && edge.data.condition;
    const target = isEdge ? edge : node;

    // Skip if no target or if target lacks required plugin/condition
    if (!target) return;
    if (!isEdge && !node?.data?.plugin) return;
    if (isEdge && !edge.data?.condition) {
      setConfigurationForm(null);
      return;
    }

    // Skip configuration loading for built-in components that don't have forms
    if (!isEdge && node?.data?.plugin === 'gateway') {
      setConfigurationForm(null);
      return;
    }

    setLoading(true);

    try {
      // Check if aborted before making the request
      if (abortController.signal.aborted) return;
      const configUrl = settings.modeler_api?.config_url;
      if (!configUrl) {
        console.error('Configuration URL not found in settings');
        setLoading(false);
        return;
      }

      // Get CSRF token (validated for non-empty, non-HTML response)
      const tokenUrl = settings.modeler_api?.token_url;
      if (!tokenUrl) {
        console.error('Token URL not found in settings');
        setLoading(false);
        return;
      }
      const token = await fetchValidatedCsrfToken(tokenUrl, abortController.signal);

      // Prepare JSON payload - for edges, we need to look up the condition component
      let requestData: any;
      if (isEdge && edge) {
        // Edge with condition - componentType 5 is for conditions (Api::COMPONENT_TYPE_LINK)
        const existingConfig = edge.data?.conditionConfiguration || {};
        const isNewCondition = Object.keys(existingConfig).length === 0;

        // For new conditions, merge contextConfig defaults into the configuration
        // so the backend receives them and builds the form with pre-filled values
        let configuration = existingConfig;
        if (isNewCondition && contextConfig && Object.keys(contextConfig).length > 0) {
          configuration = { ...contextConfig };
          // Also persist the contextConfig values into the edge data
          setEdges(prev => prev.map(e =>
            e.id === edge.id
              ? { ...e, data: { ...e.data, conditionConfiguration: { ...configuration } } }
              : e
          ));
        }

        requestData = {
          component_type: '5', // Api::COMPONENT_TYPE_LINK (condition)
          component_id: edge.id,
          model_id: settings.modeler?.modelId || '',
          is_new: settings.modeler_api?.isNew || false,
          plugin_id: edge.data?.condition, // The condition plugin ID
          configuration,
        };
      } else if (node) {
        // Regular node
        const existingConfig = node.data?.configuration || {};
        const isNewNode = Object.keys(existingConfig).length === 0;

        // For new nodes, merge contextConfig defaults into the configuration
        // so the backend receives them and builds the form with pre-filled values
        let configuration = existingConfig;
        if (isNewNode && contextConfig && Object.keys(contextConfig).length > 0) {
          configuration = { ...contextConfig };
          // Also persist the contextConfig values into the node data
          setNodes(prev => prev.map(n =>
            n.id === node.id
              ? { ...n, data: { ...n.data, configuration: { ...configuration } } }
              : n
          ));
        }

        requestData = {
          component_type: node.data?.componentType || '4', // Default to element
          component_id: node.id,
          model_id: settings.modeler?.modelId || '',
          is_new: settings.modeler_api?.isNew || false,
          plugin_id: node.data?.plugin,
          configuration,
        };
      }

      const response = await fetch(configUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json;charset=UTF-8',
          'X-CSRF-Token': token.trim(),
        },
        body: JSON.stringify(requestData),
        signal: abortController.signal,
      });

      // Check if aborted after fetch
      if (abortController.signal.aborted) return;

      if (response.ok) {
        const result = await response.json();
        // Validate the JSON response and extract form data / error
        const { form, error } = validateConfigurationResponse(result);
        if (error) {
          showDrupalMessage(error, 'error');
        }
        setConfigurationForm(form);
      } else {
        showDrupalMessage(
          t('Configuration request failed: @status @text', {
            '@status': String(response.status),
            '@text': response.statusText,
          }),
          'error',
        );
      }
    } catch (error: unknown) {
      // Ignore abort errors
      if (error instanceof Error && error.name !== 'AbortError') {
        showDrupalMessage(
          t('Error loading configuration: @message', {
            '@message': error.message,
          }),
          'error',
        );
      }
    } finally {
      // Only clear loading if this wasn't aborted
      if (!abortController.signal.aborted) {
        setLoading(false);
      }
    }
  }, [node, edge, settings.modeler_api?.config_url, settings.modeler_api?.token_url, settings.modeler?.modelId, settings.modeler_api?.isNew, contextConfig, setNodes, setEdges, isStandalone, configForms]);

  // Create stable identifiers for plugin/condition changes.
  // Uses only primitive values (id, plugin string) so it doesn't change
  // when ReactFlow or useSelectionSync update the node/edge object references.
  const currentPluginId = useMemo(() => {
    if (node && node.data?.plugin) {
      return `node-${node.id}-${node.data.plugin}`;
    }
    if (edge && edge.data?.condition) {
      return `edge-${edge.id}-${edge.data.condition}`;
    }
    return null;
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [node?.id, node?.data?.plugin, edge?.id, edge?.data?.condition]);

  // Keep a ref to loadConfiguration so the effect can call the latest version
  // without re-triggering on every node/edge reference change.
  const loadConfigurationRef = useRef(loadConfiguration);
  loadConfigurationRef.current = loadConfiguration;

  // Load configuration when plugin/condition identity changes.
  // Depends only on stable string identifiers (IDs), not object references,
  // to avoid spurious re-triggers from ReactFlow's onSelectionChange or
  // useSelectionSync updating the same node/edge with a new reference.
  useEffect(() => {
    // Clear configuration when both node and edge are set (invalid state)
    if (node && node.data?.plugin && edge && edge.data?.condition) {
      setConfigurationForm(null);
      return;
    }

    if (currentPluginId === null) {
      // No node or edge selected — reset state
      lastLoadedPluginRef.current = null;
      lastLoadedConditionRef.current = null;
      setConfigurationForm(null);
      setLoading(false);
      return;
    }

    const shouldLoad = (() => {
      if (node && node.data?.plugin) {
        const pluginKey = `${node.id}-${node.data.plugin}`;
        // Always reload in replay mode or if different plugin
        if (isReplayMode) {
          return true;
        } else if (lastLoadedPluginRef.current !== pluginKey) {
          lastLoadedPluginRef.current = pluginKey;
          lastLoadedConditionRef.current = null;
          return true;
        }
      } else if (edge && edge.data?.condition) {
        const conditionKey = `${edge.id}-${edge.data.condition}`;
        // Always reload in replay mode or if different condition
        if (isReplayMode) {
          return true;
        } else if (lastLoadedConditionRef.current !== conditionKey) {
          lastLoadedConditionRef.current = conditionKey;
          lastLoadedPluginRef.current = null;
          return true;
        }
      }
      return false;
    })();

    if (shouldLoad) {
      loadConfigurationRef.current();
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [currentPluginId, isReplayMode, node?.id, edge?.id]);

  // Cancel any pending loads when selection changes or on unmount
  useEffect(() => {
    return () => {
      if (loadAbortController.current) {
        loadAbortController.current.abort();
      }
    };
  }, [node?.id, edge?.id]);

  return {
    configurationForm,
    loading,
    loadConfiguration,
  };
}
