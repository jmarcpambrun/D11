/**
 * useReplayStepFilter - Hook for filtering and mapping replay steps
 * 
 * Handles filtering of replay data to hide internal steps that shouldn't be
 * shown to users (like 'add successor' without conditions), and provides
 * mapping functions between filtered and original indices.
 */

import { useMemo, useCallback } from 'react';
import type { StoreNode as Node, StoreEdge as Edge, ReplayDataEntry as ReplayStep } from '../types/settings';

interface UseReplayStepFilterProps {
  replayData?: ReplayStep[] | null;
  nodes: Node[];
  edges: Edge[];
}

interface UseReplayStepFilterReturn {
  /** Filtered replay data with hidden steps removed */
  filteredReplayData: ReplayStep[];
  /** Convert original index to filtered index (-1 if step is hidden) */
  getFilteredIndex: (originalIndex: number) => number;
  /** Convert filtered index back to original index */
  getOriginalIndex: (filteredIndex: number) => number;
}

/**
 * Check if a step should be included in the filtered list
 */
function shouldIncludeStep(
  step: ReplayStep,
  nodes: Node[],
  edges: Edge[]
): boolean {
  // For 'add successor' steps, only include if they have a conditionId
  if (step.type === 'add successor') {
    return !!step.conditionId;
  }

  // For 'ignore successor' steps, check gateway or condition logic
  if (step.type === 'ignore successor' && step.id && step.successorId) {
    // Check if the successor is a gateway node
    const successorNode = nodes.find(n => n.id === step.successorId);
    if (successorNode?.type === 'gateway' || successorNode?.data?.nodeType === 'gateway') {
      // Always include ignore successor steps for gateway nodes
      return true;
    }

    // For non-gateway nodes, check if there's an edge with a condition between the nodes
    const edge = edges.find(e =>
      ((e.source === step.id && e.target === step.successorId) ||
       (e.source === step.successorId && e.target === step.id)) &&
      e.data?.condition
    );
    return !!edge;
  }

  // Include all other step types
  return true;
}

export function useReplayStepFilter({
  replayData,
  nodes,
  edges,
}: UseReplayStepFilterProps): UseReplayStepFilterReturn {
  // Filter replay data to hide internal steps
  const filteredReplayData = useMemo(() => {
    if (!replayData) return [];
    return replayData.filter(step => shouldIncludeStep(step, nodes, edges));
  }, [replayData, nodes, edges]);

  // Map original index to filtered index
  const getFilteredIndex = useCallback((originalIndex: number): number => {
    if (originalIndex < 0 || !replayData) return -1;
    
    let filteredIndex = -1;
    for (let i = 0; i < replayData.length; i++) {
      const step = replayData[i];
      const includeStep = shouldIncludeStep(step, nodes, edges);

      if (includeStep) {
        filteredIndex++;
      }

      if (i === originalIndex) {
        return includeStep ? filteredIndex : -1;
      }
    }
    return -1;
  }, [replayData, nodes, edges]);

  // Map filtered index back to original index
  const getOriginalIndex = useCallback((filteredIndex: number): number => {
    if (filteredIndex < 0 || !replayData) return -1;
    
    let currentFilteredIndex = 0;

    for (let i = 0; i < replayData.length; i++) {
      const step = replayData[i];
      const includeStep = shouldIncludeStep(step, nodes, edges);

      if (includeStep) {
        if (currentFilteredIndex === filteredIndex) {
          return i;
        }
        currentFilteredIndex++;
      }
    }
    
    // Return last index if not found
    return replayData.length - 1;
  }, [replayData, nodes, edges]);

  return {
    filteredReplayData,
    getFilteredIndex,
    getOriginalIndex,
  };
}
