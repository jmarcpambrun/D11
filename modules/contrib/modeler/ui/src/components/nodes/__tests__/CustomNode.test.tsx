import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import CustomNode from '../CustomNode';
import { Position } from 'reactflow';

// Mock reactflow Handle component
jest.mock('reactflow', () => ({
  Handle: ({ type, position, id }: any) => (
    <div data-testid={`handle-${type}-${id}`} data-position={position}>
      Handle
    </div>
  ),
  Position: {
    Top: 'top',
    Bottom: 'bottom',
    Left: 'left',
    Right: 'right',
  },
}));

describe('CustomNode', () => {
  const defaultNodeData = {
    label: 'Test Node',
    annotation: '',
    isAnnotationVisible: false,
    onDelete: jest.fn(),
    onToggleAnnotation: jest.fn(),
  };

  const defaultProps = {
    id: 'node1',
    type: 'element',
    data: defaultNodeData,
    selected: false,
    xPos: 0,
    yPos: 0,
    dragging: false,
    isConnectable: true,
    zIndex: 0,
    positionAbsoluteX: 0,
    positionAbsoluteY: 0,
    sourcePosition: Position.Bottom,
    targetPosition: Position.Top,
  };

  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('rendering', () => {
    it('should render node container', () => {
      render(<CustomNode {...defaultProps} />);
      expect(document.querySelector('.custom-node')).toBeInTheDocument();
    });

    it('should render action-node class', () => {
      render(<CustomNode {...defaultProps} />);
      expect(document.querySelector('.action-node')).toBeInTheDocument();
    });

    it('should render node label', () => {
      render(<CustomNode {...defaultProps} />);
      expect(screen.getByText('Test Node')).toBeInTheDocument();
    });

    it('should render node type indicator', () => {
      render(<CustomNode {...defaultProps} />);
      expect(screen.getByText('Element')).toBeInTheDocument();
    });

    it('should render input handle', () => {
      render(<CustomNode {...defaultProps} />);
      expect(screen.getByTestId('handle-target-input')).toBeInTheDocument();
    });

    it('should render output handle', () => {
      render(<CustomNode {...defaultProps} />);
      expect(screen.getByTestId('handle-source-output')).toBeInTheDocument();
    });
  });

  describe('selected state', () => {
    it('should add selected class when selected and not in read-only mode', () => {
      render(<CustomNode {...defaultProps} selected={true} />);
      expect(document.querySelector('.custom-node.selected')).toBeInTheDocument();
    });

  });

  describe('annotation', () => {
    it('should add annotation class when has annotation', () => {
      const data = { ...defaultNodeData, annotation: 'Test annotation' };
      render(<CustomNode {...defaultProps} data={data} />);
      expect(document.querySelector('.node-has-annotation')).toBeInTheDocument();
    });

    it('should render annotation indicator in footer when has annotation', () => {
      const data = { ...defaultNodeData, annotation: 'Test annotation' };
      render(<CustomNode {...defaultProps} data={data} />);
      expect(document.querySelector('.node-footer-annotation')).toBeInTheDocument();
    });

    it('should show annotation text as title attribute', () => {
      const data = { ...defaultNodeData, annotation: 'My annotation text' };
      render(<CustomNode {...defaultProps} data={data} />);
      const indicator = document.querySelector('.node-footer-annotation');
      expect(indicator).toHaveAttribute('title', 'My annotation text');
    });
  });

  describe('delete button', () => {
    it('should render delete button in footer when not in read-only mode', () => {
      render(<CustomNode {...defaultProps} />);
      expect(document.querySelector('.node-footer-delete')).toBeInTheDocument();
    });

    it('should call onDelete when delete button clicked', () => {
      const onDelete = jest.fn();
      const data = { ...defaultNodeData, onDelete };
      render(<CustomNode {...defaultProps} data={data} />);

      fireEvent.click(document.querySelector('.node-footer-delete')!);

      expect(onDelete).toHaveBeenCalled();
    });

    it('should stop propagation when delete button clicked', () => {
      const onDelete = jest.fn();
      const data = { ...defaultNodeData, onDelete };
      render(<CustomNode {...defaultProps} data={data} />);

      fireEvent.click(document.querySelector('.node-footer-delete')!);

      expect(onDelete).toHaveBeenCalled();
    });
  });

  describe('displayName', () => {
    it('should have correct displayName', () => {
      expect(CustomNode.displayName).toBe('CustomNode');
    });
  });
});
