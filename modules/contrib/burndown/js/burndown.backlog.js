/**
 * @file
 * Contains burndown.backlog.js.
 */
(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.burndownBacklog = {
    attach: function (context, settings) {
      // Only do setup once.
      $(once('setupBacklog', 'body')).each(function () {
        // Update sprint data on load.
        updateSprints();

        // We debounce the postback that saves the new sort
        // order, since users can change the order several
        // times in a row before getting it the way they want
        // it (and we only really need the final ordering).
        var reorder = debounce(function () {
          postSortOrder();
        }, 2000);

        // Make backlog and sprint areas sortable.
        var backlogSortables = [].slice.call(document.querySelectorAll('.list-group'));

        // Make the backlog list sortable.
        for (var i = 0; i < backlogSortables.length; i++) {
          new Sortable(backlogSortables[i], {
            filter: '.filtered', // i.e. for sprint card
            group: 'swimlane',
            animation: 150,
            fallbackOnBody: true,
            swapThreshold: 0.65,
            onSort: function (/**Event*/evt) {
              reorder();
            },
            onEnd: function (/**Event*/evt) {
              // Gather info.
              var taskId = $(evt.item).data("ticket-id");

              // Detect if this is a new sprint.
              // Note that backlog has id=0.
              var fromSprint = $(evt.from).data("sprint-id");
              var toSprint = $(evt.to).data("sprint-id");

              // Inform the system about the new sprint for the task.
              if (((fromSprint > 0) || (toSprint > 0)) && (fromSprint != toSprint)) {
                postSprintChange(taskId, fromSprint, toSprint);

                var fromStatus = $(evt.from).prev('.sprint').data('status');
                var toStatus = $(evt.to).prev('.sprint').data('status');

                if (fromStatus == "started" || toStatus == "started") {
                  window.alert("This will change the size of an open sprint.");
                }
              }
            },
          });
        }
      });

      // Make the "send to board" link use AJAX.
      $(once('sendToBoardAction','a.send_to_board', context))
        .on('click', function (e) {
          // Do not follow the link.
          e.preventDefault();
          e.stopPropagation();

          // Get ticket_id.
          var ticket = $(this).parent().parent();
          var ticket_id = $(ticket).data("ticket-id");

          // Remove the ticket from the backlog board.
          // We do this now to avoid a UI delay.
          ticket.remove();

          $.ajax({
              url: "/burndown/api/backlog/send_to_board/" + ticket_id,
              method :'GET',
              dataType: "json",
              success: function (result) {
                // Do nothing (we already removed the ticket from the display).
              },
              error: function (XMLHttpRequest, textStatus, errorThrown) {
                console.log("Moving the ticket to the board failed. Please reload the page.");
              }
          });
        });

      // Make the "open sprint" link use AJAX.
      $(once('openSprintAction','a.open_sprint', context))
        .on('click', function (e) {
          // Do not follow the link.
          e.preventDefault();
          e.stopPropagation();

          // Get sprint.
          var sprint = $(this).parent().parent().parent();
          var sprint_id = sprint.data('sprint-id');

          // Hide the button.
          $('a.open_sprint').hide();

          $.ajax({
              url: "/burndown/api/open_sprint",
              method :'POST',
              data: { id: sprint_id },
              success: function (result) {
                // Update sprint displays.
                updateSprints();
              },
              error: function (XMLHttpRequest, textStatus, errorThrown) {
                console.log("Opening the sprint failed. Please reload the page.");
              }
          });
        });

      // POSTs a new sort order back to Drupal to be saved.
      // @see src/Controllers/BacklogController.php::reorderBacklog.
      function postSortOrder() {
        var updated_sort = [];
        var items = $('.list-group-item');
        var counter = 0;
        items.each(function () {
          updated_sort[counter] = $(this).data("ticket-id");
          counter++;
        });

        $.ajax({
          url: '/burndown/api/backlog_reorder',
          method: 'POST',
          data: { sort: updated_sort },
          success: function (data) {
            console.log(data);

            // Update sprints (i.e. counts).
            updateSprints();
          },
          error: function (XMLHttpRequest, textStatus, errorThrown) {
            console.log("Status: " + textStatus);
            console.log("Error: " + errorThrown);
          }
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
            to_sprint: toSprint
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

      // Update sprint data.
      function updateSprints() {
        // Get shortcode.
        var code = $('#backlog').data('project-shortcode');

        // Call API.
        $.ajax({
          url: "/burndown/api/backlog/sprint_status/" + code,
          method :'GET',
          dataType: "json",
          success: function (result) {
            if (result.data.length > 0) {
              for (var i = 0; i < result.data.length; i++) {
                var sprint_data = result.data[i];
                var sprint = $('*[data-sprint-id="' + sprint_data.id + '"]');
                if (sprint.length > 0) {
                  // Update name, in case edited.
                  $('.sprint_name h3', sprint[0]).html(sprint_data.name);

                  // Update status.
                  if (sprint_data.status == 'started') {
                    $('.status', sprint[0]).html('Open').show();
                  }
                  else {
                    $('.status', sprint[0]).html('').hide();
                  }

                  // Show or hide the sprint open button.
                  if (sprint_data.can_open == '1') {
                    $('.open_button', sprint[0]).show();
                  }
                  else {
                    $('.open_button', sprint[0]).hide();
                  }

                  // Show or hide the sprint close button.
                  if (sprint_data.can_close == '1') {
                    $('.close_button', sprint[0]).show();
                  }
                  else {
                    $('.close_button', sprint[0]).hide();
                  }

                  // Get # of tasks.
                  var num_tasks = $('.list-group-item', sprint[1]).length;
                  if (num_tasks > 0) {
                    $('.num_tasks', sprint[0]).html('(' + num_tasks + ')');
                  }
                  else {
                    $('.num_tasks', sprint[0]).html('');
                  }
                }
              }
            }
          },
          error: function (XMLHttpRequest, textStatus, errorThrown) {
            console.log("Could not update sprint data. Please reload the page.");
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
