/* eslint-disable no-undef */
/**
 * @file
 * Behavior which initializes the select_extractor library for the menu node form
 */
(function (Drupal, drupalSettings, once) {
  Drupal.behaviors.book_parent_ui = {
    attach(context) {
      if (drupalSettings.book.alternative_form) {
        once('book_parent_ui', '#edit-book-plid-wrapper', context).forEach(
          function () {
            const $newBook =
              context.querySelector('#edit-book-create-new-book')?.checked ??
              false;
            if ($newBook) {
              if (document.getElementById('edit-book-pid')) {
                new SelectExtractorBook({
                  selectors: {
                    select_input_original: '#edit-book-pid',
                    select_box_container: '.js-form-item-book-pid',
                    active_set: drupalSettings.book_active_path,
                  },
                });
              }
              // This is the node add form.
              else {
                new SelectExtractorBook({
                  selectors: {
                    select_input_original: 'select[name="book[pid]"]',
                    select_box_container: '.js-form-item-book-pid',
                    active_set: drupalSettings.book_active_path,
                  },
                });
              }
            }
          },
        );
      } else {
        once('book_parent_ui', '.js-form-item-book-pid', context).forEach(
          function () {
            if (document.getElementById('edit-book-pid')) {
              new SelectExtractorBook({
                selectors: {
                  select_input_original: '#edit-book-pid',
                  select_box_container: '.js-form-item-book-pid',
                  active_set: drupalSettings.book_active_path,
                },
              });
            }
            // This is the node add form.
            else {
              new SelectExtractorBook({
                selectors: {
                  select_input_original: 'select[name="book[pid]"]',
                  select_box_container: '.js-form-item-book-pid',
                  active_set: drupalSettings.book_active_path,
                },
              });
            }
          },
        );
      }
    },
  };
})(Drupal, drupalSettings, once);
