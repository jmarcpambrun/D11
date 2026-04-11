import React, { Profiler, memo } from 'react';
import { Handle, Position, NodeProps } from 'reactflow';
import { FiZap } from 'react-icons/fi';
import { getComponentLabel } from '../../utils/componentUtils';
import NodeWrapper from './NodeWrapper';
import { onRenderCallback } from '../../utils/profiling';
import type { BaseNodeData } from '../../types/settings';

const StartNode = memo<NodeProps<BaseNodeData>>(({ data, selected }) => {
  return (
    <Profiler id="StartNode" onRender={onRenderCallback}>
    <NodeWrapper data={data} selected={selected} nodeClass="start-node">
      <div className="node-header">
        <FiZap className="node-icon" />
        <span className="node-type">{getComponentLabel('start')}</span>
      </div>

      <div className="node-body">
        <div className="node-label">{data.label || getComponentLabel('start')}</div>
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

StartNode.displayName = 'StartNode';

export default StartNode;
