/**
 * Tests for replayStepUtils
 */

import React from 'react';
import { render } from '@testing-library/react';
import {
  getStepIcon,
  getStepLabel,
  ReplayStep,
  isNodeExecutionStep,
  isSuccessorStep,
  isAddSuccessorStep,
  isAccessDeniedStep,
  isConditionStep,
  findReplayStepForElement,
  findElementForReplayStep,
  findMatchingReplayStepForSelection,
} from '../replayStepUtils';
import type { StoreNode as Node, StoreEdge as Edge } from '../../types/settings';

describe('replayStepUtils', () => {
  describe('getStepIcon', () => {
    it('should return play icon for started step', () => {
      const step: ReplayStep = { type: 'started' };
      const { container } = render(<>{getStepIcon(step)}</>);
      expect(container.querySelector('.step-icon.started')).toBeInTheDocument();
    });

    it('should return activity icon for execute step', () => {
      const step: ReplayStep = { type: 'execute' };
      const { container } = render(<>{getStepIcon(step)}</>);
      expect(container.querySelector('.step-icon.execute')).toBeInTheDocument();
    });

    it('should return chevron right icon for add successor step', () => {
      const step: ReplayStep = { type: 'add successor' };
      const { container } = render(<>{getStepIcon(step)}</>);
      expect(container.querySelector('.step-icon.add-successor')).toBeInTheDocument();
    });

    it('should return faded chevron icon for ignore successor step', () => {
      const step: ReplayStep = { type: 'ignore successor' };
      const { container } = render(<>{getStepIcon(step)}</>);
      const icon = container.querySelector('.step-icon.ignore-successor');
      expect(icon).toBeInTheDocument();
      expect(icon).toHaveStyle({ opacity: '0.5' });
    });

    it('should return alert icon for access denied step', () => {
      const step: ReplayStep = { type: 'access denied' };
      const { container } = render(<>{getStepIcon(step)}</>);
      expect(container.querySelector('.step-icon.access-denied')).toBeInTheDocument();
    });

    it('should return clock icon for unknown step type', () => {
      const step: ReplayStep = { type: 'unknown' };
      const { container } = render(<>{getStepIcon(step)}</>);
      expect(container.querySelector('.step-icon.default')).toBeInTheDocument();
    });
  });

  describe('getStepLabel', () => {
    const createNode = (id: string, label: string): Node => ({
      id,
      type: 'element',
      position: { x: 0, y: 0 },
      data: { label },
    });

    const createEdge = (id: string, source: string, target: string, condition?: string, conditionLabel?: string): Edge => ({
      id,
      source,
      target,
      type: 'default',
      data: condition ? { condition, conditionLabel: conditionLabel || condition } : {},
    });

    it('should return numbered label for started step with node label', () => {
      const step: ReplayStep = { type: 'started', id: 'node1' };
      const nodes = [createNode('node1', 'Start Event')];
      const edges: Edge[] = [];

      const label = getStepLabel(step, 0, nodes, edges);
      expect(label).toBe('1: Start Event');
    });

    it('should return numbered label for execute step', () => {
      const step: ReplayStep = { type: 'execute', id: 'action1' };
      const nodes = [createNode('action1', 'Send Email')];
      const edges: Edge[] = [];

      const label = getStepLabel(step, 2, nodes, edges);
      expect(label).toBe('3: Send Email');
    });

    it('should use node ID when label not found', () => {
      const step: ReplayStep = { type: 'execute', id: 'node123' };
      const nodes: Node[] = [];
      const edges: Edge[] = [];

      const label = getStepLabel(step, 0, nodes, edges);
      expect(label).toBe('1: node123');
    });

    it('should return condition label for add successor with conditionId', () => {
      const step: ReplayStep = {
        type: 'add successor',
        id: 'node1',
        successorId: 'node2',
        conditionId: 'edge1',
      };
      const nodes = [
        createNode('node1', 'Gateway'),
        createNode('node2', 'Action'),
      ];
      const edges = [createEdge('edge1', 'node1', 'node2', 'is_admin', 'User is Admin')];

      const label = getStepLabel(step, 1, nodes, edges);
      expect(label).toBe('2: User is Admin');
    });

    it('should find edge by source/target when conditionId not found', () => {
      const step: ReplayStep = {
        type: 'add successor',
        id: 'node1',
        successorId: 'node2',
      };
      const nodes = [
        createNode('node1', 'Gateway'),
        createNode('node2', 'Action'),
      ];
      const edges = [createEdge('edge1', 'node1', 'node2', 'condition1', 'My Condition')];

      const label = getStepLabel(step, 0, nodes, edges);
      expect(label).toBe('1: My Condition');
    });

    it('should fall back to successor node label when no condition', () => {
      const step: ReplayStep = {
        type: 'add successor',
        id: 'node1',
        successorId: 'node2',
      };
      const nodes = [
        createNode('node1', 'Gateway'),
        createNode('node2', 'Next Action'),
      ];
      const edges = [createEdge('edge1', 'node1', 'node2')]; // No condition

      const label = getStepLabel(step, 0, nodes, edges);
      expect(label).toBe('1: Next Action');
    });

    it('should return condition label for ignore successor', () => {
      const step: ReplayStep = {
        type: 'ignore successor',
        id: 'node1',
        successorId: 'node2',
        conditionId: 'edge1',
      };
      const nodes = [
        createNode('node1', 'Gateway'),
        createNode('node2', 'Skipped Action'),
      ];
      const edges = [createEdge('edge1', 'node1', 'node2', 'is_inactive', 'User Inactive')];

      const label = getStepLabel(step, 3, nodes, edges);
      expect(label).toBe('4: User Inactive');
    });

    it('should return access denied label', () => {
      const step: ReplayStep = { type: 'access denied' };
      const label = getStepLabel(step, 5, [], []);
      expect(label).toBe('6: Access Denied');
    });

    it('should return step type for unknown types', () => {
      const step: ReplayStep = { type: 'custom_step' };
      const label = getStepLabel(step, 0, [], []);
      expect(label).toBe('1: custom_step');
    });

    it('should handle reverse edge direction', () => {
      const step: ReplayStep = {
        type: 'add successor',
        id: 'node1',
        successorId: 'node2',
      };
      const nodes = [
        createNode('node1', 'Source'),
        createNode('node2', 'Target'),
      ];
      // Edge is defined in reverse direction
      const edges = [createEdge('edge1', 'node2', 'node1', 'reverse', 'Reverse Condition')];

      const label = getStepLabel(step, 0, nodes, edges);
      expect(label).toBe('1: Reverse Condition');
    });

    it('should handle missing node ID gracefully', () => {
      const step: ReplayStep = { type: 'started' };
      const label = getStepLabel(step, 0, [], []);
      expect(label).toBe('1: Component');
    });
  });

  // ============ Step Type Predicates ============

  describe('isNodeExecutionStep', () => {
    it('should return true for started', () => {
      expect(isNodeExecutionStep({ type: 'started' })).toBe(true);
    });
    it('should return true for execute', () => {
      expect(isNodeExecutionStep({ type: 'execute' })).toBe(true);
    });
    it('should return true for access denied', () => {
      expect(isNodeExecutionStep({ type: 'access denied' })).toBe(true);
    });
    it('should return false for add successor', () => {
      expect(isNodeExecutionStep({ type: 'add successor' })).toBe(false);
    });
    it('should return false for ignore successor', () => {
      expect(isNodeExecutionStep({ type: 'ignore successor' })).toBe(false);
    });
    it('should return false for unknown types', () => {
      expect(isNodeExecutionStep({ type: 'custom' })).toBe(false);
    });
  });

  describe('isSuccessorStep', () => {
    it('should return true for add successor', () => {
      expect(isSuccessorStep({ type: 'add successor' })).toBe(true);
    });
    it('should return true for ignore successor', () => {
      expect(isSuccessorStep({ type: 'ignore successor' })).toBe(true);
    });
    it('should return false for execute', () => {
      expect(isSuccessorStep({ type: 'execute' })).toBe(false);
    });
  });

  describe('isAddSuccessorStep', () => {
    it('should return true for add successor', () => {
      expect(isAddSuccessorStep({ type: 'add successor' })).toBe(true);
    });
    it('should return false for ignore successor', () => {
      expect(isAddSuccessorStep({ type: 'ignore successor' })).toBe(false);
    });
  });

  describe('isAccessDeniedStep', () => {
    it('should return true for access denied', () => {
      expect(isAccessDeniedStep({ type: 'access denied' })).toBe(true);
    });
    it('should return false for execute', () => {
      expect(isAccessDeniedStep({ type: 'execute' })).toBe(false);
    });
  });

  describe('isConditionStep', () => {
    it('should return true for add successor with conditionId', () => {
      expect(isConditionStep({ type: 'add successor', conditionId: 'c1' })).toBe(true);
    });
    it('should return true for ignore successor with conditionId', () => {
      expect(isConditionStep({ type: 'ignore successor', conditionId: 'c1' })).toBe(true);
    });
    it('should return false for add successor without conditionId', () => {
      expect(isConditionStep({ type: 'add successor' })).toBe(false);
    });
    it('should return false for execute even with conditionId', () => {
      expect(isConditionStep({ type: 'execute', conditionId: 'c1' })).toBe(false);
    });
  });

  // ============ Element Matching ============

  describe('findReplayStepForElement', () => {
    const edges: Edge[] = [
      { id: 'e1', source: 'n1', target: 'n2', data: { condition: 'cond1' } },
      { id: 'e2', source: 'n2', target: 'n3', data: {} },
    ];

    const replayData: ReplayStep[] = [
      { id: 'n1', type: 'started' },
      { id: 'n1', type: 'execute' },
      { id: 'n1', type: 'add successor', successorId: 'n2', conditionId: 'cond1' },
      { id: 'n2', type: 'execute' },
      { id: 'n2', type: 'add successor', successorId: 'n3' },
      { id: 'n3', type: 'access denied' },
    ];

    it('should find node execution step for node element', () => {
      expect(findReplayStepForElement(replayData, edges, 'n1', 'node')).toBe(0);
    });

    it('should find first node execution step (started before execute)', () => {
      expect(findReplayStepForElement(replayData, edges, 'n1', 'node')).toBe(0);
    });

    it('should find execute step for node without started step', () => {
      expect(findReplayStepForElement(replayData, edges, 'n2', 'node')).toBe(3);
    });

    it('should find access denied step for node', () => {
      expect(findReplayStepForElement(replayData, edges, 'n3', 'node')).toBe(5);
    });

    it('should find edge step by source/target', () => {
      expect(findReplayStepForElement(replayData, edges, 'e2', 'edge')).toBe(4);
    });

    it('should find condition step by conditionId', () => {
      expect(findReplayStepForElement(replayData, edges, 'cond1', 'condition')).toBe(2);
    });

    it('should return -1 for non-existent element', () => {
      expect(findReplayStepForElement(replayData, edges, 'nonexistent', 'node')).toBe(-1);
    });

    it('should return -1 for empty replay data', () => {
      expect(findReplayStepForElement([], edges, 'n1', 'node')).toBe(-1);
    });

    it('should return -1 when no node execution step exists (only successor steps)', () => {
      const data: ReplayStep[] = [
        { id: 'n1', type: 'add successor', successorId: 'n2' },
      ];
      expect(findReplayStepForElement(data, edges, 'n1', 'node')).toBe(-1);
    });
  });

  describe('findElementForReplayStep', () => {
    const nodes: Node[] = [
      { id: 'n1', type: 'start', position: { x: 0, y: 0 }, data: { label: 'N1' } },
      { id: 'n2', type: 'element', position: { x: 100, y: 0 }, data: { label: 'N2' } },
    ];

    const edges: Edge[] = [
      { id: 'e1', source: 'n1', target: 'n2', data: { condition: 'cond1' } },
      { id: 'e2', source: 'n2', target: 'n3', data: {} },
    ];

    const replayData: ReplayStep[] = [
      { id: 'n1', type: 'started' },
      { id: 'n1', type: 'execute' },
      { id: 'n1', type: 'add successor', successorId: 'n2', conditionId: 'cond1' },
      { id: 'n2', type: 'execute' },
      { id: 'n2', type: 'add successor', successorId: 'n3' },
    ];

    it('should find node for started step', () => {
      expect(findElementForReplayStep(replayData, nodes, edges, 0)).toEqual({ type: 'node', id: 'n1' });
    });

    it('should find node for execute step', () => {
      expect(findElementForReplayStep(replayData, nodes, edges, 1)).toEqual({ type: 'node', id: 'n1' });
    });

    it('should find edge for condition step', () => {
      expect(findElementForReplayStep(replayData, nodes, edges, 2)).toEqual({ type: 'edge', id: 'e1' });
    });

    it('should find edge by source/target for successor step without condition', () => {
      expect(findElementForReplayStep(replayData, nodes, edges, 4)).toEqual({ type: 'edge', id: 'e2' });
    });

    it('should return null for negative step index', () => {
      expect(findElementForReplayStep(replayData, nodes, edges, -1)).toBeNull();
    });

    it('should return null for out-of-bounds step index', () => {
      expect(findElementForReplayStep(replayData, nodes, edges, 100)).toBeNull();
    });
  });

  describe('findMatchingReplayStepForSelection', () => {
    const edges: Edge[] = [
      { id: 'e1', source: 'n1', target: 'n2', data: { condition: 'cond1' } },
    ];

    const replayData: ReplayStep[] = [
      { id: 'n1', type: 'started' },
      { id: 'n1', type: 'execute' },
      { id: 'n1', type: 'add successor', successorId: 'n2', conditionId: 'cond1' },
    ];

    it('should find step matching a selected node', () => {
      const node: Node = { id: 'n1', position: { x: 0, y: 0 }, data: {} };
      expect(findMatchingReplayStepForSelection(replayData, edges, node, null)).toBe(0);
    });

    it('should find step matching a selected edge by conditionId', () => {
      const edge: Edge = { id: 'e1', source: 'n1', target: 'n2', data: { condition: 'cond1' } };
      expect(findMatchingReplayStepForSelection(replayData, edges, null, edge)).toBe(2);
    });

    it('should find step matching edge by source/target', () => {
      const data: ReplayStep[] = [
        { id: 'n1', type: 'add successor', successorId: 'n2' },
      ];
      const edge: Edge = { id: 'e1', source: 'n1', target: 'n2', data: {} };
      expect(findMatchingReplayStepForSelection(data, edges, null, edge)).toBe(0);
    });

    it('should return -1 when no match for node', () => {
      const node: Node = { id: 'nonexistent', position: { x: 0, y: 0 }, data: {} };
      expect(findMatchingReplayStepForSelection(replayData, edges, node, null)).toBe(-1);
    });

    it('should return -1 when both node and edge are null', () => {
      expect(findMatchingReplayStepForSelection(replayData, edges, null, null)).toBe(-1);
    });

    it('should return -1 for empty replay data', () => {
      const node: Node = { id: 'n1', position: { x: 0, y: 0 }, data: {} };
      expect(findMatchingReplayStepForSelection([], edges, node, null)).toBe(-1);
    });
  });
});
