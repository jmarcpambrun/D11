import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import StartNode from '../StartNode';

// Mock ReactFlow components
jest.mock('reactflow', () => ({
  Handle: ({ type, position, id }: any) => (
    <div data-testid={`handle-${type}-${id}`} data-position={position} />
  ),
  Position: {
    Top: 'top',
    Bottom: 'bottom',
    Left: 'left',
    Right: 'right',
  },
}));

describe('StartNode', () => {
  const defaultProps = {
    id: 'start-1',
    data: {
      label: 'Test Event',
    },
    selected: false,
    type: 'start',
    xPos: 0,
    yPos: 0,
    isConnectable: true,
    zIndex: 0,
    dragging: false,
    targetPosition: undefined,
    sourcePosition: undefined,
  };

  describe('rendering', () => {
    it('should render start node with label', () => {
      render(<StartNode {...defaultProps} />);
      expect(screen.getByText('Test Event')).toBeInTheDocument();
    });

    it('should render default label when no label provided', () => {
      render(<StartNode {...defaultProps} data={{}} />);
      expect(screen.getByText('Start', { selector: '.node-label' })).toBeInTheDocument();
    });

    it('should render type indicator as Start', () => {
      render(<StartNode {...defaultProps} />);
      expect(screen.getByText('Start', { selector: '.node-type' })).toBeInTheDocument();
    });

    it('should render output handle at bottom', () => {
      render(<StartNode {...defaultProps} />);
      const handle = screen.getByTestId('handle-source-output');
      expect(handle).toBeInTheDocument();
      expect(handle).toHaveAttribute('data-position', 'bottom');
    });

    it('should have start-node class', () => {
      const { container } = render(<StartNode {...defaultProps} />);
      expect(container.firstChild).toHaveClass('start-node');
    });

  });

  describe('selected state', () => {
    it('should have selected class when selected', () => {
      const { container } = render(
        <StartNode {...defaultProps} selected={true} />
      );
      expect(container.firstChild).toHaveClass('selected');
    });

    it('should not have selected class when not selected', () => {
      const { container } = render(
        <StartNode {...defaultProps} selected={false} />
      );
      expect(container.firstChild).not.toHaveClass('selected');
    });

  });

  describe('delete functionality', () => {
    it('should show delete button', () => {
      const { container } = render(
        <StartNode
          {...defaultProps}
          data={{ label: 'Test' }}
        />
      );
      expect(container.querySelector('.node-footer-delete')).toBeInTheDocument();
    });

    it('should call onDelete when delete button clicked', () => {
      const onDelete = jest.fn();
      const { container } = render(
        <StartNode
          {...defaultProps}
          data={{ label: 'Test', onDelete }}
        />
      );
      fireEvent.click(container.querySelector('.node-footer-delete')!);
      expect(onDelete).toHaveBeenCalled();
    });

    it('should stop propagation on delete click', () => {
      const onDelete = jest.fn();
      const onContainerClick = jest.fn();

      const { container } = render(
        <div onClick={onContainerClick}>
          <StartNode
            {...defaultProps}
            data={{ label: 'Test', onDelete }}
          />
        </div>
      );

      fireEvent.click(container.querySelector('.node-footer-delete')!);
      expect(onDelete).toHaveBeenCalled();
      expect(onContainerClick).not.toHaveBeenCalled();
    });

    it('should not throw when onDelete is not provided', () => {
      const { container } = render(
        <StartNode
          {...defaultProps}
          data={{ label: 'Test' }}
        />
      );
      expect(() => {
        fireEvent.click(container.querySelector('.node-footer-delete')!);
      }).not.toThrow();
    });
  });

  describe('annotation', () => {
    it('should show annotation indicator in footer when annotation exists', () => {
      const { container } = render(
        <StartNode
          {...defaultProps}
          data={{ label: 'Test', annotation: 'Test annotation' }}
        />
      );
      expect(container.querySelector('.node-footer-annotation')).toBeInTheDocument();
    });

    it('should show annotation text as title attribute', () => {
      const { container } = render(
        <StartNode
          {...defaultProps}
          data={{ label: 'Test', annotation: 'My note' }}
        />
      );
      expect(container.querySelector('.node-footer-annotation')).toHaveAttribute('title', 'My note');
    });

    it('should have has-annotation class when annotation exists', () => {
      const { container } = render(
        <StartNode
          {...defaultProps}
          data={{ label: 'Test', annotation: 'Note' }}
        />
      );
      expect(container.firstChild).toHaveClass('node-has-annotation');
    });
  });
});
