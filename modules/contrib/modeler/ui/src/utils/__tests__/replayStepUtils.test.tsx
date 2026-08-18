/**
 * Tests for replayStepUtils
 */

import React from 'react';
import { render } from '@testing-library/react';
import {
  FiZap,
  FiActivity,
  FiGitBranch,
  FiFilter,
  FiLayers,
  FiChevronRight,
  FiAlertTriangle,
  FiXCircle,
  FiClock,
} from 'react-icons/fi';
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
  isConditionNode,
  getConditionNodeIdentifiers,
  findConditionNodeForStep,
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

    // ---- Canvas icon alignment --------------------------------------------
    // The step list must show the SAME glyph the canvas shows for the node the
    // step describes. See nodeIcons.test.tsx for the end-to-end drift lock.

    /** Inner SVG markup of the rendered step icon (identifies the glyph). */
    const stepIconMarkup = (step: ReplayStep, nodes: Node[] = []): string => {
      const { container } = render(<>{getStepIcon(step, nodes)}</>);
      return container.querySelector('svg.step-icon')!.innerHTML;
    };

    /** Inner SVG markup of a bare react-icons component. */
    const refMarkup = (Icon: React.ComponentType): string => {
      const { container } = render(<Icon />);
      return container.querySelector('svg')!.innerHTML;
    };

    const conditionNode: Node = {
      id: 'cond1',
      type: 'condition',
      position: { x: 0, y: 0 },
      data: { label: 'Field is empty', conditionId: 'cfg-1', plugin: 'eca_scalar', __isConditionNode: true },
    };
    const gatewayNode: Node = {
      id: 'gw1',
      type: 'gateway',
      position: { x: 0, y: 0 },
      data: { label: 'Gateway' },
    };
    const actionNode: Node = {
      id: 'act1',
      type: 'element',
      position: { x: 0, y: 0 },
      data: { label: 'Action' },
    };
    const subprocessNode: Node = {
      id: 'sub1',
      type: 'subprocess',
      position: { x: 0, y: 0 },
      data: { label: 'Subprocess' },
    };

    it('should use the event icon (FiZap) for a started step', () => {
      expect(stepIconMarkup({ type: 'started', id: 'e1' })).toBe(refMarkup(FiZap));
    });

    it('should use the action icon (FiActivity) for an execute step on an action node', () => {
      expect(stepIconMarkup({ type: 'execute', id: 'act1' }, [actionNode])).toBe(refMarkup(FiActivity));
    });

    it('should use the subprocess icon (FiLayers) for an execute step on a subprocess node', () => {
      expect(stepIconMarkup({ type: 'execute', id: 'sub1' }, [subprocessNode])).toBe(refMarkup(FiLayers));
    });

    it('should use the gateway icon (FiGitBranch) for an execute step on a gateway node', () => {
      expect(stepIconMarkup({ type: 'execute', id: 'gw1' }, [gatewayNode])).toBe(refMarkup(FiGitBranch));
    });

    it('should use the condition icon (FiFilter) for an add successor step with a resolvable condition', () => {
      const step: ReplayStep = { type: 'add successor', id: 'n1', successorId: 'n2', conditionId: 'cfg-1' };
      expect(stepIconMarkup(step, [conditionNode])).toBe(refMarkup(FiFilter));
    });

    it('should use the condition icon (FiFilter) for an ignore successor step, and keep it visually dimmed', () => {
      const step: ReplayStep = { type: 'ignore successor', id: 'n1', successorId: 'n2', conditionId: 'cfg-1' };
      expect(stepIconMarkup(step, [conditionNode])).toBe(refMarkup(FiFilter));

      // TRUE vs FALSE must stay distinguishable now that both are FiFilter.
      const { container } = render(<>{getStepIcon(step, [conditionNode])}</>);
      const icon = container.querySelector('svg.step-icon')!;
      expect(icon).toHaveClass('ignore-successor');
      expect(icon).not.toHaveClass('add-successor');
      expect(icon).toHaveStyle({ opacity: '0.5' });
    });

    it('should NOT dim the add successor icon (TRUE branch stays full opacity)', () => {
      const step: ReplayStep = { type: 'add successor', id: 'n1', successorId: 'n2', conditionId: 'cfg-1' };
      const { container } = render(<>{getStepIcon(step, [conditionNode])}</>);
      const icon = container.querySelector('svg.step-icon')!;
      expect(icon).toHaveClass('add-successor');
      expect(icon).not.toHaveClass('ignore-successor');
      expect(icon).not.toHaveStyle({ opacity: '0.5' });
    });

    it('should use the gateway icon when the successor is a gateway and there is no condition', () => {
      const step: ReplayStep = { type: 'add successor', id: 'n1', successorId: 'gw1' };
      expect(stepIconMarkup(step, [gatewayNode])).toBe(refMarkup(FiGitBranch));
    });

    it('should prefer the condition icon over the successor icon when both resolve', () => {
      // The row is LABELED with the condition name, so the icon must match it.
      const step: ReplayStep = { type: 'add successor', id: 'n1', successorId: 'gw1', conditionId: 'cfg-1' };
      expect(stepIconMarkup(step, [conditionNode, gatewayNode])).toBe(refMarkup(FiFilter));
    });

    it('should keep FiAlertTriangle / FiXCircle / FiClock for state-based steps', () => {
      expect(stepIconMarkup({ type: 'access denied' })).toBe(refMarkup(FiAlertTriangle));
      expect(stepIconMarkup({ type: 'exception' })).toBe(refMarkup(FiXCircle));
      expect(stepIconMarkup({ type: 'totally unknown' })).toBe(refMarkup(FiClock));
    });

    it('should degrade gracefully when nodes are omitted or nothing resolves', () => {
      // No nodes at all — must not throw, and must still produce an icon.
      expect(() => getStepIcon({ type: 'execute', id: 'missing' })).not.toThrow();
      expect(stepIconMarkup({ type: 'execute', id: 'missing' })).toBe(refMarkup(FiActivity));

      // Successor step with nothing resolvable keeps the original chevron.
      expect(stepIconMarkup({ type: 'add successor', id: 'n1', successorId: 'gone' })).toBe(
        refMarkup(FiChevronRight)
      );
      expect(stepIconMarkup({ type: 'ignore successor', id: 'n1' })).toBe(refMarkup(FiChevronRight));
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

    // ---- Condition NODES (issues #3589093 / #3589108) ----------------------
    // After promoteConditionEdges() no EDGE carries condition data any more, so
    // the label MUST come from the condition NODE. Previously these fell
    // through to the successor node's label.

    const createConditionNode = (
      id: string,
      label: string,
      identifiers: { conditionId?: string; plugin?: string }
    ): Node => ({
      id,
      type: 'condition',
      position: { x: 0, y: 0 },
      data: {
        label,
        conditionId: identifiers.conditionId ?? '',
        plugin: identifiers.plugin ?? '',
        __isConditionNode: true,
      },
    });

    it('should return the condition NODE label (not the successor label) for add successor', () => {
      const step: ReplayStep = {
        type: 'add successor',
        id: 'node1',
        successorId: 'node2',
        // ECA condition CONFIG id — never an edge id, never a node id.
        conditionId: 'eca-condition-uuid-1',
      };
      const nodes = [
        createNode('node1', 'Start Event'),
        createConditionNode('cond1', 'Field is empty', { conditionId: 'eca-condition-uuid-1', plugin: 'eca_scalar' }),
        createNode('node2', 'Get next field'),
      ];
      // Plain, post-promotion edges: node1 -> cond1 -> node2, no condition data.
      const edges: Edge[] = [
        { id: 'e-in', source: 'node1', target: 'cond1', type: 'default', data: {} },
        { id: 'e-out', source: 'cond1', target: 'node2', type: 'default', data: {} },
      ];

      const label = getStepLabel(step, 0, nodes, edges);
      expect(label).toBe('1: Field is empty');
      expect(label).not.toContain('Get next field');
    });

    it('should return the condition NODE label (not the successor label) for ignore successor', () => {
      const step: ReplayStep = {
        type: 'ignore successor',
        id: 'node1',
        successorId: 'node2',
        conditionId: 'eca-condition-uuid-1',
      };
      const nodes = [
        createNode('node1', 'Start Event'),
        createConditionNode('cond1', 'Field is empty', { conditionId: 'eca-condition-uuid-1', plugin: 'eca_scalar' }),
        createNode('node2', 'Get next field'),
      ];
      const edges: Edge[] = [
        { id: 'e-in', source: 'node1', target: 'cond1', type: 'default', data: {} },
        { id: 'e-out', source: 'cond1', target: 'node2', type: 'default', data: {} },
      ];

      const label = getStepLabel(step, 4, nodes, edges);
      expect(label).toBe('5: Field is empty');
      expect(label).not.toContain('Get next field');
    });

    it('should resolve the condition NODE label via the legacy data.plugin fallback', () => {
      const step: ReplayStep = {
        type: 'add successor',
        id: 'node1',
        successorId: 'node2',
        conditionId: 'eca_scalar',
      };
      const nodes = [
        createNode('node1', 'Start Event'),
        // Only the plugin id matches — legacy replay recordings.
        createConditionNode('cond1', 'Scalar comparison', { conditionId: '', plugin: 'eca_scalar' }),
        createNode('node2', 'Get next field'),
      ];

      const label = getStepLabel(step, 0, nodes, []);
      expect(label).toBe('1: Scalar comparison');
    });

    it('should still fall back to the successor label when no condition node matches', () => {
      const step: ReplayStep = {
        type: 'ignore successor',
        id: 'node1',
        successorId: 'node2',
        conditionId: 'unknown-condition',
      };
      const nodes = [
        createNode('node1', 'Start Event'),
        createNode('node2', 'Get next field'),
      ];

      const label = getStepLabel(step, 0, nodes, []);
      expect(label).toBe('1: Get next field');
    });
  });

  // ============ Condition Node Helpers ============

  describe('isConditionNode', () => {
    it('should return true for a node typed "condition"', () => {
      expect(isConditionNode({ id: 'c', type: 'condition' })).toBe(true);
    });
    it('should return true for a node flagged with __isConditionNode', () => {
      expect(isConditionNode({ id: 'c', data: { __isConditionNode: true } })).toBe(true);
    });
    it('should return false for a regular node', () => {
      expect(isConditionNode({ id: 'n', type: 'element', data: {} })).toBe(false);
    });
    it('should return false for null/undefined', () => {
      expect(isConditionNode(null)).toBe(false);
      expect(isConditionNode(undefined)).toBe(false);
    });
  });

  describe('getConditionNodeIdentifiers', () => {
    it('should return conditionId before plugin', () => {
      expect(
        getConditionNodeIdentifiers({ id: 'c', data: { conditionId: 'cfg-1', plugin: 'plug-1' } })
      ).toEqual(['cfg-1', 'plug-1']);
    });
    it('should omit blank identifiers', () => {
      expect(getConditionNodeIdentifiers({ id: 'c', data: { conditionId: '', plugin: 'plug-1' } })).toEqual(['plug-1']);
      expect(getConditionNodeIdentifiers({ id: 'c', data: {} })).toEqual([]);
    });
  });

  describe('findConditionNodeForStep', () => {
    it('should prefer data.conditionId over data.plugin when they point at different nodes', () => {
      const nodes: Node[] = [
        {
          id: 'by-plugin',
          type: 'condition',
          position: { x: 0, y: 0 },
          data: { label: 'Wrong', plugin: 'shared-id', conditionId: 'other', __isConditionNode: true },
        },
        {
          id: 'by-condition-id',
          type: 'condition',
          position: { x: 0, y: 0 },
          data: { label: 'Right', plugin: 'something-else', conditionId: 'shared-id', __isConditionNode: true },
        },
      ];
      const step: ReplayStep = { type: 'add successor', conditionId: 'shared-id' };

      expect(findConditionNodeForStep(nodes, step)?.id).toBe('by-condition-id');
    });

    it('should fall back to data.plugin when no conditionId matches', () => {
      const nodes: Node[] = [
        {
          id: 'cond',
          type: 'condition',
          position: { x: 0, y: 0 },
          data: { label: 'Legacy', plugin: 'legacy-plugin', conditionId: '', __isConditionNode: true },
        },
      ];
      const step: ReplayStep = { type: 'ignore successor', conditionId: 'legacy-plugin' };

      expect(findConditionNodeForStep(nodes, step)?.id).toBe('cond');
    });

    it('should return undefined when the step has no conditionId', () => {
      const nodes: Node[] = [
        {
          id: 'cond',
          type: 'condition',
          position: { x: 0, y: 0 },
          data: { label: 'C', plugin: 'p', conditionId: 'c', __isConditionNode: true },
        },
      ];
      expect(findConditionNodeForStep(nodes, { type: 'add successor' })).toBeUndefined();
    });

    it('should ignore non-condition nodes that happen to carry a matching id', () => {
      const nodes: Node[] = [
        { id: 'plain', type: 'element', position: { x: 0, y: 0 }, data: { conditionId: 'cfg-1' } },
      ];
      expect(findConditionNodeForStep(nodes, { type: 'add successor', conditionId: 'cfg-1' })).toBeUndefined();
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

    // ---- Condition-node awareness (issue #3589108) -------------------------
    // A condition NODE is never a step's `id` (the step id is the predecessor),
    // so it can only be resolved through its own condition identifiers. This is
    // what stops Flow.tsx from mislabeling condition step data as "Predicted".

    const conditionNodes: Node[] = [
      { id: 'n1', type: 'start', position: { x: 0, y: 0 }, data: { label: 'N1' } },
      {
        id: 'cond1',
        type: 'condition',
        position: { x: 50, y: 0 },
        data: { label: 'Field is empty', plugin: 'eca_scalar', conditionId: 'cfg-1', __isConditionNode: true },
      },
      { id: 'n2', type: 'element', position: { x: 100, y: 0 }, data: { label: 'N2' } },
      {
        id: 'cond-uncovered',
        type: 'condition',
        position: { x: 150, y: 0 },
        data: { label: 'Never evaluated', plugin: 'eca_other', conditionId: 'cfg-2', __isConditionNode: true },
      },
    ];

    const conditionReplayData: ReplayStep[] = [
      { id: 'n1', type: 'started' },
      { id: 'n1', type: 'add successor', successorId: 'n2', conditionId: 'cfg-1' },
      { id: 'n2', type: 'execute' },
    ];

    it('should resolve a selected condition NODE to its covering step when nodes are supplied', () => {
      expect(
        findReplayStepForElement(conditionReplayData, [], 'cond1', 'node', conditionNodes)
      ).toBe(1);
    });

    it('should resolve a condition NODE covered by an "ignore successor" step', () => {
      const data: ReplayStep[] = [
        { id: 'n1', type: 'started' },
        { id: 'n1', type: 'ignore successor', successorId: 'n2', conditionId: 'cfg-1' },
      ];
      expect(findReplayStepForElement(data, [], 'cond1', 'node', conditionNodes)).toBe(1);
    });

    it('should return -1 for a condition NODE with no covering step (predicted-token fallback)', () => {
      // Guards issue #3577207: an uncovered condition node must still fall
      // through to resolvePredictedTokens() in Flow.tsx.
      expect(
        findReplayStepForElement(conditionReplayData, [], 'cond-uncovered', 'node', conditionNodes)
      ).toBe(-1);
    });

    it('should stay condition-blind when nodes are not supplied (legacy callers)', () => {
      expect(findReplayStepForElement(conditionReplayData, [], 'cond1', 'node')).toBe(-1);
    });

    it('should not change resolution for regular nodes when nodes are supplied', () => {
      expect(
        findReplayStepForElement(conditionReplayData, [], 'n1', 'node', conditionNodes)
      ).toBe(0);
      expect(
        findReplayStepForElement(conditionReplayData, [], 'n2', 'node', conditionNodes)
      ).toBe(2);
    });

    it('should resolve elementType "condition" through a condition NODE id', () => {
      expect(
        findReplayStepForElement(conditionReplayData, [], 'cond1', 'condition', conditionNodes)
      ).toBe(1);
    });

    it('should still resolve elementType "condition" from a raw condition identifier', () => {
      expect(
        findReplayStepForElement(conditionReplayData, [], 'cfg-1', 'condition', conditionNodes)
      ).toBe(1);
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
