import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import HelpTooltip from '../HelpTooltip';

describe('HelpTooltip', () => {
  const defaultText = 'This is a helpful description.';

  it('should render a help icon button', () => {
    render(<HelpTooltip text={defaultText} />);
    expect(screen.getByRole('button', { name: 'More information' })).toBeInTheDocument();
  });

  it('should not show tooltip by default', () => {
    render(<HelpTooltip text={defaultText} />);
    expect(screen.queryByRole('tooltip')).not.toBeInTheDocument();
  });

  it('should show tooltip when clicked', () => {
    render(<HelpTooltip text={defaultText} />);
    fireEvent.click(screen.getByRole('button', { name: 'More information' }));

    expect(screen.getByRole('tooltip')).toBeInTheDocument();
    expect(screen.getByText(defaultText)).toBeInTheDocument();
  });

  it('should hide tooltip when clicked again', () => {
    render(<HelpTooltip text={defaultText} />);
    const btn = screen.getByRole('button', { name: 'More information' });

    fireEvent.click(btn);
    expect(screen.getByRole('tooltip')).toBeInTheDocument();

    fireEvent.click(btn);
    expect(screen.queryByRole('tooltip')).not.toBeInTheDocument();
  });

  it('should hide tooltip on Escape key', () => {
    render(<HelpTooltip text={defaultText} />);
    fireEvent.click(screen.getByRole('button', { name: 'More information' }));
    expect(screen.getByRole('tooltip')).toBeInTheDocument();

    fireEvent.keyDown(document, { key: 'Escape' });
    expect(screen.queryByRole('tooltip')).not.toBeInTheDocument();
  });

  it('should hide tooltip on click outside', () => {
    render(
      <div>
        <span data-testid="outside">outside</span>
        <HelpTooltip text={defaultText} />
      </div>
    );
    fireEvent.click(screen.getByRole('button', { name: 'More information' }));
    expect(screen.getByRole('tooltip')).toBeInTheDocument();

    fireEvent.mouseDown(screen.getByTestId('outside'));
    expect(screen.queryByRole('tooltip')).not.toBeInTheDocument();
  });

  it('should use custom aria-label when provided', () => {
    render(<HelpTooltip text={defaultText} ariaLabel="Storage help" />);
    expect(screen.getByRole('button', { name: 'Storage help' })).toBeInTheDocument();
  });

  it('should set aria-expanded correctly', () => {
    render(<HelpTooltip text={defaultText} />);
    const btn = screen.getByRole('button', { name: 'More information' });

    expect(btn).toHaveAttribute('aria-expanded', 'false');

    fireEvent.click(btn);
    expect(btn).toHaveAttribute('aria-expanded', 'true');

    fireEvent.click(btn);
    expect(btn).toHaveAttribute('aria-expanded', 'false');
  });
});
