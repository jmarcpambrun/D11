/**
 * @param $
 * @param Drupal
 * @param drupalSettings
 * @file
 * Contains burndown.swimlanes.js.
 */
(function ($, Drupal) {
  Drupal.behaviors.burndownSwimlaneReorder = {
    attach() {
      // POSTs a new sort order back to Drupal to be saved.
      // @see src/Controllers/BoardController.php::reorderBoard.
      function postSortOrder() {
        const updatedSort = [];

        $('.swimlane').each(function (index, laneItem) {
          updatedSort.push($(laneItem).data('swimlane-id'));
        });

        $.ajax({
          url: '/burndown/api/swimlane_reorder',
          method: 'POST',
          data: { sort: updatedSort },
          success() {},
          error() {},
        });
      }

      // Debounce function from underscore.js.
      // @see: https://davidwalsh.name/javascript-debounce-function
      function debounce(func, wait, immediate) {
        let timeout;
        return function (...args) {
          const thisContext = this;
          const later = function () {
            timeout = null;
            if (!immediate) {
              func.apply(thisContext, args);
            }
          };
          const callNow = immediate && !timeout;
          clearTimeout(timeout);
          timeout = setTimeout(later, wait);
          if (callNow) {
            func.apply(thisContext, args);
          }
        };
      }

      // Only do setup once.
      $(once('setupSwimlanes', 'body')).each(function () {
        // We debounce the postback that saves the new sort
        // order, since users can change the order several
        // times in a row before getting it the way they want
        // it (and we only really need the final ordering).
        const reorder = debounce(function () {
          postSortOrder();
        }, 2000);

        // Make the swimlanes sortable.
        Sortable.create(document.getElementById('board'), {
          animation: 150,
          swapThreshold: 0.65,
          onSort() {
            // Reorder tasks (debounced).
            reorder();
          },
        });
      });
    },
  };
})(jQuery, Drupal);
