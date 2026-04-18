/**
 * replayStepUtils - Shared utilities for replay step display and matching
 * 
 * Provides:
 * - Step type predicates for consistent step-type checks across replay hooks
 * - Element-matching functions to find canvas elements for replay steps and vice versa
 * - Icon rendering and label generation for replay steps
 * 
 * Used by ReplayPanel, ReplayTab, useSimpleReplaySync, useReplayController,
 * useReplayCoordination, and useReplayStepFilter.
 */

import React from 'react';
import { FiPlay, FiActivity, FiChevronRight, FiAlertTriangle, FiXCircle, FiClock } from 'react-icons/fi';
import type { StoreNode as Node, StoreEdge as Edge, ReplayDataEntry } from '../types/settings';
import { t } from './translation';

/**
 * Alias for {@link ReplayDataEntry} — used throughout the replay subsystem.
 * Kept as a named export so existing consumers (`useSimpleReplaySync`,
 * `FlowCanvas`, etc.) do not need renaming.
 */
export type ReplayStep = ReplayDataEntry;

// ============ Step Type Predicates ============

/** Check if a step represents direct node execution (started, execute, access denied, exception) */
export function isNodeExecutionStep(step: ReplayStep): boolean {
  return step.type === 'started' || step.type === 'execute' || step.type === 'access denied' || step.type === 'exception';
}

/** Check if a step is a successor step (add successor or ignore successor) */
export function isSuccessorStep(step: ReplayStep): boolean {
  return step.type === 'add successor' || step.type === 'ignore successor';
}

/** Check if a step is an 'add successor' step */
export function isAddSuccessorStep(step: ReplayStep): boolean {
  return step.type === 'add successor';
}

/** Check if a step is an 'access denied' step */
export function isAccessDeniedStep(step: ReplayStep): boolean {
  return step.type === 'access denied';
}

/** Check if a step is an 'exception' step */
export function isExceptionStep(step: ReplayStep): boolean {
  return step.type === 'exception';
}

/** Check if a step is a condition step (successor step with conditionId) */
export function isConditionStep(step: ReplayStep): boolean {
  return isSuccessorStep(step) && !!step.conditionId;
}

// ============ Element Matching ============

/**
 * Find the first replay step index that matches a given canvas element.
 * Used for canvas-to-replay synchronization.
 */
export function findReplayStepForElement(
  replayData: ReplayStep[],
  edges: Edge[],
  elementId: string,
  elementType: 'node' | 'edge' | 'condition'
): number {
  if (!replayData || replayData.length === 0) return -1;
  
  for (let i = 0; i < replayData.length; i++) {
    const step = replayData[i];
    
    if (elementType === 'node') {
      // Priority: Find steps where this node is the main actor
      if (step.id === elementId && isNodeExecutionStep(step)) {
        return i;
      }
    } else if (elementType === 'edge') {
      // For edges, check if this edge matches the step's source->target
      const edge = edges.find(e => e.id === elementId);
      if (edge && step.id === edge.source && step.successorId === edge.target) {
        return i;
      }
    } else if (elementType === 'condition') {
      // For conditions attached to edges
      if (step.conditionId === elementId) {
        return i;
      }
    }
  }
  
  return -1;
}

/**
 * Find the canvas element (node or edge) that corresponds to a replay step.
 * Used for replay-to-canvas synchronization.
 */
export function findElementForReplayStep(
  replayData: ReplayStep[],
  nodes: Node[],
  edges: Edge[],
  stepIndex: number
): { type: 'node' | 'edge'; id: string } | null {
  if (stepIndex < 0 || stepIndex >= replayData.length) return null;
  
  const step = replayData[stepIndex];
  
  // For successor steps, handle edge finding with condition logic
  if (isSuccessorStep(step)) {
    // Priority 1: If step has conditionId, find the edge with that condition
    if (step.conditionId) {
      const edge = edges.find(e => e.data?.condition === step.conditionId);
      if (edge) {
        return { type: 'edge', id: edge.id };
      }
      
      // Fallback: find by source/target relationship and verify it has a condition
      if (step.successorId) {
        const fallbackEdge = edges.find(e => 
          e.source === step.id && e.target === step.successorId && e.data?.condition
        );
        if (fallbackEdge) {
          return { type: 'edge', id: fallbackEdge.id };
        }
      }
    }
    
    // Priority 2: If step has successorId, find the edge by source/target
    if (step.successorId) {
      const edge = edges.find(e => e.source === step.id && e.target === step.successorId);
      if (edge) {
        return { type: 'edge', id: edge.id };
      }
    }
  }
  
  // Priority 3: For other steps with conditionId, find the edge with that condition
  if (step.conditionId) {
    const edge = edges.find(e => e.data?.condition === step.conditionId);
    if (edge) {
      return { type: 'edge', id: edge.id };
    }
  }
  
  // Priority 4: Find the node by ID
  if (step.id) {
    const node = nodes.find(n => n.id === step.id);
    if (node) {
      return { type: 'node', id: step.id };
    }
  }
  
  return null;
}

/**
 * Find the matching replay step for a given node or edge selection.
 * More flexible matcher used by useReplayCoordination.
 */
export function findMatchingReplayStepForSelection(
  replayData: ReplayStep[],
  edges: Edge[],
  node: Node | null,
  edge: Edge | null
): number {
  if (!replayData || replayData.length === 0) return -1;
  
  if (node) {
    // Find steps where this node is the main actor
    return replayData.findIndex(step =>
      step.id === node.id && isNodeExecutionStep(step)
    );
  }
  
  if (edge) {
    // For edges with conditions, find by conditionId first
    if (edge.data?.condition) {
      const stepIndex = replayData.findIndex(step => step.conditionId === edge.data!.condition);
      if (stepIndex !== -1) return stepIndex;
    }
    
    // Fallback to source/target matching
    return replayData.findIndex(step =>
      step.id === edge.source && step.successorId === edge.target
    );
  }
  
  return -1;
}

/**
 * Get the appropriate icon for a replay step based on its type
 */
export function getStepIcon(step: ReplayStep): React.ReactNode {
  switch (step.type) {
    case 'started':
      return <FiPlay className="step-icon started" />;
    case 'execute':
      return <FiActivity className="step-icon execute" />;
    case 'add successor':
      return <FiChevronRight className="step-icon add-successor" />;
    case 'ignore successor':
      return <FiChevronRight className="step-icon ignore-successor" style={{ opacity: 0.5 }} />;
    case 'access denied':
      return <FiAlertTriangle className="step-icon access-denied" />;
    case 'exception':
      return <FiXCircle className="step-icon exception" />;
    default:
      return <FiClock className="step-icon default" />;
  }
}

/**
 * Get a human-readable label for a replay step
 */
export function getStepLabel(
  step: ReplayStep,
  index: number,
  nodes: Node[],
  edges: Edge[]
): string {
  // Helper to get node label by ID
  const getNodeLabel = (nodeId: string): string => {
    const node = nodes.find(n => n.id === nodeId);
    return node?.data?.label || nodeId || t('Component');
  };

  switch (step.type) {
    case 'started':
      // For started, show the event node label that triggered the process
      return `${index + 1}: ${getNodeLabel(step.id || '')}`;
    case 'execute':
      return `${index + 1}: ${getNodeLabel(step.id || '')}`;
    case 'add successor':
    case 'ignore successor': {
      // For add/ignore successor, the conditionId is the edge ID that was evaluated
      // The condition is on the edge from step.id to step.successorId
      if (step.conditionId) {
        // Find the edge by its ID
        const edge = edges.find(e => e.id === step.conditionId);
        const conditionLabel = edge?.data?.conditionLabel || edge?.data?.condition;
        if (conditionLabel) {
          return `${index + 1}: ${conditionLabel}`;
        }
      }

      // If no conditionId or no label found, try to find edge between the nodes
      const edge = edges.find(e =>
        (e.source === step.id && e.target === step.successorId) ||
        (e.target === step.id && e.source === step.successorId)
      );
      const conditionLabel = edge?.data?.conditionLabel || edge?.data?.condition;
      if (conditionLabel) {
        return `${index + 1}: ${conditionLabel}`;
      }

      // Last fallback: show the successor node label
      const nodeLabel = getNodeLabel(step.successorId || '');
      return `${index + 1}: ${nodeLabel}`;
    }
    case 'access denied':
      return `${index + 1}: ${t('Access Denied')}`;
    case 'exception': {
      const label = getNodeLabel(step.id || '');
      const msg = step.exception && typeof step.exception === 'object' && 'message' in step.exception
        ? `: ${String(step.exception.message)}`
        : '';
      return `${index + 1}: ${label}${msg}`;
    }
    default:
      return `${index + 1}: ${step.type}`;
  }
}
