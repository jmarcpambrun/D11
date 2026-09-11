/**
 * @file
 * Extends functionality for supporting CKEditor 5 AI plugins.
 */

/**
 * @param {object} Drupal
 *   Drupal core JavaScript object.
 * @param {Function} $
 *   jQuery.
 */
((Drupal, $) => {
  /**
   * Handles background requests for editor streaming.
   *
   * @param {object} _ajax
   *   The AJAX object from Drupal.AjaxCommands (unused).
   * @param {object} parameters
   *   The parameters from AiRequestCommand.
   */
  Drupal.AjaxCommands.prototype.aiRequest = function aiRequest(
    _ajax,
    parameters,
  ) {
    // Read entity context from the dialog's hidden form fields so the
    // AiWriter command can forward it to the server-side controller.
    const form = document.querySelector('.ckeditor5-ai-ckeditor-dialog-form');
    if (form) {
      const entityType = form.querySelector('input[name="entity_type"]');
      const entityId = form.querySelector('input[name="entity_id"]');
      if (entityType) {
        parameters.entity_type = entityType.value;
      }
      if (entityId) {
        parameters.entity_id = entityId.value;
      }
    }

    const editorId = $('#ai-ckeditor-response textarea').attr(
      'data-ckeditor5-id',
    );
    const editor = Drupal.CKEditor5Instances.get(editorId);
    editor.execute('AiWriter', parameters);
  };

  /**
   * Public API for AI CKEditor integration.
   *
   * @namespace
   */
  Drupal.aickeditor = {
    /**
     * Open a dialog for a Drupal-based plugin.
     *
     * This dynamically loads jQuery UI (if necessary) using the Drupal AJAX
     * framework, then opens a dialog at the specified Drupal path.
     *
     * @param {string} url
     *   The URL that contains the contents of the dialog.
     * @param {Function} saveCallback
     *   A function to be called upon saving the dialog.
     * @param {object} dialogSettings
     *   An object containing settings to be passed to the jQuery UI.
     * @param {object} additionalData
     *   An object containing form data to be passed to the plugin.
     */
    openDialog(url, saveCallback, dialogSettings, additionalData) {
      // Add a consistent dialog class.
      const classes = dialogSettings.dialogClass
        ? dialogSettings.dialogClass.split(' ')
        : [];
      classes.push('ui-dialog--narrow');
      dialogSettings.dialogClass = classes.join(' ');

      if (typeof dialogSettings.autoResize !== 'undefined') {
        if (typeof dialogSettings.autoResize === 'string') {
          dialogSettings.autoResize = window.matchMedia(
            `(${dialogSettings.autoResize})`,
          ).matches;
        }
      }

      dialogSettings.height = dialogSettings.height
        ? dialogSettings.height
        : (dialogSettings.height = 'auto');

      dialogSettings.width = dialogSettings.width
        ? dialogSettings.width
        : (dialogSettings.width = 'auto');

      const ckeditorAjaxDialog = Drupal.ajax({
        dialog: dialogSettings,
        dialogType: 'modal',
        selector: '.ckeditor5-dialog-loading-link',
        url,
        progress: { type: 'fullscreen' },
        submit: {
          editor_object: {},
          ...additionalData,
        },
      });
      ckeditorAjaxDialog.execute();

      // Store the save callback to be executed when this dialog is closed.
      Drupal.ckeditor5.saveCallback = saveCallback;
    },
  };
})(Drupal, jQuery);
