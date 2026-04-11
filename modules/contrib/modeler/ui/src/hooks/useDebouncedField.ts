/**
 * useDebouncedField - Hook for managing debounced input field updates
 * 
 * Provides local state management with debounced callbacks for expensive operations
 * like API calls or state updates. Handles cleanup on unmount, ensures final
 * values are saved on blur, and flushes pending changes when the component
 * unmounts or when the caller explicitly requests it.
 */

import { useState, useCallback, useEffect, useRef } from 'react';
import { TIMING } from '../constants/dimensions';

interface UseDebouncedFieldProps {
  /** Initial value for the field */
  initialValue: string;
  /** Callback to invoke with the debounced value */
  onDebouncedChange: (value: string) => void;
  /** Whether the field is disabled */
  disabled?: boolean;
  /** Debounce delay in milliseconds */
  debounceDelay?: number;
}

interface UseDebouncedFieldReturn {
  /** Current local value */
  value: string;
  /** Set the local value directly (for resetting from external source) */
  setValue: (value: string) => void;
  /** onChange handler for input elements */
  onChange: (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => void;
  /** onBlur handler to ensure final value is saved */
  onBlur: (e: React.FocusEvent<HTMLInputElement | HTMLTextAreaElement>) => void;
  /** Flush any pending debounced change immediately */
  flush: () => void;
}

export function useDebouncedField({
  initialValue,
  onDebouncedChange,
  disabled = false,
  debounceDelay = TIMING.DEBOUNCE_DELAY,
}: UseDebouncedFieldProps): UseDebouncedFieldReturn {
  const [value, setValue] = useState(initialValue);
  const debounceTimer = useRef<number | null>(null);

  // Keep refs to the latest value and callback so the unmount cleanup
  // (which has an empty dependency array) always accesses current values.
  const valueRef = useRef(value);
  valueRef.current = value;
  const onDebouncedChangeRef = useRef(onDebouncedChange);
  onDebouncedChangeRef.current = onDebouncedChange;
  const disabledRef = useRef(disabled);
  disabledRef.current = disabled;

  // Sync local value when initial value changes (e.g., when selecting different node)
  useEffect(() => {
    setValue(initialValue);
  }, [initialValue]);

  // Flush pending changes and cleanup timer on unmount
  useEffect(() => {
    return () => {
      if (debounceTimer.current) {
        clearTimeout(debounceTimer.current);
        debounceTimer.current = null;
        // Flush the pending value so no edits are lost.
        if (!disabledRef.current) {
          onDebouncedChangeRef.current(valueRef.current);
        }
      }
    };
  }, []);

  const flush = useCallback(() => {
    if (debounceTimer.current) {
      clearTimeout(debounceTimer.current);
      debounceTimer.current = null;
      if (!disabled) {
        onDebouncedChange(value);
      }
    }
  }, [disabled, onDebouncedChange, value]);

  const onChange = useCallback((e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    const newValue = e.target.value;
    setValue(newValue);

    if (!disabled) {
      // Clear existing timer
      if (debounceTimer.current) {
        clearTimeout(debounceTimer.current);
      }

      // Set new debounced update
      debounceTimer.current = setTimeout(() => {
        onDebouncedChange(newValue);
        debounceTimer.current = null;
      }, debounceDelay) as unknown as number;
    }
  }, [disabled, debounceDelay, onDebouncedChange]);

  const onBlur = useCallback((e: React.FocusEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    // Ensure final value is saved on blur
    if (debounceTimer.current) {
      clearTimeout(debounceTimer.current);
      debounceTimer.current = null;
    }
    if (!disabled) {
      onDebouncedChange(e.target.value);
    }
  }, [disabled, onDebouncedChange]);

  return {
    value,
    setValue,
    onChange,
    onBlur,
    flush,
  };
}
