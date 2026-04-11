import React, { Profiler, useMemo } from 'react';
import { EdgeLabelRenderer, EdgeProps } from 'reactflow';
import { useEdgePath } from '../../hooks/useEdgePath';
import { useControlPointDrag } from '../../hooks/useControlPointDrag';
import EdgeOrderBadge from './EdgeOrderBadge';
import QuickAddConditionButton from '../QuickAddConditionButton';
import type { StoreComponent as Component } from '../../types/settings';
import { onRenderCallback } from '../../utils/profiling';
import type { BaseEdgeData } from '../../types/settings';

interface DefaultEdgeData extends BaseEdgeData {
  onAddCondition?: (edgeId: string, component: Component) => void;
}

interface DefaultEdgeProps extends EdgeProps {
  data?: DefaultEdgeData;
}

const DefaultEdge: React.FC<DefaultEdgeProps> = ({
  id,
  sourceX,
  sourceY,
  targetX,
  targetY,
  sourcePosition,
  targetPosition,
  data,
  style = {},
  markerEnd,
  selected,
}) => {
  // Get selected state from either prop or data
  const isEdgeSelected = selected || data?.isSelected || false;

  // Get control point offset from data (stored as absolute offset from center)
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

  const replayHighlight = data?.replayHighlight;

  // Use extracted control point drag hook
  const { isDragging, handleControlPointDrag } = useControlPointDrag({
    id,
    edgeCenterX,
    edgeCenterY,
    isLocked: false,
    hasCondition: false,
    label: undefined,
    controlOffset,
    onEdgeUpdate: data?.onEdgeUpdate,
  });

  // Apply replay highlight if present
  const edgeStyle = replayHighlight ? {
    ...style,
    stroke: replayHighlight,
    strokeWidth: 3
  } : style;

  return (
    <Profiler id="DefaultEdge" onRender={onRenderCallback}>
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
      <EdgeLabelRenderer>
        {/* Quick Add Condition Button - show on edges without conditions */}
        {data?.onAddCondition && (
          <div
            className="edge-quick-add-wrapper"
            style={{
              transform: `translate(-50%, -50%) translate(${labelX}px,${labelY}px)`,
            }}
          >
            <QuickAddConditionButton
              edgeId={id}
              onAddCondition={(component) => data.onAddCondition!(id, component)}
              disabled={false}
            />
          </div>
        )}

        {/* Control point when selected */}
        {isEdgeSelected && (
          <div
            className={`edge-control-point-wrapper ${isDragging ? 'dragging' : ''}`}
            onMouseDown={handleControlPointDrag}
            style={{
              transform: `translate(-50%, -50%) translate(${controlPointX}px,${controlPointY}px)`,
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
            but drag-reorder is disabled when any lock is active. */}
        {data?.edgeOrdersVisible && data?.edgeOrderInfo && (
          <EdgeOrderBadge
            edgeId={id}
            edgeOrderInfo={data.edgeOrderInfo}
            isLocked={!!data?.globalLocked}
            onReorderEdge={data.onReorderEdge}
          />
        )}
      </EdgeLabelRenderer>
    </>
    </Profiler>
  );
};

export default DefaultEdge;
