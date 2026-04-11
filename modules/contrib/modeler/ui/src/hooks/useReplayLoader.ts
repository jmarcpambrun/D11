/**
 * useReplayLoader - Hook for dynamically loading replay data from the backend
 *
 * Fetches replay execution data for a specific event component by POSTing
 * to the replay_url endpoint, similar to how useConfigurationLoader fetches
 * config forms from the config_url.
 */

import { useState, useCallback, useRef, useEffect } from 'react';
import { t } from '../utils/translation';
import { fetchValidatedCsrfToken, validateReplayEntries } from '../utils/validation';
import type { Settings, ReplayDataEntry } from '../types/settings';
import { showDrupalMessage } from '../utils/drupalMessage';

export interface ReplayEntry {
  /** Model ID */
  model_id: string;
  /** Component ID that triggered this execution */
  component_id: string;
  /** Replay steps (same structure as drupalSettings.modeler.replayData) */
  history: ReplayDataEntry[];
  /** Timestamp of the execution (ISO string or Unix seconds) */
  timestamp: string | number;
  /** User who triggered the execution (may be string or object with name) */
  user: string | { name?: string; [key: string]: unknown };
  /** IP address of the request */
  ip: string;
  /** URL context of the execution */
  url: string;
}

interface UseReplayLoaderProps {
  settings?: Settings;
  /** Called once when new entries are successfully loaded */
  onEntriesLoaded?: (entries: ReplayEntry[]) => void;
}

interface UseReplayLoaderReturn {
  /** List of replay entries returned from the backend */
  replayEntries: ReplayEntry[];
  /** Whether a fetch is in progress */
  loading: boolean;
  /** Error message if the fetch failed */
  error: string | null;
  /** Fetch replay data for a given component ID */
  loadReplayData: (componentId: string) => Promise<void>;
  /** Clear loaded replay entries */
  clearReplayEntries: () => void;
}

export function useReplayLoader({
  settings = {},
  onEntriesLoaded,
}: UseReplayLoaderProps): UseReplayLoaderReturn {
  const [replayEntries, setReplayEntries] = useState<ReplayEntry[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const abortControllerRef = useRef<AbortController | null>(null);

  // Keep a stable ref to the callback so loadReplayData never re-creates
  const onEntriesLoadedRef = useRef(onEntriesLoaded);
  useEffect(() => {
    onEntriesLoadedRef.current = onEntriesLoaded;
  }, [onEntriesLoaded]);

  const loadReplayData = useCallback(async (componentId: string) => {
    // Cancel any previous request
    if (abortControllerRef.current) {
      abortControllerRef.current.abort();
    }

    const abortController = new AbortController();
    abortControllerRef.current = abortController;

    const replayUrl = settings.modeler_api?.replay_url;
    if (!replayUrl) {
      setError(t('Replay URL not found in settings'));
      return;
    }

    const tokenUrl = settings.modeler_api?.token_url;
    if (!tokenUrl) {
      setError(t('Token URL not found in settings'));
      return;
    }

    const modelId = settings.modeler?.modelId || '';

    setLoading(true);
    setError(null);

    try {
      if (abortController.signal.aborted) return;

      // Get CSRF token (validated for non-empty, non-HTML response)
      const token = await fetchValidatedCsrfToken(tokenUrl, abortController.signal);

      if (abortController.signal.aborted) return;

      const response = await fetch(replayUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json;charset=UTF-8',
          'X-CSRF-Token': token,
        },
        body: JSON.stringify({
          modelId,
          componentId,
        }),
        signal: abortController.signal,
      });

      if (abortController.signal.aborted) return;

      if (response.ok) {
        const result = await response.json();

        if (result && typeof result === 'object' && !Array.isArray(result) && result.error) {
          const errorMessage = result.error;
          setError(errorMessage);
          setReplayEntries([]);
          // Show error message to user
          showDrupalMessage(errorMessage, 'error');
        } else {
          // Show warning if present (non-blocking — continue processing)
          if (result && typeof result === 'object' && !Array.isArray(result) && result.warning) {
            showDrupalMessage(result.warning, 'warning');
          }
          // Validate that the response is an array with valid entries
          const validEntries = validateReplayEntries(result) as ReplayEntry[];
          // Sort entries by timestamp descending (newest first)
          validEntries.sort((a, b) => {
            const dateA = typeof a.timestamp === 'number' ? a.timestamp * 1000 : new Date(a.timestamp).getTime();
            const dateB = typeof b.timestamp === 'number' ? b.timestamp * 1000 : new Date(b.timestamp).getTime();
            return dateB - dateA;
          });
          setReplayEntries(validEntries);
          setError(null);
          // Show warning if replay data is empty
          if (validEntries.length === 0) {
            showDrupalMessage(t('No replay data available for this event.'), 'warning');
          }
          // Notify parent immediately with the validated entries
          onEntriesLoadedRef.current?.(validEntries);
        }
      } else {
        setError(t('Failed to load replay data: @status', { '@status': response.statusText }));
        setReplayEntries([]);
      }
    } catch (err: unknown) {
      if (err instanceof Error && err.name !== 'AbortError') {
        setError(t('Error loading replay data: @message', { '@message': err.message }));
        setReplayEntries([]);
      }
    } finally {
      if (!abortController.signal.aborted) {
        setLoading(false);
      }
    }
  }, [settings.modeler_api?.replay_url, settings.modeler_api?.token_url, settings.modeler?.modelId]);

  const clearReplayEntries = useCallback(() => {
    setReplayEntries([]);
    setError(null);
  }, []);

  return {
    replayEntries,
    loading,
    error,
    loadReplayData,
    clearReplayEntries,
  };
}
