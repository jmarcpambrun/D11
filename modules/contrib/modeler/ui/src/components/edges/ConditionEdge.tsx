/**
 * ConditionEdge - Custom ReactFlow edge component for rendering condition edges.
 *
 * The condition label is rendered as a compact, wide card:
 *   header (icon + type + annotation indicator + trash), body (single-line label).
 *
 * Also supports draggable control points, replay highlighting, and edge order badges.
 */
import React, { Profiler, useMemo } from 'react';
import { EdgeLabelRenderer, EdgeProps } from 'reactflow';
import { FiLink, FiTrash2, FiFileText } from 'react-icons/fi';
import classNames from 'classnames';
import { useEdgePath } from '../../hooks/useEdgePath';
import { useControlPointDrag } from '../../hooks/useControlPointDrag';
import EdgeOrderBadge from './EdgeOrderBadge';
import { t } from '../../utils/translation';
import { getComponentLabel } from '../../utils/componentUtils';
import { onRenderCallback } from '../../utils/profiling';
import { UI_DIMENSIONS } from '../../constants/dimensions';
import type { BaseEdgeData } from '../../types/settings';

interface ConditionEdgeData extends BaseEdgeData {
  condition?: string;
  annotation?: string;
  onDeleteCondition?: (id: string) => void;
}

interface ConditionEdgeProps extends EdgeProps {
  data?: ConditionEdgeData;
}

const ConditionEdge: React.FC<ConditionEdgeProps> = ({
  id,
  sourceX,
  sourceY,
  targetX,
  targetY,
  sourcePosition,
  targetPosition,
  data,
  style,
  markerEnd,
  label,
  selected,
}) => {
  // Get selected state from either prop or data
  const isEdgeSelected = selected || data?.isSelected || false;

  // Get control point offset from data
  const controlOffset = useMemo(() => data?.controlOffset || { x: 0, y: 0 }, [data?.controlOffset]);

  // Calculate edge center
  const edgeCenterX = (sourceX + targetX) / 2;
  const edgeCenterY = (sourceY + targetY) / 2;

  const controlPointX = edgeCenterX + controlOffset.x;
  const controlPointY = edgeCenterY + controlOffset.y;

  // Use extracted path calculation hook
  const [edgePath, labelX, labelY] = useEdgePath({
    sourceX,
    sourceY,
    targetX,
    targetY,
    sourcePosition,
    targetPosition,
    controlOffset,
  });

  const hasCondition = data?.condition;
  const hasAnnotation = data?.annotation;
  const replayHighlight = data?.replayHighlight;

  // Use extracted control point drag hook
  const { isDragging, handleControlPointDrag } = useControlPointDrag({
    id,
    edgeCenterX,
    edgeCenterY,
    isLocked: false,
    hasCondition: !!hasCondition,
    label,
    controlOffset,
    onEdgeUpdate: data?.onEdgeUpdate,
  });

  const handleDeleteCondition = (event: React.MouseEvent) => {
    event.stopPropagation();
    if (data?.onDeleteCondition) {
      data.onDeleteCondition(id);
    }
  };

  // Apply replay highlight if present
  const edgeStyle = replayHighlight ? {
    ...style,
    stroke: `${replayHighlight} !important`,
    strokeWidth: 3
  } : style;

  return (
    <Profiler id="ConditionEdge" onRender={onRenderCallback}>
    <>
      {replayHighlight ? (
        <g style={{ stroke: replayHighlight, strokeWidth: 3 }}>
          <path
            id={id}
            style={{ fill: 'none' }}
            className="react-flow__edge-path replay-highlighted"
            d={edgePath}
            markerEnd={markerEnd}
            stroke={replayHighlight}
            strokeWidth={3}
          />
        </g>
      ) : (
        <>
          <path
            id={id}
            style={edgeStyle}
            className={`react-flow__edge-path ${selected ? 'selected' : ''}`}
            d={edgePath}
            markerEnd={markerEnd}
          />
          {/* Show line from center to control point for visual feedback */}
          {isEdgeSelected && (controlOffset.x !== 0 || controlOffset.y !== 0) && (
            <line
              className="edge-guide-line"
              x1={edgeCenterX}
              y1={edgeCenterY}
              x2={controlPointX}
              y2={controlPointY}
            />
          )}
        </>
      )}
      {/* Render control point and labels in EdgeLabelRenderer for proper z-index */}
      {(hasCondition || hasAnnotation || isEdgeSelected || (data?.edgeOrdersVisible && data?.edgeOrderInfo)) && (
        <EdgeLabelRenderer>
          <div
            className="edge-label-container nodrag nopan"
            style={{
              transform: `translate(-50%, -50%) translate(${labelX}px,${labelY}px)`,
            }}
          >
            {/* Condition card — compact wide layout: header with icons, single-line body */}
            {hasCondition && label && (
              <div
                className={classNames('condition-edge-label', {
                  'selected': isEdgeSelected,
                })}
              >
                <div className="condition-edge-header">
                  <FiLink className="node-icon" />
                  <span className="node-type">{getComponentLabel('link')}</span>
                  <div className="condition-edge-header-actions">
                    {hasAnnotation && (
                      <span
                        className="node-footer-annotation"
                        role="img"
                        title={data?.annotation}
                        aria-label={t('Has annotation')}
                      >
                        <FiFileText size={UI_DIMENSIONS.ICON_SIZE_SMALL} />
                      </span>
                    )}
                    <button
                      onClick={handleDeleteCondition}
                      className="node-footer-delete"
                      title={t('Remove @type', { '@type': getComponentLabel('link').toLowerCase() })}
                      aria-label={t('Remove @type', { '@type': getComponentLabel('link').toLowerCase() })}
                    >
                      <FiTrash2 size={UI_DIMENSIONS.ICON_SIZE_SMALL} />
                    </button>
                  </div>
                </div>
                <div className="condition-edge-body">
                  <div className="condition-edge-label-text">{label}</div>
                </div>
              </div>
            )}

            {/* Standalone annotation indicator (when no condition label) */}
            {hasAnnotation && !hasCondition && (
              <span
                className="node-footer-annotation"
                role="img"
                title={data?.annotation}
                aria-label={t('Has annotation')}
              >
                <FiFileText size={UI_DIMENSIONS.ICON_SIZE_SMALL} />
              </span>
            )}
          </div>

          {/* Draggable control point when edge is selected */}
          {isEdgeSelected && (
            <div
              className={`edge-control-point-wrapper ${isDragging ? 'dragging' : ''}`}
              onMouseDown={handleControlPointDrag}
              style={{
                transform: `translate(-50%, -50%) translate(${controlPointX}px,${hasCondition && label && (controlOffset.x === 0 && controlOffset.y === 0) ? controlPointY - 25 : controlPointY}px)`,
                cursor: isDragging ? 'grabbing' : 'grab',
              }}
            >
              <svg width="24" height="24">
                <circle className="control-point-outer" cx={12} cy={12} r={12} />
                <circle className="control-point-inner" cx={12} cy={12} r={6} />
              </svg>
            </div>
          )}

          {/* Edge Order Number Badge — visible even in read-only/locked mode,
              but drag-reorder is disabled when any lock is active.
              When a condition card is present, shift the badge up so its
              vertical center aligns with the top border of the card header. */}
          {data?.edgeOrdersVisible && data?.edgeOrderInfo && (
            <EdgeOrderBadge
              edgeId={id}
              edgeOrderInfo={
                hasCondition && label
                  ? { ...data.edgeOrderInfo, pathY: (data.edgeOrderInfo.pathY ?? 0) - 30 }
                  : data.edgeOrderInfo
              }
              isLocked={!!data?.globalLocked}
              onReorderEdge={data.onReorderEdge}
            />
          )}
        </EdgeLabelRenderer>
      )}
    </>
    </Profiler>
  );
};

export default ConditionEdge;
