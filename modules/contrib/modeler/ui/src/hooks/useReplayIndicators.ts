/**
 * Custom hook for replay indicators
 * Manages visual indicators during workflow replay for condition results.
 *
 * Positions are returned in **flow coordinates** so they can be rendered
 * inside React Flow's EdgeLabelRenderer and automatically follow pan/zoom.
 */

import { useState, useEffect } from 'react';
import type { StoreNode as Node, StoreEdge as Edge } from '../types/settings';
import { NODE_DIMENSIONS } from '../constants/dimensions';
import { ReplayStep, isConditionStep, isAddSuccessorStep } from '../utils/replayStepUtils';

interface ReplayIndicator {
  id: string;
  /** X position in flow coordinates */
  x: number;
  /** Y position in flow coordinates */
  y: number;
  color: string;
}

interface UseReplayIndicatorsProps {
  isReplayMode: boolean;
  currentReplayStep: number;
  replayData: ReplayStep[] | null;
  edges: Edge[];
  nodes: Node[];
}

export function useReplayIndicators({
  isReplayMode,
  currentReplayStep,
  replayData,
  edges,
  nodes,
}: UseReplayIndicatorsProps) {
  
  const [replayIndicators, setReplayIndicators] = useState<ReplayIndicator[]>([]);

  // Update replay indicators when replay step changes
  useEffect(() => {
    if (!isReplayMode || currentReplayStep < 0 || !replayData || currentReplayStep >= replayData.length) {
      setReplayIndicators([]);
      return;
    }

    const currentStep = replayData[currentReplayStep];
    const indicators: ReplayIndicator[] = [];

    // Check for condition result indicators
    if (isConditionStep(currentStep)) {
      const conditionId = currentStep.conditionId;

      // Primary: Try to find edge by condition ID
      let conditionEdge = edges.find(edge =>
        edge.data?.condition === conditionId ||
        edge.data?.conditionLabel === conditionId
      );

      // Fallback: Find edge by source/target relationship (same as useSimpleReplaySync)
      if (!conditionEdge && currentStep.successorId) {
        conditionEdge = edges.find(edge =>
          edge.source === currentStep.id && edge.target === currentStep.successorId && edge.data?.condition
        );
      }

      if (conditionEdge) {
        // Find source and target nodes to calculate edge center position
        const sourceNode = nodes.find(n => n.id === conditionEdge.source);
        const targetNode = nodes.find(n => n.id === conditionEdge.target);

        if (sourceNode && targetNode) {
          // Calculate edge center position (same logic as condition positioning)
          const sourceX = sourceNode.position.x + (sourceNode.width || NODE_DIMENSIONS.DEFAULT_WIDTH) / 2;
          const sourceY = sourceNode.position.y + (sourceNode.height || NODE_DIMENSIONS.DEFAULT_HEIGHT) / 2;
          const targetX = targetNode.position.x + (targetNode.width || NODE_DIMENSIONS.DEFAULT_WIDTH) / 2;
          const targetY = targetNode.position.y + (targetNode.height || NODE_DIMENSIONS.DEFAULT_HEIGHT) / 2;

          const edgeCenterX = (sourceX + targetX) / 2;
          const edgeCenterY = (sourceY + targetY) / 2;

          // Apply control point offset if edge has been manipulated
          const controlOffset = conditionEdge.data?.controlOffset || { x: 0, y: 0 };
          const flowX = edgeCenterX + controlOffset.x;
          const flowY = edgeCenterY + controlOffset.y - 20; // Position above the condition

          // Return flow coordinates — rendering will use EdgeLabelRenderer
          // which automatically applies the viewport transform.
          indicators.push({
            id: `condition-result-${currentStep.conditionId}`,
            x: flowX,
            y: flowY,
            color: isAddSuccessorStep(currentStep) ? 'var(--modeler-color-success)' : 'var(--modeler-color-danger-soft)' // Green for passed, red for failed
          });
        }
      }
    }

    setReplayIndicators(indicators);
  }, [isReplayMode, currentReplayStep, replayData, edges, nodes]);

  return {
    replayIndicators,
  };
}