import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import ConditionEdge from '../ConditionEdge';
import { Position } from 'reactflow';

// Mock reactflow
jest.mock('reactflow', () => ({
  getBezierPath: jest.fn(() => ['M 0 0 C 50 0 50 100 100 100', 50, 50]),
  getSmoothStepPath: jest.fn(() => ['M 0 0 L 100 100', 50, 50]),
  EdgeLabelRenderer: ({ children }: { children: React.ReactNode }) => (
    <div data-testid="edge-label-renderer">{children}</div>
  ),
  Position: {
    Top: 'top',
    Bottom: 'bottom',
    Left: 'left',
    Right: 'right',
  },
}));

describe('ConditionEdge', () => {
  const defaultProps = {
    id: 'edge1',
    source: 'node1',
    target: 'node2',
    sourceX: 100,
    sourceY: 100,
    targetX: 200,
    targetY: 200,
    sourcePosition: Position.Bottom,
    targetPosition: Position.Top,
    data: {},
    style: {},
    markerEnd: 'url(#arrow)',
    selected: false,
    sourceHandleId: null,
    targetHandleId: null,
    animated: false,
    interactionWidth: 10,
    label: '',
  };

  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('rendering', () => {
    it('should render edge path', () => {
      render(
        <svg>
          <ConditionEdge {...defaultProps} />
        </svg>
      );
      expect(document.querySelector('.react-flow__edge-path')).toBeInTheDocument();
    });

    it('should apply marker end', () => {
      render(
        <svg>
          <ConditionEdge {...defaultProps} />
        </svg>
      );
      const path = document.querySelector('.react-flow__edge-path');
      expect(path).toHaveAttribute('marker-end', 'url(#arrow)');
    });

    it('should add selected class when selected', () => {
      render(
        <svg>
          <ConditionEdge {...defaultProps} selected={true} />
        </svg>
      );
      expect(document.querySelector('.react-flow__edge-path.selected')).toBeInTheDocument();
    });
  });

  describe('condition label card', () => {
    it('should display condition label when condition and label are provided', () => {
      render(
        <svg>
          <ConditionEdge
            {...defaultProps}
            label="Is New Entity"
            data={{ condition: 'entity:is_new' }}
          />
        </svg>
      );
      expect(screen.getByText('Is New Entity')).toBeInTheDocument();
    });

    it('should not display label when no condition in data', () => {
      render(
        <svg>
          <ConditionEdge {...defaultProps} label="Is New Entity" />
        </svg>
      );
      expect(screen.queryByText('Is New Entity')).not.toBeInTheDocument();
    });

    it('should render condition card with header and body (no footer)', () => {
      render(
        <svg>
          <ConditionEdge
            {...defaultProps}
            label="Test Condition"
            data={{ condition: 'entity:is_new' }}
          />
        </svg>
      );
      expect(document.querySelector('.condition-edge-header')).toBeInTheDocument();
      expect(document.querySelector('.condition-edge-body')).toBeInTheDocument();
      expect(document.querySelector('.condition-edge-footer')).not.toBeInTheDocument();
    });

    it('should render type label in header', () => {
      render(
        <svg>
          <ConditionEdge
            {...defaultProps}
            label="Test Condition"
            data={{ condition: 'entity:is_new' }}
          />
        </svg>
      );
      expect(screen.getByText('Link')).toBeInTheDocument();
    });

    it('should show delete button in header actions', () => {
      const onDeleteCondition = jest.fn();
      render(
        <svg>
          <ConditionEdge
            {...defaultProps}
            label="Test Condition"
            selected={true}
            data={{ condition: 'entity:is_new', onDeleteCondition }}
          />
        </svg>
      );
      const deleteButton = document.querySelector('.condition-edge-header-actions .node-footer-delete');
      expect(deleteButton).toBeInTheDocument();
    });

    it('should call onDeleteCondition when delete button clicked', () => {
      const onDeleteCondition = jest.fn();
      render(
        <svg>
          <ConditionEdge
            {...defaultProps}
            label="Test Condition"
            selected={true}
            data={{ condition: 'entity:is_new', onDeleteCondition }}
          />
        </svg>
      );
      const deleteButton = document.querySelector('.node-footer-delete');
      if (deleteButton) {
        fireEvent.click(deleteButton);
        expect(onDeleteCondition).toHaveBeenCalledWith('edge1');
      }
    });

  });

  describe('replay highlighting', () => {
    it('should apply replay highlight color when provided', () => {
      const data = { replayHighlight: '#00ff00' };
      render(
        <svg>
          <ConditionEdge {...defaultProps} data={data} />
        </svg>
      );
      const path = document.querySelector('.react-flow__edge-path');
      expect(path).toHaveAttribute('stroke', '#00ff00');
    });

    it('should add replay-highlighted class when replay highlight present', () => {
      const data = { replayHighlight: '#00ff00' };
      render(
        <svg>
          <ConditionEdge {...defaultProps} data={data} />
        </svg>
      );
      expect(document.querySelector('.replay-highlighted')).toBeInTheDocument();
    });
  });

  describe('control point', () => {
    it('should not show control point when not selected', () => {
      render(
        <svg>
          <ConditionEdge {...defaultProps} />
        </svg>
      );
      expect(document.querySelector('.edge-control-point-wrapper')).not.toBeInTheDocument();
    });

    it('should show control point when selected', () => {
      render(
        <svg>
          <ConditionEdge {...defaultProps} selected={true} />
        </svg>
      );
      expect(document.querySelector('.edge-control-point-wrapper')).toBeInTheDocument();
    });

    it('should show dashed line when control offset exists', () => {
      const data = { controlOffset: { x: 50, y: 50 } };
      render(
        <svg>
          <ConditionEdge {...defaultProps} data={data} selected={true} />
        </svg>
      );
      const dashedLine = document.querySelector('line');
      expect(dashedLine).toHaveClass('edge-guide-line');
    });
  });

  describe('annotation in condition card header', () => {
    it('should show annotation icon in header when edge has annotation and condition', () => {
      render(
        <svg>
          <ConditionEdge
            {...defaultProps}
            label="Test"
            data={{ condition: 'test', annotation: 'A note' }}
          />
        </svg>
      );
      const annotationIcon = document.querySelector('.condition-edge-header-actions .node-footer-annotation');
      expect(annotationIcon).toBeInTheDocument();
    });

    it('should show standalone annotation icon when edge has annotation but no condition', () => {
      render(
        <svg>
          <ConditionEdge {...defaultProps} data={{ annotation: 'A note' }} />
        </svg>
      );
      const annotationIcon = document.querySelector('.node-footer-annotation');
      expect(annotationIcon).toBeInTheDocument();
    });

    it('should not show annotation icon when edge has no annotation', () => {
      render(
        <svg>
          <ConditionEdge {...defaultProps} selected={true} />
        </svg>
      );
      const annotationIcon = document.querySelector('.node-footer-annotation');
      expect(annotationIcon).not.toBeInTheDocument();
    });

    it('should render annotation indicator as passive (no active class)', () => {
      render(
        <svg>
          <ConditionEdge
            {...defaultProps}
            label="Test"
            data={{ condition: 'test', annotation: 'Some note' }}
          />
        </svg>
      );
      const annotationIcon = document.querySelector('.node-footer-annotation');
      expect(annotationIcon).toBeInTheDocument();
      expect(annotationIcon).not.toHaveClass('active');
    });

    it('should show annotation text as title attribute', () => {
      render(
        <svg>
          <ConditionEdge
            {...defaultProps}
            label="Test"
            data={{ condition: 'test', annotation: 'My annotation text' }}
          />
        </svg>
      );
      const annotationIcon = document.querySelector('.node-footer-annotation');
      expect(annotationIcon).toHaveAttribute('title', 'My annotation text');
    });
  });

  describe('edge order display', () => {
    it('should not show edge order when not visible', () => {
      const data = {
        edgeOrdersVisible: false,
        edgeOrderInfo: { order: 1, totalEdges: 2, pathX: 150, pathY: 150 },
      };
      render(
        <svg>
          <ConditionEdge {...defaultProps} data={data} />
        </svg>
      );
      expect(document.querySelector('.edge-order-number')).not.toBeInTheDocument();
    });

    it('should show edge order when visible and multiple edges exist', () => {
      const data = {
        edgeOrdersVisible: true,
        edgeOrderInfo: { order: 1, totalEdges: 2, pathX: 150, pathY: 150 },
      };
      render(
        <svg>
          <ConditionEdge {...defaultProps} data={data} />
        </svg>
      );
      expect(document.querySelector('.edge-order-number')).toBeInTheDocument();
    });

    it('should not show edge order when only one edge exists', () => {
      const data = {
        edgeOrdersVisible: true,
        edgeOrderInfo: { order: 1, totalEdges: 1, pathX: 150, pathY: 150 },
      };
      render(
        <svg>
          <ConditionEdge {...defaultProps} data={data} />
        </svg>
      );
      expect(document.querySelector('.edge-order-number')).not.toBeInTheDocument();
    });
  });

  describe('position handling with control offset', () => {
    it('should handle Right source position with control offset', () => {
      const props = {
        ...defaultProps,
        sourcePosition: Position.Right,
        targetPosition: Position.Left,
        data: { controlOffset: { x: 10, y: 10 } },
      };
      render(
        <svg>
          <ConditionEdge {...props} />
        </svg>
      );
      expect(document.querySelector('.react-flow__edge-path')).toBeInTheDocument();
    });

    it('should handle Left source position with control offset', () => {
      const props = {
        ...defaultProps,
        sourcePosition: Position.Left,
        targetPosition: Position.Right,
        data: { controlOffset: { x: 10, y: 10 } },
      };
      render(
        <svg>
          <ConditionEdge {...props} />
        </svg>
      );
      expect(document.querySelector('.react-flow__edge-path')).toBeInTheDocument();
    });

    it('should handle Top source position with control offset', () => {
      const props = {
        ...defaultProps,
        sourcePosition: Position.Top,
        targetPosition: Position.Bottom,
        data: { controlOffset: { x: 10, y: 10 } },
      };
      render(
        <svg>
          <ConditionEdge {...props} />
        </svg>
      );
      expect(document.querySelector('.react-flow__edge-path')).toBeInTheDocument();
    });
  });

  describe('backward flow handling', () => {
    it('should handle backward flow with Bottom to Top when target is above', () => {
      const props = {
        ...defaultProps,
        sourceX: 100,
        sourceY: 200,
        targetX: 100,
        targetY: 100,
        sourcePosition: Position.Bottom,
        targetPosition: Position.Top,
        data: { controlOffset: { x: 5, y: 5 } },
      };
      render(
        <svg>
          <ConditionEdge {...props} />
        </svg>
      );
      expect(document.querySelector('.react-flow__edge-path')).toBeInTheDocument();
    });

    it('should handle backward flow with Right to Left', () => {
      const props = {
        ...defaultProps,
        sourceX: 200,
        sourceY: 100,
        targetX: 100,
        targetY: 100,
        sourcePosition: Position.Right,
        targetPosition: Position.Left,
        data: { controlOffset: { x: 5, y: 5 } },
      };
      render(
        <svg>
          <ConditionEdge {...props} />
        </svg>
      );
      expect(document.querySelector('.react-flow__edge-path')).toBeInTheDocument();
    });
  });



  describe('isSelected from data', () => {
    it('should use data.isSelected when selected prop is false', () => {
      render(
        <svg>
          <ConditionEdge {...defaultProps} selected={false} data={{ isSelected: true }} />
        </svg>
      );
      expect(document.querySelector('.edge-control-point-wrapper')).toBeInTheDocument();
    });
  });

  describe('edge styles', () => {
    it('should apply custom style', () => {
      const style = { stroke: '#ff0000', strokeWidth: 5 };
      render(
        <svg>
          <ConditionEdge {...defaultProps} style={style} />
        </svg>
      );
      const path = document.querySelector('.react-flow__edge-path');
      expect(path).toHaveStyle({ stroke: '#ff0000', strokeWidth: 5 });
    });

    it('should override style with replay highlight', () => {
      const style = { stroke: '#ff0000' };
      const data = { replayHighlight: '#00ff00' };
      render(
        <svg>
          <ConditionEdge {...defaultProps} style={style} data={data} />
        </svg>
      );
      const path = document.querySelector('.react-flow__edge-path');
      expect(path).toHaveAttribute('stroke', '#00ff00');
    });
  });

  describe('empty data handling', () => {
    it('should handle undefined data', () => {
      const props = { ...defaultProps };
      delete (props as any).data;
      render(
        <svg>
          <ConditionEdge {...props} />
        </svg>
      );
      expect(document.querySelector('.react-flow__edge-path')).toBeInTheDocument();
    });

    it('should handle empty data object', () => {
      render(
        <svg>
          <ConditionEdge {...defaultProps} data={{}} />
        </svg>
      );
      expect(document.querySelector('.react-flow__edge-path')).toBeInTheDocument();
    });
  });
});
