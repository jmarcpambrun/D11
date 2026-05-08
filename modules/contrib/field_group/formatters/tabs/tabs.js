/**
 * @file
 * Provides the processing logic for tabs.
 */

(($) => {
  Drupal.FieldGroup = Drupal.FieldGroup || {};
  Drupal.FieldGroup.Effects = Drupal.FieldGroup.Effects || {};

  /**
   * Shared implementation of Drupal.FieldGroup.processHook() for tabs.
   */
  function executeTabProcessing(context, settings, groupInfo) {
    if (groupInfo.context === 'form') {
      const { direction } = groupInfo.settings;
      $(context)
        .find(`[data-${direction}-tabs-panes]`)
        .each((indexTabs, tabs) => {
          let errorFocussed = false;
          $(once('fieldgroup-effects', $(tabs).find('> details'))).each(
            (index, element) => {
              const $this = $(element);
              if (typeof $this.data(`${direction}Tab`) !== 'undefined') {
                if (
                  element.matches('.required-fields') &&
                  ($this.find('[required]').length > 0 ||
                    $this.find('.form-required').length > 0)
                ) {
                  $this
                    .data(`${direction}Tab`)
                    .link.find('strong:first')
                    .addClass('form-required');
                }

                if ($('.error', $this).length) {
                  $this.data(`${direction}Tab`).link.parent().addClass('error');

                  // Focus the first tab with error.
                  if (!errorFocussed) {
                    Drupal.FieldGroup.setGroupWithFocus($this);
                    $this.data(`${direction}Tab`).focus();
                    errorFocussed = true;
                  }
                }
              }
            },
          );
        });
    }
  }

  /**
   * Implements Drupal.FieldGroup.processHook() for vertical tabs.
   */
  Drupal.FieldGroup.Effects.processTabsVertical = {
    execute: executeTabProcessing,
  };

  /**
   * Implements Drupal.FieldGroup.processHook() for horizontal tabs.
   */
  Drupal.FieldGroup.Effects.processTabsHorizontal = {
    execute: executeTabProcessing,
  };

  /**
   * Implements Drupal.FieldGroup.processHook() for tabs.
   *
   * @deprecated Kept for backward compatibility. drupalSettings now emits
   *   per-direction keys (tabsvertical/tabshorizontal), so this effect's
   *   execute() is never invoked by the dispatcher in field_group.js. Third
   *   parties that reference Drupal.FieldGroup.Effects.processTabs directly
   *   should migrate to processTabsVertical or processTabsHorizontal.
   */
  Drupal.FieldGroup.Effects.processTabs = {
    execute: executeTabProcessing,
  };
})(jQuery);
