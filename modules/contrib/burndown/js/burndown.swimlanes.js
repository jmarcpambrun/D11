/**
 * @file
 * Contains burndown.swimlanes.js.
 */
(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.burndownSwimlaneReorder = {
    attach: function (context, settings) {
      // Only do setup once.
      $(once('setupSwimlanes', 'body')).each(function () {

        // We debounce the postback that saves the new sort
        // order, since users can change the order several
        // times in a row before getting it the way they want
        // it (and we only really need the final ordering).
        var reorder = debounce(function () {
          postSortOrder();
        }, 2000);

        // Make the swimlanes sortable.
        new Sortable(document.getElementById('board'), {
          animation: 150,
          swapThreshold: 0.65,
          onSort: function (/**Event*/evt) {
            // Reorder tasks (debounced).
            reorder();
          },
        });
      });

      // POSTs a new sort order back to Drupal to be saved.
      // @see src/Controllers/BoardController.php::reorderBoard.
      function postSortOrder() {
        var updated_sort = [];

        $(".swimlane").each(function (index, laneItem) {
          updated_sort.push($(laneItem).data("swimlane-id"));
        });

        $.ajax({
          url: '/burndown/api/swimlane_reorder',
          method: 'POST',
          data: { sort: updated_sort },
          success: function (data) {
            console.log(data);
          },
          error: function (XMLHttpRequest, textStatus, errorThrown) {
            console.log("Status: " + textStatus);
            console.log("Error: " + errorThrown);
          }
        });
      }

      // Debounce function from underscore.js.
      // @see: https://davidwalsh.name/javascript-debounce-function
      function debounce(func, wait, immediate) {
        var timeout;
        return function () {
          var context = this, args = arguments;
          var later = function () {
            timeout = null;
            if (!immediate) { func.apply(context, args);
            }
          };
          var callNow = immediate && !timeout;
          clearTimeout(timeout);
          timeout = setTimeout(later, wait);
          if (callNow) { func.apply(context, args);
          }
        };
      }

    }
  };

})(jQuery, Drupal, drupalSettings);
