/**
 * @file
 * JavaScript behaviors for the Book module.
 */

(function ($, Drupal, drupalSettings) {
  /**
   * Adds summaries to the book outline form.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches summary behavior to book outline forms.
   */
  Drupal.behaviors.bookDetailsSummaries = {
    attach(context) {
      $(context)
        .find('.book-outline-form')
        .drupalSetSummary((context) => {
          const $newBook = $(context);

          if (!drupalSettings.book.in_a_book) {
            return Drupal.t('Not in book');
          }

          const $autocomplete = $(context).find('.book-title-autocomplete');
          if ($autocomplete.length) {
            // Use native value property instead of jQuery .val()
            return Drupal.checkPlain($autocomplete[0].value);
          }

          const $select = $(context).find('.book-title-select');
          const val = $select[0].value;

          if (val === 'new') {
            return Drupal.t('Create new book');
          }
          return Drupal.checkPlain($select.find(':selected')[0].textContent);
        });
    },
  };
})(jQuery, Drupal, drupalSettings);
