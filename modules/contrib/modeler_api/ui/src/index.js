/**
 * @file
 * Entry point for the template token selector Preact app.
 *
 * Reads resolved template token data from drupalSettings and applies
 * data-template-token attributes to matched DOM elements using the
 * incremental CSS selector chains provided by the backend.
 *
 * Wraps initialization in an IIFE that captures the Drupal global so
 * that other modules can use a stable reference even after AJAX-driven
 * page rebuilds.
 */

import { applyTemplateTokens } from './template-token-selector';
import { registerAppliedTemplates } from './entry-store';
import { setDrupal } from './drupal-context';

(function (Drupal) {

  // Store the Drupal reference for use by other modules (e.g. submitSaveData).
  if (Drupal) {
    setDrupal(Drupal);
  }

  if (Drupal && Drupal.behaviors) {
    Drupal.behaviors.modelerApiTemplateTokenSelector = {
      attach: function (context, settings) {
        var tokens = settings.modelerApiTemplateTokens;

        if (!tokens || !Array.isArray(tokens) || tokens.length === 0) {
          return;
        }

        if (settings.modelerApiAppliedTemplates) {
          registerAppliedTemplates(settings.modelerApiAppliedTemplates);
        }

        applyTemplateTokens(tokens, context);
      },
    };
  }
  else {
    // Fallback for non-Drupal environments or testing.
    function init() {
      var settings = window.drupalSettings || {};
      var tokens = settings.modelerApiTemplateTokens;

      if (!tokens || !Array.isArray(tokens) || tokens.length === 0) {
        return;
      }

      if (settings.modelerApiAppliedTemplates) {
        registerAppliedTemplates(settings.modelerApiAppliedTemplates);
      }

      applyTemplateTokens(tokens, document);
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', init);
    }
    else {
      init();
    }
  }

})(Drupal);
