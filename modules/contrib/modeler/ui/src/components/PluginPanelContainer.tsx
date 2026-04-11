/**
 * PluginPanelContainer - Renders external plugin panels registered by
 * other Drupal modules.
 *
 * Each registered panel gets a resizable container with a collapse tab,
 * wrapped in a PanelErrorBoundary so plugin crashes cannot tear down the
 * modeler.  The panel's `render()` callback receives a plain DOM element
 * and the public API — it owns everything inside that element.
 */

import React, { useEffect, useRef, useState, useCallback } from 'react';
import { FiChevronLeft, FiChevronRight } from 'react-icons/fi';
import PanelErrorBoundary from './PanelErrorBoundary';
import { usePanelResize } from '../hooks/usePanelResize';
import { PANEL_DIMENSIONS } from '../constants/dimensions';
import { t } from '../utils/translation';
import type { RegisteredPanel, ModelerPluginApi, PanelPosition } from '../types/pluginApi';

interface PluginPanelProps {
  panel: RegisteredPanel;
  api: ModelerPluginApi;
}

/**
 * Renders a single plugin panel.  Handles mount/unmount lifecycle,
 * resize, and collapse/expand.
 */
function PluginPanel({ panel, api }: PluginPanelProps) {
  const containerRef = useRef<HTMLDivElement>(null);
  const mountedRef = useRef(false);
  const [collapsed, setCollapsed] = useState(false);
  const [panelWidth, setPanelWidth] = useState(panel.width);
  const [isResizing, setIsResizing] = useState(false);

  const { startResize } = usePanelResize({
    panelWidth,
    setPanelWidth,
    setPanelResizing: setIsResizing,
    direction: panel.position === 'left' ? 'right' : 'left',
    minWidth: PANEL_DIMENSIONS.PLUGIN_PANEL.MIN_WIDTH,
    maxWidth: PANEL_DIMENSIONS.PLUGIN_PANEL.MAX_WIDTH,
  });

  // Mount: call render() once; Unmount: call destroy()
  useEffect(() => {
    const el = containerRef.current;
    if (!el || mountedRef.current) return;

    mountedRef.current = true;
    try {
      panel.render(el, api);
    } catch (err) {
      console.error(`Plugin panel "${panel.id}" render() failed:`, err);
    }

    return () => {
      mountedRef.current = false;
      if (panel.destroy) {
        try {
          panel.destroy(el);
        } catch (err) {
          console.error(`Plugin panel "${panel.id}" destroy() failed:`, err);
        }
      }
    };
    // panel.id is the stable identity — we deliberately do not re-render
    // when the panel descriptor reference changes.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [panel.id]);

  // Notify plugin of resize events
  useEffect(() => {
    if (!isResizing && panel.onResize && containerRef.current) {
      const rect = containerRef.current.getBoundingClientRect();
      try {
        panel.onResize(rect.width, rect.height);
      } catch (err) {
        console.error(`Plugin panel "${panel.id}" onResize() failed:`, err);
      }
    }
  }, [isResizing, panelWidth, panel]);

  const toggleCollapse = useCallback(() => {
    setCollapsed((prev) => !prev);
  }, []);

  // Determine collapse icon based on position and state
  const isRight = panel.position === 'right';
  const CollapseIcon = collapsed
    ? (isRight ? FiChevronLeft : FiChevronRight)
    : (isRight ? FiChevronRight : FiChevronLeft);

  const collapsedWidth = PANEL_DIMENSIONS.PLUGIN_PANEL.COLLAPSED_WIDTH;

  return (
    <div
      className={`plugin-panel plugin-panel--${panel.position} ${collapsed ? 'collapsed' : ''} ${isResizing ? 'is-resizing' : ''}`}
      style={{ width: collapsed ? collapsedWidth : panelWidth }}
      data-plugin-panel-id={panel.id}
    >
      {/* Resize handle */}
      {!collapsed && (
        <div
          className={`plugin-panel-resize-handle plugin-panel-resize-handle--${panel.position === 'left' ? 'right' : 'left'}`}
          onMouseDown={startResize}
          role="separator"
          aria-orientation="vertical"
          aria-label={t('Resize @panel panel', { '@panel': panel.label })}
          tabIndex={0}
        />
      )}

      {/* Collapse tab */}
      <button
        className="plugin-panel-collapse-tab"
        onClick={toggleCollapse}
        aria-expanded={!collapsed}
        aria-label={collapsed
          ? t('Expand @panel panel', { '@panel': panel.label })
          : t('Collapse @panel panel', { '@panel': panel.label })
        }
        title={panel.label}
      >
        <CollapseIcon size={14} />
        {collapsed && (
          <span className="plugin-panel-collapse-label">{panel.label}</span>
        )}
      </button>

      {/* Panel content — plugin owns this element */}
      {!collapsed && (
        <div
          ref={containerRef}
          className="plugin-panel-content"
          role="region"
          aria-label={panel.label}
        />
      )}
    </div>
  );
}

// ── Container that renders all panels for a position ──────────────────

interface PluginPanelSlotProps {
  panels: RegisteredPanel[];
  api: ModelerPluginApi;
  position: PanelPosition;
}

/**
 * Renders all registered plugin panels for a given position.
 * Each panel is wrapped in a PanelErrorBoundary for isolation.
 */
const PluginPanelSlot: React.FC<PluginPanelSlotProps> = ({ panels, api, position }) => {
  if (panels.length === 0) return null;

  return (
    <div className={`plugin-panel-slot plugin-panel-slot--${position}`}>
      {panels.map((panel) => (
        <PanelErrorBoundary
          key={panel.id}
          panelName={panel.label}
          className="plugin-panel-error"
        >
          <PluginPanel panel={panel} api={api} />
        </PanelErrorBoundary>
      ))}
    </div>
  );
};

export default PluginPanelSlot;
export { PluginPanel };
