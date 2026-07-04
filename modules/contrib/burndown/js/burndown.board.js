/**
 * @file
 * Contains burndown.board.js.
 */
(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.burndownBoard = {
    attach: function (context, settings) {
      var assigned_to = [];

      // Only do setup once.
    $(once('setupBoard', 'body')).each(function () {
        // Get (optional) list of assigned to users.
        assigned_to = urlParam('assigned_to');
        if (assigned_to) {
          assigned_to = assigned_to.split(',');
        }

        // Initial counts at top of lanes.
        update_counters();

        // We debounce the postback that saves the new sort
        // order, since users can change the order several
        // times in a row before getting it the way they want
        // it (and we only really need the final ordering).
        var reorder = debounce(function () {
          postSortOrder();
        }, 2000);

        // Make the swimlanes sortable.
        var swimlaneSortables = [].slice.call(document.querySelectorAll('.list-group'));

        // Loop through each nested sortable element
        for (var i = 0; i < swimlaneSortables.length; i++) {
          new Sortable(swimlaneSortables[i], {
            group: 'swimlane',
            animation: 150,
            fallbackOnBody: true,
            swapThreshold: 0.65,
            onSort: function (/**Event*/evt) {
              // Reorder tasks (debounced).
              reorder();
            },
            onEnd: function (/**Event*/evt) {
              // Gather info.
              var taskId = $(evt.item).data("ticket-id");
              var fromSwimlane = $(evt.from).data("swimlane-id");
              var toSwimlane = $(evt.to).data("swimlane-id");

              // Inform the system about the new swimlane for the task.
              if (fromSwimlane != toSwimlane) {
                update_counters();
                postSwimlaneChange(taskId, fromSwimlane, toSwimlane);
              }
            },
          });
        }
      });

      // Make the "send to backlog" link use AJAX.
        $(once('sendToBacklogAction', 'a.send_to_backlog', context))
        .on('click', function (e) {
          // Do not follow the link.
          e.preventDefault();
          e.stopPropagation();

          // Confirm.
          var ret = confirm("Are you SURE that you want to send this ticket back to the backlog?");
          if (!ret) {
            return;
          }

          // Get ticket_id.
          var ticket = $(this).parent().parent().parent();
          var ticket_id = $(ticket).data("ticket-id");

          // Remove the ticket from the board.
          // We do this now to avoid a UI delay.
          ticket.remove();

          $.ajax({
              url: "/burndown/api/board/send_to_backlog/" + ticket_id,
              method :'GET',
              dataType: "json",
              success: function (result) {
                // Reorder the remaining tasks.
                postSortOrder();
              },
              error: function (XMLHttpRequest, textStatus, errorThrown) {
                console.log("Moving the ticket to the backlog failed. Please reload the page.");
              }
          });
        });

      // POST a Column (swimlane) change back to Drupal to save (not debounced).
      // @see src/Controllers/BoardController.php::changeSwimlane.
      function postSwimlaneChange(taskId, fromSwimlane, toSwimlane) {
        $.ajax({
          url: '/burndown/api/change_swimlane',
          method: 'POST',
          data: {
            task_id: taskId,
            from_swimlane: fromSwimlane,
            to_swimlane: toSwimlane
          },
          success: function (data) {
            console.log(data);
          },
          error: function (XMLHttpRequest, textStatus, errorThrown) {
            console.log("Status: " + textStatus);
            console.log("Error: " + errorThrown);
          }
        });
      }

      // POSTs a new sort order back to Drupal to be saved.
      // @see src/Controllers/BoardController.php::reorderBoard.
      function postSortOrder() {
        var updated_sort = {};

        var swimlanes = $(".list-group");

        swimlanes.each(function (index, laneItem) {
          var swimlane_id = $(laneItem).data("swimlane-id");

          var items = $(".list-group-item", $(laneItem));
          var item_sort = [];

          items.each(function (index, listItem) {
            item_sort[index] = $(listItem).data("ticket-id");
          });

          if (item_sort.length > 0) {
            updated_sort[swimlane_id] = item_sort;
          }
        });

        $.ajax({
          url: '/burndown/api/board_reorder',
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

      // Update counters at the top of the swimlanes.
      function update_counters() {
        $('.col.swimlane').each(function (index, lane) {
          // Count the tasks in the lane.
          var task_count = $('.list-group-item.row', $(lane)).length;

          // Update the count.
          if (task_count > 0) {
            $('.counter', $(lane)).html('(' + task_count + ')');
          }
          else {
            $('.counter', $(lane)).html('');
          }
        });
      }

      function urlParam(name) {
        var results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href);
        if (results == null) {
          return false;
		}
        return decodeURI(results[1]) || 0;
      }

    $(once('resetUserList','#user_list .reset', context))
      .on('click', function (e) {
    window.location = window.location.protocol + '//' + window.location.hostname + window.location.pathname;
        });

    $(once('filterUserList', '#user_list .assigned_to', context))
      .on('click', function (e) {
      var user_id = $(this).data('user');

            if (!assigned_to) {
              assigned_to = [];
            }

            if(assigned_to.indexOf(user_id) === -1) {
              assigned_to.push(user_id);
            }

            if (assigned_to.length >= 1) {
              window.location = window.location.protocol + '//' + window.location.hostname + window.location.pathname + '?assigned_to=' + assigned_to.join(',');
            }
        });
    }
  };

})(jQuery, Drupal, drupalSettings);
