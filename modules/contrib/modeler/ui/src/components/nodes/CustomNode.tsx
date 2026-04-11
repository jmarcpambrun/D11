import React, { Profiler, memo } from 'react';
import { Handle, Position, NodeProps } from 'reactflow';
import { FiActivity } from 'react-icons/fi';
import { getComponentLabel } from '../../utils/componentUtils';
import NodeWrapper from './NodeWrapper';
import { onRenderCallback } from '../../utils/profiling';
import type { BaseNodeData } from '../../types/settings';

const CustomNode = memo<NodeProps<BaseNodeData>>(({ data, selected }) => {
  return (
    <Profiler id="CustomNode" onRender={onRenderCallback}>
    <NodeWrapper data={data} selected={selected} nodeClass="action-node">
      <Handle
        type="target"
        position={Position.Top}
        className="node-handle"
        id="input"
      />

      <div className="node-header">
        <FiActivity className="node-icon" />
        <span className="node-type">{getComponentLabel('element')}</span>
      </div>

      <div className="node-body">
        <div className="node-label">{data.label}</div>
      </div>

      <Handle
        type="source"
        position={Position.Bottom}
        className="node-handle"
        id="output"
        isConnectable={!data.sourceHandleDisabled}
      />
    </NodeWrapper>
    </Profiler>
  );
});

CustomNode.displayName = 'CustomNode';

export default CustomNode;
