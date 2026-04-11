/**
 * Dimension constants for the workflow modeler
 * All hardcoded dimensions, offsets, and numeric values should be defined here
 */

// ============ Panel Dimensions ============
export const PANEL_DIMENSIONS = {
  // Property Panel
  PROPERTY_PANEL: {
    DEFAULT_WIDTH: 320,
    MIN_WIDTH: 200,
    MAX_WIDTH: 600,
    COLLAPSED_WIDTH: 40,
  },
  
  // Replay Panel
  REPLAY_PANEL: {
    DEFAULT_WIDTH: 350,
    MIN_WIDTH: 300,
    MAX_WIDTH: 600,
    COLLAPSED_WIDTH: 40,
  },

  // Plugin Panel (external panels registered by other Drupal modules)
  PLUGIN_PANEL: {
    DEFAULT_WIDTH: 320,
    MIN_WIDTH: 200,
    MAX_WIDTH: 600,
    COLLAPSED_WIDTH: 40,
  },
} as const;

// ============ Node Dimensions ============
// All node types now share the same uniform card dimensions.
export const NODE_DIMENSIONS = {
  /** Fixed card width for all node types */
  CARD_WIDTH: 180,
  /** Fixed card height for all node types */
  CARD_HEIGHT: 120,
  /** @deprecated Use CARD_WIDTH */
  DEFAULT_WIDTH: 180,
  /** @deprecated Use CARD_HEIGHT */
  DEFAULT_HEIGHT: 120,
  START_NODE_WIDTH: 180,
  START_NODE_HEIGHT: 120,
  ELEMENT_NODE_WIDTH: 180,
  ELEMENT_NODE_HEIGHT: 120,
} as const;

// ============ Edge Styling ============
export const EDGE_STYLING = {
  ARROW_WIDTH: 12,
  ARROW_HEIGHT: 12,
  STROKE_WIDTH: 2,
  STROKE_WIDTH_HIGHLIGHTED: 3,
  STROKE_WIDTH_TRANSITION: 6,
  CONTROL_OFFSET: 40, // Offset for smoother curves
  BORDER_RADIUS: 20, // Rounded corners for step edges
} as const;

// ============ Layout & Spacing ============
export const LAYOUT = {
  GRID_SIZE: 20,
  NODE_SPACING_X: 250,
  NODE_SPACING_Y: 84, // Compact gap between rows (no condition card)
  CONDITION_EXTRA_SPACING: 90, // Extra vertical space added when a condition card is present on an edge
  AUTO_LAYOUT_PADDING: 50,
  PASTE_OFFSET: 100, // Offset when pasting nodes
  DEFAULT_PASTE_OFFSET: 50,
  DEFAULT_POSITION_X: 100, // Default X position for new nodes
  DEFAULT_POSITION_Y: 100, // Default Y position for new nodes
  LAYOUT_START_X: 400, // Starting X position for layout algorithm
  LAYOUT_START_Y: 100, // Starting Y position for layout algorithm
} as const;

// ============ Viewport & Zoom ============
export const VIEWPORT = {
  DEFAULT_ZOOM: 1,
  MIN_ZOOM: 0.1,
  MAX_ZOOM: 4,
  AUTO_CENTER_ZOOM: 1.2,
  FIT_VIEW_ZOOM: 1.5,
  FIT_VIEW_PADDING: 0.1, // 10% padding
  AUTO_LAYOUT_PADDING: 0.2, // 20% padding
  TOP_ALIGN_OFFSET: 150, // Pixels from top for event nodes
  PAN_ANIMATION_DURATION: 800, // Milliseconds
} as const;

// ============ Animation & Timing ============
export const TIMING = {
  DEBOUNCE_DELAY: 300,
  SEARCH_DEBOUNCE: 200,
  COPY_FEEDBACK_DURATION: 2000,
  TOOLTIP_DELAY: 500,
  RESIZE_DEBOUNCE: 10,
  SYNC_DELAY: 100,
  REPLAY_SYNC_DELAY: 150,
  CLEANUP_DELAY: 200,
  VIEWPORT_EFFECT_DELAY: 0,
  NODE_SELECTION_DELAY: 100,
  VIEWPORT_READY_DELAY: 200,
  VIEWPORT_PAN_DURATION: 800,
} as const;

// ============ Interaction Thresholds ============
export const THRESHOLDS = {
  MIN_DRAG_DISTANCE: 5,
  DOUBLE_CLICK_TIME: 300,
  LONG_PRESS_TIME: 500,
  SELECTION_TOLERANCE: 10,
  /** Minimum number of components required to show the search field in quick-add popups */
  SEARCH_VISIBILITY_MIN_COMPONENTS: 15,
} as const;

// ============ UI Elements ============
export const UI_DIMENSIONS = {
  ICON_SIZE: 16,
  ICON_SIZE_SMALL: 12,
  ICON_SIZE_LARGE: 24,
  BUTTON_HEIGHT: 32,
  INPUT_HEIGHT: 36,
  BORDER_RADIUS: 4,
  BORDER_RADIUS_LARGE: 8,
  PADDING_SMALL: 4,
  PADDING_MEDIUM: 8,
  PADDING_LARGE: 16,
  MARGIN_SMALL: 4,
  MARGIN_MEDIUM: 8,
  MARGIN_LARGE: 16,
} as const;

// ============ Error Recovery ============
export const ERROR_RECOVERY = {
  /** Maximum number of automatic retries before giving up */
  MAX_AUTO_RETRIES: 2,
  /** Delay in ms before first auto-retry (doubles each subsequent retry) */
  AUTO_RETRY_BASE_DELAY: 1000,
  /** Maximum number of errors to keep in the error log */
  MAX_ERROR_LOG_SIZE: 50,
  /** Time window in ms for deduplicating identical errors */
  DEDUP_WINDOW: 5000,
  /** Maximum number of manual retries shown in UI */
  MAX_MANUAL_RETRIES: 5,
} as const;

// ============ Storage Keys ============
export const STORAGE_KEYS = {
  PROPERTY_PANEL_WIDTH: 'propertyPanelWidth',
  REPLAY_PANEL_WIDTH: 'replayPanelWidth',
  REPLAY_PANEL_COLLAPSED: 'replayPanelCollapsed',
  PROPERTY_PANEL_COLLAPSED: 'propertyPanelCollapsed',
  REPLAY_SECTION_RATIOS: 'replaySectionRatios',
  THEME: 'modelerTheme',
} as const;