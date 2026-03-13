/**
 * @file
 * Fullcalendar View plugin JavaScript file.
 */

// Jquery wrapper for drupal to avoid conflicts between libraries.
(function ($, Drupal, drupalSettings) {
  var initialLocaleCode = 'en';
  // Dialog index.
  var dialogIndex = 0;
  // Dialog objects.
  var dialogs = [];
  // Date entry clicked.
  var slotDate;

  /**
   * @see https://fullcalendar.io/docs/v4/eventSourceSuccess
   */
  function eventSourceSuccessRenderingBackground(content) {
    for(let i = 0; i < content.length; i++){
      content[i].rendering = 'background';
    }
  }

  /**
   * Event render handler
   */
  function eventRender (info) {
    let viewIndex = parseInt(this.el.getAttribute('data-calendar-view-index'));
    let viewSettings = drupalSettings.fullCalendarView[viewIndex];

    if (info.el.classList.contains('fc-bgevent')) {
      info.el.setAttribute('title', Drupal.checkPlain(info.event.title));
    }

    // Event title html markup.
    let eventTitleEle = info.el.getElementsByClassName('fc-title');
    if(eventTitleEle.length > 0) {
      eventTitleEle[0].innerHTML = info.event.title;
    }
    // Event list tile html markup.
    let eventListTitleEle = info.el.getElementsByClassName('fc-list-item-title');
    if(eventListTitleEle.length > 0) {
      if (info.event.url) {
        eventListTitleEle[0].innerHTML = '<a href="' + encodeURI(info.event.url) + '">' + info.event.title + '</a>';
      }
      else {
        eventListTitleEle[0].innerHTML = info.event.title;
      }
    }
    // Modal popup
    if (viewSettings.dialogModal) {
      if ($(info.el).is('a')) {
        $(info.el).addClass('use-ajax');
        if (!viewSettings.dialogCanvas) {
          $(info.el).attr('data-dialog-type', 'modal');
        }
        else {
          $(info.el).attr('data-dialog-type', 'dialog');
          $(info.el).attr('data-dialog-renderer', 'off_canvas');
        }
        $(info.el).attr('data-dialog-options', viewSettings.dialog_modal_options);
        $(info.el).attr('href', $(info.el).attr('href').replaceAll('&amp;', '&'));
      }
      else {
        $(info.el).find('a').each(function(){
          if (!viewSettings.dialogCanvas) {
            $(this).attr('data-dialog-type', 'modal');
          }
          else {
            $(this).attr('data-dialog-type', 'dialog');
            $(this).attr('data-dialog-renderer', 'off_canvas');
          }
          $(this).attr('data-dialog-options', viewSettings.dialog_modal_options);
          $(this).addClass('use-ajax');
          $(this).attr('href', $(this).attr('href').replaceAll('&amp;', '&'));
        });
      }
    }
  }

  /**
   * Formats a date object into 'YYYY-MM-DD HH:MM:SS' UTC format.
   *
   * @param {Date} date
   *   The date object to format.
   *
   * @return {string}
   *   The formatted date string.
   */
  function getUTCDateTimeString(date) {
    const year = date.getUTCFullYear();
    const month = ('0' + (date.getUTCMonth() + 1)).slice(-2);
    const day = ('0' + date.getUTCDate()).slice(-2);
    const hours = ('0' + date.getUTCHours()).slice(-2);
    const minutes = ('0' + date.getUTCMinutes()).slice(-2);
    const seconds = ('0' + date.getUTCSeconds()).slice(-2);
    return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
  }

  /**
   * Generic handler for updating an event via AJAX after drop or resize.
   *
   * @param {object} info
   *   The event information from FullCalendar.
   * @param {string} confirmationMessage
   *   The message to show in the confirmation dialog.
   */
  function updateEventHandler(info, confirmationMessage) {
    const end = info.event.end;
    const start = info.event.start;
    let strEnd = '';
    let strStart = '';
    let viewIndex = parseInt(this.el.getAttribute("data-calendar-view-index"));
    let viewSettings = drupalSettings.fullCalendarView[viewIndex];

    // Infer field type from the raw event data passed from the server.
    // A 'date' field is passed as 'YYYY-MM-DD' (length 10).
    // A 'datetime' field is passed as 'YYYY-MM-DD HH:MM:SS' (length 19).
    const rawEvent = info.event._def.raw;
    const isDateTimeField = rawEvent && rawEvent.start && rawEvent.start.length > 10;

    // Format the start date.
    if (start) {
      if (info.event.allDay && !isDateTimeField) {
        // For all-day events on date-only fields, send 'YYYY-MM-DD'.
        strStart = getUTCDateTimeString(start).substring(0, 10);
      }
      else {
        // For timed events or all-day events on datetime fields, send full UTC string.
        strStart = getUTCDateTimeString(start);
      }
    }

    // Define the end date string in 'YYYY-MM-DD HH:MM:SS' format.
    if (end) {
      if (info.event.allDay && !isDateTimeField) {
        // For all-day events on date-only fields, we need the inclusive end date.
        // FullCalendar's `end` is exclusive, so subtract one day.
        const inclusiveEnd = new Date(end.valueOf());
        inclusiveEnd.setDate(inclusiveEnd.getDate() - 1);
        strEnd = getUTCDateTimeString(inclusiveEnd).substring(0, 10);
      }
      else {
        // For timed events or all-day events on datetime fields, send the
        // exclusive end date as a full UTC string. This is what FullCalendar
        // provides, so no modification is needed.
        strEnd = getUTCDateTimeString(end);
      }
    }

    const title = info.event.title.replace(/(<([^>]+)>)/ig,"");
    const msg = Drupal.t(confirmationMessage, {
      '@title': title,
      '@event_start': strStart,
      '@event_end': strEnd
    });

    if (viewSettings.updateConfirm === 1 && !confirm(msg)) {
      info.revert();
    }
    else {
      /**
       * Perform ajax call for event update in database.
       */
      jQuery
        .post(
          drupalSettings.path.baseUrl + "fullcalendar-view-event-update",
          {
            eid: info.event.extendedProps.eid,
            entity_type: viewSettings.entityType,
            start: strStart,
            end: strEnd,
            start_field: viewSettings.startField,
            end_field: viewSettings.endField,
            token: viewSettings.token
          }
        )
        .done(function(data) {
          if (data !== '1') {
            alert("Error: " + data);
            info.revert();
          }
        });
    }
  }

  /**
   * Event resize handler
   */
  function eventResize(info) {
    const message = '@title end is now @event_end. Do you want to save this change?';
    updateEventHandler.call(this, info, message);
  }

  // Day entry click call back function.
  function dayClickCallback(info) {
    slotDate = info.dateStr;
  }

  // Event click call back function.
  function eventClick(info) {
    slotDate = null;
    info.jsEvent.preventDefault();
    let thisEvent = info.event;
    let viewIndex = parseInt(this.el.getAttribute("data-calendar-view-index"));
    let viewSettings = drupalSettings.fullCalendarView[viewIndex];
    let des = thisEvent.extendedProps.des;
    // Show the event detail in a pop up dialog.
    if (viewSettings.dialogWindow) {
      let dataDialogOptionsDetails = {};
      if ( des == '') {
        return false;
      }

      const jsFrame = new JSFrame({
        parentElement:info.el,//Set the parent element to which the jsFrame is attached here
      });
      // Position offset.
      let posOffset = dialogIndex * 20;
      // Dialog options.
      let dialogOptions = JSON.parse(viewSettings.dialog_options);
      dialogOptions.left += posOffset + info.jsEvent.pageX;
      dialogOptions.top += posOffset + info.jsEvent.pageY;
      dialogOptions.title = dialogOptions.title ? dialogOptions.title : thisEvent.title.replace(/(<([^>]+)>)/ig,"");
      dialogOptions.html = des;
      //Create window
      dialogs[dialogIndex] = jsFrame.create(dialogOptions);

      dialogs[dialogIndex].show();
      dialogIndex++;
      Drupal.ajax.bindAjaxLinks(document.body);

      return false;
    }
    // Open a new window to show the details of the event.
    if (thisEvent.url) {
      let eventURL = new URL(thisEvent.url, location.origin);
      if (eventURL.origin === "null") {
        // Invalid URL.
        return false;
      }
      if (viewSettings.openEntityInNewTab) {
        // Open a new window to show the details of the event.
       window.open(thisEvent.url);
       return false;
      }
      else {
        // Open in same window
        window.location.href = thisEvent.url;
        return false;
      }
    }

    return false;
  }

  // Event drop call back function.
  function eventDrop(info) {
    const message = '@title start is now @event_start and end is now @event_end - Do you want to save this change?';
    updateEventHandler.call(this, info, message);
  }

  function datesRender (info) {
    Drupal.attachBehaviors(info.el);
  }
  function datesDestroy (info) {
    Drupal.detachBehaviors(info.el);
  }

  // Build the calendar objects.
  function buildCalendars() {
    $('.js-drupal-fullcalendar')
    .each(function () {
      if ($(this).data('fullcalendar-view-processed')) {
        return;
      }
      $(this).data('fullcalendar-view-processed', true);

      let calendarEl = this;
      let viewIndex = parseInt(calendarEl.getAttribute("data-calendar-view-index"));
      let viewSettings = drupalSettings.fullCalendarView[viewIndex];
      var calendarOptions = JSON.parse(viewSettings.calendar_options);
      // Switch default view at mobile widths.
      if (calendarOptions.mobileWidth !== undefined && calendarOptions.defaultMobileView !== undefined && $(window).width() <= calendarOptions.mobileWidth) {
       calendarOptions.defaultView = calendarOptions.defaultMobileView;
      }
      // Bind the render event handler.
      calendarOptions.eventRender = eventRender;
      // Bind the resize event handler.
      calendarOptions.eventResize = eventResize;
      // Bind the day click handler.
      calendarOptions.dateClick = dayClickCallback;
      // Bind the event click handler.
      calendarOptions.eventClick = eventClick;
      // Bind the drop event handler.
      calendarOptions.eventDrop = eventDrop;
      // Trigger Drupal behaviors when calendar events are updated.
      calendarOptions.datesRender = datesRender;
      // Trigger Drupal behaviors when calendar events are destroyed.
      calendarOptions.datesDestroy = datesDestroy;
      // Language select element.
      var localeSelectorEl = document.getElementById('locale-selector-' + viewIndex);

      if (viewSettings.fetchGoogleHolidays === true) {
        calendarOptions.plugins = calendarOptions.plugins || [];
        calendarOptions.plugins.push('googleCalendar');
        calendarOptions.googleCalendarApiKey = viewSettings.googleCalendarAPIKey;
        calendarOptions.eventSources = calendarOptions.eventSources || [];

        calendarOptions.eventSources.push({
          id: viewSettings.googleCalendarGroup,
          googleCalendarId: viewSettings.googleCalendarGroup,
          rendering: viewSettings.renderGoogleHolidaysAsBackground ? 'background' : '',
          success: viewSettings.renderGoogleHolidaysAsBackground ? eventSourceSuccessRenderingBackground : null,
        });
      }

      // Allow passing a default date via query string.
      const params = (new URL(document.location)).searchParams;
      const initialDate = params.get('initialDate')
      if (initialDate) {
        calendarOptions.defaultDate = initialDate;
      }

      // Initial the calendar.
      if (calendarEl) {
        if (drupalSettings.calendar) {
          drupalSettings.calendar[viewIndex] = new FullCalendar.Calendar(calendarEl, calendarOptions);
        }
        else {
          drupalSettings.calendar = [];
          drupalSettings.calendar[viewIndex] = new FullCalendar.Calendar(calendarEl, calendarOptions);
        }
        let calendarObj = drupalSettings.calendar[viewIndex];
        calendarObj.render();
        // Language dropdown box.
        if (viewSettings.languageSelector) {
          // build the locale selector's options
          calendarObj.getAvailableLocaleCodes().forEach(function(localeCode) {
            var optionEl = document.createElement('option');
            optionEl.value = localeCode;
            optionEl.selected = localeCode == calendarOptions.locale;
            optionEl.innerText = localeCode;
            localeSelectorEl.appendChild(optionEl);
          });
          // when the selected option changes, dynamically change the calendar option
          localeSelectorEl.addEventListener('change', function() {
            if (this.value) {
              let viewIndex = parseInt(this.getAttribute("data-calendar-view-index"));
              drupalSettings.calendar[viewIndex].setOption('locale', this.value);
            }
          });
        }
        else if (localeSelectorEl){
          localeSelectorEl.style.display = "none";
        }

        // Double click event.
        calendarEl.addEventListener('dblclick' , function(e) {
          let viewIndex = parseInt(this.getAttribute("data-calendar-view-index"));
          let viewSettings = drupalSettings.fullCalendarView[viewIndex];
          // New event window can be open if following conditions match.
          // * The new event content type are specified.
          // * Allow to create a new event by double click.
          // * User has the permission to create a new event.
          // * The add form for the new event type is known.
          if (
              slotDate &&
              viewSettings.eventBundleType &&
              viewSettings.dblClickToCreate &&
              viewSettings.addForm !== ""
            ) {
              // Open a new window to create a new event (content).
              window.open(
                  drupalSettings.path.baseUrl +
                  viewSettings.addForm +
                  "?start=" +
                  slotDate +
                  "&start_field=" +
                  viewSettings.startField +
                  "&destination=" + window.location.pathname,
                "_blank"
              );
            }

        });
      }
    });
  }

  // document.ready event does not work with BigPipe.
  // The workaround is to ckeck the document state
  // every 100 milliseconds until it is completed.
  // @see https://www.drupal.org/project/drupal/issues/2794099#comment-13274828
  var checkReadyState = setInterval(function() {
    if (
        document.readyState === "complete" &&
        $('.js-drupal-fullcalendar').length > 0
        ) {
      clearInterval(checkReadyState);
      // Build calendar objects.
      buildCalendars();
    }
  }, 100);

  // After an Ajax call, the calendar objects need to rebuild,
  // to reflect the changes, such as Ajax filter.
  $(document).ajaxComplete(function (event, request, settings) {
    // Do not interfere with event update ajax calls.
    if (settings.url === drupalSettings.path.baseUrl + 'fullcalendar-view-event-update') {
      return;
    }

    if (drupalSettings.calendar) {
      let calendarsRemoved = false;
      const newCalendarRegistry = [];

      drupalSettings.calendar.forEach(function (calendar, index) {
        if ($.contains(document.documentElement, calendar.el)) {
          newCalendarRegistry[index] = calendar;
        }
        else {
          calendar.destroy();
          calendarsRemoved = true;
        }
      });

      // If any calendars were removed, or if new calendar elements were added,
      // we need to run buildCalendars() to initialize the new ones.
      const uninitialized = $('.js-drupal-fullcalendar').filter(function () {
        return !$(this).data('fullcalendar-view-processed');
      });

      if (calendarsRemoved || uninitialized.length > 0) {
        drupalSettings.calendar = newCalendarRegistry;
        buildCalendars();
      }
    }
  });

})(jQuery, Drupal, drupalSettings);
