import React, { Profiler, memo } from 'react';
import { Handle, Position, NodeProps } from 'reactflow';
import { FiGitBranch } from 'react-icons/fi';
import { getComponentLabel } from '../../utils/componentUtils';
import NodeWrapper from './NodeWrapper';
import { onRenderCallback } from '../../utils/profiling';
import type { BaseNodeData } from '../../types/settings';

const GatewayNode = memo<NodeProps<BaseNodeData>>(({ data, selected }) => {
  return (
    <Profiler id="GatewayNode" onRender={onRenderCallback}>
    <NodeWrapper data={data} selected={selected} nodeClass="gateway-node">
      <Handle
        type="target"
        position={Position.Top}
        className="node-handle"
        id="input"
      />

      <div className="node-header">
        <FiGitBranch className="node-icon" />
        <span className="node-type">{getComponentLabel('gateway')}</span>
      </div>

      <div className="node-body">
        <div className="node-label">{data.label || getComponentLabel('gateway')}</div>
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

GatewayNode.displayName = 'GatewayNode';

export default GatewayNode;
