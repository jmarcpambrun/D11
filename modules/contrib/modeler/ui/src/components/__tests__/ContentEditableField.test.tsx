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

    // jest.clearAllMocks() clears call history but NOT a stuck mockReturnValue
    // (e.g. set by the paste test below). Reset the sanitize mocks back to a
    // transparent identity implementation so each test starts clean and the
    // real convertHTMLToTokens' internal sanitizeTokenHtml call is a pass-through.
    const { sanitizeTokenHtml, escapeHtml } = require('../../utils/sanitize');
    (sanitizeTokenHtml as jest.Mock).mockImplementation((html: string) => html);
    (escapeHtml as jest.Mock).mockImplementation((text: string) => text);
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

  describe('trailing token caret space (Issue B)', () => {
    // convertTokensToHTML is mocked to identity, so a `value` containing a
    // trailing `.config-token` span renders that pill as the LAST node.
    const trailingTokenHtml = 'Hello <span class="config-token" data-token="[user:name]">name</span>';

    // jest.clearAllMocks() (beforeEach) wipes any custom isTokenElement impl a
    // PRIOR test installed without restoring the factory default, so set the
    // real class-based check here so these tests are order-independent.
    beforeEach(() => {
      const { isTokenElement } = require('../../utils/tokenUtils');
      isTokenElement.mockImplementation(
        (node: any) => node?.classList?.contains?.('config-token') ?? false,
      );
    });

    it('appends a zero-width-space caret spot after a value ending in a token', () => {
      const { container } = render(<ContentEditableField value={trailingTokenHtml} onChange={mockOnChange} />);
      const editableDiv = container.querySelector('.contenteditable-field') as HTMLElement;
      const last = editableDiv.lastChild as Node;
      // The last node is now a text node holding a single ZWSP (the caret spot),
      // sitting AFTER the trailing token pill.
      expect(last.nodeType).toBe(Node.TEXT_NODE);
      expect(last.textContent).toBe('\u200B');
      const tokenEl = editableDiv.querySelector('.config-token');
      expect(tokenEl?.nextSibling).toBe(last);
    });

    it('does NOT append a caret spot when acceptsTokens is false', () => {
      const { container } = render(
        <ContentEditableField value={trailingTokenHtml} onChange={mockOnChange} acceptsTokens={false} />,
      );
      const editableDiv = container.querySelector('.contenteditable-field') as HTMLElement;
      expect(editableDiv.textContent).not.toContain('\u200B');
    });

    it('the serialized value of a field ending in a token has NO zero-width space', () => {
      // convertHTMLToTokens is mocked to identity in THIS suite, so assert on the
      // real strip behavior via the actual util (the production serialize path).
      const real = jest.requireActual('../../utils/tokenUtils');
      // Guard against any leaked mockReturnValue on sanitizeTokenHtml: the real
      // convertHTMLToTokens now calls it, so force a transparent identity here.
      const { sanitizeTokenHtml } = require('../../utils/sanitize');
      (sanitizeTokenHtml as jest.Mock).mockImplementation((html: string) => html);
      const htmlWithSpacer = trailingTokenHtml + '\u200B';
      const serialized = real.convertHTMLToTokens(htmlWithSpacer);
      expect(serialized).toBe('Hello [user:name]');
      expect(serialized).not.toContain('\u200B');
    });

    it('Backspace with the caret after a trailing token (in its ZWSP spot) deletes the TOKEN', () => {
      const { container } = render(<ContentEditableField value={trailingTokenHtml} onChange={mockOnChange} />);
      const editableDiv = container.querySelector('.contenteditable-field') as HTMLElement;
      const zwspNode = editableDiv.lastChild as Text;
      expect(zwspNode.textContent).toBe('\u200B');
      const tokenEl = editableDiv.querySelector('.config-token') as HTMLElement;
      expect(tokenEl).toBeTruthy();

      // Place the caret just after the ZWSP (offset 1), i.e. visually right after
      // the trailing token.
      const selection = window.getSelection()!;
      const range = document.createRange();
      range.setStart(zwspNode, 1);
      range.collapse(true);
      selection.removeAllRanges();
      selection.addRange(range);

      fireEvent.keyDown(editableDiv, { key: 'Backspace' });

      // The token pill is gone (not merely the ZWSP), and no token remains.
      expect(editableDiv.querySelector('.config-token')).toBeNull();
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

  describe('"[" token picker', () => {
    const { TokenSourceContext } = require('../TokenSourceContext');
    const sampleGlobalTokens = {
      '[site:name]': { name: 'Site name', 'raw token': '[site:name]', token: 'name', value: 'My Site' },
    };

    // Render the field wrapped in a token-source provider, then type "[" so the
    // picker opens. Returns the editable div and a helper to set up the caret.
    function renderWithSources(extraProps: any = {}, sources: any = { globalTokens: sampleGlobalTokens, reviewAvailable: true }) {
      const onChange = jest.fn();
      const utils = render(
        <TokenSourceContext.Provider value={sources}>
          <ContentEditableField value="" onChange={onChange} {...extraProps} />
        </TokenSourceContext.Provider>,
      );
      const editableDiv = utils.container.querySelector('.contenteditable-field') as HTMLElement;
      return { onChange, editableDiv, ...utils };
    }

    // Set the field's text to `text`, place the caret at the end, then fire
    // input so the picker logic runs. To mirror real typing (which mutates the
    // SAME text node), this reuses the existing first text node when present and
    // only updates its data — so the "[" anchor's node identity is preserved
    // across successive calls (important for the consumed-bracket guard).
    function typeAt(editableDiv: HTMLElement, text: string) {
      let textNode = editableDiv.firstChild;
      if (textNode && textNode.nodeType === Node.TEXT_NODE) {
        (textNode as Text).data = text;
      } else {
        editableDiv.textContent = text;
        textNode = editableDiv.firstChild;
      }
      const range = document.createRange();
      range.setStart(textNode!, text.length);
      range.setEnd(textNode!, text.length);
      const selection = window.getSelection();
      selection?.removeAllRanges();
      selection?.addRange(range);
      fireEvent.input(editableDiv);
    }

    // The picker is a MODAL dialog rendered via a portal to document.body, so
    // it lives OUTSIDE the RTL `container`. Query it from the document.
    const picker = () => document.querySelector('.token-picker');

    it('should open the picker when "[" is typed in a token field', () => {
      const { editableDiv } = renderWithSources();
      typeAt(editableDiv, '[');
      expect(picker()).toBeTruthy();
      // Shows the category list.
      expect(picker()!.textContent).toContain('Select token category');
    });

    it('should render the picker through a portal (escaping the field container)', () => {
      const { editableDiv, container } = renderWithSources();
      typeAt(editableDiv, '[');
      // Portaled out: not a descendant of the field container.
      expect(container.querySelector('.token-picker')).toBeNull();
      expect(document.querySelector('.token-picker')).toBeTruthy();
      // A transparent modal backdrop is rendered behind it.
      expect(document.querySelector('.token-picker-backdrop')).toBeTruthy();
    });

    it('should portal into the .modeler root (theme-var + dark-mode scope) when present', () => {
      // Mount the field inside a `.modeler` root, like the real app. The picker
      // must portal INTO that root so the `--modeler-*` vars + dark-mode resolve
      // (and it still escapes the field's own subtree).
      const onChange = jest.fn();
      const modelerRoot = document.createElement('div');
      modelerRoot.className = 'modeler dark-mode';
      document.body.appendChild(modelerRoot);
      const { container } = render(
        <TokenSourceContext.Provider value={{ globalTokens: sampleGlobalTokens, reviewAvailable: true }}>
          <ContentEditableField value="" onChange={onChange} />
        </TokenSourceContext.Provider>,
        { container: modelerRoot.appendChild(document.createElement('div')) },
      );
      const editableDiv = container.querySelector('.contenteditable-field') as HTMLElement;
      typeAt(editableDiv, '[');
      const pickerEl = document.querySelector('.token-picker') as HTMLElement;
      const backdrop = document.querySelector('.token-picker-backdrop') as HTMLElement;
      expect(pickerEl).toBeTruthy();
      // Both backdrop and picker are mounted INSIDE the .modeler root (so the
      // theme variables + dark mode cascade to them) and NOT in the field.
      expect(modelerRoot.contains(pickerEl)).toBe(true);
      expect(modelerRoot.contains(backdrop)).toBe(true);
      expect(container.contains(pickerEl)).toBe(false);
      document.body.removeChild(modelerRoot);
    });

    it('should NOT open the picker when acceptsTokens is false', () => {
      const { editableDiv } = renderWithSources({ acceptsTokens: false });
      typeAt(editableDiv, '[');
      expect(picker()).toBeNull();
    });

    it('should NOT open the picker when the field is disabled', () => {
      const { editableDiv } = renderWithSources({ disabled: true });
      // Disabled contenteditable can't really be typed in, but guard anyway.
      if (editableDiv) {
        typeAt(editableDiv, '[');
      }
      expect(picker()).toBeNull();
    });

    it('should open the picker when "[" is typed mid-string (no preceding whitespace required)', () => {
      const { editableDiv } = renderWithSources();
      // The trigger may appear anywhere, including immediately after a word
      // character (mid-word). "foo[" opens the picker (showing the category
      // list — filtering now happens in the picker's own search box).
      typeAt(editableDiv, 'foo[');
      expect(picker()).toBeTruthy();
      expect(picker()!.textContent).toContain('Select token category');
    });

    it('opens on "[" and filtering happens in the picker search box, NOT field text', () => {
      // DECISION A: the picker owns its own search box. Typing "[" opens it
      // showing the categories; further FIELD typing after the "[" does NOT
      // filter the picker — the user filters by typing into the picker's input.
      const { editableDiv } = renderWithSources();
      typeAt(editableDiv, '[');
      expect(picker()).toBeTruthy();
      // The picker shows the category list, not a field-text-driven filter.
      expect(picker()!.textContent).toContain('Select token category');

      // Filter via the picker's own search input → flat list of matches.
      const searchInput = document.querySelector('.token-picker-search-input') as HTMLInputElement;
      expect(searchInput).toBeTruthy();
      fireEvent.change(searchInput, { target: { value: 'site' } });
      expect(picker()!.textContent).toContain('Site name');
    });

    it('does NOT re-open after the same "[" is dismissed and more text is typed (Caveat 3)', () => {
      const { editableDiv } = renderWithSources();
      // Type the trigger → picker opens.
      typeAt(editableDiv, '[');
      expect(picker()).toBeTruthy();
      // Dismiss via Escape.
      fireEvent.keyDown(document, { key: 'Escape' });
      expect(picker()).toBeNull();
      // Typing more characters after that SAME "[" must NOT re-open it.
      typeAt(editableDiv, '[abc');
      expect(picker()).toBeNull();
    });

    it('a brand-new "[" at a different position still opens after a prior dismiss (Caveat 3)', () => {
      const { editableDiv } = renderWithSources();
      typeAt(editableDiv, '[');
      fireEvent.keyDown(document, { key: 'Escape' });
      expect(picker()).toBeNull();
      // A NEW "[" at a different offset is a fresh, un-consumed trigger.
      typeAt(editableDiv, 'x[');
      expect(picker()).toBeTruthy();
    });

    it('should insert the token pill and remove the "[" on Use', () => {
      const { createTokenElement } = require('../../utils/tokenUtils');
      const pill = document.createElement('span');
      pill.className = 'config-token';
      pill.textContent = 'name';
      pill.setAttribute('data-token', '[site:name]');
      createTokenElement.mockReturnValue(pill);

      const { editableDiv, onChange } = renderWithSources();
      // Open via the "[" trigger, then filter in the picker's own search box.
      typeAt(editableDiv, '[');
      const searchInput = document.querySelector('.token-picker-search-input') as HTMLInputElement;
      fireEvent.change(searchInput, { target: { value: 'site' } });

      // Click the Use button on the matching token (in the portaled picker).
      const useBtn = document.querySelector('.token-picker-use-btn') as HTMLElement;
      expect(useBtn).toBeTruthy();
      fireEvent.click(useBtn);

      // The picker closes and the token pill is now in the field.
      expect(picker()).toBeNull();
      expect(editableDiv.querySelector('.config-token')).toBeTruthy();
      // The triggering "[" was removed (only the pill remains, no stray "[").
      expect(editableDiv.textContent).not.toContain('[');
      expect(createTokenElement).toHaveBeenCalledWith('Site name', '[site:name]');

      jest.advanceTimersByTime(300);
      expect(onChange).toHaveBeenCalled();
    });

    it('restores field focus and caret to the ORIGINAL offset on Escape dismiss (Caveat 2 / DECISION B)', () => {
      const { editableDiv } = renderWithSources();
      typeAt(editableDiv, 'ab[');
      expect(picker()).toBeTruthy();
      // Dismiss via Escape → focus + caret return to the field.
      fireEvent.keyDown(document, { key: 'Escape' });
      expect(picker()).toBeNull();
      expect(document.activeElement).toBe(editableDiv);
      // The caret is restored to where it was at open time (absolute offset 3,
      // just after the "[") — NOT the start of the field.
      const selection = window.getSelection();
      expect(selection && selection.rangeCount).toBeTruthy();
      if (selection && selection.rangeCount) {
        const range = selection.getRangeAt(0);
        expect(editableDiv.contains(range.startContainer)).toBe(true);
        expect(range.startOffset).toBe(3);
        expect(range.startOffset).not.toBe(0);
      }
    });

    it('restores caret to the original ABSOLUTE offset even after the text node is RE-CREATED (Issue C)', () => {
      const { editableDiv } = renderWithSources();
      typeAt(editableDiv, 'abc[');
      expect(picker()).toBeTruthy();
      // Simulate a React re-render that REPLACES the field's text node with a new
      // node object holding the same text — the OLD node reference now dangles.
      const oldNode = editableDiv.firstChild as Text;
      const replacement = document.createTextNode(oldNode.data);
      editableDiv.replaceChild(replacement, oldNode);
      expect(editableDiv.firstChild).not.toBe(oldNode);

      // Dismiss → the absolute-offset restore must re-resolve against the LIVE
      // (replaced) node and land at offset 4 (after "abc["), not 0.
      fireEvent.keyDown(document, { key: 'Escape' });
      expect(document.activeElement).toBe(editableDiv);
      const selection = window.getSelection();
      expect(selection && selection.rangeCount).toBeTruthy();
      if (selection && selection.rangeCount) {
        const range = selection.getRangeAt(0);
        expect(range.startContainer).toBe(replacement);
        expect(range.startOffset).toBe(4);
        expect(range.startOffset).not.toBe(0);
      }
    });

    it('restores field focus on backdrop click and × dismiss (Caveat 2 / DECISION B)', () => {
      const { editableDiv } = renderWithSources();
      // Backdrop (click-outside).
      typeAt(editableDiv, '[');
      expect(picker()).toBeTruthy();
      const backdrop = document.querySelector('.token-picker-backdrop') as HTMLElement;
      fireEvent.mouseDown(backdrop);
      expect(picker()).toBeNull();
      expect(document.activeElement).toBe(editableDiv);

      // × close button (a fresh, un-consumed "[" at a new offset).
      typeAt(editableDiv, 'y[');
      expect(picker()).toBeTruthy();
      const closeBtn = document.querySelector('.token-picker-close') as HTMLElement;
      fireEvent.click(closeBtn);
      expect(picker()).toBeNull();
      expect(document.activeElement).toBe(editableDiv);
    });

    it('should show the "Review the flow" hint when there is no step data', () => {
      const onReviewModel = jest.fn();
      const { editableDiv } = renderWithSources({}, {
        globalTokens: sampleGlobalTokens,
        hasStepData: false,
        reviewAvailable: true,
        onReviewModel,
      });
      typeAt(editableDiv, '[');
      expect(picker()!.textContent).toContain('Review the flow');
    });

    // ── MODAL behavior: field focus no longer affects the picker ────────────
    describe('modal dialog behavior', () => {
      it('field blur does NOT close the picker (focus is irrelevant to a modal)', () => {
        const { editableDiv, onChange } = renderWithSources();
        typeAt(editableDiv, '[');
        expect(picker()).toBeTruthy();
        // Blur to a real outside control — the picker MUST stay open.
        const other = document.createElement('input');
        document.body.appendChild(other);
        fireEvent.blur(editableDiv, { relatedTarget: other });
        expect(picker()).toBeTruthy();
        // Blur still persists the field value.
        expect(onChange).toHaveBeenCalled();
        document.body.removeChild(other);
      });

      it('survives a parent re-render (portaled out of the field subtree)', () => {
        const onChange = jest.fn();
        const { container, rerender } = render(
          <TokenSourceContext.Provider value={{ globalTokens: sampleGlobalTokens, reviewAvailable: true, owningEventId: 'event_1' }}>
            <ContentEditableField value="" onChange={onChange} />
          </TokenSourceContext.Provider>,
        );
        const editableDiv = container.querySelector('.contenteditable-field') as HTMLElement;
        typeAt(editableDiv, '[');
        expect(picker()).toBeTruthy();

        // A data-arrival / dataset-selected props update re-renders the parent.
        rerender(
          <TokenSourceContext.Provider value={{
            globalTokens: sampleGlobalTokens,
            reviewAvailable: true,
            owningEventId: 'event_1',
            replayEntries: [{ model_id: 'm', component_id: 'event_1', history: [], timestamp: '2024-01-01T00:00:00Z', user: 'a', ip: '', url: '' }],
            selectedEntryIndex: 0,
            stepData: { user: { label: 'User', token: '[user:name]' } },
            hasStepData: true,
          }}>
            <ContentEditableField value="" onChange={onChange} />
          </TokenSourceContext.Provider>,
        );
        expect(picker()).toBeTruthy();
      });

      it('closes on Escape', () => {
        const { editableDiv } = renderWithSources();
        typeAt(editableDiv, '[');
        expect(picker()).toBeTruthy();
        fireEvent.keyDown(document, { key: 'Escape' });
        expect(picker()).toBeNull();
      });

      it('closes when the close (×) icon is clicked', () => {
        const { editableDiv } = renderWithSources();
        typeAt(editableDiv, '[');
        const closeBtn = document.querySelector('.token-picker-close') as HTMLElement;
        expect(closeBtn).toBeTruthy();
        expect(closeBtn.getAttribute('aria-label')).toBe('Close');
        fireEvent.click(closeBtn);
        expect(picker()).toBeNull();
      });

      it('closes on a mousedown on the modal backdrop (click outside)', () => {
        const { editableDiv } = renderWithSources();
        typeAt(editableDiv, '[');
        const backdrop = document.querySelector('.token-picker-backdrop') as HTMLElement;
        expect(backdrop).toBeTruthy();
        fireEvent.mouseDown(backdrop);
        expect(picker()).toBeNull();
      });

      it('does NOT close when interacting inside the picker', () => {
        const { editableDiv } = renderWithSources();
        typeAt(editableDiv, '[');
        const pickerEl = picker() as HTMLElement;
        // A mousedown inside the dialog must not dismiss it.
        fireEvent.mouseDown(pickerEl);
        expect(picker()).toBeTruthy();
      });

      it('reports onPickerOpenChange(true) on genuine open and (false) on genuine close', () => {
        const onPickerOpenChange = jest.fn();
        const { editableDiv } = renderWithSources({}, {
          globalTokens: sampleGlobalTokens,
          reviewAvailable: true,
          onPickerOpenChange,
        });
        // Event-driven: NOT called on mount (no genuine open/close yet).
        expect(onPickerOpenChange).not.toHaveBeenCalled();

        // Genuine open → reports true exactly once.
        typeAt(editableDiv, '[');
        expect(onPickerOpenChange).toHaveBeenCalledTimes(1);
        expect(onPickerOpenChange).toHaveBeenCalledWith(true);

        onPickerOpenChange.mockClear();
        // Typing more while ALREADY open does NOT re-report (deduped).
        typeAt(editableDiv, '[si');
        expect(onPickerOpenChange).not.toHaveBeenCalled();

        // Genuine close via Escape → reports false exactly once.
        fireEvent.keyDown(document, { key: 'Escape' });
        expect(onPickerOpenChange).toHaveBeenCalledTimes(1);
        expect(onPickerOpenChange).toHaveBeenCalledWith(false);
      });

      it('does NOT report false on a context-value identity change while the picker is open', () => {
        // The bug: the volatile tokenSources object gets a new identity as step
        // data loads; the OLD effect-cleanup approach fired false on each such
        // change. The event-driven signal must NOT report false here.
        const onPickerOpenChange = jest.fn();
        const baseSources = {
          globalTokens: sampleGlobalTokens,
          reviewAvailable: true,
          owningEventId: 'event_1',
          onPickerOpenChange,
        };
        const { container, rerender } = render(
          <TokenSourceContext.Provider value={{ ...baseSources }}>
            <ContentEditableField value="" onChange={jest.fn()} />
          </TokenSourceContext.Provider>,
        );
        const editableDiv = container.querySelector('.contenteditable-field') as HTMLElement;
        typeAt(editableDiv, '[');
        expect(onPickerOpenChange).toHaveBeenCalledWith(true);
        onPickerOpenChange.mockClear();

        // Simulate data churn: brand-new tokenSources object identity (and new
        // volatile data) while the picker stays open.
        rerender(
          <TokenSourceContext.Provider value={{
            ...baseSources,
            replayEntries: [{ model_id: 'm', component_id: 'event_1', history: [], timestamp: '2024-01-01T00:00:00Z', user: 'a', ip: '', url: '' }] as any,
            selectedEntryIndex: 0,
            isLoadingStepData: false,
          }}>
            <ContentEditableField value="" onChange={jest.fn()} />
          </TokenSourceContext.Provider>,
        );
        // The picker is still open and NO false was reported by the churn.
        expect(picker()).toBeTruthy();
        expect(onPickerOpenChange).not.toHaveBeenCalledWith(false);
      });

      it('reports false once for each genuine close path (× / backdrop / insert)', () => {
        // Close via the × button.
        const onClose1 = jest.fn();
        const r1 = renderWithSources({}, { globalTokens: sampleGlobalTokens, reviewAvailable: true, onPickerOpenChange: onClose1 });
        typeAt(r1.editableDiv, '[');
        onClose1.mockClear();
        fireEvent.click(document.querySelector('.token-picker-close') as HTMLElement);
        expect(onClose1).toHaveBeenCalledTimes(1);
        expect(onClose1).toHaveBeenCalledWith(false);
        r1.unmount();

        // Close via the backdrop.
        const onClose2 = jest.fn();
        const r2 = renderWithSources({}, { globalTokens: sampleGlobalTokens, reviewAvailable: true, onPickerOpenChange: onClose2 });
        typeAt(r2.editableDiv, '[');
        onClose2.mockClear();
        fireEvent.mouseDown(document.querySelector('.token-picker-backdrop') as HTMLElement);
        expect(onClose2).toHaveBeenCalledWith(false);
      });

      it('is a no-op (no throw) when no onPickerOpenChange is in context', () => {
        // Default renderWithSources context has no onPickerOpenChange; opening
        // and closing the picker must not throw.
        const { editableDiv } = renderWithSources();
        expect(() => {
          typeAt(editableDiv, '[');
          fireEvent.keyDown(document, { key: 'Escape' });
        }).not.toThrow();
      });
    });
  });

  // ── Regression: caret/serialization bugs in the in-field "[" picker ───────
  //
  // These exercise the realistic POST-INSERT DOM that the simple `typeAt`
  // helper (which overwrites firstChild) cannot build: a `.config-token` pill
  // followed by a trailing text node that may carry a leading ZWSP caret spot
  // plus typed characters. We construct that DOM directly and drive the real
  // handlers.
  describe('caret/serialization regressions ("[" picker)', () => {
    const { TokenSourceContext } = require('../TokenSourceContext');
    const sampleGlobalTokens = {
      '[site:name]': { name: 'Site name', 'raw token': '[site:name]', token: 'name', value: 'My Site' },
    };

    beforeEach(() => {
      const { isTokenElement } = require('../../utils/tokenUtils');
      isTokenElement.mockImplementation(
        (node: any) => node?.classList?.contains?.('config-token') ?? false,
      );
    });

    function renderField(extraProps: any = {}, sources: any = { globalTokens: sampleGlobalTokens, reviewAvailable: true }) {
      const onChange = jest.fn();
      const utils = render(
        <TokenSourceContext.Provider value={sources}>
          <ContentEditableField value="" onChange={onChange} {...extraProps} />
        </TokenSourceContext.Provider>,
      );
      const editableDiv = utils.container.querySelector('.contenteditable-field') as HTMLElement;
      return { onChange, editableDiv, ...utils };
    }

    // Build: [pill]["<prefix><typed>"] in the field. Returns the pill and the
    // trailing text node so a caller can position the caret within it.
    function buildPillThenText(
      editableDiv: HTMLElement,
      opts: { dataToken?: string; label?: string; text: string },
    ): { pill: HTMLElement; textNode: Text } {
      editableDiv.innerHTML = '';
      const pill = document.createElement('span');
      pill.className = 'config-token';
      pill.setAttribute('contenteditable', 'false');
      pill.setAttribute('data-token', opts.dataToken ?? '[site:name]');
      pill.textContent = opts.label ?? 'name';
      editableDiv.appendChild(pill);
      const textNode = document.createTextNode(opts.text);
      editableDiv.appendChild(textNode);
      return { pill, textNode };
    }

    function setCaret(node: Node, offset: number): void {
      const selection = window.getSelection()!;
      const range = document.createRange();
      range.setStart(node, offset);
      range.collapse(true);
      selection.removeAllRanges();
      selection.addRange(range);
    }

    // The picker is a portaled modal living on document.body, outside the RTL
    // container — query it from the document.
    const picker = () => document.querySelector('.token-picker');

    // BUG 2, case 1 — caret after real typed chars: Backspace must NOT delete
    // the token; native deletion is allowed (preventDefault NOT called).
    it('Bug 2: Backspace with typed chars after a token (caret mid-text) does NOT delete the token', () => {
      const { editableDiv } = renderField();
      const { pill, textNode } = buildPillThenText(editableDiv, { text: '\u200Babc' });
      // Caret at the very end of "\u200Babc" (offset 4) — a real char "c"
      // precedes it, so Backspace should delete "c", not the token.
      setCaret(textNode, 4);

      const event = fireEvent.keyDown(editableDiv, { key: 'Backspace' });

      // The token pill must still be present (browser handles char deletion).
      expect(editableDiv.querySelector('.config-token')).toBe(pill);
      // preventDefault was NOT called → native Backspace proceeds.
      expect(event).toBe(true);
    });

    // BUG 2, case 2 — caret in a ZWSP-only trailing node (no typed chars):
    // Backspace DELETES the token (preserves existing trailing-token behavior).
    it('Bug 2: Backspace in a ZWSP-only trailing node deletes the token', () => {
      const { editableDiv } = renderField();
      const { textNode } = buildPillThenText(editableDiv, { text: '\u200B' });
      setCaret(textNode, 1);

      fireEvent.keyDown(editableDiv, { key: 'Backspace' });

      expect(editableDiv.querySelector('.config-token')).toBeNull();
    });

    // BUG 2, case 3 — caret genuinely at the token boundary (no ZWSP, plain
    // text node, caret offset 0 with the token as previousSibling): the token
    // IS removed.
    it('Bug 2: Backspace at the visible start of a plain text node (token boundary) deletes the token', () => {
      const { editableDiv } = renderField();
      const { textNode } = buildPillThenText(editableDiv, { text: 'abc' });
      // Caret at offset 0 → nothing between the caret and the token.
      setCaret(textNode, 0);

      fireEvent.keyDown(editableDiv, { key: 'Backspace' });

      expect(editableDiv.querySelector('.config-token')).toBeNull();
    });

    // Distinct-pill factory so we can count two separate pills.
    function mockDistinctPills(): void {
      const { createTokenElement } = require('../../utils/tokenUtils');
      createTokenElement.mockImplementation((label: string, token: string) => {
        const span = document.createElement('span');
        span.className = 'config-token';
        span.setAttribute('contenteditable', 'false');
        span.setAttribute('data-token', token);
        span.textContent = label;
        return span;
      });
    }

    // Assert the field ends up ordered: [pill1] "abc" [pill2] — i.e. the new
    // pill is inserted WHERE the "[" was (after the typed "abc"), NOT orphaned
    // at the field start or appended at the end before/after stray content.
    function expectCorrectOrdering(editableDiv: HTMLElement): void {
      const pills = editableDiv.querySelectorAll('.config-token');
      expect(pills.length).toBe(2);
      const [pill1, pill2] = Array.from(pills);
      // The visible text between the two pills is the typed query "abc"
      // (ignoring the zero-width caret spacer).
      const between = (pill1.nextSibling?.textContent || '').replace(/\u200B/g, '');
      expect(between).toBe('abc');
      // pill2 comes AFTER pill1 in document order.
      expect(
        pill1.compareDocumentPosition(pill2) & Node.DOCUMENT_POSITION_FOLLOWING,
      ).toBeTruthy();
    }

    // BUG 1 (the REAL failure path) — clicking "Use" in the portaled picker
    // moves focus/selection INTO the dialog, so the live caret is NOT in the
    // field, AND a React re-render has detached the recorded "[" anchor node.
    // The trigger "[" must STILL be removed (via a field-DOM scan), the new pill
    // inserted where the "[" was, leaving no stray bracket and exactly two pills.
    it('Bug 1: inserting a second token removes the trigger "[" even when focus is in the picker (anchor detached)', () => {
      mockDistinctPills();

      const { editableDiv } = renderField();
      // Realistic post-first-insert DOM: pill + trailing "\u200Babc[" where the
      // user typed "abc" then a fresh "[" to open the picker again.
      const { textNode } = buildPillThenText(editableDiv, { text: '\u200Babc[' });
      // Caret right after the "[" (end of the node, offset 5) → opens picker.
      setCaret(textNode, 5);
      fireEvent.input(editableDiv);
      const searchInput = document.querySelector('.token-picker-search-input') as HTMLInputElement;
      expect(searchInput).toBeTruthy();

      // Simulate the React re-render that RE-CREATES (detaches) the trailing text
      // node between opening the picker and clicking Use (setLocalValue/innerHTML
      // churn). The recorded "[" anchor node now dangles.
      const oldTrailing = textNode;
      const replacement = document.createTextNode(oldTrailing.data);
      editableDiv.replaceChild(replacement, oldTrailing);
      expect(editableDiv.contains(oldTrailing)).toBe(false);

      // FAITHFUL to reality: clicking the portaled picker button moves the
      // selection INTO the dialog (NOT back into the field). Point the selection
      // at a node inside the picker so the field has NO live caret.
      const pickerEl = picker() as HTMLElement;
      const sel = window.getSelection()!;
      sel.removeAllRanges();
      const inPicker = document.createRange();
      inPicker.selectNodeContents(pickerEl);
      inPicker.collapse(true);
      sel.addRange(inPicker);
      expect(editableDiv.contains(sel.getRangeAt(0).startContainer)).toBe(false);

      fireEvent.change(searchInput, { target: { value: 'site' } });
      const useBtn = document.querySelector('.token-picker-use-btn') as HTMLElement;
      expect(useBtn).toBeTruthy();
      fireEvent.click(useBtn);

      // No stray "[" anywhere, exactly two pills, and the new pill is positioned
      // where the "[" was (after "abc"), not orphaned at a wrong index.
      expect(editableDiv.textContent).not.toContain('[');
      expectCorrectOrdering(editableDiv);
    });

    // Companion: anchor NOT detached, but focus is still in the picker on Use.
    // Path 1's charAt('[') guard succeeds here; confirms the happy path also
    // removes the bracket and orders the pill correctly.
    it('Bug 1: inserting a second token removes the trigger "[" with a live anchor (focus in picker)', () => {
      mockDistinctPills();

      const { editableDiv } = renderField();
      const { textNode } = buildPillThenText(editableDiv, { text: '\u200Babc[' });
      setCaret(textNode, 5);
      fireEvent.input(editableDiv);
      const searchInput = document.querySelector('.token-picker-search-input') as HTMLInputElement;
      expect(searchInput).toBeTruthy();

      // Anchor node is NOT detached (no re-render). Move selection into the
      // picker dialog (mirrors clicking the portaled button).
      const pickerEl = picker() as HTMLElement;
      const sel = window.getSelection()!;
      sel.removeAllRanges();
      const inPicker = document.createRange();
      inPicker.selectNodeContents(pickerEl);
      inPicker.collapse(true);
      sel.addRange(inPicker);

      fireEvent.change(searchInput, { target: { value: 'site' } });
      const useBtn = document.querySelector('.token-picker-use-btn') as HTMLElement;
      expect(useBtn).toBeTruthy();
      fireEvent.click(useBtn);

      expect(editableDiv.textContent).not.toContain('[');
      expectCorrectOrdering(editableDiv);
    });
  });
});
