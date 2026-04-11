import React from 'react';
import { render, screen, fireEvent, act } from '@testing-library/react';
import ReplayPanel from '../ReplayPanel';

const mockUseReplayPlayback = jest.fn(() => ({
  isPlaying: false,
  playbackSpeed: 1,
  setPlaybackSpeed: jest.fn(),
  handlePlay: jest.fn(),
  handleStop: jest.fn(),
  handlePrevious: jest.fn(),
  handleNext: jest.fn(),
  handleStepClick: jest.fn(),
}));

jest.mock('react-icons/fi', () => ({
  FiPlay: () => <span data-testid="fi-play" />,
  FiPause: () => <span data-testid="fi-pause" />,
  FiSquare: () => <span data-testid="fi-square" />,
  FiSkipBack: () => <span data-testid="fi-skip-back" />,
  FiSkipForward: () => <span data-testid="fi-skip-forward" />,
  FiActivity: () => <span data-testid="fi-activity" />,
  FiDatabase: () => <span data-testid="fi-database" />,
  FiChevronLeft: () => <span data-testid="fi-chevron-left" />,
  FiChevronRight: () => <span data-testid="fi-chevron-right" />,
  FiCopy: () => <span data-testid="fi-copy" />,
  FiZap: () => <span data-testid="fi-zap" />,
  FiInfo: () => <span data-testid="fi-info" />,
  FiChevronDown: () => <span data-testid="fi-chevron-down" />,
  FiClock: () => <span data-testid="fi-clock" />,
  FiUser: () => <span data-testid="fi-user" />,
  FiGlobe: () => <span data-testid="fi-globe" />,
  FiLink: () => <span data-testid="fi-link" />,
  FiRefreshCw: () => <span data-testid="fi-refresh-cw" />,
  FiXCircle: () => <span data-testid="fi-x-circle" />,
  FiFileText: () => <span data-testid="fi-file-text" />,
}));

const mockToggleReplayPanelCollapse = jest.fn();

jest.mock('../../store/usePanelStore', () => ({
  usePanelStore: jest.fn((selector) => {
    const state = {
      replayPanelWidth: 300,
      replayPanelIsResizing: false,
      setReplayPanelWidth: jest.fn(),
      setReplayPanelResizing: jest.fn(),
      replayPanelCollapsed: false,
      toggleReplayPanelCollapse: mockToggleReplayPanelCollapse,
    };
    if (typeof selector === 'function') return selector(state);
    return state;
  }),
}));

jest.mock('../../hooks/useReplayStepFilter', () => ({
  useReplayStepFilter: jest.fn(({ replayData }) => ({
    filteredReplayData: replayData || [],
    getFilteredIndex: jest.fn((idx: number) => idx),
    getOriginalIndex: jest.fn((idx: number) => idx),
  })),
}));

jest.mock('../../hooks/useReplayPlayback', () => ({
  useReplayPlayback: (...args: Parameters<typeof mockUseReplayPlayback>) => mockUseReplayPlayback(...args),
}));

jest.mock('../../hooks/usePanelResize', () => ({
  usePanelResize: jest.fn(() => ({
    startResize: jest.fn(),
  })),
}));

jest.mock('../ReplayDataRenderer', () => ({
  StepDataContainer: ({ stepData: _stepData }: any) => <div data-testid="step-data-container" />,
  GlobalTokensContainer: ({ globalTokens: _globalTokens }: any) => <div data-testid="global-tokens-container" />,
  TemplateTokensContainer: ({ templateTokens: _templateTokens }: any) => <div data-testid="template-tokens-container" />,
}));

jest.mock('../InfoPopup', () => {
  const MockInfoPopup = (props: any) => <div data-testid="info-popup">{props.items?.map((item: any, i: number) => item.show !== false ? <span key={i}>{item.value}</span> : null)}</div>;
  MockInfoPopup.displayName = 'MockInfoPopup';
  return MockInfoPopup;
});

jest.mock('../../utils/replayStepUtils', () => ({
  getStepIcon: () => <span data-testid="step-icon" />,
  getStepLabel: (step: any, index: number) => `Step ${index}`,
}));

describe('ReplayPanel', () => {
  const mockOnSelectStep = jest.fn();
  const mockOnToggleReplay = jest.fn();

  const mockReplayData = [
    { type: 'event', id: 'e1', data: null },
    { type: 'action', id: 'a1', data: { token: 'val' } },
  ];

  const defaultProps = {
    replayData: mockReplayData as any,
    isReplayMode: true,
    onToggleReplay: mockOnToggleReplay,
    onSelectStep: mockOnSelectStep,
    isVisible: true,
    currentStep: -1,
    edges: [],
    nodes: [],
  };

  beforeEach(() => {
    jest.clearAllMocks();
    mockUseReplayPlayback.mockReturnValue({
      isPlaying: false,
      playbackSpeed: 1,
      setPlaybackSpeed: jest.fn(),
      handlePlay: jest.fn(),
      handleStop: jest.fn(),
      handlePrevious: jest.fn(),
      handleNext: jest.fn(),
      handleStepClick: jest.fn(),
    });
    // Mock clipboard API
    Object.assign(navigator, {
      clipboard: { writeText: jest.fn().mockResolvedValue(undefined) },
    });
  });

  describe('visibility', () => {
    it('should return null when not visible', () => {
      const { container } = render(<ReplayPanel {...defaultProps} isVisible={false} />);
      expect(container.firstChild).toBeNull();
    });
  });

  describe('empty data', () => {
    it('should show empty message when replay data is empty', () => {
      render(<ReplayPanel {...defaultProps} replayData={[]} />);
      expect(screen.getByText('No execution data available')).toBeTruthy();
    });

    it('should show empty message when replay data is null', () => {
      render(<ReplayPanel {...defaultProps} replayData={null} />);
      expect(screen.getByText('No execution data available')).toBeTruthy();
    });

    it('should render the empty state header', () => {
      render(<ReplayPanel {...defaultProps} replayData={[]} />);
      expect(screen.getByText('Execution Replay')).toBeTruthy();
    });

    it('should show fallback message when no URLs are available', () => {
      render(<ReplayPanel {...defaultProps} replayData={[]} />);
      expect(screen.getByText('Run your workflow to generate execution data')).toBeTruthy();
    });

    it('should show replay URL message when hasReplayUrl is true', () => {
      render(<ReplayPanel {...defaultProps} replayData={[]} hasReplayUrl />);
      expect(screen.getByText('Select an event and use the reload button in the property panel to load past execution data.')).toBeTruthy();
      expect(screen.queryByText('Run your workflow to generate execution data')).toBeNull();
    });

    it('should show test message with event selection when hasTestUrl but no event selected', () => {
      render(<ReplayPanel {...defaultProps} replayData={[]} hasTestUrl />);
      expect(screen.getByText('Select an event and click Test to execute the workflow and capture the results.')).toBeTruthy();
    });

    it('should show test message without event selection when event is auto-detected', () => {
      render(<ReplayPanel {...defaultProps} replayData={[]} hasTestUrl selectedStartNodeId="event-1" />);
      expect(screen.getByText('Click Test to execute the workflow and capture the results.')).toBeTruthy();
    });

    it('should show both messages with separator when both URLs available', () => {
      render(<ReplayPanel {...defaultProps} replayData={[]} hasReplayUrl hasTestUrl selectedStartNodeId="event-1" />);
      expect(screen.getByText('Select an event and use the reload button in the property panel to load past execution data.')).toBeTruthy();
      expect(screen.getByText('- or -')).toBeTruthy();
      expect(screen.getByText('Click Test to execute the workflow and capture the results.')).toBeTruthy();
    });
  });

  describe('with replay data', () => {
    it('should render step count', () => {
      render(<ReplayPanel {...defaultProps} />);
      expect(screen.getByText('(2 steps)')).toBeTruthy();
    });

    it('should render playback controls', () => {
      render(<ReplayPanel {...defaultProps} />);
      expect(screen.getByTitle('Previous Step')).toBeTruthy();
      expect(screen.getByTitle('Play')).toBeTruthy();
      expect(screen.getByTitle('Stop & Reset')).toBeTruthy();
      expect(screen.getByTitle('Next Step')).toBeTruthy();
    });

    it('should show Ready when no step selected', () => {
      render(<ReplayPanel {...defaultProps} />);
      expect(screen.getByText('Ready')).toBeTruthy();
    });

    it('should show step progress', () => {
      render(<ReplayPanel {...defaultProps} currentStep={0} />);
      expect(screen.getByText('Step 1 of 2')).toBeTruthy();
    });

    it('should render step list', () => {
      render(<ReplayPanel {...defaultProps} />);
      expect(screen.getByText('Step 0')).toBeTruthy();
      expect(screen.getByText('Step 1')).toBeTruthy();
    });

    it('should render speed control', () => {
      render(<ReplayPanel {...defaultProps} />);
      const select = screen.getByTitle('Playback Speed') as HTMLSelectElement;
      expect(select).toBeTruthy();
      expect(select.value).toBe('1');
    });

    it('should render progress bar with width 0 when no step selected', () => {
      render(<ReplayPanel {...defaultProps} currentStep={-1} />);
      const fill = document.querySelector('.progress-fill') as HTMLElement;
      expect(fill).toBeTruthy();
      expect(fill.style.width).toBe('0%');
    });

    it('should render progress bar with correct width when step selected', () => {
      render(<ReplayPanel {...defaultProps} currentStep={0} />);
      const fill = document.querySelector('.progress-fill') as HTMLElement;
      expect(fill.style.width).toBe('50%');
    });
  });

  describe('step data', () => {
    it('should show select prompt when no step active', () => {
      render(<ReplayPanel {...defaultProps} />);
      expect(screen.getByText('Select a step to view its data')).toBeTruthy();
    });

    it('should show StepDataContainer when data exists', () => {
      render(<ReplayPanel {...defaultProps} currentStep={1} stepData={{ token: 'val' }} />);
      expect(screen.getByTestId('step-data-container')).toBeTruthy();
    });

    it('should show token drag hint when step data exists', () => {
      render(<ReplayPanel {...defaultProps} currentStep={1} stepData={{ token: 'val' }} />);
      expect(screen.getByText('Drag tokens into configuration fields to insert them.')).toBeTruthy();
    });

    it('should not show token drag hint when no step data', () => {
      render(<ReplayPanel {...defaultProps} currentStep={0} stepData={{}} />);
      expect(screen.queryByText('Drag tokens into configuration fields to insert them.')).toBeNull();
    });

    it('should show no data message when step selected but stepData is empty', () => {
      render(<ReplayPanel {...defaultProps} currentStep={0} stepData={{}} />);
      expect(screen.getByText('No token data available for this step')).toBeTruthy();
    });

    it('should show no data message when step selected but stepData is null', () => {
      render(<ReplayPanel {...defaultProps} currentStep={0} stepData={null} />);
      expect(screen.getByText('No token data available for this step')).toBeTruthy();
    });
  });

  describe('metadata info button', () => {
    it('should render info button when stepInfo provided', () => {
      render(<ReplayPanel {...defaultProps} stepInfo={{ type: 'action', id: 'a1' }} />);
      expect(screen.getByTitle('Show metadata')).toBeTruthy();
    });

    it('should show info popup with metadata when info button clicked', () => {
      render(<ReplayPanel {...defaultProps} stepInfo={{ type: 'action', id: 'a1', conditionId: 'c1', exception: { message: 'Err' } }} />);
      fireEvent.click(screen.getByTitle('Show metadata'));
      expect(screen.getByTestId('info-popup')).toBeTruthy();
      expect(screen.getByText('a1')).toBeTruthy();
      expect(screen.getByText('c1')).toBeTruthy();
      expect(screen.getByText('Err')).toBeTruthy();
    });

    it('should not render info button when no stepInfo', () => {
      render(<ReplayPanel {...defaultProps} />);
      expect(screen.queryByTitle('Show metadata')).toBeNull();
    });

    it('should toggle info popup off when clicked again', () => {
      render(<ReplayPanel {...defaultProps} stepInfo={{ type: 'action', id: 'a1' }} />);
      const btn = screen.getByTitle('Show metadata');
      fireEvent.click(btn);
      expect(screen.getByTestId('info-popup')).toBeTruthy();
      fireEvent.click(btn);
      expect(screen.queryByTestId('info-popup')).toBeNull();
    });

    it('should include successorId in info items when present', () => {
      render(<ReplayPanel {...defaultProps} stepInfo={{ type: 'action', id: 'a1', successorId: 'succ_1' }} />);
      fireEvent.click(screen.getByTitle('Show metadata'));
      expect(screen.getByText('succ_1')).toBeTruthy();
    });
  });

  describe('copy to clipboard', () => {
    it('should render copy button when stepData is provided', () => {
      render(<ReplayPanel {...defaultProps} currentStep={0} stepData={{ token: 'val' }} />);
      expect(screen.getByTitle('Copy all data')).toBeTruthy();
    });

    it('should render copy button when stepInfo is provided', () => {
      render(<ReplayPanel {...defaultProps} stepInfo={{ type: 'action' }} />);
      expect(screen.getByTitle('Copy all data')).toBeTruthy();
    });

    it('should not render copy button when neither stepData nor stepInfo', () => {
      render(<ReplayPanel {...defaultProps} />);
      expect(screen.queryByTitle('Copy all data')).toBeNull();
    });

    it('should call navigator.clipboard.writeText on copy', () => {
      jest.useFakeTimers();
      render(<ReplayPanel {...defaultProps} currentStep={0} stepData={{ token: 'val' }} stepInfo={{ type: 'action' }} />);
      fireEvent.click(screen.getByTitle('Copy all data'));
      expect(navigator.clipboard.writeText).toHaveBeenCalledWith(
        JSON.stringify({ info: { type: 'action' }, data: { token: 'val' } }, null, 2)
      );
      jest.useRealTimers();
    });

    it('should show Copied! feedback briefly after copy', () => {
      jest.useFakeTimers();
      render(<ReplayPanel {...defaultProps} currentStep={0} stepData={{ a: 1 }} stepInfo={{ type: 'action' }} />);
      fireEvent.click(screen.getByTitle('Copy all data'));
      expect(screen.getByText('Copied!')).toBeTruthy();
      act(() => { jest.advanceTimersByTime(2000); });
      expect(screen.queryByText('Copied!')).toBeNull();
      jest.useRealTimers();
    });
  });

  describe('collapsed panel', () => {
    it('should render collapsed label when collapsed', () => {
      const { usePanelStore } = require('../../store/usePanelStore');
      usePanelStore.mockImplementation((selector: any) => {
        const state = {
          replayPanelWidth: 300,
          replayPanelIsResizing: false,
          setReplayPanelWidth: jest.fn(),
          setReplayPanelResizing: jest.fn(),
          replayPanelCollapsed: true,
          toggleReplayPanelCollapse: mockToggleReplayPanelCollapse,
        };
        return selector(state);
      });

      render(<ReplayPanel {...defaultProps} />);
      expect(screen.getByText('Replay')).toBeTruthy();
    });

    it('should expand when collapsed panel is clicked', () => {
      const { usePanelStore } = require('../../store/usePanelStore');
      usePanelStore.mockImplementation((selector: any) => {
        const state = {
          replayPanelWidth: 300,
          replayPanelIsResizing: false,
          setReplayPanelWidth: jest.fn(),
          setReplayPanelResizing: jest.fn(),
          replayPanelCollapsed: true,
          toggleReplayPanelCollapse: mockToggleReplayPanelCollapse,
        };
        return selector(state);
      });

      render(<ReplayPanel {...defaultProps} />);
      const panel = document.querySelector('.replay-panel')!;
      fireEvent.click(panel);
      expect(mockToggleReplayPanelCollapse).toHaveBeenCalled();
    });

    it('should not expand when non-collapsed panel is clicked', () => {
      // Reset store mock back to non-collapsed
      const { usePanelStore } = require('../../store/usePanelStore');
      usePanelStore.mockImplementation((selector: any) => {
        const state = {
          replayPanelWidth: 300,
          replayPanelIsResizing: false,
          setReplayPanelWidth: jest.fn(),
          setReplayPanelResizing: jest.fn(),
          replayPanelCollapsed: false,
          toggleReplayPanelCollapse: mockToggleReplayPanelCollapse,
        };
        return selector(state);
      });

      render(<ReplayPanel {...defaultProps} />);
      const panel = document.querySelector('.replay-panel')!;
      fireEvent.click(panel);
      expect(mockToggleReplayPanelCollapse).not.toHaveBeenCalled();
    });

    it('should toggle collapse when collapse widget is clicked', () => {
      // Ensure store mock is non-collapsed so button says "Collapse panel"
      const { usePanelStore } = require('../../store/usePanelStore');
      usePanelStore.mockImplementation((selector: any) => {
        const state = {
          replayPanelWidth: 300,
          replayPanelIsResizing: false,
          setReplayPanelWidth: jest.fn(),
          setReplayPanelResizing: jest.fn(),
          replayPanelCollapsed: false,
          toggleReplayPanelCollapse: mockToggleReplayPanelCollapse,
        };
        return selector(state);
      });

      render(<ReplayPanel {...defaultProps} />);
      const collapseBtn = screen.getByTitle('Collapse panel');
      fireEvent.click(collapseBtn);
      expect(mockToggleReplayPanelCollapse).toHaveBeenCalled();
    });
  });

  describe('replay entry selector', () => {
    const mockEntries = [
      {
        model_id: 'model1',
        component_id: 'ev1',
        history: [],
        timestamp: '2026-01-15T10:30:00Z',
        user: 'admin',
        ip: '127.0.0.1',
        url: '/node/1',
      },
      {
        model_id: 'model1',
        component_id: 'ev1',
        history: [],
        timestamp: 1737000000,
        user: { name: 'editor', uid: 2 },
        ip: '192.168.1.1',
        url: '/node/2',
      },
    ];

    const mockOnSelectReplayEntry = jest.fn();

    it('should not render entry selector when no entries', () => {
      render(<ReplayPanel {...defaultProps} replayEntries={[]} />);
      expect(document.querySelector('.replay-entry-selector')).toBeNull();
    });

    it('should not render entry selector when no callback', () => {
      render(<ReplayPanel {...defaultProps} replayEntries={mockEntries} />);
      expect(document.querySelector('.replay-entry-selector')).toBeNull();
    });

    it('should render entry selector with entries and callback', () => {
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={mockEntries}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      expect(document.querySelector('.replay-entry-selector')).toBeTruthy();
    });

    it('should show "Select an execution..." when no entry is selected', () => {
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={mockEntries}
          selectedEntryIndex={-1}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      expect(screen.getByText('Select an execution...')).toBeTruthy();
    });

    it('should show selected entry timestamp and user', () => {
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={mockEntries}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      // The toggle label should contain formatted timestamp and user
      const toggle = screen.getByLabelText('Select execution replay');
      expect(toggle).toBeTruthy();
      // "admin" is the user string
      expect(toggle.textContent).toContain('admin');
    });

    it('should open dropdown when toggle is clicked', () => {
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={mockEntries}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      const toggle = screen.getByLabelText('Select execution replay');
      fireEvent.click(toggle);
      expect(screen.getByRole('listbox')).toBeTruthy();
    });

    it('should render all entries in the dropdown', () => {
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={mockEntries}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      fireEvent.click(screen.getByLabelText('Select execution replay'));
      const listbox = screen.getByRole('listbox');
      const options = listbox.querySelectorAll('[role="option"]');
      expect(options).toHaveLength(2);
    });

    it('should mark the selected entry with aria-selected', () => {
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={mockEntries}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      fireEvent.click(screen.getByLabelText('Select execution replay'));
      const options = screen.getAllByRole('option');
      expect(options[0]).toHaveAttribute('aria-selected', 'true');
      expect(options[1]).toHaveAttribute('aria-selected', 'false');
    });

    it('should call onSelectReplayEntry when an entry is clicked', () => {
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={mockEntries}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      fireEvent.click(screen.getByLabelText('Select execution replay'));
      const options = screen.getAllByRole('option');
      fireEvent.click(options[1]);
      expect(mockOnSelectReplayEntry).toHaveBeenCalledWith(1);
    });

    it('should call onSelectReplayEntry on Enter key', () => {
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={mockEntries}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      fireEvent.click(screen.getByLabelText('Select execution replay'));
      const options = screen.getAllByRole('option');
      fireEvent.keyDown(options[1], { key: 'Enter' });
      expect(mockOnSelectReplayEntry).toHaveBeenCalledWith(1);
    });

    it('should call onSelectReplayEntry on Space key', () => {
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={mockEntries}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      fireEvent.click(screen.getByLabelText('Select execution replay'));
      const options = screen.getAllByRole('option');
      fireEvent.keyDown(options[1], { key: ' ' });
      expect(mockOnSelectReplayEntry).toHaveBeenCalledWith(1);
    });

    it('should close dropdown after selecting an entry', () => {
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={mockEntries}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      fireEvent.click(screen.getByLabelText('Select execution replay'));
      expect(screen.getByRole('listbox')).toBeTruthy();
      fireEvent.click(screen.getAllByRole('option')[1]);
      expect(screen.queryByRole('listbox')).toBeNull();
    });

    it('should close dropdown on outside click', () => {
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={mockEntries}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      fireEvent.click(screen.getByLabelText('Select execution replay'));
      expect(screen.getByRole('listbox')).toBeTruthy();
      fireEvent.mouseDown(document.body);
      expect(screen.queryByRole('listbox')).toBeNull();
    });

    it('should display user object with name and uid', () => {
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={mockEntries}
          selectedEntryIndex={1}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      // The toggle should show user name + uid
      const toggle = screen.getByLabelText('Select execution replay');
      expect(toggle.textContent).toContain('editor');
    });

    it('should display IP and URL in dropdown items', () => {
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={mockEntries}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      fireEvent.click(screen.getByLabelText('Select execution replay'));
      expect(screen.getByText('127.0.0.1')).toBeTruthy();
      expect(screen.getByText('/node/1')).toBeTruthy();
    });

    it('should not render entry selector with only one entry', () => {
      const singleEntry = [mockEntries[0]];
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={singleEntry}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      expect(document.querySelector('.replay-entry-selector')).toBeNull();
    });

    it('should handle user as object without uid', () => {
      const entries = [
        { ...mockEntries[0], user: { name: 'no uid' } as any },
        mockEntries[1],
      ];
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={entries}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      const toggle = screen.getByLabelText('Select execution replay');
      expect(toggle.textContent).toContain('no uid');
    });

    it('should handle null user gracefully', () => {
      const entries = [
        { ...mockEntries[0], user: null as any },
        mockEntries[1],
      ];
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={entries}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      // Should not throw
      expect(screen.getByLabelText('Select execution replay')).toBeTruthy();
    });

    it('should handle numeric timestamp (unix seconds)', () => {
      const entries = [
        { ...mockEntries[0], timestamp: 1700000000 },
        mockEntries[1],
      ];
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={entries}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      const toggle = screen.getByLabelText('Select execution replay');
      // Should render a formatted date string (not the raw number)
      expect(toggle.textContent).not.toContain('1700000000');
    });

    it('should handle invalid timestamp string gracefully', () => {
      const entries = [
        { ...mockEntries[0], timestamp: 'not-a-date' },
        mockEntries[1],
      ];
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={entries}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      // Should render the raw string as fallback
      const toggle = screen.getByLabelText('Select execution replay');
      expect(toggle.textContent).toContain('not-a-date');
    });

    it('should handle empty timestamp gracefully', () => {
      const entries = [
        { ...mockEntries[0], timestamp: '' as any },
        mockEntries[1],
      ];
      render(
        <ReplayPanel
          {...defaultProps}
          replayEntries={entries}
          selectedEntryIndex={0}
          onSelectReplayEntry={mockOnSelectReplayEntry}
        />
      );
      // Should not throw
      expect(screen.getByLabelText('Select execution replay')).toBeTruthy();
    });

  });

  describe('step interactions', () => {
    it('should call handleStepClick when a step is clicked', () => {
      const handleStepClick = jest.fn();
      mockUseReplayPlayback.mockReturnValue({
        isPlaying: false,
        playbackSpeed: 1,
        setPlaybackSpeed: jest.fn(),
        handlePlay: jest.fn(),
        handleStop: jest.fn(),
        handlePrevious: jest.fn(),
        handleNext: jest.fn(),
        handleStepClick,
      });
      render(<ReplayPanel {...defaultProps} />);
      fireEvent.click(screen.getByText('Step 0'));
      expect(handleStepClick).toHaveBeenCalledWith(0);
    });

    it('should call handleStepClick on Enter key', () => {
      const handleStepClick = jest.fn();
      mockUseReplayPlayback.mockReturnValue({
        isPlaying: false,
        playbackSpeed: 1,
        setPlaybackSpeed: jest.fn(),
        handlePlay: jest.fn(),
        handleStop: jest.fn(),
        handlePrevious: jest.fn(),
        handleNext: jest.fn(),
        handleStepClick,
      });
      render(<ReplayPanel {...defaultProps} />);
      const step = screen.getByText('Step 0').closest('[role="button"]')!;
      fireEvent.keyDown(step, { key: 'Enter' });
      expect(handleStepClick).toHaveBeenCalledWith(0);
    });

    it('should call handleStepClick on Space key', () => {
      const handleStepClick = jest.fn();
      mockUseReplayPlayback.mockReturnValue({
        isPlaying: false,
        playbackSpeed: 1,
        setPlaybackSpeed: jest.fn(),
        handlePlay: jest.fn(),
        handleStop: jest.fn(),
        handlePrevious: jest.fn(),
        handleNext: jest.fn(),
        handleStepClick,
      });
      render(<ReplayPanel {...defaultProps} />);
      const step = screen.getByText('Step 0').closest('[role="button"]')!;
      fireEvent.keyDown(step, { key: ' ' });
      expect(handleStepClick).toHaveBeenCalledWith(0);
    });

    it('should apply current class to active step', () => {
      render(<ReplayPanel {...defaultProps} currentStep={0} />);
      const step = screen.getByText('Step 0').closest('.replay-step')!;
      expect(step.className).toContain('current');
    });

    it('should apply completed class to previous steps', () => {
      render(<ReplayPanel {...defaultProps} currentStep={1} />);
      const step = screen.getByText('Step 0').closest('.replay-step')!;
      expect(step.className).toContain('completed');
    });
  });

  describe('test button', () => {
    it('should show Test button when event selected and hasTestUrl', () => {
      render(<ReplayPanel {...defaultProps} hasTestUrl selectedStartNodeId="event-1" />);
      expect(screen.getByTitle('Test this event')).toBeTruthy();
    });

    it('should not show Test button when no event selected', () => {
      render(<ReplayPanel {...defaultProps} hasTestUrl />);
      expect(screen.queryByTitle('Test this event')).toBeNull();
    });

    it('should not show Test button when no hasTestUrl', () => {
      render(<ReplayPanel {...defaultProps} selectedStartNodeId="event-1" />);
      expect(screen.queryByTitle('Test this event')).toBeNull();
    });

    it('should not show Test button when test is running', () => {
      render(<ReplayPanel {...defaultProps} hasTestUrl selectedStartNodeId="event-1" isTestRunning />);
      expect(screen.queryByTitle('Test this event')).toBeNull();
    });

    it('should not show Test button when test is initiating', () => {
      render(<ReplayPanel {...defaultProps} hasTestUrl selectedStartNodeId="event-1" isTestInitiating />);
      expect(screen.queryByTitle('Test this event')).toBeNull();
    });

    it('should call onStartTest with event ID when clicked', () => {
      const onStartTest = jest.fn();
      render(<ReplayPanel {...defaultProps} hasTestUrl selectedStartNodeId="event-1" onStartTest={onStartTest} />);
      fireEvent.click(screen.getByTitle('Test this event'));
      expect(onStartTest).toHaveBeenCalledWith('event-1');
    });

    it('should show Test button in empty state when event selected and hasTestUrl', () => {
      render(<ReplayPanel {...defaultProps} replayData={[]} hasTestUrl selectedStartNodeId="event-1" />);
      expect(screen.getByTitle('Test this event')).toBeTruthy();
    });

    it('should not show Test button in empty state when no hasTestUrl', () => {
      render(<ReplayPanel {...defaultProps} replayData={[]} selectedStartNodeId="event-1" />);
      expect(screen.queryByTitle('Test this event')).toBeNull();
    });
  });

  describe('test waiting state', () => {
    it('should show waiting state when test is running', () => {
      render(<ReplayPanel {...defaultProps} replayData={[]} isTestRunning />);
      expect(screen.getByText('Waiting for test execution...')).toBeTruthy();
    });

    it('should show initiating state when test is initiating', () => {
      render(<ReplayPanel {...defaultProps} replayData={[]} isTestInitiating />);
      expect(screen.getByText('Starting test...')).toBeTruthy();
    });

    it('should show instructional text during test', () => {
      render(<ReplayPanel {...defaultProps} replayData={[]} isTestRunning />);
      expect(screen.getByText('Trigger the selected event on your Drupal site so that the workflow gets executed and the results are captured.')).toBeTruthy();
    });

    it('should show cancel button when test is running', () => {
      const onCancelTest = jest.fn();
      render(<ReplayPanel {...defaultProps} replayData={[]} isTestRunning onCancelTest={onCancelTest} />);
      const cancelBtn = screen.getByLabelText('Cancel test');
      expect(cancelBtn).toBeTruthy();
    });

    it('should call onCancelTest when cancel button is clicked', () => {
      const onCancelTest = jest.fn();
      render(<ReplayPanel {...defaultProps} replayData={[]} isTestRunning onCancelTest={onCancelTest} />);
      fireEvent.click(screen.getByLabelText('Cancel test'));
      expect(onCancelTest).toHaveBeenCalled();
    });

    it('should not show cancel button when test is only initiating', () => {
      render(<ReplayPanel {...defaultProps} replayData={[]} isTestInitiating onCancelTest={jest.fn()} />);
      expect(screen.queryByLabelText('Cancel test')).toBeNull();
    });

    it('should hide playback controls when test is running', () => {
      render(<ReplayPanel {...defaultProps} isTestRunning />);
      expect(screen.queryByTitle('Previous Step')).toBeNull();
      expect(screen.queryByTitle('Play')).toBeNull();
    });
  });

  describe('global tokens', () => {
    const sampleGlobalTokens = {
      '[site:name]': {
        name: 'Site name',
        'raw token': '[site:name]',
        token: 'name',
        value: 'My Site',
      },
      '[current-date:custom:?]': {
        name: 'Custom format',
        'raw token': '[current-date:custom:?]',
        token: 'custom:?',
        value: '2026-02-13',
      },
    } as any;

    it('should render Global Tokens section in empty state when globalTokens provided', () => {
      render(<ReplayPanel {...defaultProps} replayData={[]} globalTokens={sampleGlobalTokens} />);
      expect(screen.getByText('Global Tokens')).toBeTruthy();
      expect(screen.getByTestId('global-tokens-container')).toBeTruthy();
    });

    it('should render Global Tokens section in replay state when globalTokens provided', () => {
      render(<ReplayPanel {...defaultProps} globalTokens={sampleGlobalTokens} />);
      expect(screen.getByText('Global Tokens')).toBeTruthy();
      expect(screen.getByTestId('global-tokens-container')).toBeTruthy();
    });

    it('should not render Global Tokens section when globalTokens is undefined', () => {
      render(<ReplayPanel {...defaultProps} />);
      expect(screen.queryByText('Global Tokens')).toBeNull();
      expect(screen.queryByTestId('global-tokens-container')).toBeNull();
    });

    it('should not render Global Tokens section when globalTokens is empty', () => {
      render(<ReplayPanel {...defaultProps} globalTokens={{} as any} />);
      expect(screen.queryByText('Global Tokens')).toBeNull();
      expect(screen.queryByTestId('global-tokens-container')).toBeNull();
    });

    it('should show drag hint in global tokens section', () => {
      render(<ReplayPanel {...defaultProps} replayData={[]} globalTokens={sampleGlobalTokens} />);
      expect(screen.getByText('Drag tokens into configuration fields to insert them.')).toBeTruthy();
    });

    it('should render Global Tokens section with global-tokens-section class', () => {
      render(<ReplayPanel {...defaultProps} globalTokens={sampleGlobalTokens} />);
      const section = document.querySelector('.global-tokens-section');
      expect(section).toBeTruthy();
    });
  });

  describe('template tokens', () => {
    const sampleTemplateTokens = {
      '[template:author]': {
        name: 'Author',
        'raw token': '[template:author]',
        token: 'author',
        value: 'Jane Doe',
      },
      '[template:version]': {
        name: 'Version',
        'raw token': '[template:version]',
        token: 'version',
        value: '1.0.0',
      },
    } as any;

    it('should render Template Tokens section in empty state when isTemplate and templateTokens provided', () => {
      render(<ReplayPanel {...defaultProps} replayData={[]} isTemplate templateTokens={sampleTemplateTokens} />);
      expect(screen.getByText('Template Tokens')).toBeTruthy();
      expect(screen.getByTestId('template-tokens-container')).toBeTruthy();
    });

    it('should render Template Tokens section in replay state when isTemplate and templateTokens provided', () => {
      render(<ReplayPanel {...defaultProps} isTemplate templateTokens={sampleTemplateTokens} />);
      expect(screen.getByText('Template Tokens')).toBeTruthy();
      expect(screen.getByTestId('template-tokens-container')).toBeTruthy();
    });

    it('should not render Template Tokens section when isTemplate is false', () => {
      render(<ReplayPanel {...defaultProps} isTemplate={false} templateTokens={sampleTemplateTokens} />);
      expect(screen.queryByText('Template Tokens')).toBeNull();
      expect(screen.queryByTestId('template-tokens-container')).toBeNull();
    });

    it('should not render Template Tokens section when isTemplate is true but templateTokens is undefined', () => {
      render(<ReplayPanel {...defaultProps} isTemplate />);
      expect(screen.queryByText('Template Tokens')).toBeNull();
      expect(screen.queryByTestId('template-tokens-container')).toBeNull();
    });

    it('should not render Template Tokens section when isTemplate is true but templateTokens is empty', () => {
      render(<ReplayPanel {...defaultProps} isTemplate templateTokens={{} as any} />);
      expect(screen.queryByText('Template Tokens')).toBeNull();
      expect(screen.queryByTestId('template-tokens-container')).toBeNull();
    });

    it('should render Template Tokens section with template-tokens-section class', () => {
      render(<ReplayPanel {...defaultProps} isTemplate templateTokens={sampleTemplateTokens} />);
      const section = document.querySelector('.template-tokens-section');
      expect(section).toBeTruthy();
    });

    it('should show drag hint in template tokens section', () => {
      render(<ReplayPanel {...defaultProps} replayData={[]} isTemplate templateTokens={sampleTemplateTokens} />);
      const hints = screen.getAllByText('Drag tokens into configuration fields to insert them.');
      expect(hints.length).toBeGreaterThanOrEqual(1);
    });

    it('should render both Global and Template Tokens sections when both are available', () => {
      const sampleGlobalTokens = {
        '[site:name]': {
          name: 'Site name',
          'raw token': '[site:name]',
          token: 'name',
          value: 'My Site',
        },
      } as any;
      render(<ReplayPanel {...defaultProps} globalTokens={sampleGlobalTokens} isTemplate templateTokens={sampleTemplateTokens} />);
      expect(screen.getByText('Global Tokens')).toBeTruthy();
      expect(screen.getByText('Template Tokens')).toBeTruthy();
      expect(screen.getByTestId('global-tokens-container')).toBeTruthy();
      expect(screen.getByTestId('template-tokens-container')).toBeTruthy();
    });
  });
});
