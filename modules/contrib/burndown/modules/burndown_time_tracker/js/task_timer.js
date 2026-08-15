/**
 * @param $
 * @param Drupal
 * @param once
 * @file
 * Task timer UI behavior for Burndown task cards.
 */
(function ($, Drupal, once) {
  Drupal.behaviors.burndownTaskTimer = {
    attach(context) {
      const buttons = once(
        'burndown-task-timer',
        '.burndown-time-timer-toggle',
        context,
      );
      if (!buttons.length) {
        return;
      }

      function notify(text, type) {
        if (!text) {
          return;
        }

        function escapeHtml(value) {
          return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
        }

        function emphasizeTaskTitles(message) {
          function normalizeQuoteEntities(value) {
            return String(value)
              .replace(/&amp;quot;/g, '"')
              .replace(/&quot;/g, '"')
              .replace(/&#34;/g, '"')
              .replace(/&amp;#039;/g, "'")
              .replace(/&#039;/g, "'");
          }

          function highlighted(title) {
            return `<strong class="burndown-time-timer-task-title">"${normalizeQuoteEntities(
              title,
            )}"</strong>`;
          }

          let safe = escapeHtml(normalizeQuoteEntities(message));
          let matched = false;

          // Match the full auto-stop message first so both task names are highlighted.
          safe = safe.replace(
            /^Since a new timer was started for task (.*?), your previously running timer for (.*?) has been stopped and recorded for that task\.$/,
            function (_, newTask, oldTask) {
              matched = true;
              return `Since a new timer was started for task ${highlighted(
                newTask,
              )}, your previously running timer for ${highlighted(
                oldTask,
              )} has been stopped and recorded for that task.`;
            },
          );

          // Match direct start message.
          safe = safe.replace(
            /^Timer started for task (.*?)\.$/,
            function (_, task) {
              matched = true;
              return `Timer started for task ${highlighted(task)}.`;
            },
          );

          // Match already-running message.
          safe = safe.replace(
            /^Timer is already running for task (.*?)\.$/,
            function (_, task) {
              matched = true;
              return `Timer is already running for task ${highlighted(task)}.`;
            },
          );

          if (matched) {
            return safe;
          }

          // Emphasize task names in known timer status messages.
          safe = safe.replace(
            /(for task )(.+?)([,.])/g,
            function (_, prefix, title, suffix) {
              return prefix + highlighted(title) + suffix;
            },
          );

          safe = safe.replace(
            /(timer for )(.+?)( has been stopped and recorded for that task\.)/g,
            function (_, prefix, title, suffix) {
              return prefix + highlighted(title) + suffix;
            },
          );

          return safe;
        }

        const messageType = type || 'status';
        const formattedMessage = emphasizeTaskTitles(text);
        let toastRoot = document.getElementById(
          'burndown-time-timer-toast-root',
        );

        if (!toastRoot) {
          toastRoot = document.createElement('div');
          toastRoot.id = 'burndown-time-timer-toast-root';
          toastRoot.style.position = 'fixed';
          toastRoot.style.top = '16px';
          toastRoot.style.right = '16px';
          toastRoot.style.maxWidth = '480px';
          toastRoot.style.zIndex = '10000';
          toastRoot.style.display = 'grid';
          toastRoot.style.gap = '8px';
          document.body.appendChild(toastRoot);
        }

        const toast = document.createElement('div');
        toast.setAttribute(
          'role',
          messageType === 'error' ? 'alert' : 'status',
        );
        toast.style.padding = '10px 12px';
        toast.style.borderRadius = '4px';
        toast.style.boxShadow = '0 2px 8px rgba(0, 0, 0, 0.15)';
        toast.style.backgroundColor =
          messageType === 'error' ? '#fbeaea' : '#eaf6ed';
        toast.style.border =
          messageType === 'error' ? '1px solid #d84a4a' : '1px solid #2f7d4b';
        toast.style.color = '#111';
        toast.innerHTML = formattedMessage;
        toastRoot.appendChild(toast);

        window.setTimeout(function () {
          if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
          }
        }, 10000);

        if (Drupal.Message) {
          new Drupal.Message().add(text, { type: messageType });
          return;
        }

        if (window.alert && messageType === 'error') {
          window.alert(text);
        }
      }

      function setButtonState(button, isRunning) {
        const label = isRunning ? 'stop timer' : 'start timer';
        button.setAttribute('data-state', isRunning ? 'running' : 'stopped');
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
        button.classList.toggle('is-running', isRunning);

        const hiddenText = button.querySelector('.burndown-time-timer-label');
        if (hiddenText) {
          hiddenText.textContent = label;
        }
      }

      function resetButtons() {
        buttons.forEach(function (button) {
          setButtonState(button, false);
        });
      }

      function applyActiveTicket(ticketId) {
        resetButtons();
        if (!ticketId) {
          return;
        }

        buttons.forEach(function (button) {
          if (button.getAttribute('data-task-ticket-id') === ticketId) {
            setButtonState(button, true);
          }
        });
      }

      function readTicketId(result) {
        if (!result || !result.timer) {
          return null;
        }

        return result.timer.ticket_id || null;
      }

      $.ajax({
        url: '/burndown/api/time-tracker/state',
        method: 'GET',
        dataType: 'json',
      }).done(function (result) {
        if (result && result.success) {
          applyActiveTicket(readTicketId(result));
        }
      });

      buttons.forEach(function (button) {
        setButtonState(button, false);

        $(button).on('click', function (event) {
          event.preventDefault();
          event.stopPropagation();

          const isRunning = button.getAttribute('data-state') === 'running';
          const ticketId = button.getAttribute('data-task-ticket-id');
          let request;

          button.disabled = true;

          if (isRunning) {
            request = $.ajax({
              url: '/burndown/api/time-tracker/stop',
              method: 'POST',
              dataType: 'json',
            });
          } else {
            request = $.ajax({
              url: `/burndown/api/time-tracker/start/${encodeURIComponent(
                ticketId,
              )}`,
              method: 'POST',
              dataType: 'json',
            });
          }

          request
            .done(function (result) {
              if (!result || !result.success) {
                notify(
                  result && result.message
                    ? result.message
                    : 'Timer request failed.',
                  'error',
                );
                return;
              }

              applyActiveTicket(readTicketId(result));
              notify(result.message || '', 'status');
            })
            .fail(function () {
              notify('Timer request failed. Please reload the page.', 'error');
            })
            .always(function () {
              button.disabled = false;
            });
        });
      });
    },
  };
})(jQuery, Drupal, once);
