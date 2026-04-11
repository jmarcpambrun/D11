/**
 * ContentEditableField - Rich text input component with token support
 * 
 * Provides a contenteditable div that supports:
 * - Token display and editing (e.g., "[user:name]" shown as styled pills)
 * - Drag-and-drop token insertion from token browser
 * - Copy/paste with token preservation
 * - Single-line and multi-line modes
 */

import React, { useState, useCallback, useEffect, useRef } from 'react';
import { FiEdit2 } from 'react-icons/fi';
import { TIMING, UI_DIMENSIONS } from '../constants/dimensions';
import { sanitizeTokenHtml, escapeHtml } from '../utils/sanitize';
import {
  convertTokensToHTML,
  convertHTMLToTokens,
  createTokenElement,
  isTokenElement,
  parseTokenFromDragEvent,
} from '../utils/tokenUtils';
import { t } from '../utils/translation';

interface TokenEditState {
  element: HTMLElement;
  token: string;
  x: number;
  y: number;
  position: 'above' | 'below';
}

interface TokenIconState {
  x: number;
  y: number;
  element: HTMLElement;
}

interface DropCursor {
  x: number;
  y: number;
  height: number;
}

interface ContentEditableFieldProps {
  value: string;
  onChange: (value: string) => void;
  className?: string;
  placeholder?: string;
  disabled?: boolean;
  multiline?: boolean;
  /** Whether this field accepts token drops. Defaults to true. */
  acceptsTokens?: boolean;
  /** Whether a token is currently being dragged (used for visual indicators). */
  isTokenDragging?: boolean;
}

/**
 * Get caret position from coordinates using browser APIs
 */
function getCaretPositionFromPoint(x: number, y: number, container: HTMLElement): Range | null {
  let insertPosition: Range | null = null;

  // Use document.caretPositionFromPoint (Firefox) or document.caretRangeFromPoint (Chrome/Safari)
  if ((document as any).caretPositionFromPoint) {
    const caretPosition = (document as any).caretPositionFromPoint(x, y);
    if (caretPosition && container.contains(caretPosition.offsetNode)) {
      const range = document.createRange();
      range.setStart(caretPosition.offsetNode, caretPosition.offset);
      range.setEnd(caretPosition.offsetNode, caretPosition.offset);
      insertPosition = range;
    }
  } else if ((document as any).caretRangeFromPoint) {
    const caretRange = (document as any).caretRangeFromPoint(x, y);
    if (caretRange && container.contains(caretRange.startContainer)) {
      insertPosition = caretRange;
    }
  }

  return insertPosition;
}

/**
 * Get fallback insertion position (current selection or end of container)
 */
function getFallbackInsertPosition(container: HTMLElement): Range {
  const selection = window.getSelection();
  if (selection && selection.rangeCount > 0) {
    const range = selection.getRangeAt(0);
    if (container === range.commonAncestorContainer ||
        container.contains(range.commonAncestorContainer)) {
      return range;
    }
  }

  // Final fallback: insert at the end
  const range = document.createRange();
  if (container.firstChild) {
    range.setStartAfter(container.lastChild || container);
    range.setEndAfter(container.lastChild || container);
  } else {
    range.selectNodeContents(container);
    range.collapse(false);
  }
  return range;
}

const ContentEditableField: React.FC<ContentEditableFieldProps> = ({
  value,
  onChange,
  className = '',
  placeholder,
  disabled = false,
  multiline = false,
  acceptsTokens = true,
  isTokenDragging = false,
}) => {
  const divRef = useRef<HTMLDivElement>(null);
  const wrapperRef = useRef<HTMLDivElement>(null);
  const debounceTimeoutRef = useRef<number | null>(null);
  const dragSourceRef = useRef<HTMLElement | null>(null);
  const editInputRef = useRef<HTMLInputElement>(null);
  const [isEditing, setIsEditing] = useState(false);
  const [isDragOver, setIsDragOver] = useState(false);
  const [dropCursor, setDropCursor] = useState<DropCursor | null>(null);
  const [_localValue, setLocalValue] = useState(value || '');
  const [editIconTarget, setEditIconTarget] = useState<TokenIconState | null>(null);
  const [editingToken, setEditingToken] = useState<TokenEditState | null>(null);

  // Sync external value to internal state when not editing
  useEffect(() => {
    if (divRef.current && !isEditing) {
      const incomingValue = value || '';
      // Only convert bracket syntax to token pills when the field accepts tokens
      const htmlContent = acceptsTokens ? convertTokensToHTML(incomingValue) : incomingValue;
      const currentContent = divRef.current.innerHTML || '';

      if (currentContent !== htmlContent) {
        divRef.current.innerHTML = htmlContent;
        setLocalValue(htmlContent);
      }
    }
  }, [value, isEditing, acceptsTokens]);

  // Track selection changes to highlight selected token spans as whole pills
  useEffect(() => {
    const container = divRef.current;
    if (!container) return;

    const updateTokenSelection = () => {
      const tokens = container.querySelectorAll('.config-token');
      const selection = window.getSelection();

      if (!selection || selection.rangeCount === 0) {
        tokens.forEach(tok => tok.classList.remove('selected'));
        return;
      }

      const range = selection.getRangeAt(0);

      tokens.forEach(tok => {
        // A token is selected if the selection range intersects it
        const isSelected = range.intersectsNode(tok) && !range.collapsed;
        tok.classList.toggle('selected', isSelected);
      });
    };

    document.addEventListener('selectionchange', updateTokenSelection);
    return () => {
      document.removeEventListener('selectionchange', updateTokenSelection);
    };
  }, []);

  // Compute edit icon position relative to the wrapper
  const getTokenIconPosition = useCallback((tokenEl: HTMLElement): { x: number; y: number } | null => {
    const wrapper = wrapperRef.current;
    if (!wrapper) return null;
    const tokenRect = tokenEl.getBoundingClientRect();
    const wrapperRect = wrapper.getBoundingClientRect();
    return {
      x: tokenRect.left - wrapperRect.left + tokenRect.width / 2,
      y: tokenRect.top - wrapperRect.top,
    };
  }, []);

  // Track mouseover/mouseout on the wrapper to show edit icon
  // Using the wrapper (not the contenteditable container) so the icon stays visible
  // when the mouse moves from the token to the edit icon (both are inside the wrapper).
  useEffect(() => {
    const wrapper = wrapperRef.current;
    const container = divRef.current;
    if (!wrapper || !container || disabled) return;

    const handleMouseOver = (e: MouseEvent) => {
      if (editingToken) return; // Don't move icon while editing
      const target = (e.target as HTMLElement).closest?.('.config-token') as HTMLElement | null;
      if (target && container.contains(target)) {
        const pos = getTokenIconPosition(target);
        if (pos) {
          setEditIconTarget({ x: pos.x, y: pos.y, element: target });
        }
      }
    };

    const handleMouseOut = (e: MouseEvent) => {
      if (editingToken) return;
      const relatedTarget = e.relatedTarget as HTMLElement | null;
      // Only hide if the mouse is leaving the wrapper entirely
      if (relatedTarget && wrapper.contains(relatedTarget)) return;
      setEditIconTarget(null);
    };

    wrapper.addEventListener('mouseover', handleMouseOver);
    wrapper.addEventListener('mouseout', handleMouseOut);
    return () => {
      wrapper.removeEventListener('mouseover', handleMouseOver);
      wrapper.removeEventListener('mouseout', handleMouseOut);
    };
  }, [disabled, editingToken, getTokenIconPosition]);

  // Also show edit icon for selected tokens (keyboard navigation)
  useEffect(() => {
    if (editingToken || disabled) return;
    const container = divRef.current;
    if (!container) return;

    const updateEditIconForSelection = () => {
      const selectedToken = container.querySelector('.config-token.selected') as HTMLElement | null;
      if (selectedToken) {
        const pos = getTokenIconPosition(selectedToken);
        if (pos) {
          setEditIconTarget({ x: pos.x, y: pos.y, element: selectedToken });
        }
      }
    };

    document.addEventListener('selectionchange', updateEditIconForSelection);
    return () => {
      document.removeEventListener('selectionchange', updateEditIconForSelection);
    };
  }, [disabled, editingToken, getTokenIconPosition]);

  // Open the token edit popup
  const openTokenEdit = useCallback((tokenEl: HTMLElement) => {
    const wrapper = wrapperRef.current;
    if (!wrapper) return;
    const tokenRect = tokenEl.getBoundingClientRect();
    const wrapperRect = wrapper.getBoundingClientRect();
    const token = tokenEl.getAttribute('data-token') || '';
    // Strip brackets for editing
    const tokenValue = token.startsWith('[') && token.endsWith(']')
      ? token.slice(1, -1)
      : token;
    // Check if there is enough space above the token for the popup (~80px)
    const spaceAbove = tokenRect.top - wrapperRect.top;
    const position = spaceAbove < 80 ? 'below' : 'above';
    // Clamp horizontal position so the popup doesn't overflow left/right
    const popupWidth = 200; // matches CSS min-width
    const centerX = tokenRect.left - wrapperRect.left + tokenRect.width / 2;
    const halfPopup = popupWidth / 2;
    const clampedX = Math.max(halfPopup, Math.min(centerX, wrapperRect.width - halfPopup));
    setEditingToken({
      element: tokenEl,
      token: tokenValue,
      x: clampedX,
      y: position === 'above'
        ? tokenRect.top - wrapperRect.top
        : tokenRect.bottom - wrapperRect.top,
      position,
    });
    setEditIconTarget(null);
    // Focus the input after render
    requestAnimationFrame(() => {
      editInputRef.current?.focus();
      editInputRef.current?.select();
    });
  }, []);

  // Ref tracking the latest onChange callback so the unmount cleanup (which
  // has an empty dependency array) always calls the current version.
  const onChangeRef = useRef(onChange);
  onChangeRef.current = onChange;

  // Ref holding the raw value that is pending in the debounce timer.
  // Set every time debouncedOnChange is called; cleared when the timer fires.
  const pendingValueRef = useRef<string | null>(null);

  // Flush pending changes and cleanup timeout on unmount.
  useEffect(() => {
    return () => {
      if (debounceTimeoutRef.current) {
        clearTimeout(debounceTimeoutRef.current);
        debounceTimeoutRef.current = null;
        // Flush the pending value so no edits are lost.
        if (pendingValueRef.current !== null) {
          const tokenValue = convertHTMLToTokens(pendingValueRef.current);
          onChangeRef.current(tokenValue);
          pendingValueRef.current = null;
        }
      }
    };
  }, []);

  const debouncedOnChange = useCallback((newValue: string) => {
    if (debounceTimeoutRef.current) {
      clearTimeout(debounceTimeoutRef.current);
    }

    pendingValueRef.current = newValue;
    debounceTimeoutRef.current = setTimeout(() => {
      const tokenValue = convertHTMLToTokens(newValue);
      onChange(tokenValue);
      pendingValueRef.current = null;
    }, TIMING.DEBOUNCE_DELAY) as unknown as number;
  }, [onChange]);

  // Save the edited token
  const saveTokenEdit = useCallback(() => {
    if (!editingToken || !divRef.current) return;
    const newToken = editInputRef.current?.value?.trim() || '';
    if (!newToken) {
      setEditingToken(null);
      return;
    }
    const wrappedToken = `[${newToken}]`;
    const label = newToken.split(':').pop() || newToken;
    editingToken.element.setAttribute('data-token', wrappedToken);
    editingToken.element.setAttribute('title', t('Token: @token', { '@token': wrappedToken }));
    editingToken.element.textContent = label;
    setEditingToken(null);

    // Trigger change immediately (not debounced) so the parent gets the update
    if (debounceTimeoutRef.current) {
      clearTimeout(debounceTimeoutRef.current);
      debounceTimeoutRef.current = null;
    }
    pendingValueRef.current = null;
    const htmlContent = divRef.current.innerHTML || '';
    setLocalValue(htmlContent);
    const tokenValue = convertHTMLToTokens(htmlContent);
    onChange(tokenValue);
  }, [editingToken, onChange]);

  // Cancel token editing
  const cancelTokenEdit = useCallback(() => {
    setEditingToken(null);
  }, []);

  // Handle keydown in the edit popup input
  const handleEditInputKeyDown = useCallback((e: React.KeyboardEvent<HTMLInputElement>) => {
    e.stopPropagation();
    if (e.key === 'Enter') {
      e.preventDefault();
      saveTokenEdit();
    } else if (e.key === 'Escape') {
      e.preventDefault();
      cancelTokenEdit();
    }
  }, [saveTokenEdit, cancelTokenEdit]);

  const handleInput = useCallback(() => {
    if (divRef.current) {
      const htmlContent = divRef.current.innerHTML || '';
      const hasTokens = htmlContent.includes('config-token');
      const newValue = hasTokens ? htmlContent : (divRef.current.textContent || '');
      setLocalValue(newValue);
      debouncedOnChange(newValue);
    }
  }, [debouncedOnChange]);

  const handlePaste = useCallback((e: React.ClipboardEvent) => {
    e.preventDefault();

    const plainText = e.clipboardData.getData('text/plain');
    const htmlText = e.clipboardData.getData('text/html');

    // Prefer sanitized HTML if it contains tokens, otherwise use plain text
    let contentToInsert: string;
    if (htmlText && htmlText.includes('config-token')) {
      contentToInsert = sanitizeTokenHtml(htmlText);
    } else {
      contentToInsert = escapeHtml(plainText);
    }

    // Insert at cursor position
    const selection = window.getSelection();
    if (selection && selection.rangeCount > 0) {
      const range = selection.getRangeAt(0);
      range.deleteContents();

      const tempDiv = document.createElement('div');
      tempDiv.innerHTML = contentToInsert;

      const fragment = document.createDocumentFragment();
      while (tempDiv.firstChild) {
        fragment.appendChild(tempDiv.firstChild);
      }
      range.insertNode(fragment);

      range.collapse(false);
      selection.removeAllRanges();
      selection.addRange(range);
    }

    if (divRef.current) {
      const htmlContent = divRef.current.innerHTML || '';
      setLocalValue(htmlContent);
      debouncedOnChange(htmlContent);
    }
  }, [debouncedOnChange]);

  const handleFocus = useCallback(() => {
    setIsEditing(true);
  }, []);

  const handleBlur = useCallback(() => {
    setIsEditing(false);

    // Cancel any pending debounce — we save synchronously below.
    if (debounceTimeoutRef.current) {
      clearTimeout(debounceTimeoutRef.current);
      debounceTimeoutRef.current = null;
    }
    pendingValueRef.current = null;

    // Always persist the current DOM content on blur so that values typed
    // just before a Tab / click-away are never lost.  The previous
    // implementation compared against localValue, but handleInput already
    // updates localValue on every keystroke, so by the time blur fires the
    // two always match and onChange was silently skipped.
    if (divRef.current) {
      const htmlContent = divRef.current.innerHTML || '';
      setLocalValue(htmlContent);
      const tokenValue = convertHTMLToTokens(htmlContent);
      onChange(tokenValue);
    }
  }, [onChange]);

  const handleKeyDown = useCallback((e: React.KeyboardEvent) => {
    // Prevent Enter in single-line mode
    if (!multiline && e.key === 'Enter') {
      e.preventDefault();
      divRef.current?.blur();
      return;
    }

    // Ctrl+E to edit the selected or adjacent token
    if (e.key === 'e' && (e.ctrlKey || e.metaKey)) {
      const container = divRef.current;
      if (!container) return;

      // Find the selected token or the token adjacent to the cursor
      const selectedToken = container.querySelector('.config-token.selected') as HTMLElement | null;
      if (selectedToken) {
        e.preventDefault();
        openTokenEdit(selectedToken);
        return;
      }

      // Check if cursor is adjacent to a token
      const selection = window.getSelection();
      if (selection && selection.rangeCount > 0) {
        const range = selection.getRangeAt(0);
        const nextNode = range.endContainer.nodeType === Node.TEXT_NODE
          ? range.endContainer.nextSibling
          : range.endContainer.childNodes[range.endOffset];
        const prevNode = range.startContainer.nodeType === Node.TEXT_NODE
          ? range.startContainer.previousSibling
          : range.startContainer.childNodes[range.startOffset - 1];

        const adjacentToken = (isTokenElement(nextNode) ? nextNode : null) ||
          (isTokenElement(prevNode) ? prevNode : null);
        if (adjacentToken) {
          e.preventDefault();
          openTokenEdit(adjacentToken as HTMLElement);
          return;
        }
      }
    }

    // Handle Delete and Backspace for token deletion
    if (e.key === 'Delete' || e.key === 'Backspace') {
      const selection = window.getSelection();
      if (selection && selection.rangeCount > 0) {
        const range = selection.getRangeAt(0);

        let tokenToDelete: Element | null = null;

        if (e.key === 'Delete') {
          const nextNode = range.endContainer.nodeType === Node.TEXT_NODE
            ? range.endContainer.nextSibling
            : range.endContainer.childNodes[range.endOffset];

          if (isTokenElement(nextNode)) {
            tokenToDelete = nextNode;
          }
        } else if (e.key === 'Backspace') {
          const prevNode = range.startContainer.nodeType === Node.TEXT_NODE
            ? range.startContainer.previousSibling
            : range.startContainer.childNodes[range.startOffset - 1];

          if (isTokenElement(prevNode)) {
            tokenToDelete = prevNode;
          }
        }

        if (tokenToDelete) {
          e.preventDefault();
          tokenToDelete.remove();

          const htmlContent = divRef.current?.innerHTML || '';
          setLocalValue(htmlContent);
          debouncedOnChange(htmlContent);
          return;
        }
      }
    }
  }, [multiline, debouncedOnChange, openTokenEdit]);

  const handleDragStart = useCallback((e: React.DragEvent) => {
    const target = e.target as HTMLElement;
    if (!isTokenElement(target)) return;

    const token = target.getAttribute('data-token') || '';
    const label = target.textContent || '';

    e.dataTransfer.setData('application/token', JSON.stringify({ label, token }));
    e.dataTransfer.setData('text/plain', token);
    e.dataTransfer.effectAllowed = 'move';
    dragSourceRef.current = target;
  }, []);

  const handleDragEnd = useCallback(() => {
    dragSourceRef.current = null;
  }, []);

  const handleDragEnter = useCallback((e: React.DragEvent) => {
    if (disabled) return;
    e.preventDefault();
    if (!acceptsTokens) {
      if (e.dataTransfer) {
        e.dataTransfer.dropEffect = 'none';
      }
      return;
    }
    setIsDragOver(true);
  }, [disabled, acceptsTokens]);

  const handleDragLeave = useCallback((e: React.DragEvent) => {
    if (disabled) return;
    e.preventDefault();
    if (!e.currentTarget.contains(e.relatedTarget as Node)) {
      setIsDragOver(false);
      setDropCursor(null);
    }
  }, [disabled]);

  const handleDragOver = useCallback((e: React.DragEvent) => {
    if (disabled || !divRef.current) return;
    e.preventDefault();
    if (!acceptsTokens) {
      e.dataTransfer.dropEffect = 'none';
      return;
    }
    e.dataTransfer.dropEffect = dragSourceRef.current ? 'move' : 'copy';

    const insertPosition = getCaretPositionFromPoint(e.clientX, e.clientY, divRef.current);

    if (insertPosition) {
      const tempSpan = document.createElement('span');
      tempSpan.style.position = 'absolute';
      tempSpan.style.visibility = 'hidden';
      tempSpan.style.height = '1px';
      tempSpan.style.width = '1px';

      try {
        insertPosition.insertNode(tempSpan);
        const rect = tempSpan.getBoundingClientRect();
        const fieldRect = divRef.current.getBoundingClientRect();

        tempSpan.remove();

        setDropCursor({
          x: rect.left - fieldRect.left,
          y: rect.top - fieldRect.top,
          height: Math.max(rect.height, 16)
        });
      } catch (_error) {
        if (tempSpan.parentNode) {
          tempSpan.remove();
        }
        setDropCursor(null);
      }
    } else {
      setDropCursor(null);
    }
  }, [disabled, acceptsTokens]);

  const handleDrop = useCallback((e: React.DragEvent) => {
    if (disabled || !divRef.current || !acceptsTokens) return;
    e.preventDefault();
    setIsDragOver(false);
    setDropCursor(null);

    const tokenData = parseTokenFromDragEvent(e.dataTransfer);
    if (!tokenData) return;

    // For internal moves, remove the source token before inserting at new position
    const sourceElement = dragSourceRef.current;
    if (sourceElement && divRef.current.contains(sourceElement)) {
      sourceElement.remove();
    }
    dragSourceRef.current = null;

    const tokenElement = createTokenElement(tokenData.label, tokenData.token);

    // Find insertion position
    let insertPosition = getCaretPositionFromPoint(e.clientX, e.clientY, divRef.current);
    if (!insertPosition) {
      insertPosition = getFallbackInsertPosition(divRef.current);
    }

    // Insert the token
    insertPosition.deleteContents();
    insertPosition.insertNode(tokenElement);

    // Position cursor right after the token
    insertPosition.setStartAfter(tokenElement);
    insertPosition.setEndAfter(tokenElement);

    const selection = window.getSelection();
    if (selection) {
      selection.removeAllRanges();
      selection.addRange(insertPosition);
    }

    // Trigger change event
    const htmlContent = divRef.current.innerHTML || '';
    setLocalValue(htmlContent);
    debouncedOnChange(htmlContent);
  }, [disabled, acceptsTokens, debouncedOnChange]);

  return (
    <div className="contenteditable-wrapper" ref={wrapperRef}>
      <div
        ref={divRef}
        contentEditable={!disabled}
        onInput={handleInput}
        onFocus={handleFocus}
        onBlur={handleBlur}
        onKeyDown={handleKeyDown}
        onPaste={handlePaste}
        onDragStart={handleDragStart}
        onDragEnd={handleDragEnd}
        onDragEnter={handleDragEnter}
        onDragLeave={handleDragLeave}
        onDragOver={handleDragOver}
        onDrop={handleDrop}
        className={`contenteditable-field ${className} ${disabled ? 'disabled' : ''} ${multiline ? 'multiline' : 'singleline'} ${isDragOver ? 'drag-over' : ''} ${isTokenDragging && acceptsTokens ? 'token-drop-target' : ''} ${isTokenDragging && !acceptsTokens ? 'token-drop-rejected' : ''}`}
        data-placeholder={placeholder}
        role="textbox"
        aria-multiline={multiline}
        aria-label={placeholder || t('Text input')}
        suppressContentEditableWarning={true}
      />
      {dropCursor && (
        <div
          className="drop-cursor"
          style={{
            left: `${dropCursor.x}px`,
            top: `${dropCursor.y}px`,
            height: `${dropCursor.height}px`,
          }}
        />
      )}
      {editIconTarget && !editingToken && !disabled && (
        <button
          className="token-edit-icon"
          style={{
            left: `${editIconTarget.x}px`,
            top: `${editIconTarget.y}px`,
          }}
          onClick={(e) => {
            e.preventDefault();
            e.stopPropagation();
            openTokenEdit(editIconTarget.element);
          }}
          onMouseDown={(e) => e.preventDefault()}
          title={t('Edit token (Ctrl+E)')}
          aria-label={t('Edit token')}
          type="button"
        >
          <FiEdit2 size={UI_DIMENSIONS.ICON_SIZE_SMALL} />
        </button>
      )}
      {editingToken && (
        <div
          className={`token-edit-popup ${editingToken.position === 'below' ? 'token-edit-popup-below' : ''}`}
          style={{
            left: `${editingToken.x}px`,
            top: `${editingToken.y}px`,
          }}
          onMouseDown={(e) => e.stopPropagation()}
        >
          <input
            ref={editInputRef}
            type="text"
            className="token-edit-input"
            defaultValue={editingToken.token}
            onKeyDown={handleEditInputKeyDown}
            aria-label={t('Edit token value')}
          />
          <div className="token-edit-actions">
            <button
              className="token-edit-save"
              onClick={saveTokenEdit}
              type="button"
            >
              {t('Save')}
            </button>
            <button
              className="token-edit-cancel"
              onClick={cancelTokenEdit}
              type="button"
            >
              {t('Cancel')}
            </button>
          </div>
        </div>
      )}
    </div>
  );
};

export default ContentEditableField;
