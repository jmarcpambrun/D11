import React from 'react';
import { render, fireEvent } from '@testing-library/react';
import ContentEditableField from '../ContentEditableField';

jest.mock('../../utils/sanitize', () => ({
  sanitizeTokenHtml: jest.fn((html: string) => html),
  escapeHtml: jest.fn((text: string) => text),
}));

jest.mock('../../utils/tokenUtils', () => ({
  convertTokensToHTML: jest.fn((val: string) => val),
  convertHTMLToTokens: jest.fn((html: string) => html),
  createTokenElement: jest.fn(() => {
    const span = document.createElement('span');
    span.className = 'config-token';
    span.textContent = '[token:test]';
    return span;
  }),
  isTokenElement: jest.fn((node: any) => node?.classList?.contains?.('config-token')),
  parseTokenFromDragEvent: jest.fn(() => null),
}));

describe('ContentEditableField', () => {
  const mockOnChange = jest.fn();

  const defaultProps = {
    value: 'Hello world',
    onChange: mockOnChange,
  };

  beforeEach(() => {
    jest.clearAllMocks();
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  describe('rendering', () => {
    it('should render a contenteditable div', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('[contenteditable="true"]');
      expect(editableDiv).toBeTruthy();
    });

    it('should render with disabled state', () => {
      const { container } = render(<ContentEditableField {...defaultProps} disabled={true} />);
      const editableDiv = container.querySelector('[contenteditable="false"]');
      expect(editableDiv).toBeTruthy();
    });

    it('should render with placeholder', () => {
      const { container } = render(<ContentEditableField {...defaultProps} placeholder="Enter text..." />);
      const editableDiv = container.querySelector('[data-placeholder="Enter text..."]');
      expect(editableDiv).toBeTruthy();
    });

    it('should apply custom className', () => {
      const { container } = render(<ContentEditableField {...defaultProps} className="custom-class" />);
      const editableDiv = container.querySelector('.contenteditable-field.custom-class');
      expect(editableDiv).toBeTruthy();
    });

    it('should apply singleline class by default', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.singleline');
      expect(editableDiv).toBeTruthy();
    });

    it('should apply multiline class when multiline prop is true', () => {
      const { container } = render(<ContentEditableField {...defaultProps} multiline={true} />);
      const editableDiv = container.querySelector('.multiline');
      expect(editableDiv).toBeTruthy();
    });
  });

  describe('focus and blur', () => {
    it('should handle focus event', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;
      fireEvent.focus(editableDiv);
      // Focus should set isEditing state internally
    });

    it('should handle blur event', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;
      fireEvent.focus(editableDiv);
      fireEvent.blur(editableDiv);
    });
  });

  describe('keyboard handling', () => {
    it('should prevent Enter in single-line mode', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;
      const event = new KeyboardEvent('keydown', { key: 'Enter', bubbles: true });
      const preventDefaultSpy = jest.spyOn(event, 'preventDefault');
      editableDiv.dispatchEvent(event);
      expect(preventDefaultSpy).toHaveBeenCalled();
    });

    it('should allow Enter in multiline mode', () => {
      const { container } = render(<ContentEditableField {...defaultProps} multiline={true} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;
      fireEvent.keyDown(editableDiv, { key: 'Enter' });
      // In multiline mode, Enter should not be prevented
      // fireEvent returns false if preventDefault was called
    });
  });

  describe('drag and drop', () => {
    it('should handle dragEnter when not disabled', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;
      fireEvent.dragEnter(editableDiv);
      expect(editableDiv.classList.contains('drag-over')).toBe(true);
    });

    it('should not handle dragEnter when disabled', () => {
      const { container } = render(<ContentEditableField {...defaultProps} disabled={true} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;
      fireEvent.dragEnter(editableDiv);
      expect(editableDiv.classList.contains('drag-over')).toBe(false);
    });

    it('should handle dragLeave', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;
      fireEvent.dragEnter(editableDiv);
      expect(editableDiv.classList.contains('drag-over')).toBe(true);
      fireEvent.dragLeave(editableDiv, { relatedTarget: document.body });
      expect(editableDiv.classList.contains('drag-over')).toBe(false);
    });

    it('should handle drop when disabled by returning early', () => {
      const { container } = render(<ContentEditableField {...defaultProps} disabled={true} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;
      fireEvent.drop(editableDiv, { dataTransfer: { getData: () => '' } });
      // Should not throw
    });
  });

  describe('internal token dragging', () => {
    it('should set token data on dragstart when dragging a token', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;

      // Create a token inside the field
      const tokenSpan = document.createElement('span');
      tokenSpan.className = 'config-token';
      tokenSpan.textContent = 'name';
      tokenSpan.setAttribute('data-token', '[user:name]');
      tokenSpan.setAttribute('draggable', 'true');
      editableDiv.appendChild(tokenSpan);

      const setData = jest.fn();
      fireEvent.dragStart(tokenSpan, {
        dataTransfer: {
          setData,
          effectAllowed: '',
        },
      });

      expect(setData).toHaveBeenCalledWith(
        'application/token',
        JSON.stringify({ label: 'name', token: '[user:name]' })
      );
      expect(setData).toHaveBeenCalledWith('text/plain', '[user:name]');
    });

    it('should not set data on dragstart for non-token elements', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;

      const setData = jest.fn();
      fireEvent.dragStart(editableDiv, {
        dataTransfer: {
          setData,
          effectAllowed: '',
        },
      });

      expect(setData).not.toHaveBeenCalled();
    });

    it('should remove source token on internal move drop', () => {
      const { parseTokenFromDragEvent, createTokenElement } = require('../../utils/tokenUtils');
      parseTokenFromDragEvent.mockReturnValue({ label: 'name', token: '[user:name]' });

      const newTokenSpan = document.createElement('span');
      newTokenSpan.className = 'config-token';
      newTokenSpan.textContent = 'name';
      createTokenElement.mockReturnValue(newTokenSpan);

      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;

      // Set up field with text and a token
      const beforeText = document.createTextNode('before ');
      const tokenSpan = document.createElement('span');
      tokenSpan.className = 'config-token';
      tokenSpan.textContent = 'name';
      tokenSpan.setAttribute('data-token', '[user:name]');
      tokenSpan.setAttribute('draggable', 'true');
      const afterText = document.createTextNode(' after');
      editableDiv.innerHTML = '';
      editableDiv.appendChild(beforeText);
      editableDiv.appendChild(tokenSpan);
      editableDiv.appendChild(afterText);

      // Simulate dragstart to set the source ref
      fireEvent.dragStart(tokenSpan, {
        dataTransfer: {
          setData: jest.fn(),
          effectAllowed: '',
        },
      });

      // Now drop
      fireEvent.drop(editableDiv, {
        clientX: 10,
        clientY: 10,
        dataTransfer: { getData: () => '' },
      });

      // Original token should have been removed
      expect(editableDiv.contains(tokenSpan)).toBe(false);

      jest.advanceTimersByTime(300);
      expect(mockOnChange).toHaveBeenCalled();
    });

    it('should clear dragSourceRef on dragEnd', () => {
      const { parseTokenFromDragEvent } = require('../../utils/tokenUtils');
      parseTokenFromDragEvent.mockReturnValue(null);

      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;

      const tokenSpan = document.createElement('span');
      tokenSpan.className = 'config-token';
      tokenSpan.textContent = 'name';
      tokenSpan.setAttribute('data-token', '[user:name]');
      tokenSpan.setAttribute('draggable', 'true');
      editableDiv.appendChild(tokenSpan);

      // Dragstart sets source ref
      fireEvent.dragStart(tokenSpan, {
        dataTransfer: { setData: jest.fn(), effectAllowed: '' },
      });

      // Dragend clears it
      fireEvent.dragEnd(editableDiv);

      // Drop after dragEnd should not remove any token (source ref cleared)
      fireEvent.drop(editableDiv, {
        dataTransfer: { getData: () => '' },
      });

      // Token should still be in the field
      expect(editableDiv.contains(tokenSpan)).toBe(true);
    });
  });

  describe('paste handling', () => {
    it('should handle paste with plain text', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;

      // Create a proper Range for the selection mock
      const range = document.createRange();
      range.selectNodeContents(editableDiv);
      range.collapse(false);
      const selection = window.getSelection();
      selection?.removeAllRanges();
      selection?.addRange(range);

      fireEvent.paste(editableDiv, {
        clipboardData: {
          getData: (type: string) => type === 'text/plain' ? 'pasted text' : '',
        },
      });
    });
  });

  describe('drop cursor', () => {
    it('should not show drop cursor by default', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      expect(container.querySelector('.drop-cursor')).toBeNull();
    });
  });

  describe('handleInput', () => {
    it('should debounce onChange when user types', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;

      // Simulate typing by setting textContent and firing input
      editableDiv.textContent = 'New text';
      fireEvent.input(editableDiv);

      // onChange should not be called immediately
      expect(mockOnChange).not.toHaveBeenCalled();

      // Advance timers to trigger debounce
      jest.advanceTimersByTime(300);
      expect(mockOnChange).toHaveBeenCalledWith('New text');
    });

    it('should detect token content in innerHTML', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;

      // Set innerHTML with token content
      editableDiv.innerHTML = 'Text with <span class="config-token">[token:test]</span>';
      fireEvent.input(editableDiv);

      jest.advanceTimersByTime(300);
      // When tokens detected, should pass full HTML content
      expect(mockOnChange).toHaveBeenCalled();
    });

    it('should use textContent when no tokens present', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;

      editableDiv.textContent = 'Plain text only';
      fireEvent.input(editableDiv);

      jest.advanceTimersByTime(300);
      expect(mockOnChange).toHaveBeenCalledWith('Plain text only');
    });

    it('should cancel previous debounce when typing rapidly', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;

      editableDiv.textContent = 'First';
      fireEvent.input(editableDiv);
      jest.advanceTimersByTime(100);

      editableDiv.textContent = 'Second';
      fireEvent.input(editableDiv);
      jest.advanceTimersByTime(300);

      // Only the second value should be reported
      expect(mockOnChange).toHaveBeenCalledTimes(1);
      expect(mockOnChange).toHaveBeenCalledWith('Second');
    });
  });

  describe('handleBlur with content changes', () => {
    it('should immediately save on blur when content differs from localValue', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;

      fireEvent.focus(editableDiv);

      // Directly change innerHTML without firing input (simulates external DOM change)
      // The localValue won't match the current innerHTML
      editableDiv.innerHTML = 'Directly changed';
      fireEvent.blur(editableDiv);

      expect(mockOnChange).toHaveBeenCalledWith('Directly changed');
    });

    it('should cancel pending debounce timeout on blur', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;

      fireEvent.focus(editableDiv);
      editableDiv.textContent = 'Typed content';
      fireEvent.input(editableDiv);

      // Blur before debounce fires - should cancel timeout and save immediately
      // After input, localValue was updated. Now change innerHTML again to trigger save
      editableDiv.innerHTML = 'Final content';
      fireEvent.blur(editableDiv);

      expect(mockOnChange).toHaveBeenCalledWith('Final content');

      // Clear mock to check debounce doesn't fire
      mockOnChange.mockClear();
      jest.advanceTimersByTime(300);
      // The debounced call was cancelled, so no additional call
      expect(mockOnChange).not.toHaveBeenCalled();
    });

    it('should save value on blur even when handleInput already updated localValue (type + tab)', () => {
      const { container } = render(<ContentEditableField value="old" onChange={mockOnChange} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;

      fireEvent.focus(editableDiv);

      // Simulate typing: handleInput updates localValue AND starts debounce
      editableDiv.textContent = 'new value';
      fireEvent.input(editableDiv);

      // onChange should not be called yet (still debouncing)
      expect(mockOnChange).not.toHaveBeenCalled();

      // Blur immediately (simulates pressing Tab right after typing)
      fireEvent.blur(editableDiv);

      // The value must be saved — this is the core bug fix
      expect(mockOnChange).toHaveBeenCalledWith('new value');
    });

    it('should always call onChange on blur to guarantee no data loss', () => {
      const { container } = render(<ContentEditableField value="" onChange={mockOnChange} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;

      fireEvent.focus(editableDiv);
      // Don't change content
      fireEvent.blur(editableDiv);

      // onChange is always called on blur to prevent the race condition where
      // handleInput already updated localValue, making the comparison match
      // and silently skipping the save.
      expect(mockOnChange).toHaveBeenCalledWith('');
    });
  });

  describe('paste with token HTML', () => {
    it('should handle paste with HTML containing tokens', () => {
      const { sanitizeTokenHtml } = require('../../utils/sanitize');
      sanitizeTokenHtml.mockReturnValue('<span class="config-token">[token:val]</span>');

      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;

      // Set up selection
      const range = document.createRange();
      range.selectNodeContents(editableDiv);
      range.collapse(false);
      const selection = window.getSelection();
      selection?.removeAllRanges();
      selection?.addRange(range);

      fireEvent.paste(editableDiv, {
        clipboardData: {
          getData: (type: string) => {
            if (type === 'text/html') return '<span class="config-token">[token:val]</span>';
            if (type === 'text/plain') return '[token:val]';
            return '';
          },
        },
      });

      expect(sanitizeTokenHtml).toHaveBeenCalled();
    });

    it('should prefer plain text when HTML has no tokens', () => {
      const { escapeHtml } = require('../../utils/sanitize');
      escapeHtml.mockReturnValue('plain text');

      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;

      const range = document.createRange();
      range.selectNodeContents(editableDiv);
      range.collapse(false);
      const selection = window.getSelection();
      selection?.removeAllRanges();
      selection?.addRange(range);

      fireEvent.paste(editableDiv, {
        clipboardData: {
          getData: (type: string) => {
            if (type === 'text/html') return '<b>bold text</b>';
            if (type === 'text/plain') return 'plain text';
            return '';
          },
        },
      });

      expect(escapeHtml).toHaveBeenCalledWith('plain text');
    });

    it('should handle paste when no selection exists', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;

      // Clear selection
      window.getSelection()?.removeAllRanges();

      fireEvent.paste(editableDiv, {
        clipboardData: {
          getData: (type: string) => type === 'text/plain' ? 'pasted' : '',
        },
      });
      // Should not throw
    });
  });

  describe('handleKeyDown - token deletion', () => {
    it('should handle Delete key next to a token element', () => {
      const { isTokenElement } = require('../../utils/tokenUtils');
      
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;

      // Create content with a token
      const textNode = document.createTextNode('before ');
      const tokenSpan = document.createElement('span');
      tokenSpan.className = 'config-token';
      tokenSpan.textContent = '[user:name]';
      editableDiv.innerHTML = '';
      editableDiv.appendChild(textNode);
      editableDiv.appendChild(tokenSpan);

      // Place cursor right before the token (at end of text node)
      const range = document.createRange();
      range.setStart(textNode, textNode.length);
      range.setEnd(textNode, textNode.length);
      const selection = window.getSelection();
      selection?.removeAllRanges();
      selection?.addRange(range);

      // Mock isTokenElement to return true for the token
      isTokenElement.mockImplementation((node: any) => node === tokenSpan);

      fireEvent.keyDown(editableDiv, { key: 'Delete' });

      jest.advanceTimersByTime(300);
    });

    it('should handle Backspace key next to a token element', () => {
      const { isTokenElement } = require('../../utils/tokenUtils');

      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;

      // Create content: token followed by text
      const tokenSpan = document.createElement('span');
      tokenSpan.className = 'config-token';
      tokenSpan.textContent = '[user:name]';
      const textNode = document.createTextNode(' after');
      editableDiv.innerHTML = '';
      editableDiv.appendChild(tokenSpan);
      editableDiv.appendChild(textNode);

      // Place cursor at start of text node (right after token)
      const range = document.createRange();
      range.setStart(textNode, 0);
      range.setEnd(textNode, 0);
      const selection = window.getSelection();
      selection?.removeAllRanges();
      selection?.addRange(range);

      isTokenElement.mockImplementation((node: any) => node === tokenSpan);

      fireEvent.keyDown(editableDiv, { key: 'Backspace' });

      jest.advanceTimersByTime(300);
    });

    it('should not prevent default when Delete key is not next to a token', () => {
      const { isTokenElement } = require('../../utils/tokenUtils');
      isTokenElement.mockReturnValue(false);

      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;

      editableDiv.textContent = 'normal text';
      const textNode = editableDiv.firstChild!;
      const range = document.createRange();
      range.setStart(textNode, 3);
      range.setEnd(textNode, 3);
      const selection = window.getSelection();
      selection?.removeAllRanges();
      selection?.addRange(range);

      fireEvent.keyDown(editableDiv, { key: 'Delete' });
      // Should not prevent default - regular delete behavior
    });

    it('should not prevent default when Backspace key is not next to a token', () => {
      const { isTokenElement } = require('../../utils/tokenUtils');
      isTokenElement.mockReturnValue(false);

      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;

      editableDiv.textContent = 'normal text';
      const textNode = editableDiv.firstChild!;
      const range = document.createRange();
      range.setStart(textNode, 5);
      range.setEnd(textNode, 5);
      const selection = window.getSelection();
      selection?.removeAllRanges();
      selection?.addRange(range);

      fireEvent.keyDown(editableDiv, { key: 'Backspace' });
    });
  });

  describe('drag and drop - dragOver', () => {
    it('should not handle dragOver when disabled', () => {
      const { container } = render(<ContentEditableField {...defaultProps} disabled={true} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;
      fireEvent.dragOver(editableDiv);
      expect(container.querySelector('.drop-cursor')).toBeNull();
    });

    it('should handle dragOver with caretRangeFromPoint', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;

      // Add text content so container has a child
      editableDiv.textContent = 'Some text';

      // Set up caretRangeFromPoint mock
      const originalCaretRangeFromPoint = (document as any).caretRangeFromPoint;
      (document as any).caretRangeFromPoint = jest.fn((_x: number, _y: number) => {
        const range = document.createRange();
        if (editableDiv.firstChild) {
          range.setStart(editableDiv.firstChild, 0);
          range.setEnd(editableDiv.firstChild, 0);
        }
        return range;
      });

      fireEvent.dragOver(editableDiv, {
        clientX: 50,
        clientY: 50,
        dataTransfer: { dropEffect: '' },
      });

      (document as any).caretRangeFromPoint = originalCaretRangeFromPoint;
    });

    it('should set null drop cursor when no insert position found', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;

      // Remove caretRangeFromPoint and caretPositionFromPoint
      const origCaretRange = (document as any).caretRangeFromPoint;
      const origCaretPos = (document as any).caretPositionFromPoint;
      delete (document as any).caretRangeFromPoint;
      delete (document as any).caretPositionFromPoint;

      fireEvent.dragOver(editableDiv, {
        clientX: 50,
        clientY: 50,
        dataTransfer: { dropEffect: '' },
      });

      expect(container.querySelector('.drop-cursor')).toBeNull();

      (document as any).caretRangeFromPoint = origCaretRange;
      (document as any).caretPositionFromPoint = origCaretPos;
    });

    it('should handle caretPositionFromPoint (Firefox path)', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;
      editableDiv.textContent = 'Some text';

      // Remove Chrome API, add Firefox API
      const origCaretRange = (document as any).caretRangeFromPoint;
      delete (document as any).caretRangeFromPoint;

      (document as any).caretPositionFromPoint = jest.fn(() => ({
        offsetNode: editableDiv.firstChild,
        offset: 2,
      }));

      fireEvent.dragOver(editableDiv, {
        clientX: 50,
        clientY: 50,
        dataTransfer: { dropEffect: '' },
      });

      delete (document as any).caretPositionFromPoint;
      (document as any).caretRangeFromPoint = origCaretRange;
    });

    it('should handle caretPositionFromPoint returning node outside container', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;

      const origCaretRange = (document as any).caretRangeFromPoint;
      delete (document as any).caretRangeFromPoint;

      const outsideNode = document.createTextNode('outside');
      document.body.appendChild(outsideNode);

      (document as any).caretPositionFromPoint = jest.fn(() => ({
        offsetNode: outsideNode,
        offset: 0,
      }));

      fireEvent.dragOver(editableDiv, {
        clientX: 50,
        clientY: 50,
        dataTransfer: { dropEffect: '' },
      });

      expect(container.querySelector('.drop-cursor')).toBeNull();

      delete (document as any).caretPositionFromPoint;
      (document as any).caretRangeFromPoint = origCaretRange;
      outsideNode.remove();
    });

    it('should handle caretRangeFromPoint returning range outside container', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;

      const outsideNode = document.createTextNode('outside');
      document.body.appendChild(outsideNode);

      const origCaretRange = (document as any).caretRangeFromPoint;
      (document as any).caretRangeFromPoint = jest.fn(() => {
        const range = document.createRange();
        range.setStart(outsideNode, 0);
        range.setEnd(outsideNode, 0);
        return range;
      });

      fireEvent.dragOver(editableDiv, {
        clientX: 50,
        clientY: 50,
        dataTransfer: { dropEffect: '' },
      });

      expect(container.querySelector('.drop-cursor')).toBeNull();

      (document as any).caretRangeFromPoint = origCaretRange;
      outsideNode.remove();
    });
  });

  describe('drag and drop - handleDrop', () => {
    it('should not handle drop when disabled', () => {
      const { container } = render(<ContentEditableField {...defaultProps} disabled={true} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;
      fireEvent.drop(editableDiv);
      expect(mockOnChange).not.toHaveBeenCalled();
    });

    it('should not insert token when parseTokenFromDragEvent returns null', () => {
      const { parseTokenFromDragEvent } = require('../../utils/tokenUtils');
      parseTokenFromDragEvent.mockReturnValue(null);

      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;
      
      fireEvent.dragEnter(editableDiv);
      fireEvent.drop(editableDiv, {
        dataTransfer: { getData: () => '' },
      });

      jest.advanceTimersByTime(300);
      // onChange should not be called since no token data
      expect(mockOnChange).not.toHaveBeenCalled();
    });

    it('should insert token on valid drop', () => {
      const { parseTokenFromDragEvent, createTokenElement } = require('../../utils/tokenUtils');
      parseTokenFromDragEvent.mockReturnValue({ label: 'User Name', token: '[user:name]' });

      const tokenSpan = document.createElement('span');
      tokenSpan.className = 'config-token';
      tokenSpan.textContent = '[user:name]';
      createTokenElement.mockReturnValue(tokenSpan);

      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;
      editableDiv.textContent = 'Hello ';

      // Mock caretRangeFromPoint to return a valid position
      const origCaretRange = (document as any).caretRangeFromPoint;
      (document as any).caretRangeFromPoint = jest.fn(() => {
        if (editableDiv.firstChild) {
          const range = document.createRange();
          range.setStart(editableDiv.firstChild, editableDiv.firstChild.textContent!.length);
          range.setEnd(editableDiv.firstChild, editableDiv.firstChild.textContent!.length);
          return range;
        }
        return null;
      });

      fireEvent.drop(editableDiv, {
        clientX: 100,
        clientY: 50,
        dataTransfer: { getData: () => '' },
      });

      jest.advanceTimersByTime(300);
      expect(mockOnChange).toHaveBeenCalled();

      (document as any).caretRangeFromPoint = origCaretRange;
    });

    it('should use fallback insert position when caretRangeFromPoint fails', () => {
      const { parseTokenFromDragEvent, createTokenElement } = require('../../utils/tokenUtils');
      parseTokenFromDragEvent.mockReturnValue({ label: 'Token', token: '[token:val]' });

      const tokenSpan = document.createElement('span');
      tokenSpan.className = 'config-token';
      tokenSpan.textContent = '[token:val]';
      createTokenElement.mockReturnValue(tokenSpan);

      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;
      editableDiv.textContent = 'Content';

      // Remove both caret APIs to force fallback
      const origCaretRange = (document as any).caretRangeFromPoint;
      const origCaretPos = (document as any).caretPositionFromPoint;
      delete (document as any).caretRangeFromPoint;
      delete (document as any).caretPositionFromPoint;

      fireEvent.drop(editableDiv, {
        clientX: 50,
        clientY: 50,
        dataTransfer: { getData: () => '' },
      });

      jest.advanceTimersByTime(300);
      expect(mockOnChange).toHaveBeenCalled();

      (document as any).caretRangeFromPoint = origCaretRange;
      (document as any).caretPositionFromPoint = origCaretPos;
    });

    it('should use fallback position inserting at end of empty container', () => {
      const { parseTokenFromDragEvent, createTokenElement } = require('../../utils/tokenUtils');
      parseTokenFromDragEvent.mockReturnValue({ label: 'Token', token: '[token:val]' });

      const tokenSpan = document.createElement('span');
      tokenSpan.className = 'config-token';
      tokenSpan.textContent = '[token:val]';
      createTokenElement.mockReturnValue(tokenSpan);

      const { container } = render(<ContentEditableField value="" onChange={mockOnChange} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;
      editableDiv.innerHTML = '';

      // Remove caret APIs
      const origCaretRange = (document as any).caretRangeFromPoint;
      const origCaretPos = (document as any).caretPositionFromPoint;
      delete (document as any).caretRangeFromPoint;
      delete (document as any).caretPositionFromPoint;

      // Clear selection to trigger final fallback
      window.getSelection()?.removeAllRanges();

      fireEvent.drop(editableDiv, {
        clientX: 50,
        clientY: 50,
        dataTransfer: { getData: () => '' },
      });

      jest.advanceTimersByTime(300);
      expect(mockOnChange).toHaveBeenCalled();

      (document as any).caretRangeFromPoint = origCaretRange;
      (document as any).caretPositionFromPoint = origCaretPos;
    });

    it('should reset drag state on drop', () => {
      const { parseTokenFromDragEvent } = require('../../utils/tokenUtils');
      parseTokenFromDragEvent.mockReturnValue(null);

      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;

      fireEvent.dragEnter(editableDiv);
      expect(editableDiv.classList.contains('drag-over')).toBe(true);

      fireEvent.drop(editableDiv, {
        dataTransfer: { getData: () => '' },
      });
      expect(editableDiv.classList.contains('drag-over')).toBe(false);
    });
  });

  describe('getFallbackInsertPosition', () => {
    it('should use current selection when inside container', () => {
      const { parseTokenFromDragEvent, createTokenElement } = require('../../utils/tokenUtils');
      parseTokenFromDragEvent.mockReturnValue({ label: 'Token', token: '[t:v]' });
      createTokenElement.mockReturnValue((() => {
        const s = document.createElement('span');
        s.className = 'config-token';
        s.textContent = '[t:v]';
        return s;
      })());

      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;
      editableDiv.textContent = 'Text here';

      // Place selection inside the container
      const range = document.createRange();
      range.setStart(editableDiv.firstChild!, 4);
      range.setEnd(editableDiv.firstChild!, 4);
      const selection = window.getSelection();
      selection?.removeAllRanges();
      selection?.addRange(range);

      // Remove caret APIs to force fallback
      const origCaretRange = (document as any).caretRangeFromPoint;
      const origCaretPos = (document as any).caretPositionFromPoint;
      delete (document as any).caretRangeFromPoint;
      delete (document as any).caretPositionFromPoint;

      fireEvent.drop(editableDiv, {
        clientX: 50,
        clientY: 50,
        dataTransfer: { getData: () => '' },
      });

      jest.advanceTimersByTime(300);
      expect(mockOnChange).toHaveBeenCalled();

      (document as any).caretRangeFromPoint = origCaretRange;
      (document as any).caretPositionFromPoint = origCaretPos;
    });
  });

  describe('dragLeave edge cases', () => {
    it('should clear drag state when leaving to outside element', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;
      
      fireEvent.dragEnter(editableDiv);
      expect(editableDiv.classList.contains('drag-over')).toBe(true);

      // relatedTarget is outside the container
      fireEvent.dragLeave(editableDiv, { relatedTarget: document.body });
      expect(editableDiv.classList.contains('drag-over')).toBe(false);
    });

    it('should not handle dragLeave when disabled', () => {
      const { container } = render(<ContentEditableField {...defaultProps} disabled={true} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;
      // Should not throw
      fireEvent.dragLeave(editableDiv);
    });

    it('should clear drop cursor on dragLeave', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;

      fireEvent.dragEnter(editableDiv);
      fireEvent.dragLeave(editableDiv, { relatedTarget: document.body });
      
      expect(container.querySelector('.drop-cursor')).toBeNull();
    });
  });

  describe('value sync', () => {
    it('should sync external value when not editing', () => {
      const { container, rerender } = render(<ContentEditableField value="initial" onChange={mockOnChange} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;
      
      expect(editableDiv.innerHTML).toBe('initial');

      rerender(<ContentEditableField value="updated" onChange={mockOnChange} />);
      expect(editableDiv.innerHTML).toBe('updated');
    });

    it('should not sync external value when editing', () => {
      const { container, rerender } = render(<ContentEditableField value="initial" onChange={mockOnChange} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;

      fireEvent.focus(editableDiv);
      editableDiv.textContent = 'user typing';

      rerender(<ContentEditableField value="external update" onChange={mockOnChange} />);
      // Should preserve user's typing, not overwrite with external value
      expect(editableDiv.textContent).toBe('user typing');
    });
  });

  describe('acceptsTokens prop', () => {
    it('should not enter drag-over state when acceptsTokens is false', () => {
      const { container } = render(
        <ContentEditableField {...defaultProps} acceptsTokens={false} />
      );
      const editableDiv = container.querySelector('.contenteditable-field')!;
      fireEvent.dragEnter(editableDiv);
      expect(editableDiv.classList.contains('drag-over')).toBe(false);
    });

    it('should enter drag-over state when acceptsTokens is true (default)', () => {
      const { container } = render(
        <ContentEditableField {...defaultProps} acceptsTokens={true} />
      );
      const editableDiv = container.querySelector('.contenteditable-field')!;
      fireEvent.dragEnter(editableDiv);
      expect(editableDiv.classList.contains('drag-over')).toBe(true);
    });

    it('should not insert token on drop when acceptsTokens is false', () => {
      const { parseTokenFromDragEvent } = require('../../utils/tokenUtils');
      parseTokenFromDragEvent.mockReturnValue({ label: 'Token', token: '[t:v]' });

      const { container } = render(
        <ContentEditableField {...defaultProps} acceptsTokens={false} />
      );
      const editableDiv = container.querySelector('.contenteditable-field')!;
      fireEvent.drop(editableDiv, {
        dataTransfer: { getData: () => '' },
      });

      jest.advanceTimersByTime(300);
      expect(mockOnChange).not.toHaveBeenCalled();
    });

    it('should apply token-drop-target class when isTokenDragging and acceptsTokens', () => {
      const { container } = render(
        <ContentEditableField {...defaultProps} acceptsTokens={true} isTokenDragging={true} />
      );
      const editableDiv = container.querySelector('.contenteditable-field')!;
      expect(editableDiv.classList.contains('token-drop-target')).toBe(true);
    });

    it('should apply token-drop-rejected class when isTokenDragging and not acceptsTokens', () => {
      const { container } = render(
        <ContentEditableField {...defaultProps} acceptsTokens={false} isTokenDragging={true} />
      );
      const editableDiv = container.querySelector('.contenteditable-field')!;
      expect(editableDiv.classList.contains('token-drop-rejected')).toBe(true);
    });

    it('should not apply token classes when not dragging', () => {
      const { container } = render(
        <ContentEditableField {...defaultProps} acceptsTokens={true} isTokenDragging={false} />
      );
      const editableDiv = container.querySelector('.contenteditable-field')!;
      expect(editableDiv.classList.contains('token-drop-target')).toBe(false);
      expect(editableDiv.classList.contains('token-drop-rejected')).toBe(false);
    });

    it('should convert bracket text to token HTML when acceptsTokens is true', () => {
      const { convertTokensToHTML } = require('../../utils/tokenUtils');
      convertTokensToHTML.mockImplementation(
        (val: string) => val.replace(/\[([^\]]+)\]/g, '<span class="config-token">$1</span>')
      );

      const { container } = render(
        <ContentEditableField value="Hello [user:name]" onChange={mockOnChange} acceptsTokens={true} />
      );
      const editableDiv = container.querySelector('.contenteditable-field')!;
      expect(editableDiv.innerHTML).toContain('config-token');

      // Restore default mock
      convertTokensToHTML.mockImplementation((val: string) => val);
    });

    it('should not convert bracket text to token HTML when acceptsTokens is false', () => {
      const { convertTokensToHTML } = require('../../utils/tokenUtils');
      convertTokensToHTML.mockImplementation(
        (val: string) => val.replace(/\[([^\]]+)\]/g, '<span class="config-token">$1</span>')
      );

      const { container } = render(
        <ContentEditableField value="Hello [user:name]" onChange={mockOnChange} acceptsTokens={false} />
      );
      const editableDiv = container.querySelector('.contenteditable-field')!;
      expect(editableDiv.innerHTML).not.toContain('config-token');
      expect(editableDiv.innerHTML).toBe('Hello [user:name]');

      // Restore default mock
      convertTokensToHTML.mockImplementation((val: string) => val);
    });

    it('should start converting tokens when acceptsTokens changes from false to true', () => {
      const { convertTokensToHTML } = require('../../utils/tokenUtils');
      convertTokensToHTML.mockImplementation(
        (val: string) => val.replace(/\[([^\]]+)\]/g, '<span class="config-token">$1</span>')
      );

      const { container, rerender } = render(
        <ContentEditableField value="Hello [user:name]" onChange={mockOnChange} acceptsTokens={false} />
      );
      const editableDiv = container.querySelector('.contenteditable-field')!;
      expect(editableDiv.innerHTML).not.toContain('config-token');

      // Switch to acceptsTokens=true (simulates replace_tokens checkbox being checked)
      rerender(
        <ContentEditableField value="Hello [user:name]" onChange={mockOnChange} acceptsTokens={true} />
      );
      expect(editableDiv.innerHTML).toContain('config-token');

      // Restore default mock
      convertTokensToHTML.mockImplementation((val: string) => val);
    });
  });

  describe('token selection highlighting', () => {
    it('should add selected class to token when selection intersects it', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;

      // Set up content with a token span
      const textNode = document.createTextNode('before ');
      const tokenSpan = document.createElement('span');
      tokenSpan.className = 'config-token';
      tokenSpan.textContent = 'name';
      tokenSpan.setAttribute('contenteditable', 'false');
      const afterText = document.createTextNode(' after');
      editableDiv.innerHTML = '';
      editableDiv.appendChild(textNode);
      editableDiv.appendChild(tokenSpan);
      editableDiv.appendChild(afterText);

      // Create a selection that spans across the token
      const range = document.createRange();
      range.setStart(textNode, 3);
      range.setEnd(afterText, 3);
      const selection = window.getSelection();
      selection?.removeAllRanges();
      selection?.addRange(range);

      // Fire selectionchange event
      document.dispatchEvent(new Event('selectionchange'));

      expect(tokenSpan.classList.contains('selected')).toBe(true);
    });

    it('should remove selected class when selection collapses', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;

      // Set up content with a token span
      const textNode = document.createTextNode('before ');
      const tokenSpan = document.createElement('span');
      tokenSpan.className = 'config-token';
      tokenSpan.textContent = 'name';
      tokenSpan.setAttribute('contenteditable', 'false');
      editableDiv.innerHTML = '';
      editableDiv.appendChild(textNode);
      editableDiv.appendChild(tokenSpan);

      // First select across the token
      const range = document.createRange();
      range.setStart(textNode, 0);
      range.setEndAfter(tokenSpan);
      const selection = window.getSelection();
      selection?.removeAllRanges();
      selection?.addRange(range);
      document.dispatchEvent(new Event('selectionchange'));
      expect(tokenSpan.classList.contains('selected')).toBe(true);

      // Now collapse the selection (click somewhere else)
      const collapsedRange = document.createRange();
      collapsedRange.setStart(textNode, 2);
      collapsedRange.collapse(true);
      selection?.removeAllRanges();
      selection?.addRange(collapsedRange);
      document.dispatchEvent(new Event('selectionchange'));
      expect(tokenSpan.classList.contains('selected')).toBe(false);
    });

    it('should remove selected class when there is no selection', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;

      const tokenSpan = document.createElement('span');
      tokenSpan.className = 'config-token selected';
      tokenSpan.textContent = 'name';
      editableDiv.innerHTML = '';
      editableDiv.appendChild(tokenSpan);

      // Clear all selections
      window.getSelection()?.removeAllRanges();
      document.dispatchEvent(new Event('selectionchange'));

      expect(tokenSpan.classList.contains('selected')).toBe(false);
    });

    it('should clean up selectionchange listener on unmount', () => {
      const removeEventListenerSpy = jest.spyOn(document, 'removeEventListener');
      const { unmount } = render(<ContentEditableField {...defaultProps} />);

      unmount();

      expect(removeEventListenerSpy).toHaveBeenCalledWith(
        'selectionchange',
        expect.any(Function)
      );
      removeEventListenerSpy.mockRestore();
    });
  });

  describe('token editing', () => {
    // Helper to set up a field with a token
    function setupTokenField(container: HTMLElement) {
      const editableDiv = container.querySelector('.contenteditable-field')!;
      const textNode = document.createTextNode('before ');
      const tokenSpan = document.createElement('span');
      tokenSpan.className = 'config-token';
      tokenSpan.textContent = 'name';
      tokenSpan.setAttribute('data-token', '[user:name]');
      tokenSpan.setAttribute('contenteditable', 'false');
      tokenSpan.setAttribute('draggable', 'true');
      editableDiv.innerHTML = '';
      editableDiv.appendChild(textNode);
      editableDiv.appendChild(tokenSpan);
      return { editableDiv, tokenSpan, textNode };
    }

    it('should show edit icon on token hover', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const { tokenSpan } = setupTokenField(container);

      // Mock getBoundingClientRect for positioning
      tokenSpan.getBoundingClientRect = jest.fn(() => ({
        top: 10, left: 50, right: 100, bottom: 30, width: 50, height: 20,
        x: 50, y: 10, toJSON: () => ({}),
      }));
      const wrapper = container.querySelector('.contenteditable-wrapper')!;
      (wrapper as HTMLElement).getBoundingClientRect = jest.fn(() => ({
        top: 0, left: 0, right: 300, bottom: 100, width: 300, height: 100,
        x: 0, y: 0, toJSON: () => ({}),
      }));

      fireEvent.mouseOver(tokenSpan);

      const editIcon = container.querySelector('.token-edit-icon');
      expect(editIcon).toBeTruthy();
    });

    it('should hide edit icon on mouseout from token', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const { editableDiv, tokenSpan } = setupTokenField(container);

      tokenSpan.getBoundingClientRect = jest.fn(() => ({
        top: 10, left: 50, right: 100, bottom: 30, width: 50, height: 20,
        x: 50, y: 10, toJSON: () => ({}),
      }));
      const wrapper = container.querySelector('.contenteditable-wrapper')!;
      (wrapper as HTMLElement).getBoundingClientRect = jest.fn(() => ({
        top: 0, left: 0, right: 300, bottom: 100, width: 300, height: 100,
        x: 0, y: 0, toJSON: () => ({}),
      }));

      fireEvent.mouseOver(tokenSpan);
      expect(container.querySelector('.token-edit-icon')).toBeTruthy();

      fireEvent.mouseOut(editableDiv, { relatedTarget: document.body });
      expect(container.querySelector('.token-edit-icon')).toBeNull();
    });

    it('should open edit popup when edit icon is clicked', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const { tokenSpan } = setupTokenField(container);

      tokenSpan.getBoundingClientRect = jest.fn(() => ({
        top: 10, left: 50, right: 100, bottom: 30, width: 50, height: 20,
        x: 50, y: 10, toJSON: () => ({}),
      }));
      const wrapper = container.querySelector('.contenteditable-wrapper')!;
      (wrapper as HTMLElement).getBoundingClientRect = jest.fn(() => ({
        top: 0, left: 0, right: 300, bottom: 100, width: 300, height: 100,
        x: 0, y: 0, toJSON: () => ({}),
      }));

      fireEvent.mouseOver(tokenSpan);
      const editIcon = container.querySelector('.token-edit-icon')!;
      fireEvent.click(editIcon);

      const popup = container.querySelector('.token-edit-popup');
      expect(popup).toBeTruthy();
      const input = container.querySelector('.token-edit-input') as HTMLInputElement;
      expect(input).toBeTruthy();
      expect(input.defaultValue).toBe('user:name');
    });

    it('should save edited token on Save button click', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const { tokenSpan } = setupTokenField(container);

      tokenSpan.getBoundingClientRect = jest.fn(() => ({
        top: 10, left: 50, right: 100, bottom: 30, width: 50, height: 20,
        x: 50, y: 10, toJSON: () => ({}),
      }));
      const wrapper = container.querySelector('.contenteditable-wrapper')!;
      (wrapper as HTMLElement).getBoundingClientRect = jest.fn(() => ({
        top: 0, left: 0, right: 300, bottom: 100, width: 300, height: 100,
        x: 0, y: 0, toJSON: () => ({}),
      }));

      // Open edit popup
      fireEvent.mouseOver(tokenSpan);
      fireEvent.click(container.querySelector('.token-edit-icon')!);

      // Change value and save
      const input = container.querySelector('.token-edit-input') as HTMLInputElement;
      fireEvent.change(input, { target: { value: 'node:title' } });
      // Manually set the value since jsdom doesn't sync defaultValue
      Object.defineProperty(input, 'value', { value: 'node:title', writable: true });

      fireEvent.click(container.querySelector('.token-edit-save')!);

      // Popup should be closed
      expect(container.querySelector('.token-edit-popup')).toBeNull();
      // Token should be updated
      expect(tokenSpan.getAttribute('data-token')).toBe('[node:title]');
      expect(tokenSpan.textContent).toBe('title');

      // onChange is called immediately (not debounced) on save
      expect(mockOnChange).toHaveBeenCalled();
    });

    it('should cancel editing on Cancel button click', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const { tokenSpan } = setupTokenField(container);

      tokenSpan.getBoundingClientRect = jest.fn(() => ({
        top: 10, left: 50, right: 100, bottom: 30, width: 50, height: 20,
        x: 50, y: 10, toJSON: () => ({}),
      }));
      const wrapper = container.querySelector('.contenteditable-wrapper')!;
      (wrapper as HTMLElement).getBoundingClientRect = jest.fn(() => ({
        top: 0, left: 0, right: 300, bottom: 100, width: 300, height: 100,
        x: 0, y: 0, toJSON: () => ({}),
      }));

      fireEvent.mouseOver(tokenSpan);
      fireEvent.click(container.querySelector('.token-edit-icon')!);
      expect(container.querySelector('.token-edit-popup')).toBeTruthy();

      fireEvent.click(container.querySelector('.token-edit-cancel')!);
      expect(container.querySelector('.token-edit-popup')).toBeNull();
      // Token should be unchanged
      expect(tokenSpan.getAttribute('data-token')).toBe('[user:name]');
    });

    it('should save on Enter key in edit input', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const { tokenSpan } = setupTokenField(container);

      tokenSpan.getBoundingClientRect = jest.fn(() => ({
        top: 10, left: 50, right: 100, bottom: 30, width: 50, height: 20,
        x: 50, y: 10, toJSON: () => ({}),
      }));
      const wrapper = container.querySelector('.contenteditable-wrapper')!;
      (wrapper as HTMLElement).getBoundingClientRect = jest.fn(() => ({
        top: 0, left: 0, right: 300, bottom: 100, width: 300, height: 100,
        x: 0, y: 0, toJSON: () => ({}),
      }));

      fireEvent.mouseOver(tokenSpan);
      fireEvent.click(container.querySelector('.token-edit-icon')!);

      const input = container.querySelector('.token-edit-input') as HTMLInputElement;
      Object.defineProperty(input, 'value', { value: 'site:name', writable: true });
      fireEvent.keyDown(input, { key: 'Enter' });

      expect(container.querySelector('.token-edit-popup')).toBeNull();
      expect(tokenSpan.getAttribute('data-token')).toBe('[site:name]');
    });

    it('should cancel on Escape key in edit input', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const { tokenSpan } = setupTokenField(container);

      tokenSpan.getBoundingClientRect = jest.fn(() => ({
        top: 10, left: 50, right: 100, bottom: 30, width: 50, height: 20,
        x: 50, y: 10, toJSON: () => ({}),
      }));
      const wrapper = container.querySelector('.contenteditable-wrapper')!;
      (wrapper as HTMLElement).getBoundingClientRect = jest.fn(() => ({
        top: 0, left: 0, right: 300, bottom: 100, width: 300, height: 100,
        x: 0, y: 0, toJSON: () => ({}),
      }));

      fireEvent.mouseOver(tokenSpan);
      fireEvent.click(container.querySelector('.token-edit-icon')!);

      const input = container.querySelector('.token-edit-input')!;
      fireEvent.keyDown(input, { key: 'Escape' });

      expect(container.querySelector('.token-edit-popup')).toBeNull();
      expect(tokenSpan.getAttribute('data-token')).toBe('[user:name]');
    });

    it('should open edit popup with Ctrl+E when token is selected', () => {
      const { isTokenElement } = require('../../utils/tokenUtils');

      const { container } = render(<ContentEditableField {...defaultProps} />);
      const { editableDiv, tokenSpan } = setupTokenField(container);

      tokenSpan.getBoundingClientRect = jest.fn(() => ({
        top: 10, left: 50, right: 100, bottom: 30, width: 50, height: 20,
        x: 50, y: 10, toJSON: () => ({}),
      }));
      const wrapper = container.querySelector('.contenteditable-wrapper')!;
      (wrapper as HTMLElement).getBoundingClientRect = jest.fn(() => ({
        top: 0, left: 0, right: 300, bottom: 100, width: 300, height: 100,
        x: 0, y: 0, toJSON: () => ({}),
      }));

      // Mark token as selected
      tokenSpan.classList.add('selected');

      isTokenElement.mockImplementation((node: any) => node?.classList?.contains?.('config-token'));

      fireEvent.keyDown(editableDiv, { key: 'e', ctrlKey: true });

      const popup = container.querySelector('.token-edit-popup');
      expect(popup).toBeTruthy();
      const input = container.querySelector('.token-edit-input') as HTMLInputElement;
      expect(input.defaultValue).toBe('user:name');
    });

    it('should not show edit icon when disabled', () => {
      const { container } = render(
        <ContentEditableField {...defaultProps} disabled={true} />
      );
      const editableDiv = container.querySelector('.contenteditable-field')!;

      const tokenSpan = document.createElement('span');
      tokenSpan.className = 'config-token';
      tokenSpan.textContent = 'name';
      tokenSpan.setAttribute('data-token', '[user:name]');
      editableDiv.innerHTML = '';
      editableDiv.appendChild(tokenSpan);

      fireEvent.mouseOver(tokenSpan);
      expect(container.querySelector('.token-edit-icon')).toBeNull();
    });

    it('should close popup without saving when input is empty', () => {
      const { container } = render(<ContentEditableField {...defaultProps} />);
      const { tokenSpan } = setupTokenField(container);

      tokenSpan.getBoundingClientRect = jest.fn(() => ({
        top: 10, left: 50, right: 100, bottom: 30, width: 50, height: 20,
        x: 50, y: 10, toJSON: () => ({}),
      }));
      const wrapper = container.querySelector('.contenteditable-wrapper')!;
      (wrapper as HTMLElement).getBoundingClientRect = jest.fn(() => ({
        top: 0, left: 0, right: 300, bottom: 100, width: 300, height: 100,
        x: 0, y: 0, toJSON: () => ({}),
      }));

      fireEvent.mouseOver(tokenSpan);
      fireEvent.click(container.querySelector('.token-edit-icon')!);

      const input = container.querySelector('.token-edit-input') as HTMLInputElement;
      Object.defineProperty(input, 'value', { value: '', writable: true });
      fireEvent.click(container.querySelector('.token-edit-save')!);

      expect(container.querySelector('.token-edit-popup')).toBeNull();
      // Token should be unchanged
      expect(tokenSpan.getAttribute('data-token')).toBe('[user:name]');
    });
  });

  describe('cleanup on unmount', () => {
    it('should flush pending debounced change on unmount', () => {
      const { container, unmount } = render(<ContentEditableField {...defaultProps} />);
      const editableDiv = container.querySelector('.contenteditable-field')!;

      // Trigger a debounced change
      editableDiv.textContent = 'some text';
      fireEvent.input(editableDiv);

      // onChange should not be called yet (still debouncing)
      expect(mockOnChange).not.toHaveBeenCalled();

      // Unmount should flush the pending value
      unmount();

      expect(mockOnChange).toHaveBeenCalledTimes(1);
      expect(mockOnChange).toHaveBeenCalledWith('some text');

      // Advancing timers should not cause additional calls
      jest.advanceTimersByTime(300);
      expect(mockOnChange).toHaveBeenCalledTimes(1);
    });

    it('should not flush on unmount when no pending debounce', () => {
      const { unmount } = render(<ContentEditableField {...defaultProps} />);

      unmount();

      expect(mockOnChange).not.toHaveBeenCalled();
    });
  });
});
