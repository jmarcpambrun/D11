/**
 * Present the existing Views results without replacing their links or AJAX.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.maestroViewsTaskConsole = {
    attach(context) {
      const selector = '.maestro-views-taskconsole';
      const views = new Set(context.querySelectorAll(selector));
      if (context.closest) {
        const parent = context.closest(selector);
        if (parent) views.add(parent);
      }
      views.forEach((view) => {
        const table = view.querySelector('.view-content table, table.maestro-sortable-table');
        if (!table) return;
        once('maestro-views-console-table', [table]).forEach(() => {
          table.classList.add('maestro-views-console-table');
          const wrapper = document.createElement('div');
          wrapper.className = 'maestro-views-console-table-wrapper';
          wrapper.tabIndex = 0;
          wrapper.setAttribute('role', 'region');
          wrapper.setAttribute('aria-label', Drupal.t('Available workflow tasks'));
          table.before(wrapper);
          wrapper.append(table);
          const rows = Array.from(table.tBodies[0].rows);
          const summary = document.createElement('p');
          summary.className = 'maestro-views-console-summary';
          summary.textContent = view.classList.contains('maestro-all-flows')
            ? Drupal.formatPlural(rows.length, '1 process on this page', '@count processes on this page')
            : Drupal.formatPlural(rows.length, '1 task on this page', '@count tasks on this page');
          wrapper.before(summary);
          const actionsHeader = table.querySelector('th.views-field-active-handler');
          if (actionsHeader && !actionsHeader.textContent.trim()) {
            actionsHeader.textContent = Drupal.t('Actions');
          }
          rows.forEach((row) => {
            row.classList.add('maestro-views-console-task');
            const details = row.querySelector('[id^="details_replace_column_"]');
            if (!details) return;
            const detailsRow = document.createElement('tr');
            detailsRow.id = details.id.replace('details_replace_column_', 'details_replace_row_');
            detailsRow.className = 'maestro-views-console-details-row';
            detailsRow.style.display = 'none';
            const cell = document.createElement('td');
            cell.colSpan = row.cells.length;
            cell.append(details);
            detailsRow.append(cell);
            row.after(detailsRow);
          });
        });
        // Maestro replaces the toggle on every AJAX response. Update its label
        // and accessible state after each replacement, including Views refreshes.
        once('maestro-views-console-toggle', view.querySelectorAll('.maestro-expand-wrapper a')).forEach((link) => {
          const expanded = link.classList.contains('maestro-status-toggle-up');
          const row = link.closest('tr');
          link.textContent = Drupal.t('Details');
          link.setAttribute('aria-expanded', String(expanded));
          if (row && row.nextElementSibling?.classList.contains('maestro-views-console-details-row')) {
            link.setAttribute('aria-controls', row.nextElementSibling.id);
            row.classList.toggle('maestro-views-console-task--expanded', expanded);
          }
        });
      });
    },
  };
})(Drupal, once);
