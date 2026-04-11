/**
 * Tests for replayHighlightUtils
 */

import { Node, Edge } from 'reactflow';
import {
  clearNodeHighlights,
  clearEdgeHighlights,
  applyNodeHighlight,
  applyEdgeHighlight,
  highlightNodesForStep,
  highlightEdgesForStep,
} from '../replayHighlightUtils';
import { ReplayStep } from '../replayStepUtils';

// ============ Test Fixtures ============

const createNode = (id: string, overrides: Partial<Node> = {}): Node => ({
  id,
  position: { x: 0, y: 0 },
  data: { label: id, highlighted: false },
  selected: false,
  ...overrides,
});

const createEdge = (id: string, source: string, target: string, overrides: Partial<Edge> = {}): Edge => ({
  id,
  source,
  target,
  data: {},
  selected: false,
  animated: false,
  ...overrides,
});

// ============ Tests ============

describe('replayHighlightUtils', () => {
  describe('clearNodeHighlights', () => {
    it('should deselect all nodes and clear highlighted flag', () => {
      const nodes = [
        createNode('n1', { selected: true, data: { label: 'N1', highlighted: true } }),
        createNode('n2', { selected: false, data: { label: 'N2', highlighted: false } }),
      ];
      const result = clearNodeHighlights(nodes);
      
      expect(result[0].selected).toBe(false);
      expect(result[0].data.highlighted).toBe(false);
      expect(result[1].selected).toBe(false);
      expect(result[1].data.highlighted).toBe(false);
    });

    it('should return a new array (immutable)', () => {
      const nodes = [createNode('n1')];
      const result = clearNodeHighlights(nodes);
      expect(result).not.toBe(nodes);
      expect(result[0]).not.toBe(nodes[0]);
    });

    it('should handle empty array', () => {
      expect(clearNodeHighlights([])).toEqual([]);
    });
  });

  describe('clearEdgeHighlights', () => {
    it('should clear all replay-related properties from edges', () => {
      const edges = [
        createEdge('e1', 'n1', 'n2', {
          selected: true,
          animated: true,
          className: 'replay-highlighted replay-add some-class',
          data: { highlighted: true, replayHighlight: 'green', fromReplay: true },
          style: { stroke: 'green', strokeWidth: 3 },
        }),
      ];
      const result = clearEdgeHighlights(edges);
      
      expect(result[0].selected).toBe(false);
      expect(result[0].animated).toBe(false);
      expect(result[0].data.highlighted).toBe(false);
      expect(result[0].data.replayHighlight).toBeUndefined();
      expect(result[0].data.fromReplay).toBe(false);
      expect(result[0].className).toBe('some-class');
      expect(result[0].style?.stroke).toBeUndefined();
      expect(result[0].style?.strokeWidth).toBeUndefined();
    });

    it('should handle edges with no style', () => {
      const edges = [createEdge('e1', 'n1', 'n2')];
      const result = clearEdgeHighlights(edges);
      expect(result[0].style).toBeUndefined();
    });

    it('should strip replay class names completely when no others remain', () => {
      const edges = [
        createEdge('e1', 'n1', 'n2', {
          className: 'replay-highlighted replay-add',
        }),
      ];
      const result = clearEdgeHighlights(edges);
      expect(result[0].className).toBeUndefined();
    });

    it('should handle empty array', () => {
      expect(clearEdgeHighlights([])).toEqual([]);
    });
  });

  describe('applyNodeHighlight', () => {
    it('should select and highlight the target node', () => {
      const nodes = [createNode('n1'), createNode('n2')];
      const result = applyNodeHighlight(nodes, 'n1', false);
      
      expect(result[0].selected).toBe(true);
      expect(result[0].data.highlighted).toBe(true);
      expect(result[0].data.fromReplay).toBe(true);
      expect(result[1].selected).toBe(false);
    });

    it('should apply access denied styling when isAccessDenied is true', () => {
      const nodes = [createNode('n1')];
      const result = applyNodeHighlight(nodes, 'n1', true);
      
      expect(result[0].style?.border).toBe('2px solid var(--modeler-color-danger-soft)');
      expect(result[0].style?.boxShadow).toBe('var(--modeler-shadow-danger-glow)');
    });

    it('should not add access denied styling when isAccessDenied is false', () => {
      const nodes = [createNode('n1')];
      const result = applyNodeHighlight(nodes, 'n1', false);
      
      // Style should remain as original (undefined in this case)
      expect(result[0].style).toBeUndefined();
    });

    it('should not modify other nodes', () => {
      const nodes = [createNode('n1'), createNode('n2')];
      const result = applyNodeHighlight(nodes, 'n1', false);
      
      expect(result[1].data.highlighted).toBeFalsy();
      expect(result[1].data.fromReplay).toBeUndefined();
    });
  });

  describe('applyEdgeHighlight', () => {
    it('should highlight edge as add successor (green)', () => {
      const edges = [createEdge('e1', 'n1', 'n2')];
      const result = applyEdgeHighlight(edges, 'e1', true);
      
      expect(result[0].data.highlighted).toBe(true);
      expect(result[0].data.fromReplay).toBe(true);
      expect(result[0].data.replayType).toBe('add');
      expect(result[0].style?.stroke).toBe('var(--modeler-color-success)');
      expect(result[0].animated).toBe(true);
      expect(result[0].className).toBe('replay-highlighted replay-add');
    });

    it('should preserve the incoming selected state from the caller', () => {
      const edges = [{ ...createEdge('e1', 'n1', 'n2'), selected: true }];
      const result = applyEdgeHighlight(edges, 'e1', true);
      expect(result[0].selected).toBe(true);
    });

    it('should highlight edge as ignore successor (red)', () => {
      const edges = [createEdge('e1', 'n1', 'n2')];
      const result = applyEdgeHighlight(edges, 'e1', false);
      
      expect(result[0].data.replayType).toBe('ignore');
      expect(result[0].style?.stroke).toBe('var(--modeler-color-danger-soft)');
      expect(result[0].className).toBe('replay-highlighted replay-ignore');
    });

    it('should not modify other edges', () => {
      const edges = [createEdge('e1', 'n1', 'n2'), createEdge('e2', 'n2', 'n3')];
      const result = applyEdgeHighlight(edges, 'e1', true);
      
      expect(result[1].data.highlighted).toBeFalsy();
    });
  });

  describe('highlightNodesForStep', () => {
    const nodes = [createNode('n1'), createNode('n2')];

    it('should highlight node for "started" step', () => {
      const step: ReplayStep = { type: 'started', id: 'n1' };
      const result = highlightNodesForStep(nodes, step);
      
      expect(result[0].selected).toBe(true);
      expect(result[0].data.highlighted).toBe(true);
      expect(result[1].selected).toBe(false);
    });

    it('should highlight node for "execute" step', () => {
      const step: ReplayStep = { type: 'execute', id: 'n1' };
      const result = highlightNodesForStep(nodes, step);
      
      expect(result[0].data.highlighted).toBe(true);
    });

    it('should apply access denied styling for "access denied" step', () => {
      const step: ReplayStep = { type: 'access denied', id: 'n1' };
      const result = highlightNodesForStep(nodes, step);
      
      expect(result[0].data.highlighted).toBe(true);
      expect(result[0].style?.border).toContain('danger');
    });

    it('should highlight node for successor step without conditionId', () => {
      const step: ReplayStep = { type: 'add successor', id: 'n1', successorId: 'n2' };
      const result = highlightNodesForStep(nodes, step);
      
      expect(result[0].data.highlighted).toBe(true);
    });

    it('should only clear highlights for successor step with conditionId', () => {
      const step: ReplayStep = { type: 'add successor', id: 'n1', conditionId: 'cond1' };
      const result = highlightNodesForStep(nodes, step);
      
      // Should not highlight any node (edge highlighting will handle this)
      expect(result[0].data.highlighted).toBe(false);
      expect(result[1].data.highlighted).toBe(false);
    });

    it('should only clear when step has no id', () => {
      const step: ReplayStep = { type: 'execute' };
      const result = highlightNodesForStep(nodes, step);
      
      expect(result.every(n => !n.data.highlighted)).toBe(true);
    });
  });

  describe('highlightEdgesForStep', () => {
    const edges = [
      createEdge('e1', 'n1', 'n2', { data: { condition: 'cond1' } }),
      createEdge('e2', 'n2', 'n3', { data: {} }),
    ];

    it('should highlight edge for condition step (add successor)', () => {
      const step: ReplayStep = { type: 'add successor', id: 'n1', conditionId: 'cond1' };
      const result = highlightEdgesForStep(edges, step);
      
      expect(result[0].data.highlighted).toBe(true);
      expect(result[0].data.replayType).toBe('add');
      expect(result[1].data.highlighted).toBe(false);
    });

    it('should highlight edge for condition step (ignore successor)', () => {
      const step: ReplayStep = { type: 'ignore successor', id: 'n1', conditionId: 'cond1' };
      const result = highlightEdgesForStep(edges, step);
      
      expect(result[0].data.highlighted).toBe(true);
      expect(result[0].data.replayType).toBe('ignore');
    });

    it('should only clear for non-condition steps', () => {
      const step: ReplayStep = { type: 'execute', id: 'n1' };
      const result = highlightEdgesForStep(edges, step);
      
      expect(result.every(e => !e.data.highlighted)).toBe(true);
    });

    it('should only clear for successor steps without conditionId', () => {
      const step: ReplayStep = { type: 'add successor', id: 'n1', successorId: 'n2' };
      const result = highlightEdgesForStep(edges, step);
      
      expect(result.every(e => !e.data.highlighted)).toBe(true);
    });
  });

});
