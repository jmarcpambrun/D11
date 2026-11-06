/**
 * @file
 * Task sorting, keyboard support and navigation for Maestro task forms.
 */
(function (Drupal, once) {
  'use strict';

  const mayLeave = (wrapper) => !wrapper || !wrapper.dataset.maestroDirty || window.confirm(Drupal.t('Discard your unsaved changes and leave this task?'));
  // AJAX insertions may pass the new wrapper itself as the behavior context.
  const elements = (selector, context) => {
    const matches = Array.from(context.querySelectorAll(selector));
    if (context.nodeType === 1 && context.matches(selector)) matches.unshift(context);
    return matches;
  };

  const reportScrollCleanup = new WeakMap();
  let reportScrollId = 0;

  Drupal.behaviors.maestroTaskConsole = {
    attach(context) {
      once('maestro-report-scroll', elements('.maestro-task-report .view-content', context)).forEach((region) => {
        if (region.closest('.maestro-attached-view')) return;
        const table = region.querySelector('.maestro-report-table');
        if (!table) return;
        region.classList.add('maestro-report-region');
        region.setAttribute('tabindex', '0');
        region.setAttribute('role', 'region');
        region.setAttribute('aria-label', Drupal.t('Workflow task report'));
        if (!region.id) region.id = `maestro-report-scroll-${++reportScrollId}`;
        // Themes may add their own scroll wrapper, even after behaviors attach.
        const scrollContainer = () => {
          for (let parent = table.parentElement; parent && parent !== region; parent = parent.parentElement) {
            if (['auto', 'scroll'].includes(getComputedStyle(parent).overflowX)) return parent;
          }
          return region;
        };
        const controls = document.createElement('div');
        controls.className = 'maestro-report-scroll';
        controls.hidden = true;
        const hint = document.createElement('span');
        hint.className = 'maestro-report-scroll__hint';
        const left = document.createElement('button');
        const right = document.createElement('button');
        [left, right].forEach((button, index) => {
          button.type = 'button';
          button.className = 'maestro-report-scroll__button';
          button.textContent = index ? '→' : '←';
          button.setAttribute('aria-label', Drupal.t(index ? 'Scroll columns right' : 'Scroll columns left'));
          button.setAttribute('aria-controls', region.id);
          button.addEventListener('click', () => scrollContainer().scrollBy({
            left: (index ? 1 : -1) * scrollContainer().clientWidth * 0.75,
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth',
          }));
        });
        controls.append(hint, left, right);
        const frame = document.createElement('div');
        frame.className = 'maestro-report-scroll-frame';
        const shell = document.createElement('div');
        shell.className = 'maestro-report-scroll-shell';
        region.before(shell);
        shell.append(controls, frame);
        frame.append(region);
        let observedScroller = null;
        const update = () => {
          const scroller = scrollContainer();
          if (observer && scroller !== observedScroller) {
            if (observedScroller && observedScroller !== region) observer.unobserve(observedScroller);
            observer.observe(scroller);
            observedScroller = scroller;
          }
          // Compare physical edges, so arrows also work for RTL tables.
          const viewport = scroller.getBoundingClientRect();
          const content = table.getBoundingClientRect();
          const hasLeft = content.left < viewport.left - 2;
          const hasRight = content.right > viewport.right + 2;
          controls.hidden = scroller.scrollWidth <= scroller.clientWidth + 2;
          left.disabled = !hasLeft;
          right.disabled = !hasRight;
          frame.classList.toggle('has-columns-left', !controls.hidden && hasLeft);
          frame.classList.toggle('has-columns-right', !controls.hidden && hasRight);
          hint.textContent = Drupal.t(hasLeft && hasRight ? 'More columns on both sides' : hasLeft ? 'More columns to the left' : 'More columns to the right');
        };
        region.addEventListener('scroll', update, { passive: true, capture: true });
        const observer = typeof ResizeObserver === 'undefined' ? null : new ResizeObserver(update);
        if (observer) {
          observer.observe(region);
          observer.observe(table);
        }
        const mutations = new MutationObserver(update);
        mutations.observe(region, { childList: true, subtree: true });
        window.addEventListener('resize', update);
        update();
        reportScrollCleanup.set(region, () => {
          observer?.disconnect();
          mutations.disconnect();
          window.removeEventListener('resize', update);
          region.removeEventListener('scroll', update, true);
          controls.remove();
          shell.replaceWith(region);
        });
      });
      once('maestro-table-sort', elements('.maestro-console .taskconsole-tasks', context)).forEach((table) => {
        const body = table.tBodies[0];
        if (!body || !body.querySelector('.task-record')) return;
        const headers = Array.from(table.tHead.querySelectorAll('[data-maestro-sort]'));
        const collator = new Intl.Collator(document.documentElement.lang || undefined, { numeric: true, sensitivity: 'base' });
        // Retain the original order for equal values in either direction.
        const originalOrder = new Map(Array.from(body.rows).map((row, index) => [row, index]));
        headers.forEach((header) => {
          const label = header.textContent.trim();
          const button = document.createElement('button');
          button.type = 'button';
          button.className = 'maestro-sort';
          button.textContent = label;
          const indicator = document.createElement('span');
          indicator.className = 'maestro-sort__indicator';
          indicator.setAttribute('aria-hidden', 'true');
          const initialSort = header.getAttribute('aria-sort');
          indicator.textContent = initialSort === 'descending' ? '↓' : initialSort === 'ascending' ? '↑' : '↕';
          button.append(indicator);
          button.setAttribute('aria-label', Drupal.t('Sort by @column ascending', { '@column': label }));
          header.replaceChildren(button);
          header.setAttribute('scope', 'col');
          button.addEventListener('click', () => {
            const ascending = header.getAttribute('aria-sort') !== 'ascending';
            const value = (row) => {
              const cell = row.cells[header.cellIndex];
              return cell.dataset.sortValue ?? cell.textContent.trim();
            };
            const rows = Array.from(body.rows).filter((row) => row.classList.contains('task-record'));
            // Capture companion rows before moving any task, preserving AJAX state.
            const details = new Map(rows.map((row) => [row, row.nextElementSibling?.classList.contains('maestro-ajax-row') ? row.nextElementSibling : null]));
            rows.sort((a, b) => {
              const left = value(a);
              const right = value(b);
              // Missing stages stay at the end for both directions.
              if ((left === '') !== (right === '')) return left === '' ? 1 : -1;
              const comparison = header.dataset.maestroSort === 'number'
                ? Number(left) - Number(right) : collator.compare(left, right);
              return comparison * (ascending ? 1 : -1) || originalOrder.get(a) - originalOrder.get(b);
            });
            rows.forEach((row) => {
              body.append(row);
              if (details.get(row)) body.append(details.get(row));
            });
            headers.forEach((item) => {
              const active = item === header;
              item.removeAttribute('aria-sort');
              if (active) item.setAttribute('aria-sort', ascending ? 'ascending' : 'descending');
              item.querySelector('.maestro-sort__indicator').textContent = active ? (ascending ? '↑' : '↓') : '↕';
              item.querySelector('button').setAttribute('aria-label', Drupal.t(
                active && ascending ? 'Sort by @column descending' : 'Sort by @column ascending',
                { '@column': item.querySelector('button').firstChild.textContent },
              ));
            });
          });
        });
      });
      once('maestro-details-keyboard', elements('.maestro-details-toggle', context)).forEach((toggle) => {
        toggle.addEventListener('keydown', (event) => {
          if (event.key === ' ') {
            event.preventDefault();
            toggle.click();
          }
        });
      });
      once('maestro-execution', elements('.maestro-execution', context)).forEach((wrapper) => {
        wrapper.addEventListener('input', () => { wrapper.dataset.maestroDirty = 'true'; });
        wrapper.addEventListener('change', () => { wrapper.dataset.maestroDirty = 'true'; });
        wrapper.addEventListener('click', (event) => {
          const cancel = event.target.closest('.maestro-dialog-cancel');
          const leave = event.target.closest('.maestro-leave-task');
          if (!cancel && !leave) return;
          if (!mayLeave(wrapper)) {
            event.preventDefault();
            return;
          }
          if (cancel) {
            event.preventDefault();
            const dialog = wrapper.closest('.ui-dialog-content');
            if (dialog) Drupal.dialog(dialog).close();
          }
        });
      });
      once('maestro-dialog-events', 'html').forEach(() => {
        // Capture user dismissal independently of Drupal's dialog event API,
        // which differs between supported Drupal 10 minor releases.
        document.addEventListener('click', (event) => {
          const close = event.target.closest('.ui-dialog-titlebar-close');
          const wrapper = close?.closest('.ui-dialog')?.querySelector('.maestro-execution');
          if (wrapper && !mayLeave(wrapper)) {
            event.preventDefault();
            event.stopImmediatePropagation();
          }
        }, true);
        document.addEventListener('keydown', (event) => {
          const wrapper = event.target.closest('.ui-dialog')?.querySelector('.maestro-execution');
          if (event.key === 'Escape' && wrapper && !mayLeave(wrapper)) {
            event.preventDefault();
            event.stopImmediatePropagation();
          }
        }, true);
      });
    },
    detach(context, settings, trigger) {
      if (trigger !== 'unload') return;
      once.remove('maestro-report-scroll', elements('.maestro-task-report .view-content', context)).forEach((region) => {
        reportScrollCleanup.get(region)?.();
        reportScrollCleanup.delete(region);
      });
    },
  };
})(Drupal, once);
