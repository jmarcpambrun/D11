/**
 * Progressive enhancement of Maestro action links into a split button.
 * A disclosure of ordinary links, not an ARIA application menu.
 */
(function (Drupal, once) {
  'use strict';
  let active = null;
  const close = (restoreFocus = false) => {
    if (!active) return;
    const { menu, toggle } = active;
    if (menu.hasAttribute('popover') && menu.matches(':popover-open')) menu.hidePopover();
    menu.hidden = true;
    toggle.setAttribute('aria-expanded', 'false');
    active = null;
    if (restoreFocus) toggle.focus();
  };
  const position = () => {
    if (!active) return;
    const { menu, toggle } = active;
    const rect = toggle.getBoundingClientRect();
    const bounds = menu.getBoundingClientRect();
    menu.style.left = `${Math.max(8, Math.min(rect.right - bounds.width, window.innerWidth - bounds.width - 8))}px`;
    menu.style.top = `${Math.max(8, rect.bottom + bounds.height + 4 <= window.innerHeight ? rect.bottom + 4 : rect.top - bounds.height - 4)}px`;
  };
  Drupal.behaviors.maestroActions = {
    attach(context) {
      const wrappers = Array.from(context.querySelectorAll('.maestro-actions'));
      if (context.matches?.('.maestro-actions')) wrappers.unshift(context);
      once('maestro-actions', wrappers).forEach((wrapper) => {
        const toggle = wrapper.querySelector('.maestro-actions__toggle');
        const menu = wrapper.querySelector('.maestro-actions__menu');
        if (!toggle || !menu) return;
        wrapper.dataset.enhanced = 'true';
        menu.hidden = true;
        toggle.hidden = false;
        // The top layer escapes scrollable tables and theme stacking contexts.
        if (typeof menu.showPopover === 'function') menu.setAttribute('popover', 'manual');
        const open = (focusLink) => {
          close();
          menu.hidden = false;
          if (menu.hasAttribute('popover')) menu.showPopover();
          toggle.setAttribute('aria-expanded', 'true');
          active = { wrapper, menu, toggle };
          position();
          if (focusLink) menu.querySelector('a')?.focus();
        };
        toggle.addEventListener('click', () => active?.toggle === toggle ? close() : open(false));
        toggle.addEventListener('keydown', (event) => {
          if (event.key === 'ArrowDown') {
            event.preventDefault();
            open(true);
          }
        });
        menu.addEventListener('click', (event) => {
          if (event.target.closest('a')) close();
        });
      });
      once('maestro-actions-events', 'html').forEach(() => {
        document.addEventListener('pointerdown', (event) => {
          if (active && !active.wrapper.contains(event.target)) close();
        });
        document.addEventListener('focusin', (event) => {
          if (active && !active.wrapper.contains(event.target)) close();
        });
        document.addEventListener('keydown', (event) => {
          if (active && event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            close(true);
          }
        }, true);
        window.addEventListener('resize', position);
        document.addEventListener('scroll', (event) => {
          if (active && !active.menu.contains(event.target)) position();
        }, true);
      });
    },
    detach(context, settings, trigger) {
      if (trigger === 'unload' && active && context.contains(active.wrapper)) close();
    },
  };
})(Drupal, once);
