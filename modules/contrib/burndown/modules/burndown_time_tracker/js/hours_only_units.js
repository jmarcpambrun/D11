/**
 * @param $
 * @param Drupal
 * @param once
 * @file
 * Forces Burndown work-unit selectors to hours when time tracker is enabled.
 */
(function ($, Drupal, once) {
  Drupal.behaviors.burndownTimeTrackerHoursOnlyUnits = {
    attach(context) {
      function enforceHoursOnly($select) {
        if (!$select || !$select.length) {
          return;
        }

        $select.find('option').each(function () {
          if (this.value !== 'h') {
            $(this).remove();
          }
        });

        if ($select.find('option[value="h"]').length === 0) {
          $select.append($('<option/>', { value: 'h', text: 'h' }));
        }

        $select.get(0).value = 'h';
      }

      function attachHoursOnlyHelp($container) {
        if (!$container || !$container.length) {
          return;
        }

        if ($container.find('.burndown-hours-only-help').length) {
          return;
        }

        $('<div/>', {
          class: 'description burndown-hours-only-help',
          text: Drupal.t('Time entries are tracked in hours only.'),
        }).appendTo($container);
      }

      $(
        once(
          'burndown-hours-only-default-unit',
          '.add_work .add_work_quantity_type',
          context,
        ),
      ).each(function () {
        enforceHoursOnly($(this));
        attachHoursOnlyHelp($(this).closest('.add_work'));
      });

      $(once('burndown-hours-only-inline-edit', 'body', context)).on(
        'click',
        'a.edit-log-entry',
        function () {
          setTimeout(function () {
            enforceHoursOnly($('.edit-log-work-increment'));
            attachHoursOnlyHelp($('.edit-log-inline'));
          }, 0);
        },
      );
    },
  };
})(jQuery, Drupal, once);
