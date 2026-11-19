/**
 * @file
 * Menu buttons and the bulk actions bar of the Upgrade Status pages.
 */

((Drupal, once) => {
  /**
   * Turns the export details elements into menu buttons.
   *
   * The details element opens and closes the menu. This adds aria-expanded,
   * and closes the menu on Escape, on outside clicks and on a choice.
   */
  Drupal.behaviors.upgradeStatusMenus = {
    attach(context) {
      once('us-menu', '[data-us-menu]', context).forEach((menu) => {
        const button = menu.querySelector('summary');
        const sync = () => {
          button.setAttribute('aria-expanded', menu.open ? 'true' : 'false');
        };
        sync();
        menu.addEventListener('toggle', sync);
        menu.addEventListener('keydown', (event) => {
          if (event.key === 'Escape' && menu.open) {
            menu.open = false;
            button.focus();
          }
        });
        document.addEventListener('click', (event) => {
          if (menu.open && !menu.contains(event.target)) {
            menu.open = false;
          }
        });
        menu.querySelectorAll('[role="menuitem"]').forEach((item) => {
          item.addEventListener('click', () => {
            // Close after the click, so a submit button still submits.
            setTimeout(() => {
              menu.open = false;
              button.focus();
              // An export downloads a file and keeps the page. Core's
              // double-submit protection would then block the next export
              // or analysis with the same selection, so the stored values
              // are removed.
              if (item.form) {
                item.form.removeAttribute('data-drupal-form-submit-last');
              }
            });
          });
        });
      });
    },
  };

  /**
   * Opens a collapsed section when a link points to it.
   */
  Drupal.behaviors.upgradeStatusAnchors = {
    attach(context) {
      once('us-anchors', 'a[href^="#"]', context).forEach((link) => {
        link.addEventListener('click', () => {
          const target = document.getElementById(link.hash.slice(1));
          if (target && target.tagName === 'DETAILS') {
            target.open = true;
          }
        });
      });
    },
  };

  /**
   * Copies the path of a file, or the Composer command of the tool updates.
   *
   * A button with a value in data-us-copy copies that value. A button
   * without a value copies the command next to it. The clipboard API needs a
   * secure context, so the button is hidden without it.
   */
  Drupal.behaviors.upgradeStatusCopy = {
    attach(context) {
      once('us-copy', '[data-us-copy]', context).forEach((button) => {
        const path = button.getAttribute('data-us-copy');
        const code = button.parentElement.querySelector('[data-us-command]');
        if (!navigator.clipboard || (!path && !code)) {
          button.hidden = true;
          return;
        }
        let timer;
        const reset = () => button.classList.remove('is-copied');
        button.addEventListener('click', () => {
          navigator.clipboard.writeText(path || code.textContent).then(() => {
            Drupal.announce(
              path ? Drupal.t('Path copied.') : Drupal.t('Command copied.'),
            );
            // Show the confirmation icon for a short time.
            button.classList.add('is-copied');
            clearTimeout(timer);
            timer = setTimeout(reset, 2000);
          });
        });
      });
    },
  };

  /**
   * Shows the bulk actions bar while projects are selected.
   */
  Drupal.behaviors.upgradeStatusSelection = {
    attach(context) {
      once('us-selection', 'form.upgrade-status', context).forEach((form) => {
        const bar = form.querySelector('[data-us-bulk-bar]');
        if (!bar) {
          return;
        }
        const count = bar.querySelector('[data-us-count]');
        const warning = bar.querySelector('[data-us-warning]');
        const boxes = () =>
          Array.from(form.querySelectorAll('input.us-select'));

        const update = () => {
          const selected = boxes().filter((box) => box.checked);
          bar.classList.toggle('is-empty', selected.length === 0);
          count.textContent = Drupal.formatPlural(
            selected.length,
            '1 project selected',
            '@count projects selected',
          );
          const notAnalyzed = selected.filter(
            (box) => box.dataset.usAnalyzed === '0',
          ).length;
          warning.hidden = notAnalyzed === 0;
          warning.textContent = notAnalyzed
            ? Drupal.formatPlural(
                notAnalyzed,
                '1 selected project is not analyzed yet, so the export has no results.',
                '@count selected projects are not analyzed yet, so the exports have no results.',
              )
            : '';
          form.querySelectorAll('[data-us-select-all]').forEach((all) => {
            const group = form.querySelectorAll(
              `[data-us-group="${all.dataset.usSelectAll}"] input.us-select`,
            );
            all.checked =
              group.length > 0 && Array.from(group).every((box) => box.checked);
          });
        };

        form.addEventListener('change', (event) => {
          const all = event.target.closest('[data-us-select-all]');
          if (all) {
            form
              .querySelectorAll(
                `[data-us-group="${all.dataset.usSelectAll}"] input.us-select`,
              )
              .forEach((box) => {
                box.checked = all.checked;
              });
          }
          // The table select-all script changes the boxes after this event.
          setTimeout(update);
        });

        const clear = bar.querySelector('[data-us-clear]');
        if (clear) {
          clear.addEventListener('click', () => {
            form
              .querySelectorAll(
                'input.us-select, [data-us-select-all], th.select-all input',
              )
              .forEach((box) => {
                box.checked = false;
                box.closest('tr')?.classList.remove('selected');
              });
            update();
          });
        }
        update();
      });
    },
  };
})(Drupal, once);
