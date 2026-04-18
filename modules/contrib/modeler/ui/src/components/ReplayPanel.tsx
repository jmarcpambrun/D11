import React, { Profiler, useState, useRef, useCallback, useEffect, useMemo } from 'react';
import { FiPlay, FiPause, FiSquare, FiSkipBack, FiSkipForward, FiActivity, FiDatabase, FiFileText, FiChevronLeft, FiChevronRight, FiCopy, FiZap, FiInfo, FiChevronDown, FiClock, FiUser, FiGlobe, FiLink, FiRefreshCw, FiXCircle } from 'react-icons/fi';
import { usePanelStore } from '../store/usePanelStore';
import type { StoreNode as Node, StoreEdge as Edge } from '../types/settings';
import { PANEL_DIMENSIONS, TIMING, STORAGE_KEYS } from '../constants/dimensions';
import { t } from '../utils/translation';
import { useReplayStepFilter } from '../hooks/useReplayStepFilter';
import { useReplayPlayback } from '../hooks/useReplayPlayback';
import { usePanelResize } from '../hooks/usePanelResize';
import { useVerticalPanelResize } from '../hooks/useVerticalPanelResize';
import { StepDataContainer, GlobalTokensContainer, TemplateTokensContainer } from './ReplayDataRenderer';
import InfoPopup from './InfoPopup';
import type { InfoItem } from './InfoPopup';
import { getStepIcon, getStepLabel, ReplayStep } from '../utils/replayStepUtils';
import type { ReplayEntry } from '../hooks/useReplayLoader';
import type { GlobalToken } from '../types/settings';
import { onRenderCallback } from '../utils/profiling';

/** Format a timestamp string into a localized date/time */
function formatTimestamp(ts: string | number | undefined): string {
  if (!ts) return '';
  try {
    // Handle Unix timestamps (seconds)
    const date = typeof ts === 'number' ? new Date(ts * 1000) : new Date(ts);
    if (isNaN(date.getTime())) return String(ts);
    return date.toLocaleString(undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
    });
  } catch {
    return String(ts);
  }
}

/** Extract user display string from entry.user */
function formatUser(user: ReplayEntry['user']): string {
  if (typeof user === 'string') return user;
  if (user && typeof user === 'object') {
    const name = user.name || '';
    const uid = user.uid !== undefined ? ` (#${String(user.uid)})` : '';
    return `${name}${uid}` || String(user);
  }
  return String(user ?? '');
}

/** Extract a display string from a replay step exception. */
function formatException(exc: StepInfo['exception']): string {
  if (!exc) return '';
  if (typeof exc === 'object') {
    const parts: string[] = [];
    if ('class' in exc && typeof exc.class === 'string') parts.push(exc.class);
    if ('message' in exc && typeof exc.message === 'string') parts.push(exc.message);
    if ('file' in exc && typeof exc.file === 'string') parts.push(`at ${exc.file}`);
    if (parts.length > 0) return parts.join(': ');
  }
  return String(exc);
}

interface StepInfo {
  type: string;
  id?: string;
  successorId?: string;
  successorType?: number;
  conditionId?: string;
  object?: unknown;
  exception?: {
    class?: string;
    code?: number;
    message?: string;
    file?: string;
    trace?: string;
  } | Record<string, unknown>;
}

interface ReplayPanelProps {
  replayData?: ReplayStep[] | null;
  isReplayMode: boolean;
  onToggleReplay: () => void;
  onSelectStep: (step: number) => void;
  currentStep?: number;
  stepData?: Record<string, any> | null;
  stepInfo?: StepInfo | null;
  edges?: Edge[];
  nodes?: Node[];
  isVisible?: boolean;
  onClose?: () => void;
  /** Loaded replay entries from backend (multiple executions) */
  replayEntries?: ReplayEntry[];
  /** Index of the currently selected replay entry */
  selectedEntryIndex?: number;
  /** Callback when user selects a different replay entry */
  onSelectReplayEntry?: (index: number) => void;
  /** ID of the currently selected (or auto-detected) event node */
  selectedStartNodeId?: string | null;
  /** Whether a replay_url endpoint is available */
  hasReplayUrl?: boolean;
  /** Whether a test_url endpoint is available */
  hasTestUrl?: boolean;
  /** Whether a test is currently running (polling for results) */
  isTestRunning?: boolean;
  /** Whether the initial test request is in flight */
  isTestInitiating?: boolean;
  /** Error message if the test failed */
  testError?: string | null;
  /** Start a test for the given event component ID */
  onStartTest?: (componentId: string) => void;
  /** Cancel the running test */
  onCancelTest?: () => void;
  /** Global tokens from drupalSettings.modeler_api.global_tokens */
  globalTokens?: Record<string, GlobalToken>;
  /** Template tokens from drupalSettings.modeler_api.template_tokens */
  templateTokens?: Record<string, GlobalToken>;
  /** Whether the current model is a template */
  isTemplate?: boolean;
}

const ReplayPanel: React.FC<ReplayPanelProps> = ({
  replayData,
  isReplayMode: _isReplayMode,
  onToggleReplay,
  onSelectStep,
  currentStep = -1,
  stepData,
  stepInfo,
  edges = [],
  nodes = [],
  isVisible = false,
  onClose: _onClose,
  replayEntries = [],
  selectedEntryIndex = -1,
  onSelectReplayEntry,
  selectedStartNodeId,
  hasReplayUrl = false,
  hasTestUrl = false,
  isTestRunning = false,
  isTestInitiating = false,
  testError: _testError,
  onStartTest,
  onCancelTest,
  globalTokens,
  templateTokens,
  isTemplate = false,
}) => {
  const [showInfoPopup, setShowInfoPopup] = useState(false);
  const [copiedPath, setCopiedPath] = useState<string | null>(null);
  const [entryDropdownOpen, setEntryDropdownOpen] = useState(false);
  const entryDropdownRef = useRef<HTMLDivElement>(null);
  const stepsContainerRef = useRef<HTMLDivElement>(null);
  const stepRefs = useRef<Record<number, HTMLDivElement | null>>({});

  // Get replay panel width and resizing state from store
  const replayPanelWidth = usePanelStore((state) => state.replayPanelWidth);
  const replayPanelIsResizing = usePanelStore((state) => state.replayPanelIsResizing);
  const setReplayPanelWidth = usePanelStore((state) => state.setReplayPanelWidth);
  const setReplayPanelResizing = usePanelStore((state) => state.setReplayPanelResizing);
  const replayPanelCollapsed = usePanelStore((state) => state.replayPanelCollapsed);
  const toggleReplayPanelCollapse = usePanelStore((state) => state.toggleReplayPanelCollapse);

  // Resize handler using the extracted hook
  const { startResize } = usePanelResize({
    panelWidth: replayPanelWidth,
    setPanelWidth: setReplayPanelWidth,
    setPanelResizing: setReplayPanelResizing,
    direction: 'left', // Middle panel: dragging left increases width
  });

  // Close the entry dropdown on outside click
  useEffect(() => {
    if (!entryDropdownOpen) return;
    const handleClickOutside = (e: MouseEvent) => {
      if (entryDropdownRef.current && !entryDropdownRef.current.contains(e.target as HTMLElement)) {
        setEntryDropdownOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [entryDropdownOpen]);

  const handleEntrySelect = useCallback((index: number) => {
    onSelectReplayEntry?.(index);
    setEntryDropdownOpen(false);
  }, [onSelectReplayEntry]);

  // Handle click on collapsed panel to expand
  const handlePanelClick = useCallback((e: React.MouseEvent) => {
    if (replayPanelCollapsed) {
      e.stopPropagation();
      toggleReplayPanelCollapse();
    }
  }, [replayPanelCollapsed, toggleReplayPanelCollapse]);

  // Use the extracted step filter hook
  const { filteredReplayData, getFilteredIndex, getOriginalIndex } = useReplayStepFilter({
    replayData,
    nodes,
    edges,
  });

  const filteredCurrentStep = getFilteredIndex(currentStep);

  // Use the extracted playback control hook
  const {
    isPlaying,
    playbackSpeed,
    setPlaybackSpeed,
    handlePlay,
    handleStop,
    handlePrevious,
    handleNext,
    handleStepClick,
  } = useReplayPlayback({
    totalSteps: filteredReplayData.length,
    filteredCurrentStep,
    onSelectStep,
    onToggleReplay,
    getOriginalIndex,
    stepRefs,
    stepsContainerRef,
  });

  // Build info items from stepInfo for the popup
  const exc = stepInfo?.exception;
  const excTrace = exc && typeof exc === 'object' && 'trace' in exc && typeof exc.trace === 'string' ? exc.trace : '';
  const infoItems: InfoItem[] = stepInfo ? [
    { label: t('Type'), value: stepInfo.type, show: true },
    { label: t('Component ID'), value: stepInfo.id || '', show: !!stepInfo.id },
    { label: t('Successor ID'), value: stepInfo.successorId || '', show: !!stepInfo.successorId },
    { label: t('Condition ID'), value: stepInfo.conditionId || '', show: !!stepInfo.conditionId },
    { label: t('Error'), value: formatException(stepInfo.exception), show: !!stepInfo.exception, isError: true },
    { label: t('Stack Trace'), value: excTrace, show: !!excTrace, isError: true },
  ] : [];

  const copyToClipboard = (text: unknown, path: string) => {
    navigator.clipboard.writeText(JSON.stringify(text, null, 2));
    setCopiedPath(path);
    setTimeout(() => setCopiedPath(null), TIMING.COPY_FEEDBACK_DURATION);
  };

  const hasReplaySteps = filteredReplayData && filteredReplayData.length > 0;
  const showTestButton = selectedStartNodeId && hasTestUrl && !isTestRunning && !isTestInitiating;
  const hasGlobalTokens = globalTokens && Object.keys(globalTokens).length > 0;
  const hasTemplateTokens = isTemplate && templateTokens && Object.keys(templateTokens).length > 0;

  // Count how many resizable sections are visible so we can distribute
  // the available vertical space among them via draggable separators.
  const isActiveReplay = hasReplaySteps && !isTestRunning && !isTestInitiating;
  const resizableSectionCount = useMemo(() => {
    if (!isVisible) return 0;
    if (isActiveReplay) {
      // control + step-data + optional global + optional template
      return 2 + (hasGlobalTokens ? 1 : 0) + (hasTemplateTokens ? 1 : 0);
    }
    // Empty state: only global + template token sections (if any)
    return (hasGlobalTokens ? 1 : 0) + (hasTemplateTokens ? 1 : 0);
  }, [isVisible, isActiveReplay, hasGlobalTokens, hasTemplateTokens]);

  const {
    sectionRatios,
    isResizing: isVerticalResizing,
    startSeparatorDrag,
    containerRef: resizableContainerRef,
  } = useVerticalPanelResize({
    sectionCount: resizableSectionCount,
    storageKey: STORAGE_KEYS.REPLAY_SECTION_RATIOS,
  });

  if (!isVisible) {
    return null;
  }

  if (!hasReplaySteps && !isTestRunning && !isTestInitiating) {
    let emptySectionIdx = 0;
    return (
      <Profiler id="ReplayPanel" onRender={onRenderCallback}>
      <div
        className={`replay-panel ${replayPanelIsResizing ? 'resizing' : ''} ${replayPanelCollapsed ? 'collapsed' : ''} ${isVerticalResizing ? 'vertical-resizing' : ''}`}
        style={{ width: replayPanelCollapsed ? `${PANEL_DIMENSIONS.REPLAY_PANEL.COLLAPSED_WIDTH}px` : `${replayPanelWidth}px` }}
        onClick={handlePanelClick}
        title={replayPanelCollapsed ? t('Click to expand') : undefined}
      >
        <div
          className="resize-handle"
          onMouseDown={startResize}
          title={t('Drag to resize')}
        />
        <button
          className="panel-collapse-widget"
          onClick={toggleReplayPanelCollapse}
          title={replayPanelCollapsed ? t('Expand panel') : t('Collapse panel')}
          aria-label={replayPanelCollapsed ? t('Expand panel') : t('Collapse panel')}
        >
          <span className="collapse-icon">
            {replayPanelCollapsed ? <FiChevronLeft /> : <FiChevronRight />}
          </span>
        </button>
        {replayPanelCollapsed && (
          <div className="panel-collapsed-label">
            <span>{t('Replay')}</span>
          </div>
        )}
        <div className="panel-content">
          <div className="replay-panel-header">
            <h3>
              <FiActivity />
              {t('Execution Replay')}
            </h3>
            {showTestButton && (
              <button
                className="header-test-btn"
                onClick={() => onStartTest?.(selectedStartNodeId)}
                title={t('Test this event')}
                aria-label={t('Test this event')}
              >
                <FiPlay /> {t('Test')}
              </button>
            )}
          </div>
          <div className="replay-panel-empty">
            <FiActivity className="empty-icon" />
            <p>{t('No execution data available')}</p>
            {hasReplayUrl && (
              <small>{t('Select an event and use the reload button in the property panel to load past execution data.')}</small>
            )}
            {hasReplayUrl && hasTestUrl && (
              <p className="empty-separator">{t('- or -')}</p>
            )}
            {hasTestUrl && selectedStartNodeId && (
              <small>{t('Click Test to execute the workflow and capture the results.')}</small>
            )}
            {hasTestUrl && !selectedStartNodeId && (
              <small>{t('Select an event and click Test to execute the workflow and capture the results.')}</small>
            )}
            {!hasReplayUrl && !hasTestUrl && (
              <small>{t('Run your workflow to generate execution data')}</small>
            )}
          </div>
          {(hasGlobalTokens || hasTemplateTokens) && (
            <div className="resizable-sections" ref={resizableContainerRef}>
              {hasGlobalTokens && (() => {
                const idx = emptySectionIdx++;
                return (
                  <React.Fragment key="global-tokens">
                    {idx > 0 && (
                      <div
                        className="section-separator"
                        onMouseDown={startSeparatorDrag(idx - 1)}
                        title={t('Drag to resize sections')}
                        role="separator"
                        aria-orientation="horizontal"
                      />
                    )}
                    <div
                      className="step-data-section global-tokens-section resizable-section"
                      style={{ flexBasis: `${sectionRatios[idx] * 100}%` }}
                    >
                      <div className="data-header">
                        <h4>
                          <FiDatabase />
                          {t('Global Tokens')}
                        </h4>
                      </div>
                      <div className="data-content" tabIndex={0} role="region" aria-label={t('Global Tokens')}>
                        <p className="token-drag-hint">{t('Drag tokens into configuration fields to insert them.')}</p>
                        <GlobalTokensContainer globalTokens={globalTokens} />
                      </div>
                    </div>
                  </React.Fragment>
                );
              })()}
              {hasTemplateTokens && (() => {
                const idx = emptySectionIdx++;
                return (
                  <React.Fragment key="template-tokens">
                    {idx > 0 && (
                      <div
                        className="section-separator"
                        onMouseDown={startSeparatorDrag(idx - 1)}
                        title={t('Drag to resize sections')}
                        role="separator"
                        aria-orientation="horizontal"
                      />
                    )}
                    <div
                      className="step-data-section template-tokens-section resizable-section"
                      style={{ flexBasis: `${sectionRatios[idx] * 100}%` }}
                    >
                      <div className="data-header">
                        <h4>
                          <FiFileText />
                          {t('Template Tokens')}
                        </h4>
                      </div>
                      <div className="data-content" tabIndex={0} role="region" aria-label={t('Template Tokens')}>
                        <p className="token-drag-hint">{t('Drag tokens into configuration fields to insert them.')}</p>
                        <TemplateTokensContainer templateTokens={templateTokens} />
                      </div>
                    </div>
                  </React.Fragment>
                );
              })()}
            </div>
          )}
        </div>
      </div>
      </Profiler>
    );
  }

  // Track section index for the active replay view.
  let sectionIdx = 0;

  return (
    <Profiler id="ReplayPanel" onRender={onRenderCallback}>
    <div
      className={`replay-panel ${replayPanelIsResizing ? 'resizing' : ''} ${replayPanelCollapsed ? 'collapsed' : ''} ${isVerticalResizing ? 'vertical-resizing' : ''}`}
      style={{ width: replayPanelCollapsed ? `${PANEL_DIMENSIONS.REPLAY_PANEL.COLLAPSED_WIDTH}px` : `${replayPanelWidth}px` }}
      onClick={handlePanelClick}
      title={replayPanelCollapsed ? t('Click to expand') : undefined}
    >
      <div
        className="resize-handle"
        onMouseDown={startResize}
        title={t('Drag to resize')}
      />
      <button
        className="panel-collapse-widget"
        onClick={toggleReplayPanelCollapse}
        title={replayPanelCollapsed ? t('Expand panel') : t('Collapse panel')}
        aria-label={replayPanelCollapsed ? t('Expand panel') : t('Collapse panel')}
      >
        <span className="collapse-icon">
          {replayPanelCollapsed ? <FiChevronLeft /> : <FiChevronRight />}
        </span>
      </button>
      {replayPanelCollapsed && (
        <div className="panel-collapsed-label">
          <span>{t('Replay')}</span>
        </div>
      )}
      <div className="panel-content">
      {/* Panel Header */}
      <div className="replay-panel-header">
        <h3>
          <FiActivity />
          {t('Execution Replay')}
          {hasReplaySteps && (
            <span className="replay-count">{t('(@count steps)', { '@count': filteredReplayData.length })}</span>
          )}
        </h3>
        <div className="replay-panel-header-actions">
          {showTestButton && (
            <button
              className="header-test-btn"
              onClick={() => onStartTest?.(selectedStartNodeId)}
              title={t('Test this event')}
              aria-label={t('Test this event')}
            >
              <FiPlay /> {t('Test')}
            </button>
          )}
          {stepInfo && (
            <button
              className="header-info-btn"
              aria-label={t('Show metadata')}
              onClick={() => setShowInfoPopup(prev => !prev)}
              title={t('Show metadata')}
            >
              <FiInfo />
            </button>
          )}
        </div>
      </div>
      {showInfoPopup && infoItems.length > 0 && (
        <InfoPopup items={infoItems} onClose={() => setShowInfoPopup(false)} />
      )}

      {/* Replay Entry Selector - shown when multiple entries are loaded (hidden for single test result) */}
      {replayEntries.length > 1 && onSelectReplayEntry && (
        <div className="replay-entry-selector" ref={entryDropdownRef}>
          <button
            className="replay-entry-toggle"
            onClick={() => setEntryDropdownOpen(prev => !prev)}
            aria-expanded={entryDropdownOpen}
            aria-haspopup="listbox"
            aria-label={t('Select execution replay')}
          >
            <span className="replay-entry-toggle-label">
              {selectedEntryIndex >= 0 && selectedEntryIndex < replayEntries.length ? (
                <>
                  <FiClock className="toggle-icon" />
                  {formatTimestamp(replayEntries[selectedEntryIndex].timestamp)}
                  {' — '}
                  {formatUser(replayEntries[selectedEntryIndex].user)}
                </>
              ) : (
                t('Select an execution...')
              )}
            </span>
            <FiChevronDown className={`toggle-chevron ${entryDropdownOpen ? 'open' : ''}`} />
          </button>
          {entryDropdownOpen && (
            <div className="replay-entry-list" role="listbox" aria-label={t('Execution replays')}>
              {replayEntries.map((entry, index) => (
                <div
                  key={index}
                  className={`replay-entry-item ${index === selectedEntryIndex ? 'selected' : ''}`}
                  role="option"
                  aria-selected={index === selectedEntryIndex}
                  onClick={() => handleEntrySelect(index)}
                  onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); handleEntrySelect(index); } }}
                  tabIndex={0}
                >
                  <div className="replay-entry-row">
                    <FiClock className="entry-icon" />
                    <span className="entry-value">{formatTimestamp(entry.timestamp)}</span>
                  </div>
                  <div className="replay-entry-row">
                    <FiUser className="entry-icon" />
                    <span className="entry-value">{formatUser(entry.user)}</span>
                  </div>
                  <div className="replay-entry-row">
                    <FiGlobe className="entry-icon" />
                    <span className="entry-value">{typeof entry.ip === 'string' ? entry.ip : String(entry.ip ?? '')}</span>
                  </div>
                  <div className="replay-entry-row">
                    <FiLink className="entry-icon" />
                    <span className="entry-value entry-url">{typeof entry.url === 'string' ? entry.url : String(entry.url ?? '')}</span>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {/* Test Waiting State */}
      {(isTestRunning || isTestInitiating) && (
        <div className="replay-test-waiting">
          <FiRefreshCw className="spinning test-spinner" />
          <h4>{isTestInitiating ? t('Starting test...') : t('Waiting for test execution...')}</h4>
          <p>{t('Trigger the selected event on your Drupal site so that the workflow gets executed and the results are captured.')}</p>
          {isTestRunning && onCancelTest && (
            <button
              className="btn btn-secondary test-cancel-btn"
              onClick={onCancelTest}
              aria-label={t('Cancel test')}
            >
              <FiXCircle /> {t('Cancel')}
            </button>
          )}
        </div>
      )}

      {/* Resizable sections: control, step data, global tokens, template tokens */}
      {!isTestRunning && !isTestInitiating && hasReplaySteps && (
      <div className="resizable-sections" ref={resizableContainerRef}>
        {/* Execution Control Section */}
        {(() => {
          const idx = sectionIdx++;
          return (
            <div
              className="replay-control-section resizable-section"
              style={{ flexBasis: `${sectionRatios[idx] * 100}%` }}
            >
              <div className="replay-controls">
                <div className="playback-controls">
                  <button
                    className="control-btn"
                    onClick={handlePrevious}
                    disabled={filteredCurrentStep <= -1}
                    title={t('Previous Step')}
                    aria-label={t('Previous Step')}
                  >
                    <FiSkipBack />
                  </button>
                  <button
                    className={`control-btn play-btn ${isPlaying ? 'playing' : ''}`}
                    onClick={handlePlay}
                    title={isPlaying ? t('Pause') : t('Play')}
                    aria-label={isPlaying ? t('Pause') : t('Play')}
                  >
                    {isPlaying ? <FiPause /> : <FiPlay />}
                  </button>
                  <button
                    className="control-btn"
                    onClick={handleStop}
                    title={t('Stop & Reset')}
                    aria-label={t('Stop & Reset')}
                  >
                    <FiSquare />
                  </button>
                  <button
                    className="control-btn"
                    onClick={handleNext}
                    disabled={filteredCurrentStep >= filteredReplayData.length - 1}
                    title={t('Next Step')}
                    aria-label={t('Next Step')}
                  >
                    <FiSkipForward />
                  </button>
                </div>

                <div className="speed-control">
                  <FiZap className="speed-icon" title={t('Playback Speed')} />
                  <select
                    value={playbackSpeed}
                    onChange={(e) => setPlaybackSpeed(parseFloat(e.target.value))}
                    title={t('Playback Speed')}
                    aria-label={t('Playback Speed')}
                  >
                    <option value={0.5}>0.5x</option>
                    <option value={1}>1x</option>
                    <option value={2}>2x</option>
                    <option value={4}>4x</option>
                  </select>
                </div>
              </div>

              <div className="replay-progress">
                <div className="progress-bar">
                  <div
                    className="progress-fill"
                    style={{
                      width: `${filteredCurrentStep >= 0 ? ((filteredCurrentStep + 1) / filteredReplayData.length) * 100 : 0}%`
                    }}
                  />
                </div>
                <div className="progress-label">
                  {filteredCurrentStep >= 0 ? t('Step @current of @total', { '@current': filteredCurrentStep + 1, '@total': filteredReplayData.length }) : t('Ready')}
                </div>
              </div>

              <div className="replay-steps" ref={stepsContainerRef}>
                {filteredReplayData.map((step, index) => (
                  <div
                    key={index}
                    ref={el => { stepRefs.current[index] = el; }}
                    className={`replay-step ${index === filteredCurrentStep ? 'current' : ''} ${index < filteredCurrentStep ? 'completed' : ''}`}
                    onClick={() => handleStepClick(index)}
                    role="button"
                    tabIndex={0}
                    onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); handleStepClick(index); } }}
                  >
                    <div className="step-indicator">
                      {getStepIcon(step)}
                    </div>
                    <div className="step-label">{getStepLabel(step, index, nodes, edges)}</div>
                  </div>
                ))}
              </div>
            </div>
          );
        })()}

        {/* Separator: control <-> step data */}
        <div
          className="section-separator"
          onMouseDown={startSeparatorDrag(sectionIdx - 1)}
          title={t('Drag to resize sections')}
          role="separator"
          aria-orientation="horizontal"
        />

        {/* Step Data Section */}
        {(() => {
          const idx = sectionIdx++;
          return (
            <div
              className="step-data-section resizable-section"
              style={{ flexBasis: `${sectionRatios[idx] * 100}%` }}
            >
              <div className="data-header">
                <h4>
                  <FiDatabase />
                  {t('Step Data')}
                </h4>
                {(stepData || stepInfo) && (
                  <button
                    className="copy-btn"
                    onClick={() => copyToClipboard({ info: stepInfo, data: stepData }, 'all')}
                    title={t('Copy all data')}
                    aria-label={t('Copy all data')}
                  >
                    <FiCopy />
                    {copiedPath === 'all' && <span className="copied">{t('Copied!')}</span>}
                  </button>
                )}
              </div>

              <div className="data-content" tabIndex={0} role="region" aria-label={t('Step Data')}>
                {stepData && Object.keys(stepData).length > 0 ? (
                  <>
                    <p className="token-drag-hint">{t('Drag tokens into configuration fields to insert them.')}</p>
                    <StepDataContainer stepData={stepData} />
                  </>
                ) : (
                  <>
                    {(!stepData || Object.keys(stepData).length === 0) && filteredCurrentStep >= 0 && (
                      <div className="no-data">
                        <p>{t('No token data available for this step')}</p>
                      </div>
                    )}
                    {filteredCurrentStep < 0 && (
                      <div className="no-data">
                        <p>{t('Select a step to view its data')}</p>
                      </div>
                    )}
                  </>
                )}
              </div>
            </div>
          );
        })()}

        {hasGlobalTokens && (() => {
          const idx = sectionIdx++;
          return (
            <React.Fragment key="global-tokens">
              <div
                className="section-separator"
                onMouseDown={startSeparatorDrag(idx - 1)}
                title={t('Drag to resize sections')}
                role="separator"
                aria-orientation="horizontal"
              />
              <div
                className="step-data-section global-tokens-section resizable-section"
                style={{ flexBasis: `${sectionRatios[idx] * 100}%` }}
              >
                <div className="data-header">
                  <h4>
                    <FiDatabase />
                    {t('Global Tokens')}
                  </h4>
                </div>
                <div className="data-content" tabIndex={0} role="region" aria-label={t('Global Tokens')}>
                  <p className="token-drag-hint">{t('Drag tokens into configuration fields to insert them.')}</p>
                  <GlobalTokensContainer globalTokens={globalTokens} />
                </div>
              </div>
            </React.Fragment>
          );
        })()}

        {hasTemplateTokens && (() => {
          const idx = sectionIdx++;
          return (
            <React.Fragment key="template-tokens">
              <div
                className="section-separator"
                onMouseDown={startSeparatorDrag(idx - 1)}
                title={t('Drag to resize sections')}
                role="separator"
                aria-orientation="horizontal"
              />
              <div
                className="step-data-section template-tokens-section resizable-section"
                style={{ flexBasis: `${sectionRatios[idx] * 100}%` }}
              >
                <div className="data-header">
                  <h4>
                    <FiFileText />
                    {t('Template Tokens')}
                  </h4>
                </div>
                <div className="data-content" tabIndex={0} role="region" aria-label={t('Template Tokens')}>
                  <p className="token-drag-hint">{t('Drag tokens into configuration fields to insert them.')}</p>
                  <TemplateTokensContainer templateTokens={templateTokens} />
                </div>
              </div>
            </React.Fragment>
          );
        })()}
      </div>
      )}

      </div>
    </div>
    </Profiler>
  );
};

export default ReplayPanel;
