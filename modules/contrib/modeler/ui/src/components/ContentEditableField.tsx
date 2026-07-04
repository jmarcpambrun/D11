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
import { createPortal } from 'react-dom';
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
import TokenPicker from './TokenPicker';
import { useTokenSources } from './TokenSourceContext';

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

interface TokenPickerState {
  /** Position relative to the wrapper, in px. */
  x: number;
  y: number;
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

/** Zero-width space used purely as a caret landing spot after a trailing token. */
const ZERO_WIDTH_SPACE = '\u200B';

/**
 * Issue B: a `contenteditable="false"` `.config-token` pill that is the LAST
 * node in the field has no caret position AFTER it, so the user cannot click /
 * type past a trailing token. Ensure a trailing text node exists after such a
 * token by appending a single zero-width space (U+200B). The ZWSP is stripped in
 * the serialize path (convertHTMLToTokens) so it never reaches the saved value.
 *
 * Idempotent: only appends when the last child is a token element (so we never
 * accumulate multiple ZWSPs, and never touch a field that already ends in text).
 */
function ensureTrailingCaretSpace(container: HTMLElement): void {
  const last = container.lastChild;
  if (last && isTokenElement(last)) {
    container.appendChild(document.createTextNode(ZERO_WIDTH_SPACE));
  }
}

/**
 * Issue C: capture the caret as an ABSOLUTE character offset from the start of
 * the container, counting text-node characters AND each `.config-token` pill as
 * the length of its own textContent. This survives React re-renders that
 * re-create the field's text nodes (a saved node reference would dangle), so the
 * caret can be re-resolved against the LIVE DOM on restore. Returns null when
 * the selection is not inside the container.
 */
function captureAbsoluteCaretOffset(container: HTMLElement): number | null {
  const selection = window.getSelection();
  if (!selection || selection.rangeCount === 0) return null;
  const range = selection.getRangeAt(0);
  if (!container.contains(range.startContainer)) return null;

  let offset = 0;
  let done = false;
  const walk = (node: Node): void => {
    if (done) return;
    if (node === range.startContainer) {
      if (node.nodeType === Node.TEXT_NODE) {
        offset += range.startOffset;
      } else {
        // Element container: count the lengths of children before startOffset.
        for (let i = 0; i < range.startOffset && i < node.childNodes.length; i++) {
          offset += nodeTextLength(node.childNodes[i]);
        }
      }
      done = true;
      return;
    }
    if (node.nodeType === Node.TEXT_NODE) {
      offset += (node.textContent || '').length;
      return;
    }
    if (isTokenElement(node)) {
      // A token pill is opaque: count its full textContent length as one block.
      offset += nodeTextLength(node);
      return;
    }
    node.childNodes.forEach(walk);
  };
  container.childNodes.forEach(walk);
  return done ? offset : null;
}

/** Total visible character length of a node's text (ZWSPs excluded). */
function nodeTextLength(node: Node): number {
  return (node.textContent || '').replace(/\u200B/g, '').length;
}

/**
 * Issue C: re-resolve an absolute character offset (from
 * captureAbsoluteCaretOffset) to a concrete `{ node, offset }` against the LIVE
 * DOM, then return a collapsed Range there. Token pills are treated as opaque
 * blocks (the caret lands just before or just after a pill, never inside).
 * Clamps to the visible end so it never lands inside a trailing ZWSP.
 */
function resolveCaretFromAbsoluteOffset(container: HTMLElement, target: number): Range | null {
  type CaretPos = { node: Node; offset: number };
  let remaining = target;
  const found: { result: CaretPos | null; last: CaretPos | null } = { result: null, last: null };

  const walk = (node: Node): void => {
    if (found.result) return;
    if (node.nodeType === Node.TEXT_NODE) {
      const text = (node.textContent || '');
      const visibleLen = text.replace(/\u200B/g, '').length;
      // Track the furthest visible text position so we can clamp to it.
      found.last = { node, offset: Math.min(text.length, visibleLen) };
      if (remaining <= visibleLen) {
        // Map the visible offset back to a raw offset (skip leading ZWSPs).
        let raw = 0;
        let seen = 0;
        while (raw < text.length && seen < remaining) {
          if (text[raw] !== ZERO_WIDTH_SPACE) seen++;
          raw++;
        }
        found.result = { node, offset: raw };
        return;
      }
      remaining -= visibleLen;
      return;
    }
    if (isTokenElement(node)) {
      const len = nodeTextLength(node);
      if (remaining <= len) {
        // Land just AFTER the token pill (caret before it would feel wrong when
        // restoring next to a freshly-typed "[").
        found.result = { node: container, offset: indexOfChild(container, node) + 1 };
        return;
      }
      remaining -= len;
      return;
    }
    node.childNodes.forEach(walk);
  };
  container.childNodes.forEach(walk);

  const pos: CaretPos | null = found.result ?? found.last;
  if (!pos) {
    // Empty field: collapse at the start of the container.
    const range = document.createRange();
    range.selectNodeContents(container);
    range.collapse(true);
    return range;
  }
  const range = document.createRange();
  range.setStart(pos.node, pos.offset);
  range.collapse(true);
  return range;
}

/** Index of a direct child within its parent's childNodes (or -1). */
function indexOfChild(parent: Node, child: Node): number {
  return Array.prototype.indexOf.call(parent.childNodes, child);
}

const ContentEditableField: React.FC<ContentEditableFieldProps> = ({
  value,
  onChange,
  className = '',
  placeholder,
  disabled = false,
  multiline = false,
  acceptsTokens = true,
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
  // "[" token-picker popup state (null when closed).
  const [tokenPicker, setTokenPicker] = useState<TokenPickerState | null>(null);
  // Ref to the text node + offset where the triggering "[" was typed, so we can
  // remove the "[" + any partial query when a token is selected.
  const atAnchorRef = useRef<{ node: Node; offset: number } | null>(null);
  // The "[" that has already been HANDLED (opened then closed/dismissed). While
  // the caret's nearest preceding "[" matches this anchor, the picker must NOT
  // re-open — typing more characters after a dismissed "[" does nothing
  // (DECISION A: filtering lives in the picker's own search box, not field
  // text). Cleared on insert and when the "[" itself disappears, so a NEW "["
  // (different node/offset) still opens.
  const consumedBracketRef = useRef<{ node: Node; offset: number } | null>(null);
  // The field caret captured when the picker OPENS, stored as an ABSOLUTE
  // character offset from the start of the field (Issue C) — NOT a node
  // reference, which would dangle when React re-creates the field's text nodes
  // between open and close. Restored (re-resolved against the live DOM) on any
  // user dismiss (DECISION B: Escape, ×, backdrop).
  const restoreCaretRef = useRef<number | null>(null);

  // The picker reads its data from this shared context (provided by
  // PropertyPanel). We only need `onPickerOpenChange` here so PropertyPanel can
  // FREEZE its review chrome while the picker is open. It is undefined for the
  // many non-picker usages of this field (ConfigurationForm, etc.) — where the
  // reporter below is a no-op.
  const { onPickerOpenChange } = useTokenSources();

  // EVENT-DRIVEN picker-open signal (NOT an effect-cleanup signal).
  //
  // The freeze must release ONLY on a genuine picker close. Reporting `false`
  // from an effect CLEANUP is wrong: the cleanup re-runs on every remount /
  // dependency change caused by the volatile token-source context churning as
  // step data loads — which momentarily flips the freeze off and lets a LIVE
  // (unfrozen) render flash the panel. So we report edge-triggered:
  //   • `true` ONLY on the genuine open transition (closed → open), in
  //     updateTokenPickerFromCaret where the picker is actually shown.
  //   • `false` ONLY from closeTokenPicker (the single genuine-close path:
  //     insert / × / Escape / backdrop / caret moved past "[").
  // A ref dedupes repeated reports (the open path also runs on every keystroke
  // while already open). `onPickerOpenChange` is kept in a ref so the open/close
  // callbacks stay stable and don't themselves churn.
  const pickerReportedOpenRef = useRef(false);
  const onPickerOpenChangeRef = useRef(onPickerOpenChange);
  onPickerOpenChangeRef.current = onPickerOpenChange;
  const reportPickerOpen = useCallback((open: boolean) => {
    if (pickerReportedOpenRef.current === open) return;
    pickerReportedOpenRef.current = open;
    onPickerOpenChangeRef.current?.(open);
  }, []);

  // If the field genuinely UNMOUNTS while the picker was reported open (e.g. the
  // user navigates to another node so the field is destroyed), the picker is
  // truly gone — report closed once on real unmount only. This cleanup runs on
  // unmount; it does NOT re-run on data churn because its dep array is empty.
  useEffect(() => {
    return () => {
      if (pickerReportedOpenRef.current) {
        pickerReportedOpenRef.current = false;
        onPickerOpenChangeRef.current?.(false);
      }
    };
  }, []);

  // Sync external value to internal state when not editing
  useEffect(() => {
    if (divRef.current && !isEditing) {
      const incomingValue = value || '';
      // Only convert bracket syntax to token pills when the field accepts tokens
      const htmlContent = acceptsTokens ? convertTokensToHTML(incomingValue) : incomingValue;
      const currentContent = divRef.current.innerHTML || '';

      if (currentContent !== htmlContent) {
        divRef.current.innerHTML = htmlContent;
        // Issue B: if the rendered value ends in a token pill, append a caret
        // landing spot so the user can place the cursor / type after it.
        if (acceptsTokens) ensureTrailingCaretSpace(divRef.current);
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

  // Close the "[" token picker and clear the anchor. This is the SINGLE genuine
  // close path (insert / × / Escape / backdrop / caret moved past "[") — the
  // only place that reports the freeze release.
  //
  // `restoreFocus` (DECISION B): on a USER dismiss (Escape / × / backdrop) we
  // return focus + caret to the field. The internal auto-close paths (caret
  // moved away, field disabled, etc.) pass `false` so they never fight the
  // user's cursor.
  //
  // The dismissed "[" is recorded as CONSUMED (DECISION A / Caveat 3) so that
  // typing more characters after it — or the caret-restore landing right next
  // to it — does not re-open the picker.
  const closeTokenPicker = useCallback((restoreFocus = false) => {
    reportPickerOpen(false);
    setTokenPicker(null);
    // Mark the just-closed "[" as consumed so it cannot re-trigger.
    if (atAnchorRef.current) {
      consumedBracketRef.current = atAnchorRef.current;
    }
    atAnchorRef.current = null;

    if (restoreFocus) {
      const container = divRef.current;
      const absoluteOffset = restoreCaretRef.current;
      if (container) {
        container.focus();
        // Restore the caret to where it was when the picker opened by
        // RE-RESOLVING the saved absolute offset against the LIVE DOM (Issue C):
        // text nodes may have been re-created since open, so a node reference
        // would dangle and silently no-op (leaving the caret at field start).
        // Range APIs may be limited under jsdom — guard so unit tests never
        // throw.
        try {
          const selection = window.getSelection();
          if (selection && absoluteOffset !== null) {
            const range = resolveCaretFromAbsoluteOffset(container, absoluteOffset);
            if (range) {
              selection.removeAllRanges();
              selection.addRange(range);
            }
          }
        } catch {
          // jsdom / unsupported range API — focus alone is sufficient.
        }
      }
    }
    restoreCaretRef.current = null;
  }, [reportPickerOpen]);

  /**
   * Inspect the caret to decide whether the "[" token picker should OPEN. Walks
   * back from the caret within the current text node to the most recent "[" —
   * which may appear anywhere in the string, including mid-word. The picker
   * OPENS once for a freshly-typed "[" trigger; thereafter filtering happens in
   * the picker's own search box (DECISION A), NOT in the field. So once the
   * picker is open — or has been dismissed — typing more characters after that
   * SAME "[" must NOT re-open or re-filter it (the bracket is "consumed").
   * Closes the picker if no active "[" trigger is found (e.g. the user
   * backspaced past it, or typed whitespace immediately after it).
   */
  const updateTokenPickerFromCaret = useCallback(() => {
    const container = divRef.current;
    const wrapper = wrapperRef.current;
    if (!container || !wrapper || disabled || !acceptsTokens) {
      if (tokenPicker) closeTokenPicker();
      return;
    }

    const selection = window.getSelection();
    if (!selection || selection.rangeCount === 0 || !selection.isCollapsed) {
      if (tokenPicker) closeTokenPicker();
      return;
    }

    const range = selection.getRangeAt(0);
    const node = range.startContainer;
    // Only operate inside a text node within this field.
    if (node.nodeType !== Node.TEXT_NODE || !container.contains(node)) {
      if (tokenPicker) closeTokenPicker();
      return;
    }

    const text = node.textContent || '';
    const caret = range.startOffset;
    // Find the last "[" before the caret in this text node. The trigger may
    // appear anywhere in the string (including mid-word) — no preceding
    // whitespace or start-of-node is required.
    const bracketIndex = text.lastIndexOf('[', caret - 1);
    if (bracketIndex === -1) {
      // No "[" before the caret: nothing can be consumed here anymore, so clear
      // the consumed marker (a later "[" must be free to open) and close.
      consumedBracketRef.current = null;
      if (tokenPicker) closeTokenPicker();
      return;
    }

    // Identify this bracket. If it matches the consumed anchor, the picker was
    // already opened+dismissed for it — do NOT re-open and do NOT close (a close
    // here would just churn the already-closed picker).
    const consumed = consumedBracketRef.current;
    const isConsumed = !!consumed && consumed.node === node && consumed.offset === bracketIndex;
    if (isConsumed) {
      return;
    }

    const query = text.slice(bracketIndex + 1, caret);
    // A whitespace immediately inside the query ends the token trigger.
    if (/\s/.test(query)) {
      if (tokenPicker) closeTokenPicker();
      return;
    }

    // The picker is already open for this (un-consumed) bracket — keep it open;
    // further typing in the field neither re-opens nor re-filters it.
    if (tokenPicker) {
      atAnchorRef.current = { node, offset: bracketIndex };
      return;
    }

    // Fresh "[" trigger: open the picker. Remember where the "[" lives so we can
    // remove it on insert, and remember the caret as an ABSOLUTE offset (Issue
    // C) so we can restore it on a user dismiss (DECISION B) even after a
    // re-render re-creates the field's text nodes.
    atAnchorRef.current = { node, offset: bracketIndex };
    restoreCaretRef.current = captureAbsoluteCaretOffset(container);

    // Anchor the popup just below the caret in VIEWPORT coordinates. The picker
    // is rendered through a portal to document.body (a modal dialog), so it is
    // positioned with `position: fixed` using these viewport-relative values —
    // independent of the field/panel layout that may re-render beneath it.
    // Range.getBoundingClientRect may be unavailable (jsdom) — guard so the
    // picker still opens; positioning just falls back to the viewport origin.
    let x = 0;
    let y = 0;
    if (typeof range.getBoundingClientRect === 'function') {
      const caretRect = range.getBoundingClientRect();
      x = caretRect.left;
      y = caretRect.bottom;
    }

    // Report the genuine OPEN transition (deduped inside reportPickerOpen).
    reportPickerOpen(true);
    setTokenPicker({ x: Math.max(0, x), y: Math.max(0, y) });
  }, [disabled, acceptsTokens, tokenPicker, closeTokenPicker, reportPickerOpen]);

  const handleInput = useCallback(() => {
    if (divRef.current) {
      const htmlContent = divRef.current.innerHTML || '';
      const hasTokens = htmlContent.includes('config-token');
      const newValue = hasTokens ? htmlContent : (divRef.current.textContent || '');
      setLocalValue(newValue);
      debouncedOnChange(newValue);
    }
    // After the DOM updates, re-evaluate whether the "[" picker should show.
    updateTokenPickerFromCaret();
  }, [debouncedOnChange, updateTokenPickerFromCaret]);

  /**
   * Insert a token chosen from the "[" picker. Removes the triggering "[" and
   * any partial query the user typed, then inserts the token pill at that spot
   * using the SAME createTokenElement path as the drop handler.
   */
  const handleTokenPickerSelect = useCallback((label: string, token: string) => {
    const container = divRef.current;
    const anchor = atAnchorRef.current;
    if (!container) {
      closeTokenPicker();
      return;
    }

    const tokenElement = createTokenElement(label, token);

    const selection = window.getSelection();
    const liveRange =
      selection && selection.rangeCount > 0 ? selection.getRangeAt(0) : null;

    // BUG 1 fix: removing the triggering "[" must NOT depend on the live caret
    // remaining inside the field. When the user clicks "Use" in the portaled
    // picker dialog, focus/selection moves INTO that dialog, and a prior React
    // re-render may have re-created (detached) the recorded anchor text node.
    // So we try, in order: (1) the recorded anchor when still live with a real
    // "[" at its offset; (2) re-locate from the live caret IF it is still in the
    // field; (3) scan the field's OWN DOM for the last "[" (the open picker is
    // tied to exactly one trigger bracket, so the last "[" in the field IS the
    // trigger); (4) only if no "[" exists anywhere, fall back to the caret/end.
    // In every located case the new pill is inserted WHERE the "[" was, never at
    // the field end.
    let insertRange: Range | null = null;

    // Path 1: the recorded anchor is still live AND a "[" sits at that offset.
    if (anchor && container.contains(anchor.node)) {
      const anchorText = anchor.node.textContent || '';
      if (anchorText.charAt(anchor.offset) === '[') {
        const caretOffset =
          liveRange && liveRange.startContainer === anchor.node
            ? liveRange.startOffset
            : anchorText.length;
        insertRange = document.createRange();
        insertRange.setStart(anchor.node, Math.min(anchor.offset, anchorText.length));
        insertRange.setEnd(
          anchor.node,
          Math.max(anchor.offset, Math.min(caretOffset, anchorText.length)),
        );
        insertRange.deleteContents();
      }
    }

    // Path 2: anchor is stale/detached — re-locate the "[" from the live caret,
    // but ONLY when that caret is genuinely still inside the field.
    if (!insertRange && liveRange) {
      const caretNode = liveRange.startContainer;
      if (
        caretNode.nodeType === Node.TEXT_NODE &&
        container.contains(caretNode)
      ) {
        const text = caretNode.textContent || '';
        const caret = liveRange.startOffset;
        const bracketIndex = text.lastIndexOf('[', caret - 1);
        if (bracketIndex !== -1) {
          insertRange = document.createRange();
          insertRange.setStart(caretNode, bracketIndex);
          insertRange.setEnd(caretNode, caret);
          insertRange.deleteContents();
        }
      }
    }

    // Path 2.5 (the real fix): the live caret is NOT in the field (focus is in
    // the picker dialog) and the anchor is gone. Scan the field's own text nodes
    // for the LAST "[" anywhere and delete just that bracket, then insert the
    // pill exactly there. Token pills are opaque (their visible text never
    // contains "["), so a plain SHOW_TEXT walk is sufficient and safe.
    if (!insertRange) {
      const walker = document.createTreeWalker(container, NodeFilter.SHOW_TEXT);
      let lastBracketNode: Text | null = null;
      let lastBracketIndex = -1;
      let current = walker.nextNode();
      while (current) {
        const idx = (current.textContent || '').lastIndexOf('[');
        if (idx !== -1) {
          lastBracketNode = current as Text;
          lastBracketIndex = idx;
        }
        current = walker.nextNode();
      }
      if (lastBracketNode && lastBracketIndex !== -1) {
        // Delete the single trigger "[" (the picker owns query filtering, so the
        // field text holds only the bare bracket), leaving a collapsed range at
        // that spot so the new pill lands WHERE the "[" was.
        insertRange = document.createRange();
        insertRange.setStart(lastBracketNode, lastBracketIndex);
        insertRange.setEnd(lastBracketNode, lastBracketIndex + 1);
        insertRange.deleteContents();
        insertRange.collapse(true);
      }
    }

    // Path 3: no "[" exists anywhere in the field — insert at the caret/end.
    if (!insertRange) {
      insertRange = getFallbackInsertPosition(container);
    }

    insertRange.insertNode(tokenElement);
    // Issue B: ensure there is a caret landing spot AFTER the (possibly
    // trailing) inserted token, then place the caret there so the user can type
    // immediately after the pill.
    ensureTrailingCaretSpace(container);
    const afterToken = tokenElement.nextSibling;
    if (afterToken && afterToken.nodeType === Node.TEXT_NODE) {
      insertRange.setStart(afterToken, Math.min(1, (afterToken.textContent || '').length));
      insertRange.collapse(true);
    } else {
      // A following non-text node exists (e.g. more content) → caret after pill.
      insertRange.setStartAfter(tokenElement);
      insertRange.collapse(true);
    }
    if (selection) {
      selection.removeAllRanges();
      selection.addRange(insertRange);
    }

    // Close WITHOUT restoring the old caret (we just placed it after the new
    // pill). A successful insert removes the triggering "[" entirely, so clear
    // the consumed marker AFTER closing (close records the anchor as consumed) —
    // a later "[" at a new position must then open freely.
    closeTokenPicker();
    consumedBracketRef.current = null;

    // Persist using the same serialize path as drag-and-drop.
    const htmlContent = container.innerHTML || '';
    setLocalValue(htmlContent);
    debouncedOnChange(htmlContent);
  }, [closeTokenPicker, debouncedOnChange]);

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
      // Issue B: a paste that ends in a token pill needs a trailing caret spot.
      ensureTrailingCaretSpace(divRef.current);
      const htmlContent = divRef.current.innerHTML || '';
      setLocalValue(htmlContent);
      debouncedOnChange(htmlContent);
    }
  }, [debouncedOnChange]);

  const handleFocus = useCallback(() => {
    setIsEditing(true);
  }, []);

  const handleBlur = useCallback(() => {
    // The "[" token picker is a MODAL dialog rendered through a portal; it owns
    // its own lifecycle (close on token insert / × / Escape / backdrop click).
    // Field focus is therefore IRRELEVANT to the picker — blur must NOT touch
    // it. This handler only persists the field value on blur.
    setIsEditing(false);

    // Cancel any pending debounce — we save synchronously below.
    if (debounceTimeoutRef.current) {
      clearTimeout(debounceTimeoutRef.current);
      debounceTimeoutRef.current = null;
    }
    pendingValueRef.current = null;

    // Always persist the current DOM content on blur so that values typed
    // just before a Tab / click-away are never lost.  handleInput already
    // updates localValue on every keystroke, so comparing would skip onChange.
    if (divRef.current) {
      const htmlContent = divRef.current.innerHTML || '';
      setLocalValue(htmlContent);
      const tokenValue = convertHTMLToTokens(htmlContent);
      onChange(tokenValue);
    }
  }, [onChange]);

  const handleKeyDown = useCallback((e: React.KeyboardEvent) => {
    // While the "[" token picker is open, let it handle navigation/selection
    // keys (the picker listens at the document level). Swallow them here so the
    // field's own handlers (e.g. single-line Enter → blur) don't interfere.
    if (tokenPicker) {
      if (e.key === 'Enter' || e.key === 'Escape' ||
          e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'ArrowLeft') {
        e.preventDefault();
        return;
      }
    }

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
          const startNode = range.startContainer;

          if (startNode.nodeType === Node.TEXT_NODE) {
            // BUG 2 fix: when the caret is inside a TEXT node, only treat
            // Backspace as a token deletion if there is NOTHING visible between
            // the caret and a preceding token — i.e. the substring before the
            // caret is empty or consists solely of zero-width spaces (the Issue
            // B caret-landing spot). If ANY real character precedes the caret
            // (e.g. text node "\u200Babc" with the caret after "c"), fall
            // through to the browser's native Backspace so it deletes that
            // character, NOT the token to its left.
            const beforeCaret = (startNode.textContent || '').slice(0, range.startOffset);
            if (
              /^\u200B*$/.test(beforeCaret) &&
              isTokenElement(startNode.previousSibling)
            ) {
              tokenToDelete = startNode.previousSibling as Element;
              // Remove the now-orphaned ZWSP spacer (if any) so nothing lingers.
              if (beforeCaret.length > 0) {
                (startNode as Text).remove();
              }
            }
          } else {
            // Caret sits between elements in the container: a directly-preceding
            // token (childNodes[startOffset - 1]) is genuinely at the boundary.
            const prevNode = startNode.childNodes[range.startOffset - 1];
            if (isTokenElement(prevNode)) {
              tokenToDelete = prevNode;
            }
          }
        }

        if (tokenToDelete) {
          e.preventDefault();
          tokenToDelete.remove();

          // Issue B: keep a trailing caret spot if a token is STILL last.
          if (divRef.current) ensureTrailingCaretSpace(divRef.current);

          const htmlContent = divRef.current?.innerHTML || '';
          setLocalValue(htmlContent);
          debouncedOnChange(htmlContent);
          return;
        }
      }
    }
  }, [multiline, debouncedOnChange, openTokenEdit, tokenPicker]);

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

    // Issue B: a drop that leaves the token as the LAST node needs a trailing
    // caret spot after it.
    ensureTrailingCaretSpace(divRef.current);

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
        className={`contenteditable-field ${className} ${disabled ? 'disabled' : ''} ${multiline ? 'multiline' : 'singleline'} ${isDragOver ? 'drag-over' : ''}`}
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
      {tokenPicker && acceptsTokens && !disabled && typeof document !== 'undefined' &&
        createPortal(
          // MODAL: a transparent backdrop blocks (and dismisses on) interaction
          // with everything behind the picker, and the picker itself is rendered
          // OUTSIDE the field/panel subtree so the background re-rendering /
          // unmounting can never unmount the picker. The open STATE still lives
          // in this component (`tokenPicker`); only the rendered DOM is portaled.
          //
          // The portal target is the `.modeler` ROOT (not document.body): it is
          // the scope where the `--modeler-*` theme variables and the
          // `.dark-mode` class are defined, so the picker is fully themed in both
          // light and dark — while still escaping the field's subtree. A high
          // z-index (>9999) lifts it above the app root and the popup/dialog
          // overlays. Falls back to document.body in isolated/test contexts.
          <>
            <div
              className="token-picker-backdrop"
              // A mousedown anywhere on the backdrop = click-outside → close,
              // restoring focus + caret to the field (DECISION B).
              onMouseDown={() => closeTokenPicker(true)}
            />
            <TokenPicker
              position={{ x: tokenPicker.x, y: tokenPicker.y }}
              onSelect={handleTokenPickerSelect}
              // Escape / × route here → user dismiss, restore field caret.
              onClose={() => closeTokenPicker(true)}
            />
          </>,
          document.querySelector('.modeler') ?? document.body,
        )}
    </div>
  );
};

export default ContentEditableField;
