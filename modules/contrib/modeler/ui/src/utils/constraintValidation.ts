/**
 * Pure constraint validation logic extracted from Flow.tsx.
 *
 * These functions validate model-level cardinality constraints
 * (including the parallel-successor condition rule) and return
 * human-readable error strings.  They are intentionally side-effect
 * free so they can be unit-tested without mounting React components.
 */

import type { StoreNode, StoreEdge, ModelConstraints, ComponentLabels } from '../types/settings';
import { t } from './translation';
import { getComponentLabel, getComponentLabelPlural } from './componentUtils';
import { getEdgeType } from './edgeTypeUtils';

/**
 * Validate model-level cardinality constraints against the current graph.
 *
 * Returns an array of translated error strings.  An empty array means
 * the model satisfies all constraints.
 *
 * @param nodes   - Current workflow nodes.
 * @param edges   - Current workflow edges.
 * @param constraints - Model-owner-provided cardinality constraints.
 */
export function validateModelConstraints(
  nodes: StoreNode[],
  edges: StoreEdge[],
  constraints: ModelConstraints,
): string[] {
  const typeCounts: Record<string, number> = {};
  for (const node of nodes) {
    if (node.type && node.type !== 'placeholder') {
      typeCounts[node.type] = (typeCounts[node.type] ?? 0) + 1;
    }
  }
  const errors: string[] = [];

  for (const [typeName, constraint] of Object.entries(constraints)) {
    const count = typeCounts[typeName] ?? 0;
    const label = getComponentLabel(typeName as keyof ComponentLabels);
    const labelPlural = getComponentLabelPlural(typeName as keyof ComponentLabels);

    if (constraint.min !== undefined && count < constraint.min) {
      errors.push(constraint.min === 1
        ? t('A model requires at least one @label.', { '@label': label })
        : t('A model requires at least @min @label_plural.', { '@min': String(constraint.min), '@label_plural': labelPlural }),
      );
    }
    if (constraint.max !== undefined && count > constraint.max) {
      errors.push(constraint.max === 1
        ? t('A model allows at most one @label.', { '@label': label })
        : t('A model allows at most @max @label_plural.', { '@max': String(constraint.max), '@label_plural': labelPlural }),
      );
    }

    // Validate successor cardinality per node.
    if (constraint.successors) {
      const sConstraint = constraint.successors;
      for (const node of nodes) {
        if (node.type !== typeName) continue;
        const outgoing = edges.filter(e => e.source === node.id).length;

        if (sConstraint.min !== undefined && outgoing < sConstraint.min) {
          errors.push(t('@label "@name" requires at least @min successor(s).', {
            '@label': label,
            '@name': node.data?.label ?? node.id,
            '@min': String(sConstraint.min),
          }));
        }
        if (sConstraint.max !== undefined && outgoing > sConstraint.max) {
          errors.push(sConstraint.max === 0
            ? t('@label "@name" must not have any successors.', {
              '@label': label,
              '@name': node.data?.label ?? node.id,
            })
            : t('@label "@name" allows at most @max successor(s).', {
              '@label': label,
              '@name': node.data?.label ?? node.id,
              '@max': String(sConstraint.max),
            }),
          );
        }

        // Validate parallel-successor condition constraint: when the
        // flag is set, every edge in a parallel group (same source +
        // same target) must carry a condition.
        if (sConstraint.requireConditionWhenParallel) {
          const outgoingEdges = edges.filter(e => e.source === node.id);
          const byTarget: Record<string, typeof outgoingEdges> = {};
          for (const edge of outgoingEdges) {
            (byTarget[edge.target] ??= []).push(edge);
          }
          for (const [targetId, group] of Object.entries(byTarget)) {
            if (group.length < 2) continue;
            const unconditional = group.filter(e => getEdgeType(e.data) !== 'condition');
            if (unconditional.length > 0) {
              const targetNode = nodes.find(n => n.id === targetId);
              const targetName = targetNode?.data?.label ?? targetId;
              errors.push(t(
                '@label "@source" has parallel successors to "@target" without a condition on every edge.',
                {
                  '@label': label,
                  '@source': node.data?.label ?? node.id,
                  '@target': targetName,
                },
              ));
            }
          }
        }
      }
    }
  }

  return errors;
}
