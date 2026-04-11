/**
 * Tests for useDebouncedField hook
 */

import { renderHook, act } from '@testing-library/react';
import { useDebouncedField } from '../useDebouncedField';

// Mock timers
jest.useFakeTimers();

describe('useDebouncedField', () => {
  const defaultProps = {
    initialValue: 'initial',
    onDebouncedChange: jest.fn(),
  };

  beforeEach(() => {
    jest.clearAllMocks();
  });

  afterEach(() => {
    jest.clearAllTimers();
  });

  describe('initialization', () => {
    it('should initialize with the provided initial value', () => {
      const { result } = renderHook(() => useDebouncedField(defaultProps));
      
      expect(result.current.value).toBe('initial');
    });

    it('should initialize with empty string if initial value is empty', () => {
      const { result } = renderHook(() => useDebouncedField({
        ...defaultProps,
        initialValue: '',
      }));
      
      expect(result.current.value).toBe('');
    });
  });

  describe('value synchronization', () => {
    it('should sync local value when initialValue prop changes', () => {
      const { result, rerender } = renderHook(
        ({ initialValue }) => useDebouncedField({ ...defaultProps, initialValue }),
        { initialProps: { initialValue: 'initial' } }
      );
      
      expect(result.current.value).toBe('initial');
      
      rerender({ initialValue: 'new value' });
      
      expect(result.current.value).toBe('new value');
    });

    it('should allow direct setValue calls', () => {
      const { result } = renderHook(() => useDebouncedField(defaultProps));
      
      act(() => {
        result.current.setValue('direct value');
      });
      
      expect(result.current.value).toBe('direct value');
    });
  });

  describe('onChange handling', () => {
    it('should update local value immediately on change', () => {
      const { result } = renderHook(() => useDebouncedField(defaultProps));
      
      act(() => {
        result.current.onChange({ target: { value: 'new value' } } as React.ChangeEvent<HTMLInputElement>);
      });
      
      expect(result.current.value).toBe('new value');
    });

    it('should call onDebouncedChange after debounce delay', () => {
      const onDebouncedChange = jest.fn();
      const { result } = renderHook(() => useDebouncedField({
        ...defaultProps,
        onDebouncedChange,
      }));
      
      act(() => {
        result.current.onChange({ target: { value: 'new value' } } as React.ChangeEvent<HTMLInputElement>);
      });
      
      // Should not be called immediately
      expect(onDebouncedChange).not.toHaveBeenCalled();
      
      // Fast-forward timers
      act(() => {
        jest.advanceTimersByTime(300);
      });
      
      expect(onDebouncedChange).toHaveBeenCalledWith('new value');
    });

    it('should debounce multiple rapid changes', () => {
      const onDebouncedChange = jest.fn();
      const { result } = renderHook(() => useDebouncedField({
        ...defaultProps,
        onDebouncedChange,
      }));
      
      act(() => {
        result.current.onChange({ target: { value: 'a' } } as React.ChangeEvent<HTMLInputElement>);
      });
      act(() => {
        jest.advanceTimersByTime(100);
      });
      act(() => {
        result.current.onChange({ target: { value: 'ab' } } as React.ChangeEvent<HTMLInputElement>);
      });
      act(() => {
        jest.advanceTimersByTime(100);
      });
      act(() => {
        result.current.onChange({ target: { value: 'abc' } } as React.ChangeEvent<HTMLInputElement>);
      });
      
      // Should not have been called yet
      expect(onDebouncedChange).not.toHaveBeenCalled();
      
      // Fast-forward past debounce delay
      act(() => {
        jest.advanceTimersByTime(300);
      });
      
      // Should only be called once with final value
      expect(onDebouncedChange).toHaveBeenCalledTimes(1);
      expect(onDebouncedChange).toHaveBeenCalledWith('abc');
    });

    it('should not call onDebouncedChange when disabled', () => {
      const onDebouncedChange = jest.fn();
      const { result } = renderHook(() => useDebouncedField({
        ...defaultProps,
        onDebouncedChange,
        disabled: true,
      }));
      
      act(() => {
        result.current.onChange({ target: { value: 'new value' } } as React.ChangeEvent<HTMLInputElement>);
      });
      
      // Fast-forward timers
      act(() => {
        jest.advanceTimersByTime(300);
      });
      
      expect(onDebouncedChange).not.toHaveBeenCalled();
      // But local value should still update
      expect(result.current.value).toBe('new value');
    });
  });

  describe('onBlur handling', () => {
    it('should call onDebouncedChange immediately on blur', () => {
      const onDebouncedChange = jest.fn();
      const { result } = renderHook(() => useDebouncedField({
        ...defaultProps,
        onDebouncedChange,
      }));
      
      act(() => {
        result.current.onBlur({ target: { value: 'blur value' } } as React.FocusEvent<HTMLInputElement>);
      });
      
      expect(onDebouncedChange).toHaveBeenCalledWith('blur value');
    });

    it('should cancel pending debounce on blur and use blur value', () => {
      const onDebouncedChange = jest.fn();
      const { result } = renderHook(() => useDebouncedField({
        ...defaultProps,
        onDebouncedChange,
      }));
      
      // Start typing
      act(() => {
        result.current.onChange({ target: { value: 'typing' } } as React.ChangeEvent<HTMLInputElement>);
      });
      
      // Should not be called yet
      expect(onDebouncedChange).not.toHaveBeenCalled();
      
      // Blur before debounce completes
      act(() => {
        result.current.onBlur({ target: { value: 'final value' } } as React.FocusEvent<HTMLInputElement>);
      });
      
      // Should be called with blur value
      expect(onDebouncedChange).toHaveBeenCalledTimes(1);
      expect(onDebouncedChange).toHaveBeenCalledWith('final value');
      
      // Fast-forward - should not call again
      act(() => {
        jest.advanceTimersByTime(300);
      });
      
      expect(onDebouncedChange).toHaveBeenCalledTimes(1);
    });

    it('should not call onDebouncedChange on blur when disabled', () => {
      const onDebouncedChange = jest.fn();
      const { result } = renderHook(() => useDebouncedField({
        ...defaultProps,
        onDebouncedChange,
        disabled: true,
      }));
      
      act(() => {
        result.current.onBlur({ target: { value: 'blur value' } } as React.FocusEvent<HTMLInputElement>);
      });
      
      expect(onDebouncedChange).not.toHaveBeenCalled();
    });
  });

  describe('custom debounce delay', () => {
    it('should respect custom debounce delay', () => {
      const onDebouncedChange = jest.fn();
      const { result } = renderHook(() => useDebouncedField({
        ...defaultProps,
        onDebouncedChange,
        debounceDelay: 500,
      }));
      
      act(() => {
        result.current.onChange({ target: { value: 'new value' } } as React.ChangeEvent<HTMLInputElement>);
      });
      
      // Should not be called at 300ms
      act(() => {
        jest.advanceTimersByTime(300);
      });
      expect(onDebouncedChange).not.toHaveBeenCalled();
      
      // Should be called at 500ms
      act(() => {
        jest.advanceTimersByTime(200);
      });
      expect(onDebouncedChange).toHaveBeenCalledWith('new value');
    });
  });

  describe('cleanup', () => {
    it('should flush pending value on unmount', () => {
      const onDebouncedChange = jest.fn();
      const { result, unmount } = renderHook(() => useDebouncedField({
        ...defaultProps,
        onDebouncedChange,
      }));
      
      act(() => {
        result.current.onChange({ target: { value: 'new value' } } as React.ChangeEvent<HTMLInputElement>);
      });
      
      // Unmount before debounce completes - should flush immediately
      unmount();
      
      expect(onDebouncedChange).toHaveBeenCalledTimes(1);
      expect(onDebouncedChange).toHaveBeenCalledWith('new value');
    });

    it('should not flush on unmount when disabled', () => {
      const onDebouncedChange = jest.fn();
      const { result, unmount } = renderHook(() => useDebouncedField({
        ...defaultProps,
        onDebouncedChange,
        disabled: true,
      }));
      
      act(() => {
        result.current.onChange({ target: { value: 'new value' } } as React.ChangeEvent<HTMLInputElement>);
      });
      
      unmount();
      
      expect(onDebouncedChange).not.toHaveBeenCalled();
    });

    it('should not flush on unmount when no pending timer', () => {
      const onDebouncedChange = jest.fn();
      const { unmount } = renderHook(() => useDebouncedField({
        ...defaultProps,
        onDebouncedChange,
      }));
      
      // Unmount without any changes
      unmount();
      
      expect(onDebouncedChange).not.toHaveBeenCalled();
    });
  });

  describe('flush', () => {
    it('should flush pending debounced value immediately', () => {
      const onDebouncedChange = jest.fn();
      const { result } = renderHook(() => useDebouncedField({
        ...defaultProps,
        onDebouncedChange,
      }));
      
      act(() => {
        result.current.onChange({ target: { value: 'pending value' } } as React.ChangeEvent<HTMLInputElement>);
      });
      
      // Flush before debounce completes
      act(() => {
        result.current.flush();
      });
      
      expect(onDebouncedChange).toHaveBeenCalledTimes(1);
      expect(onDebouncedChange).toHaveBeenCalledWith('pending value');
      
      // After flush, debounce should not fire again
      act(() => {
        jest.advanceTimersByTime(300);
      });
      
      expect(onDebouncedChange).toHaveBeenCalledTimes(1);
    });

    it('should do nothing when no pending timer', () => {
      const onDebouncedChange = jest.fn();
      const { result } = renderHook(() => useDebouncedField({
        ...defaultProps,
        onDebouncedChange,
      }));
      
      act(() => {
        result.current.flush();
      });
      
      expect(onDebouncedChange).not.toHaveBeenCalled();
    });

    it('should not flush when disabled', () => {
      const onDebouncedChange = jest.fn();
      const { result } = renderHook(() => useDebouncedField({
        ...defaultProps,
        onDebouncedChange,
        disabled: true,
      }));
      
      act(() => {
        result.current.onChange({ target: { value: 'new value' } } as React.ChangeEvent<HTMLInputElement>);
      });
      
      act(() => {
        result.current.flush();
      });
      
      expect(onDebouncedChange).not.toHaveBeenCalled();
    });
  });
});
