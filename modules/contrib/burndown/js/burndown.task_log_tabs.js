/**
 * @file
 * Tab switching behavior for rendered Burndown task log field output.
 */
(function (Drupal, once) {
  Drupal.behaviors.burndownTaskLogTabs = {
    attach(context) {
      once('burndownTaskLogTabs', '.burndown-log-tabs', context).forEach((wrapper) => {
        const tabs = wrapper.querySelectorAll('.log_tabs a[data-log-tab]');
        const panels = wrapper.querySelectorAll('.burndown-task-log-panel[data-log-panel]');

        const activate = (target) => {
          tabs.forEach((tab) => {
            const active = tab.getAttribute('data-log-tab') === target;
            tab.classList.toggle('is-active', active);
          });

          panels.forEach((panel) => {
            const active = panel.getAttribute('data-log-panel') === target;
            panel.classList.toggle('is-active', active);
            panel.classList.toggle('is-hidden', !active);
          });
        };

        tabs.forEach((tab) => {
          tab.addEventListener('click', (event) => {
            event.preventDefault();
            activate(tab.getAttribute('data-log-tab'));
          });
        });

        const current = wrapper.querySelector('.log_tabs a.is-active[data-log-tab]');
        activate(current ? current.getAttribute('data-log-tab') : 'all');
      });
    },
  };
})(Drupal, once);
