/**
 * Tests for useTokenDragPrevention hook
 */

import { renderHook } from '@testing-library/react';
import { useTokenDragPrevention } from '../useTokenDragPrevention';

jest.mock('../../store/useFilterStore', () => ({
  useFilterStore: jest.fn(),
}));

import { useFilterStore } from '../../store/useFilterStore';

const mockUseFilterStore = useFilterStore as jest.MockedFunction<typeof useFilterStore>;

describe('useTokenDragPrevention', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockUseFilterStore.mockReturnValue(false);
  });

  it('should return isTokenDragging as false by default', () => {
    const { result } = renderHook(() => useTokenDragPrevention());
    
    expect(result.current.isTokenDragging).toBe(false);
  });

  it('should return isTokenDragging as true when token is dragging', () => {
    mockUseFilterStore.mockReturnValue(true);
    
    const { result } = renderHook(() => useTokenDragPrevention());
    
    expect(result.current.isTokenDragging).toBe(true);
  });

  describe('handleNativeFieldDragOver', () => {
    it('should not prevent default when token is not dragging', () => {
      mockUseFilterStore.mockReturnValue(false);
      
      const { result } = renderHook(() => useTokenDragPrevention());
      
      const mockEvent = {
        preventDefault: jest.fn(),
        dataTransfer: {
          dropEffect: 'copy' as const,
        },
      } as unknown as React.DragEvent;
      
      result.current.handleNativeFieldDragOver(mockEvent);
      
      expect(mockEvent.preventDefault).not.toHaveBeenCalled();
    });

    it('should prevent default and set dropEffect to none when token is dragging', () => {
      mockUseFilterStore.mockReturnValue(true);
      
      const { result } = renderHook(() => useTokenDragPrevention());
      
      const mockEvent = {
        preventDefault: jest.fn(),
        dataTransfer: {
          dropEffect: 'copy' as const,
        },
      } as unknown as React.DragEvent;
      
      result.current.handleNativeFieldDragOver(mockEvent);
      
      expect(mockEvent.preventDefault).toHaveBeenCalledTimes(1);
      expect(mockEvent.dataTransfer.dropEffect).toBe('none');
    });
  });

  describe('handleNativeFieldDrop', () => {
    it('should not prevent default when token is not dragging', () => {
      mockUseFilterStore.mockReturnValue(false);
      
      const { result } = renderHook(() => useTokenDragPrevention());
      
      const mockEvent = {
        preventDefault: jest.fn(),
      } as unknown as React.DragEvent;
      
      result.current.handleNativeFieldDrop(mockEvent);
      
      expect(mockEvent.preventDefault).not.toHaveBeenCalled();
    });

    it('should prevent default when token is dragging', () => {
      mockUseFilterStore.mockReturnValue(true);
      
      const { result } = renderHook(() => useTokenDragPrevention());
      
      const mockEvent = {
        preventDefault: jest.fn(),
      } as unknown as React.DragEvent;
      
      result.current.handleNativeFieldDrop(mockEvent);
      
      expect(mockEvent.preventDefault).toHaveBeenCalledTimes(1);
    });
  });
});
