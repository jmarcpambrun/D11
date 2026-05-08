/**
 * Tests for constraintValidation utility.
 *
 * Covers model-level cardinality constraints including the new
 * requireConditionWhenParallel flag for parallel-successor validation.
 */

import { validateModelConstraints } from '../constraintValidation';
import type { StoreNode, StoreEdge, ModelConstraints } from '../../types/settings';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Create a minimal StoreNode for testing. */
function makeNode(id: string, type: string, label?: string): StoreNode {
  return {
    id,
    type,
    position: { x: 0, y: 0 },
    data: { label: label ?? id },
  };
}

/** Create a minimal StoreEdge (default / no condition). */
function makeEdge(id: string, source: string, target: string): StoreEdge {
  return { id, source, target, data: {} };
}

/** Create a StoreEdge with a condition attached. */
function makeConditionEdge(id: string, source: string, target: string): StoreEdge {
  return {
    id,
    source,
    target,
    data: { condition: 'some_condition_plugin', conditionLabel: 'Check' },
  };
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('validateModelConstraints', () => {
  // ── Basic cardinality (min/max component counts) ───────────────────────

  describe('component count constraints', () => {
    it('should return no errors when counts are within bounds', () => {
      const nodes = [makeNode('n1', 'start'), makeNode('n2', 'element')];
      const constraints: ModelConstraints = {
        start: { min: 1, max: 1 },
        element: { min: 1 },
      };
      expect(validateModelConstraints(nodes, [], constraints)).toEqual([]);
    });

    it('should report error when count is below minimum', () => {
      const constraints: ModelConstraints = { start: { min: 1 } };
      const errors = validateModelConstraints([], [], constraints);
      expect(errors.length).toBe(1);
      expect(errors[0]).toContain('at least one');
    });

    it('should report error when count exceeds maximum', () => {
      const nodes = [
        makeNode('n1', 'start'),
        makeNode('n2', 'start'),
      ];
      const constraints: ModelConstraints = { start: { max: 1 } };
      const errors = validateModelConstraints(nodes, [], constraints);
      expect(errors.length).toBe(1);
      expect(errors[0]).toContain('at most one');
    });
  });

  // ── Successor min/max constraints ──────────────────────────────────────

  describe('successor cardinality constraints', () => {
    it('should report error when a node has fewer successors than required', () => {
      const nodes = [makeNode('n1', 'start', 'My Event')];
      const constraints: ModelConstraints = {
        start: { successors: { min: 1 } },
      };
      const errors = validateModelConstraints(nodes, [], constraints);
      expect(errors.length).toBe(1);
      expect(errors[0]).toContain('My Event');
      expect(errors[0]).toContain('at least');
    });

    it('should report error when a node exceeds maximum successors', () => {
      const nodes = [makeNode('n1', 'start', 'My Event'), makeNode('n2', 'element'), makeNode('n3', 'element')];
      const edges = [makeEdge('e1', 'n1', 'n2'), makeEdge('e2', 'n1', 'n3')];
      const constraints: ModelConstraints = {
        start: { successors: { max: 1 } },
      };
      const errors = validateModelConstraints(nodes, edges, constraints);
      expect(errors.length).toBe(1);
      expect(errors[0]).toContain('at most');
    });
  });

  // ── requireConditionWhenParallel ───────────────────────────────────────

  describe('requireConditionWhenParallel', () => {
    it('should not check parallel edges when flag is absent', () => {
      const nodes = [
        makeNode('n1', 'start', 'Event A'),
        makeNode('n2', 'element', 'Action B'),
      ];
      // Two default (unconditional) edges between same source and target.
      const edges = [
        makeEdge('e1', 'n1', 'n2'),
        makeEdge('e2', 'n1', 'n2'),
      ];
      const constraints: ModelConstraints = {
        start: { successors: {} },
      };
      const errors = validateModelConstraints(nodes, edges, constraints);
      expect(errors).toEqual([]);
    });

    it('should not check parallel edges when flag is false', () => {
      const nodes = [
        makeNode('n1', 'start', 'Event A'),
        makeNode('n2', 'element', 'Action B'),
      ];
      const edges = [
        makeEdge('e1', 'n1', 'n2'),
        makeEdge('e2', 'n1', 'n2'),
      ];
      const constraints: ModelConstraints = {
        start: { successors: { requireConditionWhenParallel: false } },
      };
      const errors = validateModelConstraints(nodes, edges, constraints);
      expect(errors).toEqual([]);
    });

    it('should allow parallel edges when all carry a condition', () => {
      const nodes = [
        makeNode('n1', 'start', 'Event A'),
        makeNode('n2', 'element', 'Action B'),
      ];
      const edges = [
        makeConditionEdge('e1', 'n1', 'n2'),
        makeConditionEdge('e2', 'n1', 'n2'),
      ];
      const constraints: ModelConstraints = {
        start: { successors: { requireConditionWhenParallel: true } },
      };
      const errors = validateModelConstraints(nodes, edges, constraints);
      expect(errors).toEqual([]);
    });

    it('should block when any parallel edge lacks a condition', () => {
      const nodes = [
        makeNode('n1', 'start', 'Event A'),
        makeNode('n2', 'element', 'Action B'),
      ];
      const edges = [
        makeConditionEdge('e1', 'n1', 'n2'),
        makeEdge('e2', 'n1', 'n2'), // no condition
      ];
      const constraints: ModelConstraints = {
        start: { successors: { requireConditionWhenParallel: true } },
      };
      const errors = validateModelConstraints(nodes, edges, constraints);
      expect(errors.length).toBe(1);
      expect(errors[0]).toContain('Event A');
      expect(errors[0]).toContain('Action B');
      expect(errors[0]).toContain('parallel');
      expect(errors[0]).toContain('condition');
    });

    it('should block when all parallel edges lack a condition', () => {
      const nodes = [
        makeNode('n1', 'start', 'Event A'),
        makeNode('n2', 'element', 'Action B'),
      ];
      const edges = [
        makeEdge('e1', 'n1', 'n2'),
        makeEdge('e2', 'n1', 'n2'),
      ];
      const constraints: ModelConstraints = {
        start: { successors: { requireConditionWhenParallel: true } },
      };
      const errors = validateModelConstraints(nodes, edges, constraints);
      expect(errors.length).toBe(1);
      expect(errors[0]).toContain('parallel');
    });

    it('should not affect single edges (no parallel group)', () => {
      const nodes = [
        makeNode('n1', 'start', 'Event A'),
        makeNode('n2', 'element', 'Action B'),
      ];
      // Only one edge from n1 to n2 — not a parallel group.
      const edges = [makeEdge('e1', 'n1', 'n2')];
      const constraints: ModelConstraints = {
        start: { successors: { requireConditionWhenParallel: true } },
      };
      const errors = validateModelConstraints(nodes, edges, constraints);
      expect(errors).toEqual([]);
    });

    it('should not affect edges to different targets', () => {
      const nodes = [
        makeNode('n1', 'start', 'Event A'),
        makeNode('n2', 'element', 'Action B'),
        makeNode('n3', 'element', 'Action C'),
      ];
      // Two edges from same source but to different targets — not parallel.
      const edges = [
        makeEdge('e1', 'n1', 'n2'),
        makeEdge('e2', 'n1', 'n3'),
      ];
      const constraints: ModelConstraints = {
        start: { successors: { requireConditionWhenParallel: true } },
      };
      const errors = validateModelConstraints(nodes, edges, constraints);
      expect(errors).toEqual([]);
    });

    it('should only apply to the constrained component type', () => {
      const nodes = [
        makeNode('n1', 'start', 'Event A'),
        makeNode('n2', 'element', 'Action B'),
        makeNode('n3', 'element', 'Action C'),
      ];
      // Parallel unconditional edges from an element node (not constrained).
      const edges = [
        makeEdge('e1', 'n2', 'n3'),
        makeEdge('e2', 'n2', 'n3'),
      ];
      // Constraint is on 'start', not 'element'.
      const constraints: ModelConstraints = {
        start: { successors: { requireConditionWhenParallel: true } },
      };
      const errors = validateModelConstraints(nodes, edges, constraints);
      expect(errors).toEqual([]);
    });

    it('should report errors per parallel group', () => {
      const nodes = [
        makeNode('n1', 'start', 'Event A'),
        makeNode('n2', 'element', 'Action B'),
        makeNode('n3', 'element', 'Action C'),
      ];
      // Two parallel groups, both violating.
      const edges = [
        makeEdge('e1', 'n1', 'n2'),
        makeEdge('e2', 'n1', 'n2'),
        makeEdge('e3', 'n1', 'n3'),
        makeEdge('e4', 'n1', 'n3'),
      ];
      const constraints: ModelConstraints = {
        start: { successors: { requireConditionWhenParallel: true } },
      };
      const errors = validateModelConstraints(nodes, edges, constraints);
      expect(errors.length).toBe(2);
      expect(errors[0]).toContain('Action B');
      expect(errors[1]).toContain('Action C');
    });

    it('should use target node ID as fallback when target has no label', () => {
      const targetNode: StoreNode = {
        id: 'target-42',
        type: 'element',
        position: { x: 0, y: 0 },
        data: {},
      };
      const nodes = [
        makeNode('n1', 'start', 'Event A'),
        targetNode,
      ];
      const edges = [
        makeEdge('e1', 'n1', 'target-42'),
        makeEdge('e2', 'n1', 'target-42'),
      ];
      const constraints: ModelConstraints = {
        start: { successors: { requireConditionWhenParallel: true } },
      };
      const errors = validateModelConstraints(nodes, edges, constraints);
      expect(errors.length).toBe(1);
      expect(errors[0]).toContain('target-42');
    });

    it('should coexist with min/max successor constraints', () => {
      const nodes = [
        makeNode('n1', 'start', 'Event A'),
        makeNode('n2', 'element', 'Action B'),
      ];
      const edges = [
        makeEdge('e1', 'n1', 'n2'),
        makeEdge('e2', 'n1', 'n2'),
        makeEdge('e3', 'n1', 'n2'),
      ];
      // max:2 is violated (3 edges), AND parallel condition is violated.
      const constraints: ModelConstraints = {
        start: {
          successors: {
            max: 2,
            requireConditionWhenParallel: true,
          },
        },
      };
      const errors = validateModelConstraints(nodes, edges, constraints);
      // Should have both a max-exceeded error and a parallel-condition error.
      expect(errors.length).toBe(2);
      expect(errors.some(e => e.includes('at most'))).toBe(true);
      expect(errors.some(e => e.includes('parallel'))).toBe(true);
    });
  });
});
