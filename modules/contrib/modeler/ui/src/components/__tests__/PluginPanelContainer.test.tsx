import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import PluginPanelSlot, { PluginPanel } from '../PluginPanelContainer';
import type { RegisteredPanel, ModelerPluginApi } from '../../types/pluginApi';

// ── Mocks ─────────────────────────────────────────────────────────────

// Mock react-icons
jest.mock('react-icons/fi', () => ({
  FiChevronLeft: (props: any) => <span data-testid="fi-chevron-left" {...props} />,
  FiChevronRight: (props: any) => <span data-testid="fi-chevron-right" {...props} />,
}));

// Mock PanelErrorBoundary
jest.mock('../PanelErrorBoundary', () => {
  const MockPEB = ({ children, panelName, className }: any) => (
    <div data-testid={`error-boundary-${panelName}`} className={className}>
      {children}
    </div>
  );
  MockPEB.displayName = 'MockPanelErrorBoundary';
  return { __esModule: true, default: MockPEB };
});

// Mock usePanelResize
const mockStartResize = jest.fn();
let mockUsePanelResizeArgs: any = null;
jest.mock('../../hooks/usePanelResize', () => ({
  usePanelResize: jest.fn((args: any) => {
    mockUsePanelResizeArgs = args;
    return { startResize: mockStartResize };
  }),
}));

// Mock translation
jest.mock('../../utils/translation', () => ({
  t: (str: string, args?: Record<string, string>) => {
    if (args) {
      let result = str;
      for (const [key, value] of Object.entries(args)) {
        result = result.replace(key, value);
      }
      return result;
    }
    return str;
  },
}));

// ── Helpers ───────────────────────────────────────────────────────────

const mockRender = jest.fn();
const mockDestroy = jest.fn();
const mockOnResize = jest.fn();

function createMockPanel(overrides: Partial<RegisteredPanel> = {}): RegisteredPanel {
  return {
    id: 'test-panel',
    label: 'Test Panel',
    render: mockRender,
    destroy: mockDestroy,
    onResize: mockOnResize,
    position: 'right',
    weight: 0,
    width: 320,
    ...overrides,
  };
}

const mockApi = {} as ModelerPluginApi;

// ── Tests ─────────────────────────────────────────────────────────────

describe('PluginPanelContainer', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockUsePanelResizeArgs = null;
  });

  // ── PluginPanel ───────────────────────────────────────────────────

  describe('PluginPanel', () => {
    describe('rendering', () => {
      it('renders panel content area and collapse button', () => {
        const panel = createMockPanel();
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        // Plugin panel content region
        expect(container.querySelector('.plugin-panel-content')).toBeInTheDocument();
        // Collapse button
        expect(container.querySelector('.plugin-panel-collapse-tab')).toBeInTheDocument();
      });

      it('sets data-plugin-panel-id attribute', () => {
        const panel = createMockPanel({ id: 'my-custom-panel' });
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        expect(container.querySelector('[data-plugin-panel-id="my-custom-panel"]')).toBeInTheDocument();
      });

      it('applies position class', () => {
        const panel = createMockPanel({ position: 'right' });
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        expect(container.querySelector('.plugin-panel--right')).toBeInTheDocument();
      });

      it('applies left position class', () => {
        const panel = createMockPanel({ position: 'left' });
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        expect(container.querySelector('.plugin-panel--left')).toBeInTheDocument();
      });

      it('sets initial width from panel.width', () => {
        const panel = createMockPanel({ width: 400 });
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        const panelEl = container.querySelector('.plugin-panel') as HTMLElement;
        expect(panelEl.style.width).toBe('400px');
      });

      it('renders content region with aria-label', () => {
        const panel = createMockPanel({ label: 'Analytics' });
        render(<PluginPanel panel={panel} api={mockApi} />);

        expect(screen.getByRole('region', { name: 'Analytics' })).toBeInTheDocument();
      });
    });

    describe('mount lifecycle', () => {
      it('calls render() on mount with container element and API', () => {
        const panel = createMockPanel();
        render(<PluginPanel panel={panel} api={mockApi} />);

        expect(mockRender).toHaveBeenCalledTimes(1);
        expect(mockRender).toHaveBeenCalledWith(
          expect.any(HTMLDivElement),
          mockApi
        );
      });

      it('handles render() error gracefully', () => {
        const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});
        const failingRender = jest.fn(() => {
          throw new Error('Render boom');
        });
        const panel = createMockPanel({ render: failingRender });

        // Should not throw
        render(<PluginPanel panel={panel} api={mockApi} />);

        expect(errorSpy).toHaveBeenCalledWith(
          'Plugin panel "test-panel" render() failed:',
          expect.any(Error)
        );
        errorSpy.mockRestore();
      });
    });

    describe('unmount lifecycle', () => {
      it('calls destroy() on unmount', () => {
        const panel = createMockPanel();
        const { unmount } = render(<PluginPanel panel={panel} api={mockApi} />);

        expect(mockDestroy).not.toHaveBeenCalled();
        unmount();
        expect(mockDestroy).toHaveBeenCalledTimes(1);
        expect(mockDestroy).toHaveBeenCalledWith(expect.any(HTMLDivElement));
      });

      it('handles destroy() error gracefully', () => {
        const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});
        const failingDestroy = jest.fn(() => {
          throw new Error('Destroy boom');
        });
        const panel = createMockPanel({ destroy: failingDestroy });
        const { unmount } = render(<PluginPanel panel={panel} api={mockApi} />);

        unmount();

        expect(errorSpy).toHaveBeenCalledWith(
          'Plugin panel "test-panel" destroy() failed:',
          expect.any(Error)
        );
        errorSpy.mockRestore();
      });

      it('handles missing destroy function gracefully', () => {
        const panel = createMockPanel({ destroy: undefined });
        const { unmount } = render(<PluginPanel panel={panel} api={mockApi} />);

        // Should not throw when destroy is undefined
        expect(() => unmount()).not.toThrow();
      });
    });

    describe('collapse/expand', () => {
      it('toggles collapse on button click', () => {
        const panel = createMockPanel();
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        // Initially expanded
        expect(container.querySelector('.plugin-panel-content')).toBeInTheDocument();
        expect(container.querySelector('.plugin-panel.collapsed')).not.toBeInTheDocument();

        // Click collapse
        const button = container.querySelector('.plugin-panel-collapse-tab') as HTMLElement;
        fireEvent.click(button);

        // Now collapsed - content hidden
        expect(container.querySelector('.plugin-panel-content')).not.toBeInTheDocument();
        expect(container.querySelector('.plugin-panel.collapsed')).toBeInTheDocument();
      });

      it('shows panel label when collapsed', () => {
        const panel = createMockPanel({ label: 'My Panel' });
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        // Initially no collapse label
        expect(container.querySelector('.plugin-panel-collapse-label')).not.toBeInTheDocument();

        // Collapse
        fireEvent.click(container.querySelector('.plugin-panel-collapse-tab') as HTMLElement);

        // Label should appear
        const label = container.querySelector('.plugin-panel-collapse-label');
        expect(label).toBeInTheDocument();
        expect(label?.textContent).toBe('My Panel');
      });

      it('sets aria-expanded=true when expanded', () => {
        const panel = createMockPanel();
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        const button = container.querySelector('.plugin-panel-collapse-tab') as HTMLElement;
        expect(button.getAttribute('aria-expanded')).toBe('true');
      });

      it('sets aria-expanded=false when collapsed', () => {
        const panel = createMockPanel();
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        const button = container.querySelector('.plugin-panel-collapse-tab') as HTMLElement;
        fireEvent.click(button);
        expect(button.getAttribute('aria-expanded')).toBe('false');
      });

      it('uses correct aria-label for expand/collapse', () => {
        const panel = createMockPanel({ label: 'Analytics' });
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        const button = container.querySelector('.plugin-panel-collapse-tab') as HTMLElement;
        expect(button.getAttribute('aria-label')).toBe('Collapse Analytics panel');

        fireEvent.click(button);
        expect(button.getAttribute('aria-label')).toBe('Expand Analytics panel');
      });

      it('re-expands when clicking collapse button again', () => {
        const panel = createMockPanel();
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        const button = container.querySelector('.plugin-panel-collapse-tab') as HTMLElement;

        // Collapse
        fireEvent.click(button);
        expect(container.querySelector('.plugin-panel-content')).not.toBeInTheDocument();

        // Expand
        fireEvent.click(button);
        expect(container.querySelector('.plugin-panel-content')).toBeInTheDocument();
      });

      it('sets collapsed width when collapsed', () => {
        const panel = createMockPanel();
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        const panelEl = container.querySelector('.plugin-panel') as HTMLElement;

        // Collapse
        fireEvent.click(container.querySelector('.plugin-panel-collapse-tab') as HTMLElement);

        // COLLAPSED_WIDTH is 40
        expect(panelEl.style.width).toBe('40px');
      });
    });

    describe('collapse icons', () => {
      it('shows FiChevronRight when expanded and position=right', () => {
        const panel = createMockPanel({ position: 'right' });
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        // Expanded + right → FiChevronRight
        expect(container.querySelector('.plugin-panel-collapse-tab [data-testid="fi-chevron-right"]')).toBeInTheDocument();
        expect(container.querySelector('.plugin-panel-collapse-tab [data-testid="fi-chevron-left"]')).not.toBeInTheDocument();
      });

      it('shows FiChevronLeft when collapsed and position=right', () => {
        const panel = createMockPanel({ position: 'right' });
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        fireEvent.click(container.querySelector('.plugin-panel-collapse-tab') as HTMLElement);

        // Collapsed + right → FiChevronLeft
        expect(container.querySelector('.plugin-panel-collapse-tab [data-testid="fi-chevron-left"]')).toBeInTheDocument();
        expect(container.querySelector('.plugin-panel-collapse-tab [data-testid="fi-chevron-right"]')).not.toBeInTheDocument();
      });

      it('shows FiChevronLeft when expanded and position=left', () => {
        const panel = createMockPanel({ position: 'left' });
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        // Expanded + left → FiChevronLeft (reversed from right)
        expect(container.querySelector('.plugin-panel-collapse-tab [data-testid="fi-chevron-left"]')).toBeInTheDocument();
        expect(container.querySelector('.plugin-panel-collapse-tab [data-testid="fi-chevron-right"]')).not.toBeInTheDocument();
      });

      it('shows FiChevronRight when collapsed and position=left', () => {
        const panel = createMockPanel({ position: 'left' });
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        fireEvent.click(container.querySelector('.plugin-panel-collapse-tab') as HTMLElement);

        // Collapsed + left → FiChevronRight (reversed from right)
        expect(container.querySelector('.plugin-panel-collapse-tab [data-testid="fi-chevron-right"]')).toBeInTheDocument();
        expect(container.querySelector('.plugin-panel-collapse-tab [data-testid="fi-chevron-left"]')).not.toBeInTheDocument();
      });
    });

    describe('resize handle', () => {
      it('shows resize handle when expanded', () => {
        const panel = createMockPanel();
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        expect(container.querySelector('.plugin-panel-resize-handle')).toBeInTheDocument();
      });

      it('hides resize handle when collapsed', () => {
        const panel = createMockPanel();
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        fireEvent.click(container.querySelector('.plugin-panel-collapse-tab') as HTMLElement);

        expect(container.querySelector('.plugin-panel-resize-handle')).not.toBeInTheDocument();
      });

      it('resize handle has correct position class for right panel', () => {
        const panel = createMockPanel({ position: 'right' });
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        expect(container.querySelector('.plugin-panel-resize-handle--left')).toBeInTheDocument();
      });

      it('resize handle has correct position class for left panel', () => {
        const panel = createMockPanel({ position: 'left' });
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        expect(container.querySelector('.plugin-panel-resize-handle--right')).toBeInTheDocument();
      });

      it('triggers startResize on mousedown', () => {
        const panel = createMockPanel();
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        const handle = container.querySelector('.plugin-panel-resize-handle') as HTMLElement;
        fireEvent.mouseDown(handle);

        expect(mockStartResize).toHaveBeenCalledTimes(1);
      });

      it('resize handle has correct a11y attributes', () => {
        const panel = createMockPanel({ label: 'My Panel' });
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        const handle = container.querySelector('.plugin-panel-resize-handle') as HTMLElement;
        expect(handle.getAttribute('role')).toBe('separator');
        expect(handle.getAttribute('aria-orientation')).toBe('vertical');
        expect(handle.getAttribute('aria-label')).toBe('Resize My Panel panel');
        expect(handle.getAttribute('tabindex')).toBe('0');
      });
    });

    describe('usePanelResize integration', () => {
      it('passes correct direction for right panel', () => {
        const panel = createMockPanel({ position: 'right' });
        render(<PluginPanel panel={panel} api={mockApi} />);

        expect(mockUsePanelResizeArgs.direction).toBe('left');
      });

      it('passes correct direction for left panel', () => {
        const panel = createMockPanel({ position: 'left' });
        render(<PluginPanel panel={panel} api={mockApi} />);

        expect(mockUsePanelResizeArgs.direction).toBe('right');
      });

      it('passes panel width and dimension constants', () => {
        const panel = createMockPanel({ width: 350 });
        render(<PluginPanel panel={panel} api={mockApi} />);

        expect(mockUsePanelResizeArgs.panelWidth).toBe(350);
        expect(mockUsePanelResizeArgs.minWidth).toBe(200); // PLUGIN_PANEL.MIN_WIDTH
        expect(mockUsePanelResizeArgs.maxWidth).toBe(600); // PLUGIN_PANEL.MAX_WIDTH
      });
    });

    describe('onResize notification', () => {
      it('calls onResize when isResizing changes to false', () => {
        // The onResize effect fires when isResizing is false (initial render)
        // and containerRef.current exists. On mount, the effect fires after render.
        const panel = createMockPanel();
        render(<PluginPanel panel={panel} api={mockApi} />);

        // onResize is called on the initial effect run because isResizing starts as false
        expect(mockOnResize).toHaveBeenCalled();
      });

      it('calls onResize with element dimensions', () => {
        const panel = createMockPanel();
        render(<PluginPanel panel={panel} api={mockApi} />);

        // getBoundingClientRect returns 0s in jsdom, but the call should happen
        expect(mockOnResize).toHaveBeenCalledWith(
          expect.any(Number),
          expect.any(Number)
        );
      });

      it('handles onResize error gracefully', () => {
        const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {});
        const failingOnResize = jest.fn(() => {
          throw new Error('Resize boom');
        });
        const panel = createMockPanel({ onResize: failingOnResize });

        render(<PluginPanel panel={panel} api={mockApi} />);

        expect(errorSpy).toHaveBeenCalledWith(
          'Plugin panel "test-panel" onResize() failed:',
          expect.any(Error)
        );
        errorSpy.mockRestore();
      });

      it('does not call onResize when panel has no onResize callback', () => {
        const panel = createMockPanel({ onResize: undefined });
        // Should not throw
        expect(() => render(<PluginPanel panel={panel} api={mockApi} />)).not.toThrow();
      });
    });

    describe('CSS classes', () => {
      it('includes position class', () => {
        const panel = createMockPanel({ position: 'right' });
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        const el = container.querySelector('.plugin-panel') as HTMLElement;
        expect(el.classList.contains('plugin-panel--right')).toBe(true);
      });

      it('includes collapsed class when collapsed', () => {
        const panel = createMockPanel();
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        const el = container.querySelector('.plugin-panel') as HTMLElement;
        expect(el.classList.contains('collapsed')).toBe(false);

        fireEvent.click(container.querySelector('.plugin-panel-collapse-tab') as HTMLElement);
        expect(el.classList.contains('collapsed')).toBe(true);
      });

      it('does not have is-resizing class by default', () => {
        const panel = createMockPanel();
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        const el = container.querySelector('.plugin-panel') as HTMLElement;
        expect(el.classList.contains('is-resizing')).toBe(false);
      });
    });

    describe('title attribute', () => {
      it('sets title on collapse button', () => {
        const panel = createMockPanel({ label: 'Metrics' });
        const { container } = render(<PluginPanel panel={panel} api={mockApi} />);

        const button = container.querySelector('.plugin-panel-collapse-tab') as HTMLElement;
        expect(button.getAttribute('title')).toBe('Metrics');
      });
    });
  });

  // ── PluginPanelSlot ─────────────────────────────────────────────────

  describe('PluginPanelSlot', () => {
    it('returns null when panels array is empty', () => {
      const { container } = render(
        <PluginPanelSlot panels={[]} api={mockApi} position="right" />
      );

      expect(container.innerHTML).toBe('');
    });

    it('renders panels wrapped in error boundaries', () => {
      const panels = [
        createMockPanel({ id: 'panel-a', label: 'Panel A' }),
        createMockPanel({ id: 'panel-b', label: 'Panel B' }),
      ];

      render(<PluginPanelSlot panels={panels} api={mockApi} position="right" />);

      expect(screen.getByTestId('error-boundary-Panel A')).toBeInTheDocument();
      expect(screen.getByTestId('error-boundary-Panel B')).toBeInTheDocument();
    });

    it('renders a panel for each item in the array', () => {
      const panels = [
        createMockPanel({ id: 'p1', label: 'P1' }),
        createMockPanel({ id: 'p2', label: 'P2' }),
        createMockPanel({ id: 'p3', label: 'P3' }),
      ];

      const { container } = render(
        <PluginPanelSlot panels={panels} api={mockApi} position="right" />
      );

      const panelElements = container.querySelectorAll('[data-plugin-panel-id]');
      expect(panelElements).toHaveLength(3);
    });

    it('applies position class to slot container', () => {
      const panels = [createMockPanel()];
      const { container } = render(
        <PluginPanelSlot panels={panels} api={mockApi} position="left" />
      );

      expect(container.querySelector('.plugin-panel-slot--left')).toBeInTheDocument();
    });

    it('applies plugin-panel-slot base class', () => {
      const panels = [createMockPanel()];
      const { container } = render(
        <PluginPanelSlot panels={panels} api={mockApi} position="right" />
      );

      expect(container.querySelector('.plugin-panel-slot')).toBeInTheDocument();
    });

    it('passes plugin-panel-error className to error boundary', () => {
      const panels = [createMockPanel({ label: 'Test Panel' })];
      render(<PluginPanelSlot panels={panels} api={mockApi} position="right" />);

      const boundary = screen.getByTestId('error-boundary-Test Panel');
      expect(boundary.className).toBe('plugin-panel-error');
    });

    it('renders single panel correctly', () => {
      const panels = [createMockPanel({ id: 'solo', label: 'Solo Panel' })];
      const { container } = render(
        <PluginPanelSlot panels={panels} api={mockApi} position="right" />
      );

      expect(container.querySelector('[data-plugin-panel-id="solo"]')).toBeInTheDocument();
      expect(screen.getByTestId('error-boundary-Solo Panel')).toBeInTheDocument();
    });
  });
});
