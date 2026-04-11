/**
 * HelpTooltip - A small help icon that, when clicked, reveals a
 * descriptive tooltip popup next to the trigger element.
 *
 * Designed to sit inline with a form label so longer explanatory
 * text does not clutter the layout. Closes on click-outside or
 * Escape.
 */
import React, { useState, useEffect, useRef, useCallback } from 'react';
import { FiHelpCircle } from 'react-icons/fi';
import { t } from '../utils/translation';

interface HelpTooltipProps {
  /** The descriptive text to display inside the popup. */
  text: string;
  /** Accessible label for the trigger button (defaults to "More information"). */
  ariaLabel?: string;
}

const HelpTooltip: React.FC<HelpTooltipProps> = ({ text, ariaLabel }) => {
  const [open, setOpen] = useState(false);
  const containerRef = useRef<HTMLSpanElement>(null);

  const handleClickOutside = useCallback((e: MouseEvent) => {
    if (containerRef.current && !containerRef.current.contains(e.target as HTMLElement)) {
      setOpen(false);
    }
  }, []);

  const handleKeyDown = useCallback((e: KeyboardEvent) => {
    if (e.key === 'Escape') {
      setOpen(false);
    }
  }, []);

  useEffect(() => {
    if (open) {
      document.addEventListener('mousedown', handleClickOutside);
      document.addEventListener('keydown', handleKeyDown);
      return () => {
        document.removeEventListener('mousedown', handleClickOutside);
        document.removeEventListener('keydown', handleKeyDown);
      };
    }
  }, [open, handleClickOutside, handleKeyDown]);

  return (
    <span className="help-tooltip" ref={containerRef}>
      <button
        type="button"
        className="help-tooltip-trigger"
        aria-label={ariaLabel || t('More information')}
        aria-expanded={open}
        onClick={() => setOpen(prev => !prev)}
      >
        <FiHelpCircle size={14} />
      </button>
      {open && (
        <div className="help-tooltip-popup" role="tooltip">
          {text}
        </div>
      )}
    </span>
  );
};

export default HelpTooltip;
