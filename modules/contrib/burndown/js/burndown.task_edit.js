/**
 * @param $
 * @param Drupal
 * @param drupalSettings
 * @file
 * Contains burndown.task_edit.js.
 */
(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.burndownTaskEdit = {
    attach() {
      function updateLog(type) {
        // Get the ticket id.
        const ticketId = $('#burndown_task_log').data('ticket-id');

        // AJAX path.
        const path = `/burndown/api/task_log/${ticketId}/${type}`;
        const target = document.getElementById('burndown_task_log');
        if (!target) {
          return;
        }

        fetch(path, { credentials: 'same-origin' })
          .then(function (response) {
            return response.text();
          })
          .then(function (html) {
            target.innerHTML = html;
          });
      }

      function updateRelationships() {
        // Get the ticket id.
        const ticketId = $('#burndown_task_log').data('ticket-id');

        // AJAX path.
        const path = `/burndown/api/task/get_relationships/${ticketId}`;
        const target = document.getElementById('relationships_list');
        if (!target) {
          return;
        }

        fetch(path, { credentials: 'same-origin' })
          .then(function (response) {
            return response.text();
          })
          .then(function (html) {
            target.innerHTML = html;
          });
      }

      // Only do setup once.
      $(once('setupLogs', 'body')).each(function () {
        updateLog();
        updateRelationships();
      });

      // Make the watch/unwatch task link work.
      $(once('watchListAction', '.watch_list')).on('click', function (e) {
        // Do not follow the link.
        e.preventDefault();
        e.stopPropagation();

        // Get the link url.
        const url = $(e.target).attr('href');

        // Send a GET.
        $.ajax({
          url,
          method: 'GET',
          success() {
            // Switch the class and url.
            const container = $('.watch_list');
            const myLink = $('.watch_list a');
            let linkUrl = myLink.attr('href');

            if (linkUrl.includes('remove_from_watchlist')) {
              myLink.text('Watch this task');
              linkUrl = linkUrl.replace(
                'remove_from_watchlist',
                'add_to_watchlist',
              );
              myLink.attr('href', linkUrl);
              container.removeClass('watch').addClass('mute');
            } else {
              myLink.text('Stop watching this task');
              linkUrl = linkUrl.replace(
                'add_to_watchlist',
                'remove_from_watchlist',
              );
              myLink.attr('href', linkUrl);
              container.removeClass('mute').addClass('watch');
            }
          },
          error() {},
        });
      });

      // For modal views of the task edit form,
      // pull log data when the log details container
      // is opened.
      $(once('updateLogAction', 'body')).on(
        'click',
        '[data-drupal-selector="edit-log"] summary',
        function () {
          updateLog('comment');
        },
      );

      // Similarly, load relationships when the tab is opened.
      $(once('updateRelationshipsAction', 'body')).on(
        'click',
        '[data-drupal-selector="edit-relationships-wrapper"] summary',
        function () {
          updateRelationships();
        },
      );

      // Tabs to control which logs show.
      $(once('clickTabsAction', 'body')).on(
        'click',
        '.log_tabs > a',
        function (e) {
          // Do not follow the link.
          e.preventDefault();
          e.stopPropagation();

          const logType = $(e.currentTarget).attr('class');
          updateLog(logType);

          if (logType === 'comment') {
            // Show comment field.
            $('.form-wrapper.add_comment').show();

            // Hide work field.
            $('.form-wrapper.add_work').hide();
          } else if (logType === 'work') {
            // Show work field.
            $('.form-wrapper.add_work').show();

            // Hide comment field.
            $('.form-wrapper.add_comment').hide();
          } else {
            // Hide both comment and work fields.
            $('.form-wrapper.add_comment').hide();
            $('.form-wrapper.add_work').hide();
          }
        },
      );

      // Posting a comment.
      $(once('postCommentAction', 'body')).on(
        'click',
        '.add_comment a.button',
        function (e) {
          // Do not follow the link.
          e.preventDefault();
          e.stopPropagation();

          // Post data.
          $.ajax({
            url: '/burndown/api/task/add_comment',
            method: 'POST',
            data: {
              ticket_id: $('#burndown_task_log').data('ticket-id'),
              comment: document.querySelector('.add_comment textarea').value,
            },
            success() {
              // On success, reload comments and clear the form.
              updateLog('comment');
              document.querySelector('.add_comment textarea').value = '';
            },
            error() {},
          });
        },
      );

      // Posting a work log.
      $(once('postWorkAction', 'body')).on(
        'click',
        '.add_work a.button',
        function (e) {
          // Do not follow the link.
          e.preventDefault();
          e.stopPropagation();

          // Post data.
          $.ajax({
            url: '/burndown/api/task/add_work',
            method: 'POST',
            data: {
              ticket_id: $('#burndown_task_log').data('ticket-id'),
              comment: document.querySelector('.add_work .add_work_text').value,
              work: document.querySelector('.add_work .add_work_quantity')
                .value,
              work_increment: document.querySelector(
                '.add_work .add_work_quantity_type',
              ).value,
            },
            success() {
              // On success, reload work and clear the form.
              updateLog('work');
              document.querySelector('.add_work .add_work_text').value = '';
              document.querySelector('.add_work .add_work_quantity').value = '';
              document.querySelector(
                '.add_work .add_work_quantity_type',
              ).value = 'h';
            },
            error() {},
          });
        },
      );

      // Editing an existing comment/work log entry.
      $(once('editLogAction', 'body')).on(
        'click',
        'a.edit-log-entry',
        function (e) {
          // Do not follow the link.
          e.preventDefault();
          e.stopPropagation();

          const link = $(e.currentTarget);
          const row = link.closest('.log-item');

          if (row.find('.edit-log-inline').length) {
            return;
          }

          const type = link.data('type');
          let currentComment = '';
          if (type === 'comment') {
            currentComment = row
              .find('.comment')
              .first()
              .get(0)
              .textContent.trim();
          } else {
            currentComment = row
              .find('.log-item-changes')
              .first()
              .get(0)
              .textContent.trim();
          }

          const editor = $('<div/>', {
            class: 'edit-log-inline',
            'data-type': type,
            'data-delta': link.data('delta'),
          });

          const commentField = $('<div/>')
            .append($('<label/>').prop('textContent', 'Comment'))
            .append(
              $('<textarea/>', {
                class: 'edit-log-comment',
              }).prop('value', currentComment),
            );
          editor.append(commentField);

          if (type === 'work') {
            const workDone = String(link.data('work-done') || '').trim();
            const workMatch = workDone.match(
              /^([0-9]*\.?[0-9]+)\s*([mhdwMY])$/,
            );
            const currentWork = workMatch ? workMatch[1] : '';
            const currentIncrement = workMatch ? workMatch[2] : 'h';

            const workField = $('<div/>')
              .append($('<label/>').prop('textContent', 'Work amount'))
              .append(
                $('<input/>', {
                  type: 'number',
                  step: '0.01',
                  min: '0',
                  class: 'edit-log-work',
                }).prop('value', currentWork),
              );

            const unitField = $('<div/>').append(
              $('<label/>').prop('textContent', 'Work unit'),
            );

            const unitSelect = $('<select/>', {
              class: 'edit-log-work-increment',
            });
            ['m', 'h', 'd', 'w', 'M', 'Y'].forEach(function (unit) {
              unitSelect.append(
                $('<option/>', {
                  value: unit,
                  text: unit,
                }),
              );
            });
            unitSelect.prop('value', currentIncrement);
            unitField.append(unitSelect);

            editor.append(workField);
            editor.append(unitField);
          }

          const actions = $('<div/>', { class: 'edit-log-actions' })
            .append(
              $('<a/>', {
                href: '#',
                class: 'button save-log-entry',
                text: 'Save',
              }),
            )
            .append(' ')
            .append(
              $('<a/>', {
                href: '#',
                class: 'button cancel-log-entry',
                text: 'Cancel',
              }),
            );
          editor.append(actions);

          row.children('.comment, .hours, .log-item-changes').hide();
          link.hide();
          row.append(editor);
        },
      );

      // Save inline-edited comment/work log entry.
      $(once('saveEditedLogAction', 'body')).on(
        'click',
        'a.save-log-entry',
        function (e) {
          e.preventDefault();
          e.stopPropagation();

          const saveLink = $(e.currentTarget);
          const row = saveLink.closest('.log-item');
          const editor = saveLink.closest('.edit-log-inline');
          const type = editor.data('type');

          const payload = {
            ticket_id: $('#burndown_task_log').data('ticket-id'),
            delta: editor.data('delta'),
            comment: editor.find('.edit-log-comment').get(0).value,
          };

          if (type === 'work') {
            payload.work = editor.find('.edit-log-work').get(0).value;
            payload.work_increment = editor
              .find('.edit-log-work-increment')
              .get(0).value;
          }

          $.ajax({
            url: '/burndown/api/task/edit_log',
            method: 'POST',
            data: payload,
            success() {
              updateLog(type);
            },
            error() {
              row.find('.edit-log-inline').remove();
              row.children('.comment, .hours, .log-item-changes').show();
              row.find('a.edit-log-entry').show();
            },
          });
        },
      );

      // Cancel inline log entry edit.
      $(once('cancelEditedLogAction', 'body')).on(
        'click',
        'a.cancel-log-entry',
        function (e) {
          e.preventDefault();
          e.stopPropagation();

          const cancelLink = $(e.currentTarget);
          const row = cancelLink.closest('.log-item');

          row.find('.edit-log-inline').remove();
          row.children('.comment, .hours, .log-item-changes').show();
          row.find('a.edit-log-entry').show();
        },
      );

      // Add a relationship.
      // POST to
      $(once('postRelationshipAction', 'body')).on(
        'click',
        'a.button.add_relationship',
        function (e) {
          // Do not follow the link.
          e.preventDefault();
          e.stopPropagation();

          const fromTicketId = $(e.target).data('ticket-id');
          const toTicketValue = document.querySelector(
            '.add_relationship .add_relationship_entity',
          ).value;
          const [toTicketId] = toTicketValue.split(' ');

          // Post data.
          $.ajax({
            url: '/burndown/api/task/add_relationship',
            method: 'POST',
            data: {
              from_ticket_id: fromTicketId,
              to_ticket_id: toTicketId,
              type: document.querySelector(
                '.add_relationship .add_relationship_select',
              ).value,
            },
            success() {
              // On success, reload work and clear the form.
              updateRelationships();
              document.querySelector(
                '.add_relationship .add_relationship_entity',
              ).value = '';
            },
            error() {},
          });
        },
      );

      // Remove a relationship.
      $(once('removeRelationshipAction', 'body')).on(
        'click',
        'a.remove_relationship',
        function (e) {
          // Do not follow the link.
          e.preventDefault();
          e.stopPropagation();

          // Confirm.
          const ret = window.confirm(
            'Are you SURE that you want to remove this relationship?',
          );
          if (!ret) {
            return;
          }

          // Get ticket info.
          const relationship = $(e.target).parent().parent();
          const fromTicketId = relationship.data('from-ticket-id');
          const toTicketId = relationship.data('to-ticket-id');

          // Send data.
          $.ajax({
            url: `/burndown/api/task/remove_relationship/${fromTicketId}/${
              toTicketId
            }`,
            method: 'GET',
            success() {
              // On success, reload relationships.
              updateRelationships();
            },
            error() {},
          });
        },
      );

      // Assign to me link.
      $(once('assignToMeLink', 'body')).on(
        'click',
        'a.assign_to_me',
        function (e) {
          // Do not follow the link.
          e.preventDefault();
          e.stopPropagation();

          // Get user info from drupalSettings.
          const { user } = drupalSettings;

          // Set the user entity reference field.
          if (Object.prototype.hasOwnProperty.call(user, 'name')) {
            const userName = `${user.name} (${user.uid})`;
            document.querySelector(
              '.field--name-assigned-to input.form-autocomplete',
            ).value = userName;
          }
        },
      );
    },
  };
})(jQuery, Drupal, drupalSettings);
