/**
 * @param $
 * @param Drupal
 * @param drupalSettings
 * @file
 * Contains burndown.backlog.js.
 */
(function ($, Drupal) {
  Drupal.behaviors.burndownBacklog = {
    attach(context) {
      // Update sprint data.
      function updateSprints() {
        // Get shortcode.
        const code = $('#backlog').data('project-shortcode');

        // Call API.
        $.ajax({
          url: `/burndown/api/backlog/sprint_status/${code}`,
          method: 'GET',
          dataType: 'json',
          success(result) {
            if (result.data.length > 0) {
              for (let i = 0; i < result.data.length; i++) {
                const sprintData = result.data[i];
                const sprint = $(`*[data-sprint-id="${sprintData.id}"]`);
                if (sprint.length > 0) {
                  // Update name, in case edited.
                  $('.sprint_name h3', sprint[0]).html(sprintData.name);

                  // Update status.
                  if (sprintData.status === 'started') {
                    $('.status', sprint[0]).html('Open').show();
                  } else {
                    $('.status', sprint[0]).html('').hide();
                  }

                  // Show or hide the sprint open button.
                  if (sprintData.can_open === '1') {
                    $('.open_button', sprint[0]).show();
                  } else {
                    $('.open_button', sprint[0]).hide();
                  }

                  // Show or hide the sprint close button.
                  if (sprintData.can_close === '1') {
                    $('.close_button', sprint[0]).show();
                  } else {
                    $('.close_button', sprint[0]).hide();
                  }

                  // Get # of tasks.
                  const numTasks = $('.list-group-item', sprint[1]).length;
                  if (numTasks > 0) {
                    $('.num_tasks', sprint[0]).html(`(${numTasks})`);
                  } else {
                    $('.num_tasks', sprint[0]).html('');
                  }
                }
              }
            }
          },
          error() {},
        });
      }

      // POSTs a new sort order back to Drupal to be saved.
      // @see src/Controllers/BacklogController.php::reorderBacklog.
      function postSortOrder() {
        const updatedSort = [];
        const items = $('.list-group-item');
        let counter = 0;
        items.each(function () {
          updatedSort[counter] = $(this).data('ticket-id');
          counter++;
        });

        $.ajax({
          url: '/burndown/api/backlog_reorder',
          method: 'POST',
          data: { sort: updatedSort },
          success() {
            // Update sprints (i.e. counts).
            updateSprints();
          },
          error() {},
        });
      }

      // POSTs a sprint change back to Drupal to be saved (not debounced).
      // @see src/Controllers/BacklogController.php::changeSprint.
      function postSprintChange(taskId, fromSprint, toSprint) {
        $.ajax({
          url: '/burndown/api/change_sprint',
          method: 'POST',
          data: {
            task_id: taskId,
            from_sprint: fromSprint,
            to_sprint: toSprint,
          },
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
      $(once('setupBacklog', 'body')).each(function () {
        // Update sprint data on load.
        updateSprints();

        // We debounce the postback that saves the new sort
        // order, since users can change the order several
        // times in a row before getting it the way they want
        // it (and we only really need the final ordering).
        const reorder = debounce(function () {
          postSortOrder();
        }, 2000);

        // Make backlog and sprint areas sortable.
        const backlogSortables = [].slice.call(
          document.querySelectorAll('.list-group'),
        );

        // Make the backlog list sortable.
        for (let i = 0; i < backlogSortables.length; i++) {
          Sortable.create(backlogSortables[i], {
            filter: '.filtered', // i.e. for sprint card
            group: 'swimlane',
            animation: 150,
            fallbackOnBody: true,
            swapThreshold: 0.65,
            onSort() {
              reorder();
            },
            onEnd(/** Event */ evt) {
              // Gather info.
              const taskId = $(evt.item).data('ticket-id');

              // Detect if this is a new sprint.
              // Note that backlog has id=0.
              const fromSprint = $(evt.from).data('sprint-id');
              const toSprint = $(evt.to).data('sprint-id');

              // Inform the system about the new sprint for the task.
              if ((fromSprint > 0 || toSprint > 0) && fromSprint !== toSprint) {
                postSprintChange(taskId, fromSprint, toSprint);

                const fromStatus = $(evt.from).prev('.sprint').data('status');
                const toStatus = $(evt.to).prev('.sprint').data('status');

                if (fromStatus === 'started' || toStatus === 'started') {
                  window.alert('This will change the size of an open sprint.');
                }
              }
            },
          });
        }
      });

      // Make the "send to board" link use AJAX.
      $(once('sendToBoardAction', 'a.send_to_board', context)).on(
        'click',
        function (e) {
          // Do not follow the link.
          e.preventDefault();
          e.stopPropagation();

          // Get ticket_id.
          const ticket = $(this).parent().parent();
          const ticketId = $(ticket).data('ticket-id');

          // Remove the ticket from the backlog board.
          // We do this now to avoid a UI delay.
          ticket.remove();

          $.ajax({
            url: `/burndown/api/backlog/send_to_board/${ticketId}`,
            method: 'GET',
            dataType: 'json',
            success() {
              // Do nothing (we already removed the ticket from the display).
            },
            error() {},
          });
        },
      );

      // Make the "open sprint" link use AJAX.
      $(once('openSprintAction', 'a.open_sprint', context)).on(
        'click',
        function (e) {
          // Do not follow the link.
          e.preventDefault();
          e.stopPropagation();

          // Get sprint.
          const sprint = $(this).parent().parent().parent();
          const sprintId = sprint.data('sprint-id');

          // Hide the button.
          $('a.open_sprint').hide();

          $.ajax({
            url: '/burndown/api/open_sprint',
            method: 'POST',
            data: { id: sprintId },
            success() {
              // Update sprint displays.
              updateSprints();
            },
            error() {},
          });
        },
      );
    },
  };
})(jQuery, Drupal);
