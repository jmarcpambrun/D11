/**
 * @param $
 * @param Drupal
 * @param drupalSettings
 * @file
 * Contains burndown.board.js.
 */
(function ($, Drupal) {
  Drupal.behaviors.burndownBoard = {
    attach(context) {
      let assignedTo = [];

      // POST a Column (swimlane) change back to Drupal to save (not debounced).
      // @see src/Controllers/BoardController.php::changeSwimlane.
      function postSwimlaneChange(taskId, fromSwimlane, toSwimlane) {
        $.ajax({
          url: '/burndown/api/change_swimlane',
          method: 'POST',
          data: {
            task_id: taskId,
            from_swimlane: fromSwimlane,
            to_swimlane: toSwimlane,
          },
          success() {},
          error() {},
        });
      }

      // POSTs a new sort order back to Drupal to be saved.
      // @see src/Controllers/BoardController.php::reorderBoard.
      function postSortOrder() {
        const updatedSort = {};

        const swimlanes = $('.list-group');

        swimlanes.each(function (index, laneItem) {
          const swimlaneId = $(laneItem).data('swimlane-id');

          const items = $('.list-group-item', $(laneItem));
          const itemSort = [];

          items.each(function (itemIndex, listItem) {
            itemSort[itemIndex] = $(listItem).data('ticket-id');
          });

          if (itemSort.length > 0) {
            updatedSort[swimlaneId] = itemSort;
          }
        });

        $.ajax({
          url: '/burndown/api/board_reorder',
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

      // Update counters at the top of the swimlanes.
      function updateCounters() {
        $('.col.swimlane').each(function (index, lane) {
          // Count the tasks in the lane.
          const taskCount = $('.list-group-item.row', $(lane)).length;

          // Update the count.
          if (taskCount > 0) {
            $('.counter', $(lane)).html(`(${taskCount})`);
          } else {
            $('.counter', $(lane)).html('');
          }
        });
      }

      function urlParam(name) {
        const results = new RegExp(`[?&]${name}=([^&#]*)`).exec(
          window.location.href,
        );
        if (results == null) {
          return false;
        }
        return decodeURI(results[1]) || 0;
      }

      // Only do setup once.
      $(once('setupBoard', 'body')).each(function () {
        // Get (optional) list of assigned to users.
        assignedTo = urlParam('assigned_to');
        if (assignedTo) {
          assignedTo = assignedTo.split(',');
        }

        // Initial counts at top of lanes.
        updateCounters();

        // We debounce the postback that saves the new sort
        // order, since users can change the order several
        // times in a row before getting it the way they want
        // it (and we only really need the final ordering).
        const reorder = debounce(function () {
          postSortOrder();
        }, 2000);

        // Make the swimlanes sortable.
        const swimlaneSortables = [].slice.call(
          document.querySelectorAll('.list-group'),
        );

        // Loop through each nested sortable element
        for (let i = 0; i < swimlaneSortables.length; i++) {
          Sortable.create(swimlaneSortables[i], {
            group: 'swimlane',
            animation: 150,
            fallbackOnBody: true,
            swapThreshold: 0.65,
            onSort() {
              // Reorder tasks (debounced).
              reorder();
            },
            onEnd(/** Event */ evt) {
              // Gather info.
              const taskId = $(evt.item).data('ticket-id');
              const fromSwimlane = $(evt.from).data('swimlane-id');
              const toSwimlane = $(evt.to).data('swimlane-id');

              // Inform the system about the new swimlane for the task.
              if (fromSwimlane !== toSwimlane) {
                updateCounters();
                postSwimlaneChange(taskId, fromSwimlane, toSwimlane);
              }
            },
          });
        }
      });

      // Make the "send to backlog" link use AJAX.
      $(once('sendToBacklogAction', 'a.send_to_backlog', context)).on(
        'click',
        function (e) {
          // Do not follow the link.
          e.preventDefault();
          e.stopPropagation();

          // Confirm.
          const ret = window.confirm(
            'Are you SURE that you want to send this ticket back to the backlog?',
          );
          if (!ret) {
            return;
          }

          // Get ticket_id.
          const ticket = $(this).parent().parent().parent();
          const ticketId = $(ticket).data('ticket-id');

          // Remove the ticket from the board.
          // We do this now to avoid a UI delay.
          ticket.remove();

          $.ajax({
            url: `/burndown/api/board/send_to_backlog/${ticketId}`,
            method: 'GET',
            dataType: 'json',
            success() {
              // Reorder the remaining tasks.
              postSortOrder();
            },
            error() {},
          });
        },
      );

      $(once('resetUserList', '#user_list .reset', context)).on(
        'click',
        function () {
          window.location = `${window.location.protocol}//${
            window.location.hostname
          }${window.location.pathname}`;
        },
      );

      $(once('filterUserList', '#user_list .assigned_to', context)).on(
        'click',
        function () {
          const userId = $(this).data('user');

          if (!assignedTo) {
            assignedTo = [];
          }

          if (assignedTo.indexOf(userId) === -1) {
            assignedTo.push(userId);
          }

          if (assignedTo.length >= 1) {
            window.location = `${window.location.protocol}//${
              window.location.hostname
            }${window.location.pathname}?assigned_to=${assignedTo.join(',')}`;
          }
        },
      );
    },
  };
})(jQuery, Drupal);
