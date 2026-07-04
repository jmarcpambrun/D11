/**
 * tokenUtils - Utilities for converting between token strings and HTML
 * 
 * Handles conversion of token strings like "[node:author:name]" to/from
 * HTML elements with data attributes for use in contenteditable fields.
 */

import { t } from './translation';
import { sanitizeTokenHtml } from './sanitize';
import { safeJsonParse } from './validation';

/**
 * Convert token strings like "[node:author:name]" to HTML with token elements
 * 
 * @param text - Input text containing token strings
 * @returns HTML string with token spans
 * 
 * @example
 * convertTokensToHTML("Hello [user:name]!")
 * // Returns: 'Hello <span class="config-token" data-token="[user:name]" ...>name</span>!'
 */
export function convertTokensToHTML(text: string | null): string {
  if (!text || typeof text !== 'string') return text || '';

  // Regex to match tokens in the format [token:path:here]
  const tokenRegex = /\[([^[\]]+)\]/g;

  return text.replace(tokenRegex, (match, tokenString) => {
    // Create a readable label from the token string (last part of path)
    const label = tokenString.split(':').pop() || tokenString;

    return `<span class="config-token" data-token="${match}" contenteditable="false" draggable="true" title="${t('Token: @token', { '@token': match })}">${label}</span>`;
  });
}

/**
 * Convert HTML with token elements back to token strings
 * 
 * @param html - HTML string containing token spans
 * @returns Plain text with token strings
 * 
 * @example
 * convertHTMLToTokens('<span class="config-token" data-token="[user:name]">name</span>')
 * // Returns: '[user:name]'
 */
export function convertHTMLToTokens(html: string | null): string {
  if (!html || typeof html !== 'string') return html || '';

  // Create a temporary div to parse the HTML. Sanitize first to strip any
  // scripts or unsafe markup; sanitizeTokenHtml preserves the .config-token
  // spans and their data-token attribute that the loop below reads.
  const tempDiv = document.createElement('div');
  tempDiv.innerHTML = sanitizeTokenHtml(html);

  // Find all token elements and replace them with their token strings
  const tokenElements = tempDiv.querySelectorAll('.config-token');
  tokenElements.forEach(tokenEl => {
    const tokenString = tokenEl.getAttribute('data-token');
    if (tokenString) {
      tokenEl.replaceWith(document.createTextNode(tokenString));
    }
  });

  // Strip any zero-width space (U+200B). These are inserted into the DOM ONLY as
  // a caret landing spot after a trailing token (see ensureTrailingCaretSpace in
  // ContentEditableField); they must never appear in the saved value, so the
  // serialized output stays exactly the user's text + `[token]` strings.
  const serialized = tempDiv.textContent || tempDiv.innerText || '';
  return serialized.replace(/\u200B/g, '');
}

/**
 * Create a token element for insertion into contenteditable fields
 * 
 * @param label - Display label for the token
 * @param token - Token string (e.g., "[user:name]" or "user:name")
 * @returns HTMLSpanElement configured as a token
 */
export function createTokenElement(label: string, token: string): HTMLSpanElement {
  // Ensure token is wrapped in brackets
  const wrappedToken = token.startsWith('[') ? token : `[${token}]`;
  
  const tokenElement = document.createElement('span');
  tokenElement.className = 'config-token';
  tokenElement.textContent = label; // textContent escapes HTML
  tokenElement.setAttribute('data-token', wrappedToken);
  tokenElement.setAttribute('contenteditable', 'false');
  tokenElement.setAttribute('title', t('Token: @token', { '@token': wrappedToken }));
  tokenElement.setAttribute('draggable', 'true');
  
  return tokenElement;
}

/**
 * Check if an element is a token element
 */
export function isTokenElement(element: Node | null): element is Element {
  if (!element) return false;
  return (element as Element).classList?.contains('config-token') ?? false;
}

/**
 * Parse token data from drag event
 * 
 * @param dataTransfer - DataTransfer object from drag event
 * @returns Parsed token data or null if invalid
 */
export function parseTokenFromDragEvent(
  dataTransfer: DataTransfer
): { label: string; token: string } | null {
  const tokenData = dataTransfer.getData('application/token');
  if (!tokenData) return null;

  try {
    const parsed = safeJsonParse<{ label?: unknown; token?: unknown }>(tokenData);

    // Validate token data structure
    const label = typeof parsed.label === 'string' ? parsed.label : '';
    const token = typeof parsed.token === 'string' ? parsed.token : '';

    if (!label || !token) {
      console.warn('Invalid token data: missing label or token');
      return null;
    }

    return { label, token };
  } catch (error) {
    console.error('Failed to parse token data:', error);
    return null;
  }
}
