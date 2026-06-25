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

    // Check for condition result indicators.
    //
    // Conditions are first-class NODES now (issue #3589093), so the
    // true/false result indicator attaches to the condition NODE's position
    // rather than to a (nonexistent) condition edge.  The step's conditionId
    // historically matched the legacy edge.data.condition value, which carried
    // the condition *plugin* id (see P2 modelUtils, where node.data.plugin is
    // set from edge.condition), so we match plugin id first, then the backend
    // round-trip conditionId.
    if (isConditionStep(currentStep)) {
      const conditionId = currentStep.conditionId;

      const isConditionNode = (n: Node): boolean =>
        n.type === 'condition' || n.data?.__isConditionNode === true;

      // Primary: match conditionId against the condition node's plugin id,
      // then against its backend round-trip conditionId.
      const conditionNode =
        nodes.find(n => isConditionNode(n) && n.data?.plugin === conditionId) ??
        nodes.find(n => isConditionNode(n) && n.data?.conditionId === conditionId);

      if (conditionNode) {
        // Center the indicator above the condition node.  Nodes carry no
        // control-point offset (that was an edge-only concept), so the
        // position derives purely from the node's own coordinates.
        const nodeWidth = conditionNode.width || NODE_DIMENSIONS.DEFAULT_WIDTH;
        const flowX = conditionNode.position.x + nodeWidth / 2;
        const flowY = conditionNode.position.y - 20; // Position above the condition node

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

    setReplayIndicators(indicators);
  }, [isReplayMode, currentReplayStep, replayData, nodes]);

  return {
    replayIndicators,
  };
}