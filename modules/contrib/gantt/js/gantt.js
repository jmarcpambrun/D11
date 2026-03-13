class ClassGanttView {
  constructor(element_id, settingsGantt, data = [], links = []) {
    this.id_gantt = element_id;
    this.settingsGantt = settingsGantt;
    this.Gantt = gantt;
    if (!settingsGantt.use_cdn) {
      this.Gantt = Gantt.getGanttInstance();
    }
    this.Gantt.config.sort = true;
    this.Gantt.config.fit_tasks = true;
    this.Gantt.config.static_background = false;
    this.Gantt.config.open_tree_initially = true;
    this.Gantt.config.order_branch = 'marker';
    this.Gantt.config.order_branch_free = true;
    this.Gantt.config.date_format = "%Y-%m-%d %H:%i";
    this.Gantt.config.date_grid = settingsGantt.date_format;
    this.Gantt.config.wai_aria_attributes = false;

    this.Gantt.config.duration_unit = 'day';
    this.Gantt.config.duration_step = 1;
    this.Gantt.config.time_step = 1440;
    if (settingsGantt.format_date_actually === 'datetime') {
      this.Gantt.config.duration_unit = 'minute';
      this.Gantt.config.time_step = 1;
      this.formatDuration('minute');
    }

    this.i18n = this.geni18n()

    this.defaultHeightTask = {
      bar_height: 26,
      row_height: 35
    }

    if (settingsGantt['link_css']) {
      this.Gantt.config.link_css = settingsGantt['link_css'];
    }

    this.options = settingsGantt.control_bar ?? {};

    this.setPlugin({});

    this.taskLayer = {};

    this.zoomConfig = {};
    this.setScale()

    this.markerToday();

    this.setLocales();

    this.setColumn();

    this.setLightBox();

    this.onCollapse();

    this.onUndoRedo();

    this.onInDentOutDent();

    this.toggleGridChart();

    this.modalDashboard();

    this.exportTo();

    this.copyTask();

    this.onZoomFit();

    this.onFullScreen();

    this.onFullScreenInGantt();

    this.searchText()

    this.searchDate()

    this.groupSource()

    this.setTooltip()

    this.limitCreateLink()

    this.onPermissionTaskLink();

    this.validateField();

    this.onScrollToDay();

    this.requiredParent();

    this.importMMP(settingsGantt.import)

    this.dp = this.Gantt.createDataProcessor({
      url: settingsGantt.ajax,
      mode: "POST"
    });
    this.onHandlerRequest();

    // Settings Show end.
    if (settingsGantt.show_end) {
      this.onShowEndColumn();
    }

    // Settings Show process.
    if (settingsGantt.show_progress) {
      this.onShowProgressColumn();
    }

    // Settings work time gantt chart.
    if (settingsGantt.work_time && settingsGantt.work_day.length) {
      this.setWorkTime(settingsGantt.work_day, settingsGantt.holidays);
    }

    // Settings readonly.
    if (!settingsGantt.edit_task) {
      this.onReadonly()
    }

    // Settings add task.
    if (!settingsGantt.add_task) {
      this.onAddTask()
    }

    // Settings form type multiple select.
    if (settingsGantt.setting_resource.resource.length) {
      this.multipleSelectForm()
    }

    // Settings form type date.
    if (settingsGantt.time_input_mode === 'responsive') {
      this.dateForm()
    }

    // Schedule planned date.
    if (settingsGantt.planned_date && settingsGantt.auto_schedule_planned && !settingsGantt.use_cdn) {
      this.onSchedulePlanned();
    }

    // Settings form type number.
    if (settingsGantt.custom_field.length) {
      this.numberForm()
    }

    // Settings Auto auto type.
    if (this.options.auto_type  && !settingsGantt.use_cdn) {
      this.onAutoType();
    }

    // Settings Auto auto type.
    if (settingsGantt.planned_date && !settingsGantt.use_cdn) {
      this.onShowPlanedGantt();
      this.onDurationPlan();
    }

    // Settings Auto schedule.
    if (this.options.auto_schedule && !settingsGantt.use_cdn) {
      this.onAutoSchedule();
    }

    // Settings Shows the critical path in the chart.
    if (this.options.critical_path && !settingsGantt.use_cdn) {
      this.onHighlightCriticalPath();
    }

    // Settings click drag.
    if (this.options.click_drag && !settingsGantt.use_cdn) {
      this.onClickDrag()
    }

    // Settings drag project.
    if (this.options.drag_project) {
      this.onDragProject();
    }

    // Settings round_dnd_dates.
    if (this.options.round_dnd_dates) {
      this.onMinimumStep();
    }

    // Settings Hide weekend scale.
    if (this.options.hide_weekend_scale && !settingsGantt.use_cdn) {
      this.onHideNotWorkingTime();
    }

    // Settings Hide more task by level.
    if (this.settingsGantt.hide_add_task_level) {
      let level = Math.max(parseInt(this.settingsGantt.hide_add_task_level_value) || 0, 0);
      this.onHideAddTask(level);
    }

    // Settings Show column WBS.
    if (this.options.show_column_wbs) {
      this.showColumnWBS();
    }

    // Settings Limit editing of completed tasks.
    if (this.options.lock_completed_task && !settingsGantt.native_dialog) {
      this.onLookTaskComplete();
    }

    // Settings Highlights drag task.
    if (this.options.highlight_drag_task && !settingsGantt.use_cdn) {
      this.highLightDragTask();
      this.highLightProject();
    }

    // Settings Dynamic progress summary.
    if (this.options.dynamic_progress) {
      this.onDynamicProgress();
    }

    // Settings Show slack.
    if (this.options.show_slack && !settingsGantt.use_cdn) {
      this.showSlack();
    }

    // Settings Text progress.
    if (this.options.progress_text) {
      this.onProgressText();
    }

    // Settings Column buttons.
    if (settingsGantt.column_buttons && settingsGantt.add_task) {
      this.onShowButtonColumn();
    }

    // Settings Last of the day.
    if (settingsGantt.last_of_the_day) {
      this.options.last_of_the_day = true;
    }

    // Settings Show button detail.
    if (settingsGantt.show_button_detail && !settingsGantt.native_dialog) {
      this.onButtonDetail();
    }

    // Settings Show button complete.
    if (!settingsGantt.native_dialog) {
      this.onButtonComplete();
    }

    // Settings Native dialog.
    if (settingsGantt.native_dialog) {
      this.onNativeDialog();
    }

    // Settings Hide show column.
    if (settingsGantt.hide_show_column && !settingsGantt.use_cdn) {
      this.hideShowColumn()
    }

    if (settingsGantt.order) {
      this.onPostOrder()
    }
  }

  geni18n() {
    return {
      date: {
        month_full: [
          Drupal.t("January"),
          Drupal.t("February"),
          Drupal.t("March"),
          Drupal.t("April"),
          Drupal.t("May"),
          Drupal.t("June"),
          Drupal.t("July"),
          Drupal.t("August"),
          Drupal.t("September"),
          Drupal.t("October"),
          Drupal.t("November"),
          Drupal.t("December"),
        ],
        month_short: [
          Drupal.t("Jan"),
          Drupal.t("Feb"),
          Drupal.t("Mar"),
          Drupal.t("Apr"),
          Drupal.t("May"),
          Drupal.t("Jun"),
          Drupal.t("Jul"),
          Drupal.t("Aug"),
          Drupal.t("Sep"),
          Drupal.t("Oct"),
          Drupal.t("Nov"),
          Drupal.t("Dec"),
        ],
        day_full: [
          Drupal.t("Sunday"),
          Drupal.t("Monday"),
          Drupal.t("Tuesday"),
          Drupal.t("Wednesday"),
          Drupal.t("Thursday"),
          Drupal.t("Friday"),
          Drupal.t("Saturday"),
        ],
        day_short: [
          Drupal.t("Sun"),
          Drupal.t("Mon"),
          Drupal.t("Tue"),
          Drupal.t("Wed"),
          Drupal.t("Thu"),
          Drupal.t("Fri"),
          Drupal.t("Sat"),
        ]
      },
      labels: {
        new_task: Drupal.t("New task"),
        icon_save: Drupal.t("Save"),
        icon_cancel: Drupal.t("Cancel"),
        icon_details: Drupal.t("Details"),
        icon_edit: Drupal.t("Edit"),
        icon_delete: Drupal.t("Delete"),
        gantt_save_btn: Drupal.t("New Label"),
        gantt_cancel_btn: Drupal.t("New Label"),
        gantt_delete_btn: Drupal.t("New Label"),
        confirm_closing: Drupal.t(""),// Your changes will be lost, are you sure?
        confirm_deleting: Drupal.t("Task will be deleted permanently, are you sure?"),
        section_description: Drupal.t("Description"),
        section_time: Drupal.t("Time period"),
        section_baseline: Drupal.t("Time planned"),
        baseline_enable_button: Drupal.t("Set"),
        baseline_disable_button: Drupal.t("Cancel"),
        button_responsive_toggle: Drupal.t("Set"),
        button_responsive_disable_toggle: Drupal.t("Cancel"),
        section_type: Drupal.t("Type"),
        section_parent: Drupal.t("Task parent"),

        /* grid columns */
        column_wbs: Drupal.t("WBS"),
        column_text: Drupal.t("Task name"),
        column_start_date: Drupal.t("Start time"),
        column_end_date: Drupal.t("End time"),
        column_duration: Drupal.t("Duration"),
        column_planned_duration: Drupal.t("Planned duration"),
        column_progress: Drupal.t("Progress"),
        column_add: Drupal.t(""),
        column_priority: Drupal.t("Priority"),
        column_totalSlack: Drupal.t("Total slack"),
        column_freeSlack: Drupal.t("Free slack"),

        /* link confirmation */
        link: Drupal.t("Link"),
        confirm_link_deleting: Drupal.t("will be deleted"),
        link_start: Drupal.t(" (start)"),
        link_end: Drupal.t(" (end)"),

        type_task: Drupal.t("Task"),
        type_project: Drupal.t("Project"),
        type_milestone: Drupal.t("Milestone"),

        minutes: Drupal.t("Minutes"),
        hours: Drupal.t("Hours"),
        days: Drupal.t("Days"),
        weeks: Drupal.t("Week"),
        months: Drupal.t("Months"),
        years: Drupal.t("Years"),

        /* message popup */
        message_ok: Drupal.t("OK"),
        message_cancel: Drupal.t("Cancel"),

        /* constraints */
        section_constraint: Drupal.t("Constraint"),
        constraint_type: Drupal.t("Constraint type"),
        constraint_date: Drupal.t("Constraint date"),
        asap: Drupal.t("As Soon As Possible"),
        alap: Drupal.t("As Late As Possible"),
        snet: Drupal.t("Start No Earlier Than"),
        snlt: Drupal.t("Start No Later Than"),
        fnet: Drupal.t("Finish No Earlier Than"),
        fnlt: Drupal.t("Finish No Later Than"),
        mso: Drupal.t("Must Start On"),
        mfo: Drupal.t("Must Finish On"),

        /* resource control */
        resources_filter_placeholder: Drupal.t("type to filter"),
        resources_filter_label: Drupal.t("hide empty"),
      }
    }
  }

  setConfig(config) {
    if (Object.keys(config).length) {
      for (const key_config in config) {
        this.Gantt[key_config] = config[key_config];
      }
    }
  }

  onSchedulePlanned(Gantt = this.Gantt) {
    let listSchedulePlanned = [];
    const schedulePlanned = (task) => {
      if (!task.planned_start || !task.planned_end) return
      let link_source = task.hasOwnProperty('$source') ? task.$source : [];
      if (link_source.length) {
        link_source.forEach(function (id_link) {
          if (Gantt.isLinkExists(id_link)) {
            const link = Gantt.getLink(id_link)
            let task_target = Gantt.getTask(link.target)
            if (!task_target.planned_start || !task_target.planned_end) return
            if (!link.hasOwnProperty('lag') || typeof link.lag === null) {
              link.lag = 0
            }
            const lag = link.lag;
            let date_schedule = null;
            let diff_time = 0;
            switch (link.type) {
              case '1':
                date_schedule = Gantt.calculateEndDate({start_date: task.planned_start, unit: Gantt.config.duration_unit, duration: Number(lag)})
                diff_time = Gantt.calculateDuration({start_date: task_target.planned_start, end_date: date_schedule})
                if (diff_time === 0) return;
                break;

              case '2':
                date_schedule = Gantt.calculateEndDate({start_date: task.planned_end, unit: Gantt.config.duration_unit, duration: Number(lag)})
                diff_time = Gantt.calculateDuration({start_date: task_target.planned_end, end_date: date_schedule})
                if (diff_time === 0) return;
                break;

              case '3':
                date_schedule = Gantt.calculateEndDate({start_date: task.planned_start, unit: Gantt.config.duration_unit, duration: Number(lag)})
                diff_time = Gantt.calculateDuration({start_date: task_target.planned_end, end_date: date_schedule})
                if (diff_time === 0) return;
                break;

              default:
                date_schedule = Gantt.calculateEndDate({start_date: task.planned_end, unit: Gantt.config.duration_unit, duration: Number(lag)})
                diff_time = Gantt.calculateDuration({start_date: task_target.planned_start, end_date: date_schedule})
                if (diff_time === 0) return;
            }
            if (diff_time !== 0) {
              let target_duration = Gantt.calculateDuration({start_date: task_target.planned_start, end_date: task_target.planned_end})
              let closest_work = null;
              switch (link.type) {
                case '2':
                case '3':
                  closest_work = Gantt.getClosestWorkTime({date: date_schedule, dir: "past", unit: "minute"});
                  task_target.planned_end = closest_work
                  task_target.planned_start = Gantt.calculateEndDate({start_date: task_target.planned_end, unit: Gantt.config.duration_unit, duration: -target_duration})
                  break;

                default:
                  closest_work = Gantt.getClosestWorkTime({date: date_schedule, dir: "future", unit: "minute"});
                  task_target.planned_start = closest_work
                  task_target.planned_end = Gantt.calculateEndDate({start_date: task_target.planned_start, unit: Gantt.config.duration_unit, duration: target_duration})
              }

              Gantt.refreshTask(task_target.id);
              Gantt.message(Drupal.t("<b>@task_target</b> is scheduled schedule has been rescheduled to @date due to @task_source restrictions.", {"@date": Gantt.date.date_to_str("%Y-%m-%d %H:%i")(task_target.planned_start), "@task_target": task_target.text || "", "@task_source": task.text || ""}))
              listSchedulePlanned.push({
                id: task_target.id,
                planned_start: task_target.planned_start,
                planned_end: task_target.planned_end
              })
            }
            schedulePlanned(task_target)
          }
        })
      }
    }

    Gantt.attachEvent("onAfterLinkAdd", function(id, link){
      if (Gantt.config.auto_scheduling) {
        const task = Gantt.getTask(link.source)
        schedulePlanned(task)
        link.listSchedulePlanned = [...listSchedulePlanned]
      }
      listSchedulePlanned = [];
      return true;
    });

    Gantt.attachEvent("onAfterLinkUpdate", function(id, link){
      if (Gantt.config.auto_scheduling) {
        const task = Gantt.getTask(link.source)
        schedulePlanned(task)
        link.listSchedulePlanned = [...listSchedulePlanned]
      }
      listSchedulePlanned = [];
      return true;
    });

    Gantt.attachEvent("onLightboxSave", function(id, task, is_new) {
      if (!is_new && Gantt.config.auto_scheduling) {
        schedulePlanned(task)
        task.listSchedulePlanned = [...listSchedulePlanned]
        listSchedulePlanned = [];
      }
      return true;
    })
  }

  setPlugin(plugins) {
    if (!Object.keys(plugins).length) {
      plugins = {
        quick_info: false,
        keyboard_navigation: true,
        click_drag: true,
        fullscreen: true,
        marker: true,
        critical_path: true,
        tooltip: true,
        multiselect: true,
        auto_scheduling: true,
        drag_timeline: true,
        grouping: true,
        overlay: true,
        undo: true,
        export_api: true,
      }
    }

    if (this.settingsGantt.input_search_text) {
      plugins.keyboard_navigation = false;
    }

    this.Gantt.plugins(plugins);

    if (plugins.drag_timeline) {
      this.Gantt.config.drag_timeline = {
        ignore: "",
        useKey: "ctrlKey"
      };

    }
  }

  modalDashboard($this = this) {
    let Gantt = this.Gantt
    let settingsGantt = this.settingsGantt;
    let optionsControl = settingsGantt.control_bar_label;
    let options = this.options;
    Gantt.attachEvent('onGanttReady', function () {
      let elGantt = Gantt.$root.closest('.gantt-wrapper')
      if (elGantt.querySelector('[data-action="dashboard"]')) {
        elGantt.querySelector('[data-action="dashboard"]').addEventListener('click', function () {
          if (settingsGantt.use_cdn) {
            delete optionsControl.auto_type
            delete optionsControl.auto_schedule
            delete optionsControl.critical_path
            delete optionsControl.click_drag
            delete optionsControl.drag_project
            delete optionsControl.hide_weekend_scale
            delete optionsControl.highlight_drag_task
            delete optionsControl.show_slack
          }

          let content = '<div class="wrapper-dashboard form-switch">';

          for (const key in optionsControl) {
            const checked = options[key] || Number.isInteger(options[key]) ? 'checked' : '';
            content += `
            <div class="form-check d-inline-block">
              <input type="checkbox" ${checked} data-action="${key}" id="${key}" autocomplete="off" class="form-check-input" />
              <label for="${key}" class="form-check-label" >${optionsControl[key]}</label>
            </div>
            `;
          }
          content += '</div>'

          const endPopup = () => {
            modal = null;
          }

          let modal = Gantt.modalbox({
            title: Drupal.t('Dashboard'),
            text: content,
            buttons: [
              {label: Drupal.t('Close'), css: "link-cancel-btn", value: "cancel"}
            ],
            width: "760px",
            type: "popup-css-class-here",
            callback: function (result) {
              switch (result) {
                case "cancel":
                  endPopup();
                  break;
              }
            }
          });

          let buttons = document.querySelectorAll('.wrapper-dashboard input[data-action]');
          if (buttons.length) {
            for (let i = 0; i < buttons.length; i++) {
              buttons[i].onclick = function() {
                switch (this.dataset['action']) {
                  case 'auto_type':
                    $this.onAutoType(this.checked)
                    break;

                  case 'auto_schedule':
                    $this.onAutoSchedule(this.checked)
                    break;

                  case 'critical_path':
                    $this.onHighlightCriticalPath(this.checked)
                    break;

                  case 'drag_project':
                    $this.onDragProject(this.checked)
                    break;

                  case 'click_drag':
                    $this.onClickDrag(this.checked)
                    break;

                  case 'round_dnd_dates':
                    $this.onMinimumStep(this.checked)
                    break;

                  case 'hide_weekend_scale':
                    $this.onHideNotWorkingTime(this.checked)
                    break;

                  case 'show_column_wbs':
                    $this.showColumnWBS(this.checked)
                    break;

                  case 'lock_completed_task':
                    if (!settingsGantt.native_dialog) {
                      $this.onLookTaskComplete(this.checked)
                    }
                    break;

                  case 'highlight_drag_task':
                    $this.highLightDragTask(this.checked)
                    break;

                  case 'dynamic_progress':
                    $this.onDynamicProgress(this.checked)
                    break;

                  case 'show_slack':
                    $this.showSlack(this.checked)
                    break;

                  case 'progress_text':
                    $this.onProgressText(this.checked)
                    break;
                  default:
                    endPopup()
                }
                $this.render()
              }
            }
          }
        })
      }
    })
  }

  formatMinuteTime(duration) {
    const minute_duration = duration;
    let minute = minute_duration % 60;
    let hour = Math.floor(minute_duration / 60 % 24);
    let day = Math.floor(minute_duration / 60 / 24);
    let result = [];
    if (day) {
      day = Drupal.t("@dayd", { '@day': day})
      result.push(day)
    }
    if (hour) {
      hour = Drupal.t("@hourh", { '@hour': hour})
      result.push(hour)
    }
    if (minute) {
      minute = Drupal.t("@minutem", { '@minute': minute})
      result.push(minute)
    }
    return result.join(' ')
  }

  formatDuration(value = 'day', $this = this, Gantt = this.Gantt) {
    if (value === 'minute') {
      let column_duration = Gantt.config.columns.find(item => item.name === 'duration');
      if (column_duration) {
        column_duration.template = function(task) {
          return $this.formatMinuteTime(task.duration)
        };
      }
    }
  }

  onScrollToDay(Gantt = this.Gantt, settingsGantt = this.settingsGantt) {
    Gantt.attachEvent('onGanttReady', function () {
      setTimeout(function () {
        let date = new Date();
        const visibleTimelineWidth = Gantt.$task.offsetWidth / 2;
        const position = Gantt.posFromDate(date) - visibleTimelineWidth;
        Gantt.scrollTo(position);
        Gantt.message(Drupal.t("Scrolled to @today", {"@today" : Gantt.date.date_to_str("%Y-%m-%d %H:%i")(date)}));
      }, 1000)
    })
  }

  searchDate(Gantt = this.Gantt) {
    const genCondition = (value_start, value_end) => {
      if (value_start && value_end) {
        return '(task.start_date.valueOf() >= value_start.valueOf() && task.start_date.valueOf() < value_end.valueOf()) || (task.end_date.valueOf() > value_start.valueOf() && task.end_date.valueOf() < value_end.valueOf()) || (task.start_date.valueOf() <= value_start.valueOf() && task.end_date.valueOf() > value_end.valueOf())'
      }
      if (value_start) {
        return '(task.start_date.valueOf() >= value_start.valueOf()) || (task.end_date.valueOf() > value_start.valueOf())'
      }
      if (value_end) {
        return '(task.start_date.valueOf() < value_end.valueOf()) || (task.end_date.valueOf() < value_end.valueOf())'
      }
      return null;
    }
    const filterLogic = (task, match = false) => {
      // check children
      Gantt.eachTask(function (child) {
        if (filterLogic(child)) {
          match = true;
        }
      }, task.id);

      // check task
      if (eval(condition)) {
        if (!data_storage.includes(task.id)) {
          data_storage.push(task.id);
        }
        match = true;
      }
      return match;
    }

    let data_storage = [];
    let condition = null;
    let value_start = null;
    let value_end = null;
    Gantt.attachEvent("onGanttReady", function () {
      let elGantt = Gantt.$root.closest('.gantt-wrapper')
      let buttons = elGantt.querySelectorAll('[data-action^="date"]');
      if (buttons.length) {
        for (let i = 0; i < buttons.length; i++) {
          buttons[i].addEventListener('change', Drupal.debounce(function(e) {
            let date_position = e.target.dataset['action'];
            let date_value_left;
            value_start = null;
            value_end = null;
            if (date_position === 'date-start') {
              value_start = e.target.value ? new Date(e.target.value + " 00:00:00") : null
              date_value_left = elGantt.querySelector('[data-action="date-end"]') || null
              if (date_value_left) {
                value_end = date_value_left.value ? Gantt.date.add(new Date(date_value_left.value + " 00:00:00"), 1, 'day') : null;
              }
            }
            else {
              value_end = e.target.value ? Gantt.date.add(new Date(e.target.value + " 00:00:00"), 1, 'day') : null;
              date_value_left = elGantt.querySelector('[data-action="date-start"]') || null
              if (date_value_left) {
                value_start = date_value_left.value ? new Date(date_value_left.value + " 00:00:00") : null;
              }
            }
            condition = genCondition(value_start, value_end);
            data_storage = [];
            Gantt.refreshData();
            Gantt.config.gantt_data_filter = data_storage;
          }, 500));
        }
      }
    });

    Gantt.attachEvent("onBeforeTaskDisplay", function (id, task) {
      if (!condition) {
        return true;
      }
      return filterLogic(task);
    });
  }

  onMoveRowAction(value = true, Gantt = this.Gantt) {
    if (!value && !Gantt.checkEvent('onBeforeRowDragMove')) {
      Gantt.attachEvent("onBeforeRowDragMove", function(id, parent, tindex) {
        return false;
      }, {id: 'on_move_action'});
    }
    else {
      Gantt.detachEvent('on_move_action')
    }
  }

  groupSource($this = this, Gantt = this.Gantt, settingsGantt = this.settingsGantt) {
    Gantt.attachEvent('onGanttReady', function () {
      let elGantt = Gantt.$root.closest('.gantt-wrapper')
      let button = elGantt.querySelector('[data-action="group"]')
      if (settingsGantt.use_cdn || !settingsGantt.setting_resource.resource_group.length) {
        button.closest('li').remove();
      }
      else {
        button.addEventListener('change', function (e) {
          if (e.target.value) {
            Gantt.groupBy({
              groups: Gantt.serverList(e.target.value),
              relation_property: e.target.value,
              group_id: "key",
              group_text: "label",
              default_group_label: Drupal.t("Unassigned")
            });
            Gantt.sort('order', false)
            if (!Gantt.checkEvent('onBeforeRowDragMove')) {
              $this.onMoveRowAction(false)
            }
          }
          else {
            Gantt.groupBy(false)
            Gantt.sort('order', false)
            $this.onMoveRowAction(false)
          }
        })
        settingsGantt.setting_resource.resource_group.forEach( field => {
          if (settingsGantt.setting_resource.resource.includes(field)) {
            const option = document.createElement("option")
            option.value = field
            option.text = settingsGantt.server_list_resource[field].label
            button.appendChild(option);
          }
        })
      }
    })
  }

  validateField(Gantt = this.Gantt, settingsGantt = this.settingsGantt) {
    const validateStorage = settingsGantt.validate_field || [];
    if (Object.keys(validateStorage).length) {
      Gantt.attachEvent("onLightbox", function (id) {
        const task = Gantt.getTask(id);
        if (task.$new) {
          if (validateStorage?.text?.default_value) {
            const description = Gantt.getLightboxSection('description');
            if (description) description.setValue(validateStorage.text.default_value);
          }
          if (settingsGantt.custom_field && validateStorage?.custom_field?.default_value) {
            const custom_field = Gantt.getLightboxSection('custom_field');
            if (custom_field) custom_field.setValue(validateStorage.custom_field.default_value);
          }
          if (validateStorage.hasOwnProperty('custom_resource') && Object.keys(validateStorage.custom_resource).length) {
            for (const key in validateStorage.custom_resource) {
              const custom_resource = Gantt.getLightboxSection(key);
              if (custom_resource && validateStorage.custom_resource[key].default_value) {
                custom_resource.setValue(validateStorage.custom_resource[key].default_value)
              }
            }
          }

          if (settingsGantt.priority && validateStorage?.priority?.default_value) {
            const priority = Gantt.getLightboxSection(settingsGantt.priority);
            if (priority) priority.setValue(validateStorage.priority.default_value);
          }

          const constraint = Gantt.getLightboxSection('constraint');
          if (constraint && validateStorage?.constraint) {
            if (validateStorage?.constraint?.default_value?.first) {
              let date = new Date(validateStorage.constraint.default_value.second) || null
              constraint.setValue(null, {constraint_type: validateStorage.constraint.default_value.first, constraint_date: date})
            }
          }

          const time = Gantt.getLightboxSection('time');
          if (time && validateStorage?.time) {
            if (validateStorage?.time?.default_value?.start) {
              let date = new Date(validateStorage.time.default_value.start) || null
              let date_end = new Date(validateStorage.time.default_value.end) || null
              time.setValue(null, {start_date: date, end_date: date_end})
            }
          }

          const time_planned = Gantt.getLightboxSection('baseline');
          if (time_planned && validateStorage?.time_planned) {
            if (validateStorage?.time_planned?.default_value?.start) {
              let date = new Date(validateStorage.time_planned.default_value.start) || null
              let date_end = new Date(validateStorage.time_planned.default_value.end) || null
              time_planned.setValue(null, {start_date: date, end_date: date_end})
            }
          }
        }
        return true
      })

      Gantt.attachEvent("onLightboxSave", function (id, task, is_new) {
        if (validateStorage.hasOwnProperty('text')) {
          if (task.text.length > validateStorage.text.max_length) {
            Gantt.alert(Drupal.t("Description cannot be too long"));
            return false;
          }
        }

        if (settingsGantt.custom_field && validateStorage.hasOwnProperty('custom_field')) {
          const label = validateStorage.custom_field.label;
          const required = validateStorage.custom_field.required;
          const max = validateStorage.custom_field.max;
          const min = validateStorage.custom_field.min;
          if (required && !Number.isInteger(parseInt(task.custom_field))) {
            Gantt.alert(Drupal.t("@label cannot be empty.", { '@label': label }));
            return false;
          }
          if (min && max) {
            if (min < task.custom_field && task.custom_field <= max) {
              Gantt.alert(Drupal.t("@label value must be greater than @min and less than @max", { '@label': label, '@max': max, '@mix': min }));
              return false;
            }
          }
          else if (min && typeof max === 'null') {
            Gantt.alert(Drupal.t("@label value must be greater than @min", { '@label': label, '@mix': min }));
            return false;
          }
          else if (max && typeof min === 'null') {
            Gantt.alert(Drupal.t("@label value must be less than @max", { '@label': label, '@max': max }));
            return false;
          }
        }

        if (validateStorage.hasOwnProperty('custom_resource')) {
          for (const idKey in validateStorage.custom_resource) {
            const label = validateStorage.custom_resource[idKey].label;
            const required = validateStorage.custom_resource[idKey].required;
            const cardinality = validateStorage.custom_resource[idKey].cardinality;
            if (task.hasOwnProperty(idKey)) {
              const data = task[idKey];
              if (required && data && data.length === 0) {
                Gantt.alert(Drupal.t("@label cannot be empty.", { '@label': label }));
                return false
              }
              if (cardinality !== -1 && data && data.length > cardinality) {
                Gantt.alert(Drupal.t("@label Choose up to @cardinality.", { '@label': label, '@cardinality': cardinality }));
                return false
              }
            }
          }
        }
        return true;
      })
    }
  }

  requiredParent(Gantt = this.Gantt, settingsGantt = this.settingsGantt) {
    if (settingsGantt.required_parent) {
      Gantt.attachEvent("onLightboxSave", function (id, task, is_new) {
        if (!task.parent) {
          Gantt.alert(Drupal.t("Parent task is required"));
          return false;
        }
        return true;
      })
    }
  }

  setTooltip($this= this, Gantt = this.Gantt, settingsGantt = this.settingsGantt) {

    // Set format date tooltips.
    Gantt.templates.tooltip_date_format = function (date) {
      let formatFunc = Gantt.date.date_to_str(settingsGantt.date_format);
      return formatFunc(date);
    };

    // Setup tooltip content.
    Gantt.templates.tooltip_text = function (start, end, task) {
      let text_end = Gantt.templates.tooltip_date_format(new Date(end.valueOf()));
      // Option Last of the day.
      if (settingsGantt.last_of_the_day && Gantt.config.duration_unit === 'day') {
        text_end = Gantt.templates.tooltip_date_format(new Date(end.valueOf() - 1));
      }

      let duration = Drupal.t('@days days', {'@days': Gantt.calculateDuration(task)});
      if(Gantt.config.duration_unit === 'minute') {
        duration = $this.formatMinuteTime(Gantt.calculateDuration(task))
      }

      let creator = Drupal.t("Unassigned");
      if (typeof task !== 'undefined' && task.hasOwnProperty('creator')) {
        let result = [];

        if (settingsGantt.creator.length && !Gantt.serverList(settingsGantt.creator).length) {
          Gantt.serverList(settingsGantt.creator, settingsGantt.server_list_resource[settingsGantt.creator].data)
        }
        const serverList = Gantt.serverList(settingsGantt['creator']);
        if (task.creator.length && serverList.length) {
          task.creator.forEach(id => {
            let find = serverList.find(item => item.key == id);
            if (find) {
              result.push(find.label)
            }
          })
        }
        if (result.length) {
          creator = result.join(", ")
        }
      }
      let html = [];
      html.push(Drupal.t('@label: @content', {'@label': $this.i18n.labels.column_text, '@content': task.text}))
      html.push(Drupal.t('@label: @content', {'@label': task.type === 'milestone' ? 'Milestone' : $this.i18n.labels.column_start_date, '@content': Gantt.templates.tooltip_date_format(start)}))
      if (!(task.type === 'milestone')) {
        html.push(Drupal.t('@label: @content', {'@label': $this.i18n.labels.column_end_date, '@content': text_end}))
        html.push(Drupal.t('@label: @content', {'@label': $this.i18n.labels.column_duration, '@content': duration}))
      }
      html.push(Drupal.t('Creator: @creator', {'@creator': creator}))
      return html.join('<br>')
    };
  }

  onPermissionTaskLink(Gantt = this.Gantt) {
    Gantt.attachEvent("onLightboxSave", function (id, task, is_new) {
      if (Gantt.isReadonly(id)) {
        Gantt.alert(Drupal.t("You cannot edit this task."));
        return false;
      }
      return true;
    })

    Gantt.attachEvent("onLightboxDelete", function (id, task, is_new) {
      if (Gantt.isReadonly(id)) {
        Gantt.alert(Drupal.t("You cannot delete this task."));
        return false;
      }
      return true;
    })

    Gantt.attachEvent("onLinkDblClick", function(id, e) {
      let link = Gantt.getLink(id);
      if (link.readonly) {
        Gantt.message({
          type: 'error',
          text: Drupal.t("You do not have permission to edit this link."),
          expire: 4000
        });
        return false;
      }
      return true;
    })
  }

  onShowEndColumn(Gantt = this.Gantt) {
    let indexCol = Gantt.config.columns.findIndex((item) => item.name === 'start_date');
    Gantt.config.columns.splice(indexCol + 1, 0, {
      name: "end_date",
      width: 80,
      label: this.i18n.labels.column_end_date,
      align: "center",
      resize: true
    });
  }

  onShowProgressColumn(Gantt = this.Gantt) {
    let indexCol = Gantt.config.columns.findIndex((item) => item.name === 'duration');
    Gantt.config.columns.splice(indexCol + 1, 0, {
      name: "progress",
      width: 80,
      label: this.i18n.labels.column_progress,
      align: "center",
      resize: true,
      template: function (task) {
        let progress = (task.progress || 0) * 100
        return `${progress.toFixed(0)}%`;
      }
    });
  }

  limitCreateLink(Gantt = this.Gantt) {
    Gantt.attachEvent("onBeforeLinkAdd", function(id, link) {
      if (Gantt.isLinkExists(link.source + "-" + link.target)) {
        Gantt.message({
          text: Drupal.t("A link already exists"),
          expire: 4000
        });
        return false;
      }
      return true;
    });
  }

  hideShowColumn($this = this, Gantt = this.Gantt) {
    const id_gantt = this.id_gantt;
    let colHeader = `
        <div class="gantt-dropdown" onclick="controlColumns('${id_gantt}', this)">&#9660;</div>
      `;
    Gantt.config.columns.push({
      name: "control_columns",
      label: colHeader,
      width: 25,
      min_width: 25,
      max_width: 25,
      sort: false,
      hide: false,
      template: () => {
        return '';
      }
    })

    let hideColumnStorage = window.localStorage.getItem('hide_column_' + id_gantt)
    hideColumnStorage = JSON.parse(hideColumnStorage);
    let i18n = Gantt.i18n.getLocale(drupalSettings.path.currentLanguage)
    Gantt.config.columns = Gantt.config.columns.filter(item => {
      if (!item?.label && i18n.labels.hasOwnProperty("column_" + item.name)) {
        item.label = i18n.labels["column_" + item.name];
      }
      if (hideColumnStorage && hideColumnStorage[item.name]) item.hide = true;
      return true;
    })
  }

  importMMP(urlImport, Gantt = this.Gantt) {
    const upload = (Gantt, file, callback, urlImport) => {
      Gantt.importFromMSProject({
        data: file,
        taskProperties: [
          "Summary",
          "Milestone",
        ],
        callback: async function (project) {
          if (project) {
            // Valite date import.
            let formatFunc = gantt.date.str_to_date("%Y-%m-%d %H:%i");
            project.data.data.forEach(item => {
              item.start_date = formatFunc(item.start_date)
              item.end_date = Gantt.calculateEndDate(item.start_date, parseInt(item.duration));
              // Mark tasks import.
              item.isImport = true
            });
            Gantt.clearAll();
            let data = jQuery.extend(true, {}, project?.data || {data: [], links: []})
            Gantt.config.readonly = true
            Gantt.parse(data);
            await jQuery.post(urlImport, data).done(function (result) {
              if (data?.data.length) {
                data.data.forEach((item) => {
                  delete item.isImport
                  const id_mapping = item.id
                  item.id = result['data'][id_mapping]['id']
                  item.parent = result['data'][id_mapping]['parent']
                })
              }
              if (data?.links.length && Object.keys(result['links'])) {
                data.links.forEach((item) => {
                  const id_mapping = item.id
                  item.id = result['links'][id_mapping]['id']
                  item.source = result['links'][id_mapping]['source']
                  item.target = result['links'][id_mapping]['target']
                })
              }
              console.log("Data imported");
            })
              .fail(function (xhr, status, error) {
                console.error("Import failed:", error);
                Gantt.message({
                  type: "error",
                  text: Drupal.t('Import failed @message!', { '@status': xhr.responseText }),
                  expire: 4000
                })
              });
            Gantt.clearAll();
            Gantt.config.readonly = false
            Gantt.parse(data);
            if (callback) {
              callback(project);
            }
          }
        }
      });
    }

    Gantt.attachEvent("onTaskLoading", function (task) {
      if (task.$custom_data) {
        if (task.$custom_data.Summary === "1") {
          task.type = "project";
        }
        if (task.$custom_data.Milestone === "1" || task.duration == "0") {
          task.type = "milestone";
        }
      }
      return true;
    });

    // Import microsoft project.
    Gantt.attachEvent('onGanttReady', function () {
      let fileDnD = fileDragAndDrop();
      fileDnD.init(Gantt.$container);

      const sendFile = (file) => {
        fileDnD.showUpload();
        upload(Gantt, file, function () {
          fileDnD.hideOverlay();
        }, urlImport)
      }

      fileDnD.onDrop(sendFile);
      // Manual Upload file MPP.
      let elGantt = Gantt.$root.closest('.gantt-wrapper');
      let submitFile = elGantt.querySelector("#mspImportBtn");
      if (submitFile) {
        submitFile.addEventListener('click', function (event) {
          event.preventDefault();
          let fileInput = elGantt.querySelector("#mspFile");
          if (fileInput.files[0]) {
            sendFile(fileInput.files[0]);
          }
        });
      }
    })
  }

  onNativeDialog(Gantt = this.Gantt) {
    let settingsGantt = this.settingsGantt;
    const urlGen = (url, param = []) => {
      let regex = /-arg_[0-9]-/g;
      let args = url.match(regex);
      args.forEach(function (element, index) {
        url = url.replace(element, param[index])
      })
      return url;
    }

    Gantt.attachEvent("onTaskDblClick", function (id, e) {
      if (id == null) {
        return false;
      }
      let task = Gantt.getTask(id);
      if (task['type'] === 'project') {
        return false;
      }
      else {
        let url = task.link_detail;
        if (url === undefined) {
          url = settingsGantt.link_detail.edit;
          if (task.hasOwnProperty("readonly")) {
            url = settingsGantt.link_detail.display;
          }
          url = urlGen(url, [id]);
        }
        let ajaxSettings = {
          url: url,
          dialogType: 'modal',
          dialog: {width: '80%'},
        };
        Drupal.ajax(ajaxSettings).execute();
        return false;
      }
    });

    Gantt.attachEvent("onBeforeLightbox", function (id) {
      let task = Gantt.getTask(id);
      if (task == null) {
        return false;
      }
      if (task.$new) {
        if (settingsGantt.add_task !== true) {
          Gantt.deleteTask(task.id);
          return false;
        }
        let add_link = settingsGantt.add_link + '&parent=' + task.parent + '&parent_field=' + settingsGantt.parent_field;
        let ajaxSettings = {
          url: add_link,
          dialogType: 'modal',
          dialog: {width: '80%'},
        };
        Drupal.ajax(ajaxSettings).execute();
        Gantt.deleteTask(task.id);
        return false;
      }
      return true;
    });

  }

  onAddTask(Gantt = this.Gantt) {
    Gantt.config.columns = Gantt.config.columns.filter(item => item.name !== 'add');
  }

  onReadonly(Gantt = this.Gantt) {
    Gantt.config.readonly = true
  }

  toggleGridChart(Gantt = this.Gantt) {
    Gantt.attachEvent("onGanttReady", function() {
      let elGantt = Gantt.$root.closest('.gantt-wrapper');
      let buttons = elGantt.querySelectorAll('[data-action="toggle_grid"], [data-action="toggle_chart"]');
      if (buttons.length) {
        for (let i = 0; i < buttons.length; i++) {
          buttons[i].onclick = function() {
            if (this.dataset['action'] === 'toggle_grid') {
              Gantt.config.show_grid = !Gantt.config.show_grid;
            }
            else {
              Gantt.config.show_chart = !Gantt.config.show_chart;
            }
            Gantt.render()
          }
        }
      }
    })
  }

  exportTo($this = this, Gantt = this.Gantt, settingsGantt = this.settingsGantt) {
    const addContentCss = async (config) => {
      const { content_css, link_css } = Gantt.config;
      const addStyleToHeader = (style) => {
        config.header = (config.header || '') + '<style>' + style + '</style>';
      };

      if (content_css && content_css.length) {
        addStyleToHeader(content_css);
      } else {
        const response = await jQuery.get(decodeURIComponent(link_css)).catch(() => '');
        if (response.length) {
          Gantt.config.content_css = response;
          addStyleToHeader(response);
        }
      }
    };

    Gantt.attachEvent("onGanttReady", function() {
      let elGantt = Gantt.$root.closest('.gantt-wrapper');
      if(elGantt.querySelector('[data-action="export"]')) {
        elGantt.querySelector('[data-action="export"]').addEventListener('click', function () {
          let content = `
            <div class="d-inline-block">
              <button class="gantt_btn_set gantt_left_btn_set text-nowrap" data-action="excel" type="button"><i class="bi bi-filetype-xlsx"></i> ${Drupal.t('Export excel')}</button>
              <button class="gantt_btn_set gantt_left_btn_set text-nowrap" data-action="msproject" type="button"><i class="bi bi-bar-chart-steps"></i> ${Drupal.t('Export MSProject')}</button>
              <button class="gantt_btn_set gantt_left_btn_set text-nowrap" data-action="ical" type="button"><i class="bi bi-calendar-week"></i> ${Drupal.t('Export calendar')}</button>
              <button class="gantt_btn_set gantt_left_btn_set text-nowrap" data-action="pdf" type="button"><i class="bi bi-filetype-pdf"></i> ${Drupal.t('Export pdf')}</button>
              <button class="gantt_btn_set gantt_left_btn_set text-nowrap" data-action="png" type="button"><i class="bi bi-filetype-png"></i> ${Drupal.t('Export png')}</button>
            </div>
            <hr>
            <div>
              ${settingsGantt.calendar_ical}
            </div>
          `;

          const endPopup = () => {
            modal = null;
          }

          const convertDuration = (item_task, settingsGantt) => {
            if (!settingsGantt.use_cdn) {
              item_task.freeSlack = Gantt.config.duration_unit === 'minute' ? $this.formatMinuteTime(Gantt.getFreeSlack(item_task.id)) : Gantt.getFreeSlack(item_task.id)
              item_task.totalSlack = Gantt.config.duration_unit === 'minute' ? $this.formatMinuteTime(Gantt.getTotalSlack(item_task.id)) : Gantt.getTotalSlack(item_task.id)
            }

            item_task.duration = Gantt.config.duration_unit === 'minute' ? $this.formatMinuteTime(item_task.duration) : item_task.duration;
          }

          const convertProgress = (item_task) => {
            item_task.progress = Math.floor(item_task.progress * 100).toString() + '%';
          }

          const convertDate = (item_task, dateToStr) => {
            item_task.start_date = item_task.start_date ? dateToStr(item_task.start_date) : null
            item_task.end_date = item_task.end_date ? dateToStr(item_task.end_date) : null
            item_task.planned_start = item_task.planned_start ? dateToStr(item_task.planned_start) : null
            item_task.planned_end = item_task.planned_end ? dateToStr(item_task.planned_end) : null
          }

          const convertResource = (item_task, settingsGantt) => {
            item_task.wbs = Gantt.getWBSCode(item_task)

            let serverListPriority = Gantt.serverList('priority')
            if(item_task.hasOwnProperty('priority') && item_task.priority.length && serverListPriority) {
              let resource = serverListPriority.find(resource => resource.key == item_task.priority);
              if (resource) {
                item_task.priority = resource.label;
              }
            }

            for (const key in settingsGantt.server_list_resource) {
              let serverList = Gantt.serverList(key)
              let text = [];
              if(item_task.hasOwnProperty(key) && item_task[key].length && serverList) {
                item_task[key].forEach(item => {
                  let resource = serverList.find(resource => resource.key == item);
                  if (resource) {
                    text.push(resource.label)
                  }
                })
              }
              if (text.length) {
                item_task[key] = text.join(', ');
              }
            }
          }

          const convertDataExport = (Gantt, settingsGantt, action) => {
            let data = [];
            let links = [];
            switch (action) {
              case 'excel':
                Gantt.eachTask(function (task) {
                  let item_task = {...task};
                  if (typeof Gantt.config.gantt_data_filter !== 'undefined') {
                    if (Gantt.config.gantt_data_filter.includes(task.id)) {
                      convertResource(item_task, settingsGantt)
                      convertDate(item_task, Gantt.date.date_to_str("%d/%m/%Y %H:%i"))
                      convertDuration(item_task, settingsGantt)
                      convertProgress(item_task)
                      data.push(item_task);
                    }
                  }
                  else {
                    convertResource(item_task, settingsGantt)
                    convertDate(item_task, Gantt.date.date_to_str("%d/%m/%Y %H:%i"))
                    convertDuration(item_task, settingsGantt)
                    convertProgress(item_task)
                    data.push(item_task);
                  }
                })
                break;
              case 'msproject':
                Gantt.eachTask(function (task) {
                  let item_task = {...task};

                  let link_source = item_task.hasOwnProperty('$source') ? item_task.$source : [];
                  if (typeof Gantt.config.gantt_data_filter !== 'undefined') {
                    link_source.forEach(function (id_link) {
                      if (Gantt.isLinkExists(id_link)) {
                        let link = Gantt.getLink(id_link)
                        let task_target = Gantt.getTask(link.target)
                        if (Gantt.config.gantt_data_filter.includes(task_target.id)) {
                          links.push(link)
                        }
                      }
                    })
                  }
                  else {
                    link_source.forEach(function (id_link) {
                      if (Gantt.isLinkExists(id_link)) {
                        let link = Gantt.getLink(id_link)
                        links.push(link)
                      }
                    })
                  }

                  if (typeof Gantt.config.gantt_data_filter !== 'undefined') {
                    if (Gantt.config.gantt_data_filter.includes(task.id)) {
                      convertResource(item_task, settingsGantt)
                      convertDate(item_task, Gantt.date.date_to_str("%d-%m-%Y %H:%i"))
                      data.push(item_task);
                    }
                  }
                  else {
                    convertResource(item_task, settingsGantt)
                    convertDate(item_task, Gantt.date.date_to_str("%d-%m-%Y %H:%i"))
                    data.push(item_task);
                  }
                })
                break;
              default:
                Gantt.eachTask(function (task) {
                  let item_task = {...task};
                  if (typeof Gantt.config.gantt_data_filter !== 'undefined') {
                    if (Gantt.config.gantt_data_filter.includes(task.id)) {
                      data.push(item_task);
                    }
                  }
                  else {
                    data.push(item_task);
                  }
                })
            }
            return {
              data: data,
              links: links,
            };
          }

          let modal = Gantt.modalbox({
            title: Drupal.t('Export'),
            text: content,
            buttons: [
              {label: Drupal.t('Close'), css: "link-cancel-btn", value: "cancel"}
            ],
            width: "760px",
            type: "popup-css-class-here",
            callback: function (result) {
              switch (result) {
                case "cancel":
                  endPopup();
                  break;
              }
            }
          });

          let buttons = document.querySelectorAll('[data-action="excel"],' +
            '[data-action="msproject"],' +
            '[data-action="ical"],' +
            '[data-action="pdf"],' +
            '[data-action="png"]');
          if (buttons.length) {
            for (let i = 0; i < buttons.length; i++) {
              buttons[i].onclick = async function() {
                let config = {
                  name: document.title,
                  locale: navigator.language
                }
                switch (this.dataset['action']) {
                  case 'excel':
                    const data_export = convertDataExport(Gantt, settingsGantt, 'excel')
                    config.name += '.xlsx'
                    config.data = data_export.data
                    let columns = [];
                    Gantt.config.columns.forEach(function (col) {
                      if (!['add', 'buttons', 'control_columns'].includes(col.name)) {
                        let obj = {
                          id: col.name,
                          header: col.label,
                        }
                        if (['duration', 'custom_field', 'freeSlack', 'totalSlack'].includes(col.name)) {
                          obj.type = Gantt.config.duration_unit === 'minute' ? 'string' : 'number'
                        }
                        if (['start_date', 'end_date', 'planned_start', 'planned_end'].includes(col.name)) {
                          obj.type = 'date'
                        }
                        columns.push(obj)
                      }
                    })
                    columns.push({
                      id: 'progress',
                      header: 'Progress',
                    })
                    config.columns = columns;
                    Gantt.exportToExcel(config);
                    break;

                  case 'msproject':
                    config.name += '.xml'
                    config.data = convertDataExport(Gantt, settingsGantt, 'msproject')
                    Gantt.exportToMSProject(config);
                    break;

                  case 'ical':
                    config.name += '.ics'
                    Gantt.exportToICal(config);
                    break;

                  case 'pdf':
                    config.raw = true
                    config.name += '.pdf'
                    await addContentCss(config);
                    Gantt.exportToPDF(config);
                    break;

                  case 'png':
                    config.raw = true
                    config.name += '.png'
                    await addContentCss(config);
                    Gantt.exportToPNG(config);
                    break;

                  default:
                    Gantt.exportToExcel();
                }
              }
            }
          }
        })
      }
    });
  }

  copyTask(Gantt = this.Gantt, settingsGantt = this.settingsGantt) {
    let tasksToCopy = [];

    Gantt.ext.keyboardNavigation.addShortcut("ctrl+c", function (e) {
      tasksToCopy = [];
      Gantt.eachSelectedTask(function (task_id) {
        tasksToCopy.push(task_id);
      });
    }, "taskRow");

    Gantt.ext.keyboardNavigation.addShortcut("ctrl+v", function (e) {
      const newParent = Gantt.getSelectedId();

      Gantt.batchUpdate(function () {
        for (let i = 0; i < tasksToCopy.length; i++) {
          const task = Gantt.copy(Gantt.getTask(tasksToCopy[i]));
          task.id = +new Date() + '+' + Math.floor(Math.random() * 10);
          Gantt.addTask(task, newParent)
        }
        Gantt.getTask(newParent).$open = true;
      })
    }, "taskRow");
  }

  onInDentOutDent(Gantt = this.Gantt) {
    let settingsGantt = this.settingsGantt;
    Gantt.attachEvent("onGanttReady", function() {
      let elGantt = Gantt.$root.closest('.gantt-wrapper');
      let buttons = elGantt.querySelectorAll('[data-action="indent"], [data-action="outdent"], [data-action="del"]');
      if (buttons.length) {
        for (let i = 0; i < buttons.length; i++) {
          if (!settingsGantt.edit_task) {
            buttons[i].setAttribute('disable', '')
            continue;
          }
          buttons[i].onclick = function() {
            const action = this.dataset['action'];
            if (action === 'del') {
              Gantt.confirm(Drupal.t("This will delete all selected tasks and their subtasks. Are you sure you want to delete?"), function (result) {
                if (result) {
                  Gantt.performAction(action)
                }
              });
              return false;
            }
            Gantt.performAction(this.dataset['action'])
          }
        }
      }
    });

    const shiftTask = (task_id, direction) => {
      let task = Gantt.getTask(task_id);
      task.start_date = Gantt.date.add(task.start_date, direction, "day");
      task.end_date = Gantt.calculateEndDate(task.start_date, task.duration);
      Gantt.updateTask(task.id);
    }

    let actions = {
      indent: function indent(task_id) {
        let prev_id = Gantt.getPrevSibling(task_id);
        while (Gantt.isSelectedTask(prev_id)) {
          let prev = Gantt.getPrevSibling(prev_id);
          if (!prev) break;
          prev_id = prev;
        }
        if (prev_id) {
          let new_parent = Gantt.getTask(prev_id);
          Gantt.moveTask(task_id, Gantt.getChildren(new_parent.id).length, new_parent.id);
          new_parent.type = Gantt.config.types.project;
          new_parent.$open = true;
          Gantt.updateTask(task_id);
          Gantt.updateTask(new_parent.id);
          return task_id;
        }
        return null;
      },
      outdent: function outdent(task_id, initialIndexes, initialSiblings) {
        let cur_task = Gantt.getTask(task_id);
        let old_parent = cur_task.parent;
        if (Gantt.isTaskExists(old_parent) && old_parent !== Gantt.config.root_id) {
          let index = Gantt.getTaskIndex(old_parent) + 1;
          let prevSibling = initialSiblings[task_id].first;

          if(Gantt.isSelectedTask(prevSibling)){
            index += (initialIndexes[task_id] - initialIndexes[prevSibling]);
          }
          Gantt.moveTask(task_id, index, Gantt.getParent(cur_task.parent));
          if (!Gantt.hasChild(old_parent))
            Gantt.getTask(old_parent).type = Gantt.config.types.task;
          Gantt.updateTask(task_id);
          Gantt.updateTask(old_parent);
          return task_id;
        }
        return null;
      },
      del: function (task_id) {
        if(Gantt.isTaskExists(task_id) && !Gantt.isReadonly(task_id)) Gantt.deleteTask(task_id);
        return task_id;
      },
      moveForward: function (task_id) {
        shiftTask(task_id, 1);
      },
      moveBackward: function (task_id) {
        shiftTask(task_id, -1);
      }
    };
    let cascadeAction = {
      indent: true,
      outdent: true,
      del: true
    };

    Gantt.performAction = function (actionName) {
      let action = actions[actionName];
      if (!action) {
        return;
      }

      Gantt.batchUpdate(function () {
        // Need to preserve order of items on indent/outdent, remember order before changing anything:
        let indexes = {};
        let siblings = {};
        Gantt.eachSelectedTask(function (task_id) {
          Gantt.ext.undo.saveState(task_id, "task");
          indexes[task_id] = Gantt.getTaskIndex(task_id);
          siblings[task_id] = {
            first: null
          };

          let currentId = task_id;
          while(Gantt.isTaskExists(Gantt.getPrevSibling(currentId)) && Gantt.isSelectedTask(Gantt.getPrevSibling(currentId))){
            currentId = Gantt.getPrevSibling(currentId);
          }
          siblings[task_id].first = currentId;
        });

        let updated = {};
        Gantt.eachSelectedTask(function (task_id) {

          if (cascadeAction[actionName]) {
            if (!updated[Gantt.getParent(task_id)]) {
              let updated_id = action(task_id, indexes, siblings);

              updated[updated_id] = true;
            } else {
              updated[task_id] = true;
            }
          } else {
            action(task_id, indexes);
          }
        });
      });
    };
  }

  searchText(Gantt = this.Gantt) {
    let filterValue = "";

    Gantt.attachEvent("onGanttReady", function () {
      let elGantt = Gantt.$root.closest('.gantt-wrapper')
      if (elGantt.querySelector('[data-action="search"]')) {
        elGantt.querySelector('[data-action="search"]').addEventListener('input', Drupal.debounce(function(e) {
          filterValue = e.target.value;
          Gantt.refreshData();
          e.target.focus()
        }, 500));
      }
    });

    const filterLogic = (task, match = false) => {
      // check children
      Gantt.eachTask(function (child) {
        if (filterLogic(child)) {
          match = true;
        }
      }, task.id);

      // check task
      if (task.text.toLowerCase().indexOf(filterValue.toLowerCase()) > -1) {
        match = true;
      }
      return match;
    }

    Gantt.attachEvent("onBeforeTaskDisplay", function (id, task) {
      if (!filterValue) {
        return true;
      }
      return filterLogic(task);
    });
  }

  onButtonDetail(Gantt = this.Gantt, value = true) {
    if (!value) {
      Gantt.config.buttons_left = Gantt.config.buttons_left.filter(function (item) {
        return item !== 'button_detail'
      })
      Gantt.detachEvent("action_button_detail");
      Gantt.detachEvent("before_lightbox_button_detail");
      Gantt.resetLightbox()
      return true;
    }

    Gantt.locale.labels.detail_button = Drupal.t("Detail");
    Gantt.attachEvent("onLightboxButton", function (button_id, node, e) {
      if (button_id === "detail_button") {
        const id = Gantt.getState().lightbox;
        const task = Gantt.getTask(id)
        if (task?.link_detail && task.link_detail.length) {
          let ajaxSettings = {
            url: task.link_detail,
            dialogType: 'modal',
            dialog: {width: '80%'},
          };
          Drupal.ajax(ajaxSettings).execute();
          Gantt.hideLightbox();
        }
      }
    }, {id: "action_button_detail"});
    Gantt.attachEvent("onBeforeLightbox", function (id) {
      let task = Gantt.getTask(id);
      if (task.$new) {
        Gantt.config.buttons_left = Gantt.config.buttons_left.filter(item => item !== 'detail_button');
      }
      else {
        if (!Gantt.config.buttons_left.includes('detail_button')) {
          Gantt.config.buttons_left.push('detail_button')
        }
      }
      Gantt.resetLightbox()
      return true;
    }, {id: "before_lightbox_button_detail"});
    Gantt.resetLightbox()
  }

  onUndoRedo(Gantt = this.Gantt) {
    let settingsGantt = this.settingsGantt;
    Gantt.attachEvent("onGanttReady", function() {
      let elGantt = Gantt.$root.closest('.gantt-wrapper');
      let buttons = elGantt.querySelectorAll('[data-action="undo"], [data-action="redo"]')
      if (buttons.length) {
        for (let i = 0; i < buttons.length; i++) {
          if (!settingsGantt.edit_task) {
            buttons[i].setAttribute('disable', 'disabled')
            continue;
          }
          buttons[i].onclick = function() {
            const action = this.dataset['action'];
            if (!settingsGantt.edit_task) return;
            if (action === 'undo') {
              Gantt.undo()
            }
            else {
              Gantt.redo()
            }
          }
        }
      }
    });

    Gantt.attachEvent("onBeforeUndo", function (action) {
      if (action && action.commands[0].type === 'move') {
        const value = action.commands[0].value
        let task = Gantt.getTask(value.id)
        task.pre_order = Gantt.getTaskIndex(value.id)
        task.pre_parent = Gantt.getParent(value.id)
      }
      return true;
    });

    Gantt.attachEvent("onBeforeRedo", function (action) {
      if (action && action.commands[0].type === 'move') {
        const value = action.commands[0].value
        let task = Gantt.getTask(value.id)
        task.pre_order = Gantt.getTaskIndex(value.id)
        task.pre_parent = Gantt.getParent(value.id)
      }
      return true;
    });
  }

  onCollapse(Gantt = this.Gantt) {
    Gantt.attachEvent('onGanttReady', function () {
      let elGantt = Gantt.$root.closest('.gantt-wrapper');
      let buttons = elGantt.querySelectorAll('[data-action="open"], [data-action="close"]')
      if (buttons.length) {
        for (let i = 0; i < buttons.length; i++) {
          buttons[i].onclick = function () {
            let buttonsCollapse = document.querySelectorAll('[data-action="open"], [data-action="close"]')
            const action = this.dataset['action'];
            buttonsCollapse.forEach(button => {
              button.style.display = (button.dataset['action'] === action) ? 'none' : null
            })
            Gantt.batchUpdate(function () {
              Gantt.eachTask(function (task) {
                Gantt[action](task.id)
              });
            });
          }
        }
      }
    })
  }

  onFullScreen(Gantt = this.Gantt) {
    Gantt.attachEvent('onGanttReady', function () {
      let elGantt = Gantt.$root.closest('.gantt-wrapper')
      if (elGantt.querySelector('[data-action="fullscreen"]')) {
        elGantt.querySelector('[data-action="fullscreen"]').addEventListener('click', function () {
          Gantt.expand()
        })
      }
    })
  }

  onFullScreenInGantt(Gantt = this.Gantt) {
    Gantt.attachEvent("onTemplatesReady", function () {
      let toggle = document.createElement("i");
      toggle.className = "fa fa-expand gantt-fullscreen";
      Gantt.toggleIcon = toggle;
      Gantt.$container.appendChild(toggle);
      toggle.onclick = function() {
        Gantt.ext.fullscreen.toggle();
      };
    });
    Gantt.attachEvent("onExpand", function () {
      let icon = Gantt.toggleIcon;
      if (icon) {
        icon.className = icon.className.replace("fa-expand", "fa-compress");
      }

    });
    Gantt.attachEvent("onCollapse", function () {
      let icon = Gantt.toggleIcon;
      if (icon) {
        icon.className = icon.className.replace("fa-compress", "fa-expand");
      }
    });
  }

  onHandlerRequest(Gantt = this.Gantt) {
    // Attach event to handle updates before they are applied
    this.dp.attachEvent("onAfterUpdate", function (id, state, tid, $response) {
      if (Gantt.isTaskExists(tid) && state === 'inserted') {
        let task = Gantt.getTask(tid);
        if ($response.hasOwnProperty('link_detail')) {
          task.creator = [drupalSettings.user.uid]
          task.link_detail = $response.link_detail
          task.entity_type = $response.entity_type || null
          task.entity_bundle = $response.entity_bundle || null
          if ($response.hasOwnProperty('parent_id_entity')) {
            task.parent_id_entity = $response.parent_id_entity
          }
          if ($response.hasOwnProperty('parent_field_name_entity')) {
            task.parent_field_name_entity = $response.parent_field_name_entity
          }
          if ($response.hasOwnProperty('parent_type_entity')) {
            task.parent_type_entity = $response.parent_type_entity
          }
        }
      }
      return true;
    })
    this.dp.attachEvent("onBeforeUpdate", function (id, state, data) {
      if (Gantt.isTaskExists(id)) {
        let task = Gantt.getTask(id);
        if (task?.$virtual) {
          return false;
        }
      }
      if (data.parent && data.parent !== '0' && state === 'inserted') {
        let parentTask = Gantt.getTask(data.parent);
        if (parentTask && parentTask.hasOwnProperty("group_field") && (parentTask.group_field === 'parent_field_name' || parentTask.group_field === 'parent_id')) {
          data.group_field = parentTask.group_field;
        }
        if (parentTask.hasOwnProperty('entity_type')) {
          data.entity_type = parentTask.entity_type;
        }
        if (parentTask.hasOwnProperty('entity_bundle')) {
          data.entity_bundle = parentTask.entity_bundle;
        }
        if (parentTask.hasOwnProperty('parent_id_entity')) {
          data.parent_id_entity = parentTask.parent_id_entity;
        }
        if (parentTask.hasOwnProperty('parent_type_entity')) {
          data.parent_type_entity = parentTask.parent_type_entity;
        }
        if (parentTask.hasOwnProperty('parent_field_name_entity')) {
          data.parent_field_name_entity = parentTask.parent_field_name_entity;
        }
      }
      return true;
    })

    Gantt.attachEvent("onAjaxError", function (request) {
      Gantt.message({
        type: "error",
        text: Drupal.t('Error @status!', { '@status': request.status }),
        expire: 4000
      })
      return true;
    });
  }

  onPostOrder(Gantt = this.Gantt, settingsGantt = this.settingsGantt, value = true) {
    if (!value) {
      Gantt.detachEvent('pre_order');
      Gantt.detachEvent('post_order');
      return true;
    }

    // Attach event to store pre-order information before task move
    Gantt.attachEvent("onBeforeTaskMove", function(id, parent, tindex) {
      if (Gantt._sort || Gantt.getState()['group_mode']) {
        Gantt.message({
          type: "error",
          text: Drupal.t('Can not sort in this view'),
          expire: 4000
        })
        return false
      }

      let task = Gantt.getTask(id);
      task.pre_order = Gantt.getTaskIndex(id);
      task.pre_parent = Gantt.getParent(id);
      return true;
    }, {id: 'pre_order'});

    // Attach event to handle updates before they are applied
    this.dp.attachEvent("onBeforeUpdate", function (id, state, data) {
      if ((state === 'inserted' || state === 'order') && data.hasOwnProperty('start_date')) {
        let task = Gantt.getTask(id)
        data.order = Gantt.getTaskIndex(id)
        if (state === "inserted") {
          data.destination = settingsGantt.current_path
          return true;
        }
        if (state === "order") {
          let listOrder = [];
          if (data.parent === task.pre_parent) {
            let index_start = Math.min(data.order, task.pre_order);
            let index_end = Math.max(data.order, task.pre_order);
            for (let i = index_start; i <= index_end; i++) {
              let taskBeOrder = Gantt.getTaskBy(task => task.$local_index === i && task.parent === data.parent);
              if (taskBeOrder.length > 0) {
                listOrder.push({
                  id: taskBeOrder[0].id,
                  order: Gantt.getTaskIndex(taskBeOrder[0].id)
                });
              }
            }
          }
          else {
            const child = Gantt.getChildren(data.parent)
            child.forEach(item => {
              let taskBeOrder = Gantt.getTask(item);
              let index = Gantt.getTaskIndex(taskBeOrder.id)
              if (index >= data.order) {
                listOrder.push({
                  id: taskBeOrder.id,
                  order: index
                })
              }
            })
          }
          if (listOrder.length) {
            data.listOrder = listOrder
            for (let i = 0; i < listOrder.length; i++) {
              let task = Gantt.isTaskExists(listOrder[i].id) ? Gantt.getTask(listOrder[i].id) : null
              if (Gantt.isTaskExists(listOrder[i].id)) {
                let task = Gantt.getTask(listOrder[i].id)
                task.order = listOrder[i].order
                Gantt.refreshTask(listOrder[i].id)
              }
            }
          }
        }
      }
      return true;
    }, {id: 'post_order'})
  }

  onAutoSchedule(value = true, Gantt = this.Gantt) {
    this.options.auto_schedule = value;
    if (!value) {
      Gantt.config.auto_scheduling = false;
      Gantt.detachEvent('on_after_task_auto_schedule');
      Gantt.detachEvent('on_link_dbl_click');
      return true;
    }
    Gantt.config.auto_scheduling = true;
    Gantt.attachEvent("onAfterTaskAutoSchedule", function (task, new_date, constraint, predecessor) {
      if (task && predecessor) {
        Gantt.message({
          text: Drupal.t("<b>@text</b> has been rescheduled to @date due to @predecessor constraint", {'@text': task.text, '@date': Gantt.templates.task_date(new_date) ,'@predecessor': predecessor.text}),
          expire: 4000
        });
      }
    }, {id: 'on_after_task_auto_schedule'});

    Gantt.attachEvent("onLinkDblClick", function (id, e) {
      let link = Gantt.getLink(id);
      if (link.readonly) {
        return false;
      }

      let linkTitle;
      switch (link.type) {
        case Gantt.config.links.finish_to_start:
          linkTitle = Drupal.t("Finish to start");
          break;

        case Gantt.config.links.finish_to_finish:
          linkTitle = Drupal.t("Finish to finish");
          break;

        case Gantt.config.links.start_to_start:
          linkTitle = Drupal.t("Start to start");
          break;

        case Gantt.config.links.start_to_finish:
          linkTitle = Drupal.t("Start to finish");
          break;
      }

      const endPopup = () => {
        modal = null;
        link = null;
      }

      const saveLink = () => {
        let lagValue = modal.querySelector(".lag-input").value;
        if (Gantt.config.duration_unit === 'minute') lagValue = lagValue * 1440
        if (!isNaN(parseInt(lagValue, 10))) {
          link.lag = parseInt(lagValue, 10);
        }

        Gantt.updateLink(link.id);
        if (Gantt.autoSchedule) {
          Gantt.autoSchedule(link.source);
        }
        endPopup();
      }

      const deleteLink = () => {
        Gantt.deleteLink(link.id);
        endPopup()
      }

      const cancelEditLink = () => {
        endPopup()
      }

      let modal = Gantt.modalbox({
        title: "<div class='fs-5'>" + linkTitle + "</div>",
        text: "<div>" + Drupal.t("<b>@source</b> link to <b>@target</b>", {'@source': Gantt.getTask(link.source).text, '@target': Gantt.getTask(link.target).text}) + "</div>" +
          "<div><label>Lag <input type='number' class='lag-input form-control'/></label></div>",
        buttons: [
          {label: Drupal.t('Save'), css: "link-save-btn", value: "save"},
          {label: Drupal.t('Cancel'), css: "link-cancel-btn", value: "cancel"},
          {label: Drupal.t('Delete'), css: "link-delete-btn", value: "delete"}
        ],
        width: "500px",
        type: "popup-css-class-here",
        callback: function (result) {
          switch (result) {
            case "save":
              saveLink();
              break;

            case "cancel":
              cancelEditLink();
              break;

            case "delete":
              deleteLink();
              break;

          }
        }
      });
      let lagValue = link.lag
      if (Gantt.config.duration_unit === 'minute') lagValue = lagValue / 1440
      modal.querySelector(".lag-input").value = lagValue || 0;
    }, {id: 'on_link_dbl_click'});
  }

  onAutoType(value = true, Gantt = this.Gantt) {
    this.options.auto_type = value;
    if (!value) {
      Gantt.config.auto_types = false;
      return true;
    }
    Gantt.config.auto_types = true;
  }

  onSplitTask(value = true, Gantt = this.Gantt) {
    if (!value) {
      Gantt.config.open_split_tasks = false;
      return true;
    }
    Gantt.config.open_split_tasks = true;
  }

  onClickDrag(value = true, Gantt = this.Gantt) {
    if (!value) {
      this.options.click_drag = false;
      delete Gantt.config.click_drag;
      return true;
    }
    this.options.click_drag = true;
    Gantt.config.click_drag = {
      callback: (startPoint, endPoint, startDate, endDate, tasksBetweenDates, tasksInRow) => {
        if (tasksInRow.length === 1) {
          let parent = tasksInRow[0];
          Gantt.createTask({
            text: Drupal.t("Subtask of @parent", {'@parent': parent.text}),
            start_date: Gantt.roundDate(startDate),
            end_date: Gantt.roundDate(endDate)
          }, parent.id);
        }
        else if (tasksInRow.length === 0) {
          Gantt.createTask({
            text: this.i18n.labels.new_task,
            start_date: Gantt.roundDate(startDate),
            end_date: Gantt.roundDate(endDate)
          });
        }
      },
      useKey: "altKey",
      singleRow: true
    };
  }

  onProgressText(value = true) {
    if (!value) {
      this.options.progress_text = false;
      return true;
    }
    this.options.progress_text = true;
  }

  multipleSelectForm(Gantt = this.Gantt) {
    Gantt.form_blocks["multiselect"] = {
      render: function (sns) {
        let height = (sns.height || "23") + "px";
        let html = "<div class='gantt_cal_ltext gantt_cal_chosen gantt_cal_multiselect' style='height:" + height + ";'><select data-placeholder='...' class='chosen-select' multiple>";
        if (sns.options) {
          html += "<option value=''></option>";
          for (let i = 0; i < sns.options.length; i++) {
            if(sns.unassigned_value !== undefined && sns.options[i].key === sns.unassigned_value){
              continue;
            }
            let disabled = '';
            if (sns.options[i]?.disabled) {
              disabled = 'disabled';
            }
            html += "<option " + disabled + " value='" + sns.options[i].key + "'>" + sns.options[i].label + "</option>";
          }
        }
        html += "</select></div>";
        return html;
      },

      set_value: function (node, value, ev, sns) {
        node.style.overflow = "visible";
        node.parentNode.style.overflow = "visible";
        node.style.display = "inline-block";
        let select = jQuery(node.firstChild);

        if (value) {
          value = (value + "").split(",");
          select.val(value);
        }
        else {
          select.val([]);
        }

        let options = {width: "100%", no_results_text: Drupal.t("Oops, nothing found!")}
        if (!sns.required) {
          options.allow_single_deselect = true
          options.search_contains = true
        }
        select.chosen(options);

        if(sns.onchange){
          select.change(function(){
            sns.onchange.call(this);
          })
        }
        select.trigger('chosen:updated');
        select.trigger("change");
      },

      get_value: function (node, ev) {
        return jQuery(node.firstChild).val() || [];
      },

      focus: function (node) {
        jQuery(node.firstChild).focus();
      }
    };

    Gantt.form_blocks["onceselect"] = {
      render: function (sns) {
        let height = (sns.height || "23") + "px";
        let html = "<div class='gantt_cal_ltext gantt_cal_chosen gantt_cal_onceselect' style='height:" + height + ";'><select data-placeholder='...' class='chosen-select'>";
        if (sns.options) {
          html += "<option value=''></option>";
          for (let i = 0; i < sns.options.length; i++) {
            if(sns.unassigned_value !== undefined && sns.options[i].key === sns.unassigned_value){
              continue;
            }
            let disabled = '';
            if (sns.options[i]?.disabled) {
              disabled = 'disabled';
            }
            html += "<option " + disabled + " value='" + sns.options[i].key + "'>" + sns.options[i].label + "</option>";
          }
        }
        html += "</select></div>";
        return html;
      },

      set_value: function (node, value, ev, sns) {
        node.style.overflow = "visible";
        node.parentNode.style.overflow = "visible";
        node.style.display = "inline-block";
        let select = jQuery(node.firstChild);

        if (value) {
          value = (value + "").split(",");
          select.val(value);
        }
        else {
          select.val([]);
        }

        let options = {width: "100%", no_results_text: Drupal.t("Oops, nothing found!")}
        if (!sns.required) {
          options.allow_single_deselect = true
          options.search_contains = true
        }
        select.chosen(options);

        if(sns.onchange){
          select.change(function(){
            sns.onchange.call(this);
          })
        }
        select.trigger('chosen:updated').trigger("change");
      },

      get_value: function (node, ev) {
        let value = jQuery(node.firstChild).val() || []
        if (!Array.isArray(value)) {
          value = [value]
        }
        return value;
      },

      focus: function (node) {
        jQuery(node.firstChild).focus();
      }
    };
  }

  dateForm($this = this, Gantt = this.Gantt, settingsGantt = this.settingsGantt) {
    Gantt.form_blocks["datetime"] = {
      roundNumber: Gantt.config.duration_unit === 'minute' ? 15 : 1,
      formatFunc: Gantt.date.date_to_str(Gantt.config.duration_unit === 'minute' ? '%Y-%m-%d %H:%i' : '%Y-%m-%d'),
      dateFunc: Gantt.date.str_to_date(Gantt.config.duration_unit === 'minute' ? '%Y-%m-%d %H:%i' : '%Y-%m-%d'),
      roundTime: function (date) {
        let date_round = Gantt.roundDate({
          date: date,
          unit: Gantt.config.duration_unit,
          step: Gantt.form_blocks["datetime"].roundNumber
        });
        if (date_round.valueOf() < date.valueOf()) {
          return Gantt.date.add(date_round, Gantt.form_blocks["datetime"].roundNumber, Gantt.config.duration_unit)
        }
        return date_round;
      },
      setDuration: function(new_value, duration) {
        let val_day = new_value;
        let val_hour = null;
        let val_minute = null;
        if (Gantt.config.duration_unit === 'minute') {
          val_day = Math.floor(new_value / (60 * 24));
          val_hour = Math.floor((new_value % (60 * 24)) / 60);
          val_minute = new_value % 60;
        }

        duration.find('#duration-day').val(val_day)
        duration.find('#duration-hour').val(val_hour)
        duration.find('#duration-minute').val(val_minute)
      },
      getDuration: function (duration) {
        const val_day = Number(duration.find('#duration-day').val()) || 0
        const val_hour = Number(duration.find('#duration-hour').val()) || 0
        const val_minute = Number(duration.find('#duration-minute').val()) || 0
        let total_minute = null;
        switch (Gantt.config.duration_unit) {
          case 'day':
            total_minute = val_day * 60 * 24 + val_hour * 60 + val_minute
            break;

          case 'minute':
            total_minute = val_day * 60 * 24 + val_hour * 60 + val_minute
            break;

          default:
            total_minute = val_day * 60 * 24 + val_hour * 60 + val_minute
        }
        return total_minute;
      },
      render: function (sns) {
        let date_type = 'date'
        let step = Gantt.form_blocks["datetime"].roundNumber
        if (Gantt.config.duration_unit === 'minute') {
          date_type = 'datetime-local'
          step = Gantt.form_blocks["datetime"].roundNumber * 60
        }
        const formatFunc = Gantt.form_blocks["datetime"].formatFunc
        const roundTime = Gantt.form_blocks["datetime"].roundTime

        const default_start = new Date()
        const default_end = roundTime(default_start)

        let height = (sns.height || "34") + "px";
        let html = "<div class='gantt_cal_ltext gantt_cal_datetime' style='height:" + height + ";'>";
        html += "<span class='wrapper-start' style='display: inline-block'><input id='date_start' type='" + date_type + "' value='" + formatFunc(default_start) + "' step='" + step + "' /></span>"
        if (!sns.single_date || sns.single_date === false) {
          html += "<span class='wrapper-end' style='display: inline-block'><input id='date_end' type='" + date_type + "' value='" + formatFunc(default_end) + "' step='" + step + "' /></span>"
          html += "<span class='wrapper-duration' style='display: inline-block'>"
          html += "<button id='duration-button-diminish'>-</button>"
          html += "<span id='main-duration-day' style='display: inline-block'><input id='duration-day' min='0' type='number'/><label>" + Drupal.t('day') + "</label></span>"
          if (date_type === 'datetime-local') {
            html += "<span id='main-duration-hour' style='display: inline-block'><input id='duration-hour' min='0' max='23' type='number'/><label>" + Drupal.t('hour') + "</label></span>"
            html += "<span id='main-duration-minute' style='display: inline-block'><input id='duration-minute' min='0' max='59' type='number'/><label>" + Drupal.t('minute') + "</label></span>"
          }
          html += "<button id='duration-button-increase'>+</button>"
        }
        html += "</span>";
        html += "</div>";
        return html;
      },

      set_value: function (node, value, ev, sns) {
        node.style.overflow = "visible";
        node.parentNode.style.overflow = "visible";
        node.style.display = "inline-block";

        const date_start = jQuery(node).find('#date_start');
        const date_end = jQuery(node).find('#date_end');
        const duration = jQuery(node).find('[class^="wrapper-duration"]');
        let duration_focus = null;

        const formatFunc = Gantt.form_blocks["datetime"].formatFunc;
        const dateFunc = Gantt.form_blocks["datetime"].dateFunc;
        const roundTime = Gantt.form_blocks["datetime"].roundTime
        const getDuration = Gantt.form_blocks["datetime"].getDuration
        const setDuration = Gantt.form_blocks["datetime"].setDuration

        // set value.
        if (date_start.length && ev.start_date && !ev.$new) date_start.val(formatFunc(ev.start_date))
        if (date_end.length && ev.end_date && !ev.$new) {
          let end = ev.end_date
          if (settingsGantt.last_of_the_day && Gantt.config.duration_unit === 'day') end = Gantt.date.add(end, -1, 'day');
          date_end.val(formatFunc(end))
        }
        const start = dateFunc(date_start.val().replace('T', ' '))
        let end = date_end.length ? dateFunc(date_end.val().replace('T', ' ')) : null
        if (end) {
          if (settingsGantt.last_of_the_day && Gantt.config.duration_unit === 'day') end = Gantt.date.add(end, 1, 'day');
          const cal_duration = Gantt.calculateDuration({start_date: start, end_date: end})
          setDuration(cal_duration, duration)
        }

        const setEndDate = () => {
          const val_duration = getDuration(duration)
          const start = dateFunc(date_start.val().replace('T', ' '))
          let new_value = Gantt.calculateEndDate({start_date: start, unit: 'minute', duration: val_duration});
          if (settingsGantt.last_of_the_day && Gantt.config.duration_unit === 'day') new_value = Gantt.date.add(new_value, -1, 'day');
          date_end.val(formatFunc(new_value))
        }

        const validateDate = () => {
          if (date_start.length && !date_start.val()) date_start.val(formatFunc(new Date()))
          if (date_end.length && !date_end.val()) setEndDate()
          if (date_start.length && date_end.length) {
            const start = dateFunc(date_start.val().replace('T', ' '))
            let end = dateFunc(date_end.val().replace('T', ' '))
            if (settingsGantt.last_of_the_day && Gantt.config.duration_unit === 'day') end = Gantt.date.add(end, 1, 'day');
            if (start.valueOf() >= end.valueOf()) {
              setEndDate()
              date_end.attr('min', formatFunc(roundTime(start)))
            }
          }
        }

        const validateDuration = (new_value) => {
          new_value = Number(new_value)
          const min_focus = Number(duration_focus.attr('min')) || 0
          const max_focus = Number(duration_focus.attr('max')) || 999999
          if (min_focus <= new_value && new_value <= max_focus) {
            duration_focus.val(new_value)
          }
          else if (new_value < min_focus) {
            duration_focus.val(min_focus)
          }
          else if (new_value > max_focus) {
            duration_focus.val(max_focus)
          }
        }

        duration.find('label').on('click', function (e) {
          jQuery(this).prev().focus()
        })

        duration.find('input[id^="duration"]').on('focus', function (e) {
          duration_focus = jQuery(this)
        })

        duration.on('click', '#duration-button-diminish',function () {
          let val_duration = getDuration(duration)
          if (duration_focus) {
            duration_focus.focus()
            switch (duration_focus.attr('id')) {
              case 'duration-day':
                val_duration -= 1440
                break;

              case 'duration-hour':
                val_duration -= 60
                break;

              default:
                val_duration -= Gantt.form_blocks["datetime"].roundNumber
            }
          }
          else {
            val_duration -= Gantt.config.duration_unit === 'minute' ? Gantt.form_blocks["datetime"].roundNumber : 1440
          }
          if (val_duration > 0) {
            const start = dateFunc(date_start.val().replace('T', ' '))
            const cal_end_date = roundTime(Gantt.calculateEndDate({start_date: start, unit: 'minute', duration: val_duration}))
            const cal_duration = Gantt.calculateDuration({start_date: start, end_date: cal_end_date})
            setDuration(cal_duration, duration)
            setEndDate()
          }
        })
        duration.on('click', '#duration-button-increase',function () {
          let val_duration = getDuration(duration)
          if (duration_focus) {
            duration_focus.focus()
            switch (duration_focus.attr('id')) {
              case 'duration-day':
                val_duration += 1440
                break;

              case 'duration-hour':
                val_duration += 60
                break;

              default:
                val_duration += Gantt.form_blocks["datetime"].roundNumber
            }
          }
          else {
            val_duration += Gantt.config.duration_unit === 'minute' ? Gantt.form_blocks["datetime"].roundNumber : 1440
          }
          if (val_duration > 0) {
            const start = dateFunc(date_start.val().replace('T', ' '))
            const cal_end_date = roundTime(Gantt.calculateEndDate({start_date: start, unit: 'minute', duration: val_duration}))
            const cal_duration = Gantt.calculateDuration({start_date: start, end_date: cal_end_date})
            setDuration(cal_duration, duration)
            setEndDate()
          }
        })

        if (date_start.length && date_end.length) {
          date_start.on('change', function (e) {
            validateDate()
            if (date_start.val() && date_end.val()) {
              const start = dateFunc(date_start.val().replace('T', ' '))
              const val_duration = getDuration(duration)
              let cal_end_date = Gantt.calculateEndDate({start_date: start, unit: 'minute', duration: val_duration});
              if (settingsGantt.last_of_the_day && Gantt.config.duration_unit === 'day') cal_end_date = Gantt.date.add(cal_end_date, -1, 'day');
              date_end.val(formatFunc(cal_end_date))
            }
          })
        }
        if (date_start.length && date_end.length) {
          date_end.on('change', function (e) {
            const start = dateFunc(date_start.val().replace('T', ' '))
            let end = dateFunc(date_end.val().replace('T', ' '))
            if (start > end) {
              jQuery(this).css('color', 'red');
            }
          });
          date_end.on('change', Drupal.debounce(function (e) {
            validateDate()
            if (date_start.val() && date_end.val()) {
              const start = dateFunc(date_start.val().replace('T', ' '))
              let end = dateFunc(date_end.val().replace('T', ' '))
              if (settingsGantt.last_of_the_day && Gantt.config.duration_unit === 'day') end = Gantt.date.add(end, 1, 'day');
              const cal_duration = Gantt.calculateDuration({start_date: start, end_date: end});
              setDuration(cal_duration, duration)
              jQuery(this).css('color', 'black');
            }
          }, 500))
        }
        if (duration.length) {
          duration.on('change', 'input[id^="duration"]', function (e) {
            validateDuration(jQuery(this).val())
            setEndDate()
          })
        }
      },

      get_value: function (node, ev, sns) {
        const dateFunc = Gantt.form_blocks["datetime"].dateFunc;
        let date_start = jQuery(node).find('#date_start');
        let date_end = jQuery(node).find('#date_end');
        if (date_start.length) ev.start_date = dateFunc(date_start.val().replace('T', ' '))
        if (date_end.length) {
          ev.end_date = dateFunc(date_end.val().replace('T', ' '))
          if (settingsGantt.last_of_the_day && Gantt.config.duration_unit === 'day') ev.end_date = Gantt.date.add(ev.end_date, 1, 'day');
        }
        return true;
      }
    };

    Gantt.form_blocks["datetime_optional"] = {
      button_click: function(index, button, shead, sbody) {
        if (jQuery(sbody).is(":visible")) {
          jQuery(button).text($this.i18n.labels.button_responsive_toggle)
          jQuery(sbody).css('display', 'none')
        }
        else {
          jQuery(button).text($this.i18n.labels.button_responsive_disable_toggle)
          jQuery(sbody).css('display', 'block')
        }
      },
      roundNumber: Gantt.config.duration_unit === 'minute' ? 15 : 1,
      formatFunc: Gantt.date.date_to_str(Gantt.config.duration_unit === 'minute' ? '%Y-%m-%d %H:%i' : '%Y-%m-%d'),
      dateFunc: Gantt.date.str_to_date(Gantt.config.duration_unit === 'minute' ? '%Y-%m-%d %H:%i' : '%Y-%m-%d'),
      roundTime: function (date) {
        let date_round = Gantt.roundDate({
          date: date,
          unit: Gantt.config.duration_unit,
          step: Gantt.form_blocks["datetime_optional"].roundNumber
        });
        if (date_round.valueOf() < date.valueOf()) {
          return Gantt.date.add(date_round, Gantt.form_blocks["datetime_optional"].roundNumber, Gantt.config.duration_unit)
        }
        return date_round;
      },
      setDuration: function(new_value, duration) {
        let val_day = new_value;
        let val_hour = null;
        let val_minute = null;
        if (Gantt.config.duration_unit === 'minute') {
          val_day = Math.floor(new_value / (60 * 24));
          val_hour = Math.floor((new_value % (60 * 24)) / 60);
          val_minute = new_value % 60;
        }

        duration.find('#duration-day').val(val_day)
        duration.find('#duration-hour').val(val_hour)
        duration.find('#duration-minute').val(val_minute)
      },
      getDuration: function (duration) {
        const val_day = Number(duration.find('#duration-day').val()) || 0
        const val_hour = Number(duration.find('#duration-hour').val()) || 0
        const val_minute = Number(duration.find('#duration-minute').val()) || 0
        let total_minute = null;
        switch (Gantt.config.duration_unit) {
          case 'day':
            total_minute = val_day * 60 * 24 + val_hour * 60 + val_minute
            break;

          case 'minute':
            total_minute = val_day * 60 * 24 + val_hour * 60 + val_minute
            break;

          default:
            total_minute = val_day * 60 * 24 + val_hour * 60 + val_minute
        }
        return total_minute;
      },
      render: function (sns) {
        let date_type = 'date'
        let step = Gantt.form_blocks["datetime_optional"].roundNumber
        if (Gantt.config.duration_unit === 'minute') {
          date_type = 'datetime-local'
          step = Gantt.form_blocks["datetime_optional"].roundNumber * 60
        }
        const formatFunc = Gantt.form_blocks["datetime_optional"].formatFunc
        const roundTime = Gantt.form_blocks["datetime_optional"].roundTime

        const default_start = new Date()
        const default_end = roundTime(default_start)

        let height = (sns.height || "34") + "px";
        let html = "<div class='gantt_cal_ltext gantt_cal_datetime' style='height:" + height + ";'>";
        html += "<span class='wrapper-start' style='display: inline-block'><input id='date_start' type='" + date_type + "' value='" + formatFunc(default_start) + "' step='" + step + "' /></span>"
        if (!sns.single_date || sns.single_date === false) {
          html += "<span class='wrapper-end' style='display: inline-block'><input id='date_end' type='" + date_type + "' value='" + formatFunc(default_end) + "' step='" + step + "' /></span>"
          html += "<span class='wrapper-duration' style='display: inline-block'>"
          html += "<button id='duration-button-diminish'>-</button>"
          html += "<span id='main-duration-day' style='display: inline-block'><input id='duration-day' min='0' type='number'/><label>" + Drupal.t('day') + "</label></span>"
          if (date_type === 'datetime-local') {
            html += "<span id='main-duration-hour' style='display: inline-block'><input id='duration-hour' min='0' max='23' type='number'/><label>" + Drupal.t('hour') + "</label></span>"
            html += "<span id='main-duration-minute' style='display: inline-block'><input id='duration-minute' min='0' max='59' type='number'/><label>" + Drupal.t('minute') + "</label></span>"
          }
          html += "<button id='duration-button-increase'>+</button>"
        }
        html += "</span>";
        html += "</div>";
        return html;
      },

      set_value: function (node, value, ev, sns) {
        node.style.overflow = "visible";
        node.parentNode.style.overflow = "visible";
        node.style.display = "none";
        jQuery(node).prev().find('.gantt_custom_button_label').text($this.i18n.labels.button_responsive_toggle)
        if (!ev.$new) {
          if (ev.planned_start && ev.planned_end) {
            jQuery(node).prev().find('.gantt_custom_button_label').text($this.i18n.labels.button_responsive_disable_toggle)
            node.style.display = "inline-block";
          }
        }
        else {
          jQuery(node).prev().find('.gantt_custom_button_label').text($this.i18n.labels.button_responsive_disable_toggle)
          node.style.display = "inline-block";
        }

        const date_start = jQuery(node).find('#date_start');
        const date_end = jQuery(node).find('#date_end');
        const duration = jQuery(node).find('[class^="wrapper-duration"]');
        let duration_focus = null;

        const formatFunc = Gantt.form_blocks["datetime_optional"].formatFunc;
        const dateFunc = Gantt.form_blocks["datetime_optional"].dateFunc;
        const roundTime = Gantt.form_blocks["datetime_optional"].roundTime
        const getDuration = Gantt.form_blocks["datetime_optional"].getDuration
        const setDuration = Gantt.form_blocks["datetime_optional"].setDuration

        // set value.
        if (date_start.length && ev.planned_start && !ev.$new) date_start.val(formatFunc(ev.planned_start))
        if (date_end.length && ev.planned_end && !ev.$new) {
          let end = ev.planned_end
          if (settingsGantt.last_of_the_day && Gantt.config.duration_unit === 'day') end = Gantt.date.add(end, -1, 'day');
          date_end.val(formatFunc(end))
        }
        const start = dateFunc(date_start.val().replace('T', ' '))
        let end = date_end.length ? dateFunc(date_end.val().replace('T', ' ')) : null
        if (end) {
          if (settingsGantt.last_of_the_day && Gantt.config.duration_unit === 'day') end = Gantt.date.add(end, 1, 'day');
          const cal_duration = Gantt.calculateDuration({start_date: start, end_date: end})
          setDuration(cal_duration, duration)
        }

        const setEndDate = () => {
          const val_duration = getDuration(duration)
          const start = dateFunc(date_start.val().replace('T', ' '))
          let new_value = Gantt.calculateEndDate({start_date: start, unit: 'minute', duration: val_duration});
          if (settingsGantt.last_of_the_day && Gantt.config.duration_unit === 'day') new_value = Gantt.date.add(new_value, -1, 'day');
          date_end.val(formatFunc(new_value))
        }

        const validateDate = () => {
          if (date_start.length && !date_start.val()) date_start.val(formatFunc(new Date()))
          if (date_end.length && !date_end.val()) setEndDate()
          if (date_start.length && date_end.length) {
            const start = dateFunc(date_start.val().replace('T', ' '))
            let end = dateFunc(date_end.val().replace('T', ' '))
            if (settingsGantt.last_of_the_day && Gantt.config.duration_unit === 'day') end = Gantt.date.add(end, 1, 'day');
            if (start.valueOf() >= end.valueOf()) {
              setEndDate()
              date_end.attr('min', formatFunc(roundTime(start)))
            }
          }
        }

        const validateDuration = (new_value) => {
          new_value = Number(new_value)
          const min_focus = Number(duration_focus.attr('min')) || 0
          const max_focus = Number(duration_focus.attr('max')) || 999999
          if (min_focus <= new_value && new_value <= max_focus) {
            duration_focus.val(new_value)
          }
          else if (new_value < min_focus) {
            duration_focus.val(min_focus)
          }
          else if (new_value > max_focus) {
            duration_focus.val(max_focus)
          }
        }

        duration.find('label').on('click', function (e) {
          jQuery(this).prev().focus()
        })

        duration.find('input[id^="duration"]').on('focus', function (e) {
          duration_focus = jQuery(this)
        })

        duration.on('click', '#duration-button-diminish',function () {
          let val_duration = getDuration(duration)
          if (duration_focus) {
            duration_focus.focus()
            switch (duration_focus.attr('id')) {
              case 'duration-day':
                val_duration -= 1440
                break;

              case 'duration-hour':
                val_duration -= 60
                break;

              default:
                val_duration -= Gantt.form_blocks["datetime_optional"].roundNumber
            }
          }
          else {
            val_duration -= Gantt.config.duration_unit === 'minute' ? Gantt.form_blocks["datetime_optional"].roundNumber : 1440
          }
          if (val_duration > 0) {
            const start = dateFunc(date_start.val().replace('T', ' '))
            const cal_end_date = roundTime(Gantt.calculateEndDate({start_date: start, unit: 'minute', duration: val_duration}))
            const cal_duration = Gantt.calculateDuration({start_date: start, end_date: cal_end_date})
            setDuration(cal_duration, duration)
            setEndDate()
          }
        })
        duration.on('click', '#duration-button-increase',function () {
          let val_duration = getDuration(duration)
          if (duration_focus) {
            duration_focus.focus()
            switch (duration_focus.attr('id')) {
              case 'duration-day':
                val_duration += 1440
                break;

              case 'duration-hour':
                val_duration += 60
                break;

              default:
                val_duration += Gantt.form_blocks["datetime_optional"].roundNumber
            }
          }
          else {
            val_duration += Gantt.config.duration_unit === 'minute' ? Gantt.form_blocks["datetime_optional"].roundNumber : 1440
          }
          if (val_duration > 0) {
            const start = dateFunc(date_start.val().replace('T', ' '))
            const cal_end_date = roundTime(Gantt.calculateEndDate({start_date: start, unit: 'minute', duration: val_duration}))
            const cal_duration = Gantt.calculateDuration({start_date: start, end_date: cal_end_date})
            setDuration(cal_duration, duration)
            setEndDate()
          }
        })

        if (date_start.length && date_end.length) {
          date_start.on('change', function (e) {
            validateDate()
            if (date_start.val() && date_end.val()) {
              const start = dateFunc(date_start.val().replace('T', ' '))
              const val_duration = getDuration(duration)
              let cal_end_date = Gantt.calculateEndDate({start_date: start, unit: 'minute', duration: val_duration});
              if (settingsGantt.last_of_the_day && Gantt.config.duration_unit === 'day') cal_end_date = Gantt.date.add(cal_end_date, -1, 'day');
              date_end.val(formatFunc(cal_end_date))
            }
          })
        }
        if (date_start.length && date_end.length) {
          date_end.on('change', function (e) {
            const start = dateFunc(date_start.val().replace('T', ' '))
            let end = dateFunc(date_end.val().replace('T', ' '))
            if (start > end) {
              jQuery(this).css('color', 'red');
            }
          });
          date_end.on('change', Drupal.debounce(function (e) {
            validateDate()
            if (date_start.val() && date_end.val()) {
              const start = dateFunc(date_start.val().replace('T', ' '))
              let end = dateFunc(date_end.val().replace('T', ' '))
              if (settingsGantt.last_of_the_day && Gantt.config.duration_unit === 'day') end = Gantt.date.add(end, 1, 'day');
              const cal_duration = Gantt.calculateDuration({start_date: start, end_date: end});
              setDuration(cal_duration, duration)
              jQuery(this).css('color', 'black');
            }
          }, 500))
        }
        if (duration.length) {
          duration.on('change', 'input[id^="duration"]', function (e) {
            validateDuration(jQuery(this).val())
            setEndDate()
          })
        }
      },

      get_value: function (node, ev, sns) {
        const dateFunc = Gantt.form_blocks["datetime_optional"].dateFunc;
        let date_start = jQuery(node).find('#date_start');
        let date_end = jQuery(node).find('#date_end');
        if (!jQuery(node).is(":visible")) {
          ev.planned_start = null
          ev.planned_end = null
          return true;
        }
        if (date_start.length) ev.planned_start = dateFunc(date_start.val().replace('T', ' '))
        if (date_end.length) {
          ev.planned_end = dateFunc(date_end.val().replace('T', ' '))
          if (settingsGantt.last_of_the_day && Gantt.config.duration_unit === 'day') ev.planned_end = Gantt.date.add(ev.planned_end, 1, 'day');
        }
        return true;
      }
    };
  }

  numberForm(Gantt = this.Gantt) {
    Gantt.form_blocks["number"] = {
      render: function (sns) {
        return '<div class="gantt_cal_ltext gantt_cal_number"><input type="number" min="0"></div>';
      },
      set_value: function (node, value, task) {
        jQuery(node.firstChild).val(value);
      },
      get_value: function (node, task) {
        return jQuery(node.firstChild).val();
      },
      focus: function (node) {
        jQuery(node.firstChild).focus()
      }
    };
  }

  showSlack(value = true, $this = this, Gantt = this.Gantt) {
    this.options.show_slack = value;
    if (!value) {
      Gantt.config.show_slack = false;
      Gantt.config.columns = Gantt.config.columns.filter(item => item.name !== 'totalSlack' && item.name !== 'freeSlack');
      Gantt.removeTaskLayer(this.taskLayer.slack);
      return true;
    }
    let totalSlackColumn = {
      name: "totalSlack",
      align: "center",
      resize: true,
      width: 70,
      label: this.i18n.labels.column_totalSlack,
      template: function (task) {
        if (Gantt.isSummaryTask(task)) {
          return "";
        }
        let slack_duration = Gantt.getTotalSlack(task);
        if (Gantt.config.duration_unit === 'minute') {
          slack_duration = $this.formatMinuteTime(slack_duration)
        }
        return slack_duration;
      }
    }

    let freeSlackColumn = {
      name: "freeSlack",
      align: "center",
      resize: true,
      width: 70,
      label: this.i18n.labels.column_freeSlack,
      template: function (task) {
        if (Gantt.isSummaryTask(task)) {
          return "";
        }
        let slack_duration = Gantt.getFreeSlack(task);
        if (Gantt.config.duration_unit === 'minute') {
          slack_duration = $this.formatMinuteTime(slack_duration)
        }
        return slack_duration;
      }
    };

    let col_add = [];
    Gantt.config.columns = Gantt.config.columns.filter(item => {
      if (['add', 'buttons', 'control_columns'].includes(item.name)) {
        col_add.push(item);
        return false;
      }
      return true;
    });

    if (!Gantt.config.columns.find(item => item.name === 'freeSlack')) {
      Gantt.config.columns.push(freeSlackColumn);
    }
    if (!Gantt.config.columns.find(item => item.name === 'totalSlack')) {
      Gantt.config.columns.push(totalSlackColumn);
    }
    if (this.settingsGantt.add_task && col_add.length) {
      col_add.forEach(item => {
        Gantt.config.columns.push(item);
      })
    }

    Gantt.config.show_slack = true;
    this.taskLayer.slack = Gantt.addTaskLayer(function addSlack(task) {
      if (!Gantt.config.show_slack) {
        return null;
      }

      let slack = Gantt.getFreeSlack(task);

      if (!slack) {
        return null;
      }

      let state = Gantt.getState().drag_mode;

      if (state === 'resize' || state === 'move') {
        return null;
      }

      let slackStart = new Date(task.end_date);
      let slackEnd = Gantt.calculateEndDate(slackStart, slack);
      let sizes = Gantt.getTaskPosition(task, slackStart, slackEnd);
      let el = document.createElement('div');

      el.className = 'slack';
      el.style.left = sizes.left + 'px';
      el.style.top = sizes.top + 2 + 'px';
      el.style.width = sizes.width + 'px';
      el.style.height = sizes.height + 'px';

      return el;
    });
  }

  showColumnWBS(value = true, Gantt = this.Gantt) {
    if (!value) {
      this.options.show_column_wbs = false;
      Gantt.config.columns = Gantt.config.columns.filter(item => item.name !== 'wbs');
      return true;
    }
    this.options.show_column_wbs = true;
    Gantt.config.columns.unshift({
      name: "wbs",
      label: this.i18n.labels.column_wbs,
      width: 40,
      resize: true,
      template: Gantt.getWBSCode
    })
  }

  onShowButtonColumn(value = true, Gantt = this.Gantt) {
    this.options.column_buttons = value;
    if (this.options.add_task) {
      return false;
    }
    if (!value) {
      Gantt.detachEvent('action_button_column')
      let col_buttons = Gantt.config.columns.find(item => item.name === 'buttons');
      if (col_buttons) {
        col_buttons.name = 'add'
        col_buttons.min_width = 44
        col_buttons.max_width = 44
        col_buttons.width = 44
      }
      return true;
    }

    const id_gantt = this.id_gantt;
    let colHeader = `<div class="gantt_grid_head_cell gantt_grid_head_add" onclick="clickGridButton('${id_gantt}')"></div>`;
    let colContent = function (task) {
      return ('<i class="fa gantt_button_grid gantt_grid_edit fa-pencil" onclick="clickGridButton(\'' + id_gantt + '\', ' + task.id + ', \'edit\')"></i>' +
        '<i class="fa gantt_button_grid gantt_grid_add fa-plus" onclick="clickGridButton(\'' + id_gantt + '\', ' + task.id + ', \'add\')"></i>' +
        '<i class="fa gantt_button_grid gantt_grid_delete fa-times" onclick="clickGridButton(\'' + id_gantt + '\', ' + task.id + ', \'delete\')"></i>');
    };
    let col_add = Gantt.config.columns.find(item => item.name === 'add');
    if (col_add) {
      col_add.name = 'buttons'
      col_add.label = colHeader
      col_add.min_width = 75
      col_add.max_width = 75
      col_add.width = 75
      col_add.template = colContent
    }
  }

  onDynamicProgress(value = true, Gantt = this.Gantt) {
    this.options.dynamic_progress = value
    if (!value) {
      Gantt.detachEvent('dynamic_progress_on_parse');
      Gantt.detachEvent('dynamic_progress_after_update_task');
      Gantt.detachEvent('dynamic_progress_on_drag_task');
      Gantt.detachEvent('dynamic_progress_after_add_task');
      Gantt.detachEvent('dynamic_progress_before_delete_task');
      Gantt.detachEvent('dynamic_progress_after_delete_task');
      Gantt.detachEvent('dynamic_progress_before_change_task');
      return true;
    }
    const calculateSummaryProgress = (task) => {
      if (task.type !== Gantt.config.types.project) {
        return task.progress;
      }
      let totalToDo = 0;
      let totalDone = 0;
      Gantt.eachTask(function (child) {
        if (child.type !== Gantt.config.types.project) {
          totalToDo += child.duration;
          totalDone += (child.progress || 0) * child.duration;
        }
      }, task.id);
      if (!totalToDo) {
        return 0;
      }
      return totalDone / totalToDo;
    }

    const refreshSummaryProgress = (id, submit) => {
      if (!Gantt.isTaskExists(id)) {
        return;
      }

      let task = Gantt.getTask(id);
      let newProgress = calculateSummaryProgress(task);

      if (newProgress !== task.progress) {
        task.progress = newProgress;

        if (!submit) {
          Gantt.refreshTask(id);
        }
        else {
          Gantt.updateTask(id);
        }
      }

      if (!submit && Gantt.getParent(id) !== Gantt.config.root_id) {
        refreshSummaryProgress(Gantt.getParent(id), submit);
      }
    }

    Gantt.attachEvent("onParse", function () {
      Gantt.eachTask(function (task) {
        task.progress = calculateSummaryProgress(task);
      });
    }, { id: 'dynamic_progress_on_parse' });

    Gantt.attachEvent("onAfterTaskUpdate", function (id) {
      refreshSummaryProgress(Gantt.getParent(id), true);
    }, { id: 'dynamic_progress_after_update_task' });

    Gantt.attachEvent("onTaskDrag", function (id) {
      refreshSummaryProgress(Gantt.getParent(id), false);
    }, { id: 'dynamic_progress_on_drag_task' });
    Gantt.attachEvent("onAfterTaskAdd", function (id) {
      refreshSummaryProgress(Gantt.getParent(id), true);
    }, { id: 'dynamic_progress_after_add_task' });

    (function () {
      let idParentBeforeDeleteTask = 0;
      Gantt.attachEvent("onBeforeTaskDelete", function (id) {
        idParentBeforeDeleteTask = Gantt.getParent(id);
      }, {id: 'dynamic_progress_before_delete_task'});
      Gantt.attachEvent("onAfterTaskDelete", function () {
        refreshSummaryProgress(idParentBeforeDeleteTask, true);
      }, { id: 'dynamic_progress_after_delete_task' });
    })();

    Gantt.attachEvent("onBeforeTaskChanged", function (id, mode, old_event) {
      const task = Gantt.getTask(id);
      if (mode === Gantt.config.drag_mode.progress && task.type === Gantt.config.types.project) {
        Gantt.message(Drupal.t("@task progress can't be undone!", {'@task': task.text}));
        return false;
      }
      return true;
    }, { id: 'dynamic_progress_before_change_task' });
  }

  highLightDragTask(value = true, Gantt = this.Gantt) {
    this.options.highlight_drag_task = value;
    let taskLayer = this.taskLayer;
    if (!value) {
      delete Gantt.config.show_drag_vertical;
      delete Gantt.config.show_drag_dates;
      delete Gantt.config.drag_label_width;
      delete Gantt.config.drag_date;
      delete Gantt.templates.drag_date;
      Gantt.removeTaskLayer(taskLayer.highlight_drag_task_area);
      Gantt.removeTaskLayer(taskLayer.highlight_drag_task_date);
      Gantt.detachEvent('highlight_drag_task');
      return true;
    }
    Gantt.config.show_drag_vertical = value;
    Gantt.config.show_drag_dates = value;
    Gantt.config.drag_label_width = 70;
    Gantt.config.drag_date = Gantt.config.date_grid;
    Gantt.templates.drag_date = null;
    Gantt.templates.drag_date = Gantt.date.date_to_str(Gantt.config.drag_date);

    function addElement(config) {
      let div = document.createElement('div');
      div.style.position = "absolute";
      div.className = config.css || "";
      div.style.left = config.left;
      div.style.width = config.width;
      div.style.height = config.height;
      div.style.lineHeight = config.height;
      div.style.top = config.top;
      if (config.html) {
        div.innerHTML = config.html;
      }
      if (config.wrapper) {
        config.wrapper.appendChild(div);
      }
      return div;
    }

    //highlight area
    taskLayer.highlight_drag_task_area = Gantt.addTaskLayer({
      renderer: function highlight_area(task) {
        let sizes = Gantt.getTaskPosition(task, task.start_date, task.end_date),
          wrapper = document.createElement("div");

        addElement({
          css: 'drag_move_vertical',
          left: sizes.left + 'px',
          top: 0,
          width: sizes.width + 'px',
          height: Gantt.getVisibleTaskCount() * Gantt.config.row_height + "px",
          wrapper: wrapper
        });

        addElement({
          css: 'drag_move_horizontal',
          left: 0,
          top: sizes.top + 'px',
          width: 100 + "%",
          height: Gantt.config.row_height - 1 + 'px',
          wrapper: wrapper
        });

        return wrapper;
      },
      filter: function (task) {
        return Gantt.config.show_drag_vertical && task.id === Gantt.getState().drag_id;
      }
    });

    //show drag dates
    taskLayer.highlight_drag_task_date = Gantt.addTaskLayer({
      renderer: function show_dates(task) {
        let sizes = Gantt.getTaskPosition(task, task.start_date, task.end_date),
          wrapper = document.createElement('div');

        addElement({
          css: "drag_move_start drag_date",
          left: sizes.left - Gantt.config.drag_label_width + 'px',
          top: sizes.top + 'px',
          width: Gantt.config.drag_label_width + 'px',
          height: Gantt.config.row_height - 1 + 'px',
          html: Gantt.templates.drag_date(task.start_date),
          wrapper: wrapper
        });

        addElement({
          css: "drag_move_end drag_date",
          left: sizes.left + sizes.width + 'px',
          top: sizes.top + 'px',
          width: Gantt.config.drag_label_width + 'px',
          height: Gantt.config.row_height - 1 + 'px',
          html: Gantt.templates.drag_date(task.end_date),
          wrapper: wrapper
        });

        return wrapper;
      },
      filter: function (task) {
        return Gantt.config.show_drag_dates && task.id === Gantt.getState().drag_id;
      }
    });
  }

  highLightProject(value = true, Gantt = this.Gantt) {
    let taskLayer = this.taskLayer;
    // highlight project and task child area.
    taskLayer.highlight_project = Gantt.addTaskLayer(function (task) {
      if (Gantt.getState().selected_task == task.id && Gantt.hasChild(task.id)) {
        const sizes = Gantt.getTaskPosition(task, task.start_date, task.end_date);
        const left = [sizes.left];
        const right = [sizes.left + sizes.width];
        Gantt.eachTask(function (child) {
          const childSizes = Gantt.getTaskPosition(child, child.start_date, child.end_date);
          left.push(childSizes.left)
          right.push(childSizes.left + childSizes.width)
          if (sizes.height < childSizes.top + childSizes.height) sizes.height = childSizes.top + childSizes.height - sizes.top
        }, task.id);

        sizes.left = left.sort((a, b) => a - b)[0]

        const mostRight = right.sort((a, b) => a - b).reverse()[0]
        sizes.width = mostRight - sizes.left

        const el = document.createElement('div');
        el.className = 'group';
        el.style.left = sizes.left + 'px';
        el.style.width = sizes.width + 'px';
        el.style.top = sizes.top + 'px';
        el.style.height = sizes.height + 'px';
        return el;
      }
      return false;
    });
  }

  showColumn(column = {}) {
    let col_add = {};
    this.Gantt.config.columns = this.Gantt.config.columns.filter(function (item) {
      if (item.name === 'add' || item.name === 'buttons') {
        col_add = item;
        return false
      }
      else {
        return true
      }
    })
    if (Object.keys(column).length) {
      for (const key in column) {
        if (column[key].hasOwnProperty('data') && column[key].data.length && !this.Gantt.serverList(key)) {
          this.Gantt.serverList(key, column[key].data)
        }
        let serverList = this.Gantt.serverList(key);
        let col_obj = {
          name: key, label: column[key].label, align: "center", resize: true
        }
        if (serverList.length) {
          col_obj.template = function (task) {
            if (Array.isArray(task[key])) {
              let result = '';
              task[key].forEach(function (id) {
                let find = serverList.find(item => item.key == id);
                if (find) {
                  result += `<div style="color: ${find.color}; border: 2px solid ${find.color}6a" class="owner-label" title="${find.label}">${find.label.charAt(0)}</div>`;
                }
              });
              return result
            }
            else {
              let find = serverList.find(item => item.key == task[key]);
              if (find) {
                return '<span style="color: ' + find.color + '">' + find.label + '</span>' || '';
              }
            }
          }
        }
        this.Gantt.config.columns.push(col_obj)
      }
    }
    this.Gantt.config.columns.push(col_add)
  }

  onZoomFit(Gantt = this.Gantt) {
    document.querySelector('[data-action="zoomToFit"]').addEventListener('click', function () {
      zoomToFit()
    })

    const zoomToFit = () => {
      let project = Gantt.getSubtaskDates();
      let areaWidth = Gantt.$task.offsetWidth;
      let scaleConfigs = this.zoomConfig.levels;
      // Iterate through zoom levels to find the best fit.
      let i;
      let reverseScaleConfigs = [...scaleConfigs];
      reverseScaleConfigs = reverseScaleConfigs.reverse();
      for (i = 0; i < reverseScaleConfigs.length; i++) {
        let columnCount = getUnitsBetween(project.start_date, project.end_date, reverseScaleConfigs[i].scales[reverseScaleConfigs[i].scales.length - 1].unit, reverseScaleConfigs[i].scales[reverseScaleConfigs[i].scales.length - 1].step);
        let min_column_width = reverseScaleConfigs[i]?.min_column_width || Gantt.config.min_column_width;
        let a = (columnCount + 2) * min_column_width;
        if ((columnCount + 2) * min_column_width <= areaWidth) {
          break;
        }
      }
      // Ensure the zoom level does not exceed the available configurations.
      if (i === reverseScaleConfigs.length) {
        i--;
      }
      // Apply the determined zoom level and configuration.
      Gantt.ext.zoom.setLevel(reverseScaleConfigs[i].name);
      // applyConfig(reverseScaleConfigs[i], project);
    }

    // get number of columns in timeline
    const getUnitsBetween = (from, to, unit, step) => {
      let start = new Date(from),
        end = new Date(to);
      let units = 0;
      while (start.valueOf() < end.valueOf()) {
        units++;
        start = Gantt.date.add(start, step, unit);
      }
      return units;
    }
  }

  onHideAddTask(task_level = 0, value = true) {
    this.options.hide_add_task_level = value ? task_level : false;
  }

  onButtonComplete($this = this, Gantt = this.Gantt) {
    Gantt.locale.labels.complete_button = Drupal.t("Complete");
    Gantt.attachEvent("onBeforeLightbox", function (id) {
      let task = Gantt.getTask(id);
      if (task.$new || task.type === 'milestone' || task.progress == 1 || (task.type === 'project' && $this.options.dynamic_progress)) {
        Gantt.config.buttons_left = Gantt.config.buttons_left.filter(item => item !== 'complete_button');
      } else {
        if (!Gantt.config.buttons_left.includes('complete_button')) {
          Gantt.config.buttons_left.push('complete_button');
        }
      }
      Gantt.resetLightbox()
      return true;
    }, { id: "before_lightbox_button_complete" });

    Gantt.attachEvent("onLightboxButton", function (button_id, node, e) {
      if (button_id === "complete_button") {
        let id = Gantt.getState().lightbox;
        if (Gantt.isReadonly(id)) {
          Gantt.alert(Drupal.t("You cannot edit this task."));
          return false;
        }
        Gantt.getTask(id).progress = 1;
        Gantt.updateTask(id)
        Gantt.hideLightbox();
      }
    }, {id: "action_complete_task"});
  }

  onLookTaskComplete(value = true, Gantt = this.Gantt) {
    this.options.lock_completed_task = value;
    if (!value) {
      Gantt.config.buttons_left = Gantt.config.buttons_left.filter(item => item !== 'complete_button');
      Gantt.detachEvent("before_lightbox_complete_task");
      Gantt.resetLightbox()
      return true;
    }
    this.options.lock_completed_task = true;
    Gantt.attachEvent("onBeforeLightbox", function (id) {
      let task = Gantt.getTask(id);
      if (task.progress === 1) {
        Gantt.message({
          text: Drupal.t("The task is already completed!"),
          type: "completed"
        });
        return false;
      }
      Gantt.resetLightbox()
      return true;
    }, { id: "before_lightbox_complete_task" });
  }

  setServerList(name = null, data = [], Gantt = this.Gantt) {
    if (!name && data.length) {
      Gantt.serverList(name, data);
    }
  }

  onHideNotWorkingTime(value = true, Gantt = this.Gantt) {
    Gantt.config.skip_off_time = value;
    this.options.hide_weekend_scale = value;
  }

  onMinimumStep(value = true, Gantt = this.Gantt) {
    this.options.round_dnd_dates = value;
    Gantt.config.round_dnd_dates = !value;
  }

  onDragProject(value = true, Gantt = this.Gantt) {
    this.options.drag_project = value;
    Gantt.config.drag_project = value;
    if (!value) {
      return true;
    }
  }

  setLocales(lang = drupalSettings.path.currentLanguage, Gantt = this.Gantt) {
    if (!Gantt.i18n.getLocale(lang)) {
      Gantt.i18n.addLocale(lang, this.i18n);
    }
    Gantt.i18n.setLocale(lang);
  }

  setScale(Gantt = this.Gantt) {
    Gantt.attachEvent('onGanttReady', function () {
      let elGantt = Gantt.$root.closest('.gantt-wrapper');
      let buttons = elGantt.querySelectorAll('[data-action="zoomIn"], [data-action="zoomOut"]');
      if (buttons.length) {
        for (let i = 0; i < buttons.length; i++) {
          buttons[i].onclick = function () {
            const action = this.dataset['action'];
            if (action === 'zoomIn') {
              Gantt.ext.zoom.zoomOut()
            }
            else {
              Gantt.ext.zoom.zoomIn()
            }
          }
        }
      }
    })

    const daysStyle = (date) => {
      return !Gantt.isWorkTime(date) ? "weekend" : "";
    };

    let zoomConfig = {
      levels: [
        {
          name: "year",
          scale_height: 40,
          min_column_width: 80,
          scales: [
            {unit: "year", format: "%Y", step: 1},
          ]
        },
        {
          name: "quarter",
          scale_height: 40,
          min_column_width: 70,
          scales: [
            {unit: "year", format: "%Y", step: 1},
            {
              unit: "quarter", step: 1, format: function (date) {
                let dateToStr = Gantt.date.date_to_str("%M");
                let endDate = Gantt.date.add(Gantt.date.add(date, 3, "month"), -1, "day");
                return dateToStr(date) + " - " + dateToStr(endDate);
              }
            }
          ]
        },
        {
          name: "month",
          scale_height: 40,
          min_column_width: 20,
          scales: [
            {unit: "month", format: "%M %Y", step: 1},
            {unit: "day", format: "%j", step: 1, css: daysStyle}
          ]
        },
        {
          name: "week",
          scale_height: 40,
          min_column_width: 40,
          scales: [
            {unit: "month", format: "%M %Y", step: 1},
            {unit: "week", step: 1, format: Drupal.t("Week") + " %W"},
            {unit: "day", step: 1, format: "%D", css: daysStyle}
          ]
        },
        {
          name: "day",
          scale_height: 40,
          min_column_width: 70,
          scales: [
            {unit: "week", format: Drupal.t("Week") + " %W(%Y)",  step: 1},
            {unit: "day", format: "%D, %d/%m", step: 1, css: daysStyle}
          ]
        },
        {
          name: "hour",
          scale_height: 40,
          min_column_width: 50,
          scales: [
            {unit: "day", format: "%d %M %Y", step: 1, css: daysStyle},
            {unit: "hour", format: "%H:%i", step: 1, css: daysStyle}
          ]
        },
      ],
      activeLevelIndex: 4,
      useKey: "ctrlKey",
      trigger: "wheel",
      element: function () {
        return Gantt.$root.querySelector(".gantt_task");
      }
    }
    this.zoomConfig = zoomConfig;
    Gantt.ext.zoom.init(zoomConfig);
    Gantt.ext.zoom.attachEvent("onAfterZoom", (level, config) => {
      let elGantt = Gantt.$root.closest('.gantt-wrapper');
      let area = Gantt.$root.querySelector('.gantt_container');
      area.classList.toggle('non-zoom-day', config.name === 'year' || config.name === 'quarter');
      elGantt.querySelector('[data-action="scale_gantt"]').value = config.name;
    });

    Gantt.attachEvent("onGanttReady", function () {
      let elGantt = Gantt.$root.closest('.gantt-wrapper')
      elGantt.querySelector('[data-action="scale_gantt"]').addEventListener('change', function () {
        Gantt.ext.zoom.setLevel(this.value);
      })
    })
  }

  setColumn(Gantt = this.Gantt, settingsGantt = this.settingsGantt) {
    let column_text = Gantt.config.columns.find(item => item.name === 'text');
    column_text.min_width = 200;
    let column_duration = Gantt.config.columns.find(item => item.name === 'duration');
    column_duration.resize = true;

    const getResource = (task, source, attr = null) => {
      if (!attr) {
        attr = source;
      }
      const serverList = Gantt.serverList(source)
      if (Array.isArray(task[attr])) {
        return task[attr].map(id => {
          const find = serverList.find(item => item.key == id);
          return find ? `<div class='resource-label' title='${find.label}'>${find.label.substr(0, 1)}</div>` : '';
        }).join('');
      } else {
        const find = serverList.find(item => item.key == task[attr]);
        return find ? `<span>${find.label}</span>` : '';
      }
    }

    if (settingsGantt.priority.length) {
      Gantt.locale.labels['section_priority'] = settingsGantt.server_list_resource[settingsGantt.priority].label;
      if (!Gantt.serverList('priority').length) {
        Gantt.serverList('priority', settingsGantt.server_list_resource[settingsGantt.priority].data)
      }
      let indexCol = Gantt.config.columns.findIndex((item) => item.name === 'duration');
      Gantt.config.columns.splice(indexCol + 1, 0, {
        name: 'priority',
        label: this.i18n.labels.column_priority,
        width: 80,
        align: "center",
        resize: true,
        template: (task) => getResource(task, 'priority', 'priority')
      });
    }

    if (settingsGantt.planned_date && !settingsGantt.use_cdn) {
      let indexCol = Gantt.config.columns.findIndex((item) => item.name === 'duration');
      Gantt.config.columns.splice(indexCol + 1, 0, {
        name: 'planned_duration',
        label: this.i18n.labels.column_planned_duration,
        width: 80,
        align: "center",
        resize: true,
      });
    }

    if (settingsGantt.setting_resource.resource_column.length) {
      let cols = settingsGantt.setting_resource.resource_column.reverse();
      cols.forEach(col => {
        Gantt.locale.labels['section_'+col] = settingsGantt.server_list_resource[col].label;
        if (!Gantt.serverList(col).length) {
          Gantt.serverList(col, settingsGantt.server_list_resource[col].data)
        }
        Gantt.config.columns.unshift({
          name: col,
          label: settingsGantt.server_list_resource[col].label,
          align: "center",
          resize: true,
          template: (task) => getResource(task, col)
        })
      })
    }

    // Add custom column custom field.
    if (settingsGantt.custom_field) {
      let customColumnExist = Gantt.config.columns.findIndex((element) => element.name === 'custom_field');
      if (customColumnExist < 0) {
        Gantt.locale.labels['section_custom_field'] = settingsGantt.custom_field_name;
        Gantt.config.columns.splice(Gantt.config.columns.length - 1, 0, {
          name: 'custom_field',
          label: settingsGantt.custom_field_name,
          align: "center",
          resize: true
        });
      }
    }

  }

  setLightBox(Gantt = this.Gantt, settingsGantt = this.settingsGantt) {

    const format_date_actually = settingsGantt.format_date_actually === 'datetime' ? ["%d", "%m", "%Y", "%H:%i"] : ["%d", "%m", "%Y"];
    const mapping_time_mode = {'duration': 'duration', 'end_time': 'time', 'responsive': 'datetime'}
    // Light box task.
    Gantt.config.lightbox.sections = [
      {
        name: "description",
        height: 70,
        map_to: "text",
        type: "textarea",
        focus: true,
      },
      {
        name: "type",
        height: "auto",
        type: "typeselect",
        map_to: "type",
      },
    ];
    let el_obj = {
      name: "time",
      type: mapping_time_mode[settingsGantt.time_input_mode],
      map_to: "auto",
      autofix_end: true,
      year_range: 11,
      time_format: format_date_actually,
    }
    if (settingsGantt.time_input_mode === 'responsive') {
      delete el_obj.time_format
    }
    Gantt.config.lightbox.sections.push(el_obj)

    let position = Gantt.config.lightbox.sections.findIndex((element) => element.name === 'type');
    Gantt.config.lightbox.sections.splice(position + 1, 0, {
      name: 'custom_field',
      type: "number",
      height: 'auto',
      map_to: 'custom_field',
    })

    if (settingsGantt.setting_resource.resource_lightbox.length) {
      settingsGantt.setting_resource.resource_lightbox.forEach(item => {
        Gantt.locale.labels['section_'+item] = settingsGantt.server_list_resource[item].label;
        if (!Gantt.serverList(item).length) {
          Gantt.serverList(item, settingsGantt.server_list_resource[item].data)
        }
        let position = Gantt.config.lightbox.sections.findIndex((element) => element.name === "type")
        let obj = {
          name: item,
          type: "multiselect",
          height: 'auto',
          options: Gantt.serverList(item),
          map_to: item
        }
        if (settingsGantt.validate_field.custom_resource?.[item]?.cardinality === 1) {
          obj.type = 'onceselect'
        }
        if (settingsGantt.validate_field.custom_resource?.[item]?.required) {
          obj.required = true
        }
        Gantt.config.lightbox.sections.splice(position + 1, 0, obj)
      })
    }

    if (settingsGantt.constraint && !settingsGantt.use_cdn) {
      let position = Gantt.config.lightbox.sections.findIndex((element) => element.name === "type")
      Gantt.config.lightbox.sections.splice(position + 1, 0, {
        name: "constraint",
        type: "constraint"
      })
    }

    if (settingsGantt.priority) {
      Gantt.locale.labels['section_'+settingsGantt.priority] = settingsGantt.server_list_resource[settingsGantt.priority].label;
      if (!Gantt.serverList(settingsGantt.priority).length) {
        Gantt.serverList(settingsGantt.priority, settingsGantt.server_list_resource[settingsGantt.priority].data)
      }
      let position = Gantt.config.lightbox.sections.findIndex((element) => element.name === "type")
      Gantt.config.lightbox.sections.splice(position + 1, 0, {
        name: 'priority',
        type: "select",
        height: 'auto',
        map_to: "priority",
        default_value: "",
        options: Gantt.serverList(settingsGantt.priority)
      })
    }

    if (settingsGantt.select_parent || settingsGantt.required_parent) {
      Gantt.locale.labels.section_parent = this.i18n.labels.section_parent;
      let position = Gantt.config.lightbox.sections.findIndex((element) => element.name === "type")
      let obj = {
        name: "parent",
        height: 'auto',
        type: "parent",
        allow_root: true,
        root_label: Drupal.t("No parent"),
        sort: function (a, b) {
          a = a.$index || 0;
          b = b.$index || 0;
          return a > b ? 1 : (a < b ? -1 : 0);
        },
        filter: function (id, task) {
          return true;
        }
      }
      if (settingsGantt.required_parent) {
        obj.allow_root = false
      }
      Gantt.config.lightbox.sections.splice(position + 1, 0, obj)
    }

    const format_date_planned = settingsGantt.format_date_planned === 'datetime' ? ["%d", "%m", "%Y", "%H:%i"] : ["%d", "%m", "%Y"];
    if (settingsGantt.planned_date && !settingsGantt.use_cdn) {
      el_obj = {
        name: "baseline",
        button: true,
        type: mapping_time_mode[settingsGantt.time_input_mode] + "_optional",
        year_range: 11,
        map_to: {start_date: "planned_start", end_date: "planned_end"},
        time_format: format_date_planned,
      }
      if (settingsGantt.time_input_mode === 'responsive') {
        el_obj.button = "responsive_toggle"
        el_obj.map_to = 'auto'
        delete el_obj.time_format
      }
      let position = Gantt.config.lightbox.sections.findIndex((element) => element.name === "time")
      Gantt.config.lightbox.sections.splice(position + 1, 0, el_obj)
    }

    // Light box project.
    Gantt.config.lightbox.project_sections = [
      {
        name: "description",
        height: 70,
        map_to: "text",
        type: "textarea",
        focus: true
      },
      {
        name: "type",
        height: "auto",
        type: "typeselect",
        map_to: "type",
      },
    ];

    el_obj = {
      name: "time",
      type: mapping_time_mode[settingsGantt.time_input_mode],
      map_to: "auto",
      readonly: true,
      year_range: 11,
      time_format: format_date_actually,
    }
    if (settingsGantt.time_input_mode === 'responsive') {
      delete el_obj.time_format
    }
    Gantt.config.lightbox.project_sections.push(el_obj)

    if (settingsGantt.priority) {
      Gantt.locale.labels['section_'+settingsGantt.priority] = settingsGantt.server_list_resource[settingsGantt.priority].label;
      if (!Gantt.serverList(settingsGantt.priority).length) {
        Gantt.serverList(settingsGantt.priority, settingsGantt.server_list_resource[settingsGantt.priority].data)
      }
      let position = Gantt.config.lightbox.project_sections.findIndex((element) => element.name === "type")
      Gantt.config.lightbox.project_sections.splice(position + 1, 0, {
        name: 'priority',
        type: "select",
        height: 'auto',
        map_to: "priority",
        default_value: "",
        options: Gantt.serverList(settingsGantt.priority)
      })
    }

    if (settingsGantt.select_parent || settingsGantt.required_parent) {
      Gantt.locale.labels.section_parent = this.i18n.labels.section_parent;
      let position = Gantt.config.lightbox.project_sections.findIndex((element) => element.name === "type")
      Gantt.config.lightbox.project_sections.splice(position + 1, 0, {
        name: "parent",
        height: 27,
        type: "parent",
        allow_root: true,
        root_label: Drupal.t("No parent"),
        sort: function (a, b) {
          a = a.$index || 0;
          b = b.$index || 0;
          return a > b ? 1 : (a < b ? -1 : 0);
        },
        filter: function (id, task) {
          return true;
        }
      })
    }

    if (settingsGantt.planned_date && !settingsGantt.use_cdn) {
      el_obj = {
        name: "baseline",
        button: true,
        type: mapping_time_mode[settingsGantt.time_input_mode] + "_optional",
        year_range: 11,
        map_to: {start_date: "planned_start", end_date: "planned_end"},
        time_format: format_date_planned,
      }
      if (settingsGantt.time_input_mode === 'responsive') {
        el_obj.button = "responsive_toggle"
        el_obj.map_to = 'auto'
        delete el_obj.time_format
      }
      let position = Gantt.config.lightbox.project_sections.findIndex((element) => element.name === "time")
      Gantt.config.lightbox.project_sections.splice(position + 1, 0, el_obj)
    }

    // Light box milestone.
    Gantt.config.lightbox.milestone_sections = [
      {
        name: "description",
        height: 70,
        map_to: "text",
        type: "textarea",
        focus: true
      },
      {
        name: "type",
        height: "auto",
        type: "typeselect",
        map_to: "type",
      },
    ];
    el_obj = {
      name: "time",
      type: mapping_time_mode[settingsGantt.time_input_mode],
      map_to: "auto",
      single_date : true,
      year_range: 11,
      time_format: format_date_actually,
    }
    if (settingsGantt.time_input_mode === 'responsive') {
      delete el_obj.time_format
    }
    Gantt.config.lightbox.milestone_sections.push(el_obj)

    if (settingsGantt.setting_resource.resource_lightbox.length) {
      settingsGantt.setting_resource.resource_lightbox.forEach(item => {
        Gantt.locale.labels['section_'+item] = settingsGantt.server_list_resource[item].label;
        if (!Gantt.serverList(item).length) {
          Gantt.serverList(item, settingsGantt.server_list_resource[item].data)
        }
        let position = Gantt.config.lightbox.milestone_sections.findIndex((element) => element.name === "type")
        let obj = {
          name: item,
          type: "multiselect",
          height: 'auto',
          options: Gantt.serverList(item),
          map_to: item
        }
        if (settingsGantt.validate_field.custom_resource?.[item]?.cardinality === 1) {
          obj.type = 'onceselect'
        }
        if (settingsGantt.validate_field.custom_resource?.[item]?.required) {
          obj.required = true
        }
        Gantt.config.lightbox.milestone_sections.splice(position + 1, 0, obj)
      })
    }

    if (settingsGantt.select_parent) {
      Gantt.locale.labels.section_parent = this.i18n.labels.section_parent;
      let position = Gantt.config.lightbox.milestone_sections.findIndex((element) => element.name === "type")
      Gantt.config.lightbox.milestone_sections.splice(position + 1, 0, {
        name: "parent",
        height: 27,
        type: "parent",
        allow_root: true,
        root_label: Drupal.t("No parent"),
        sort: function (a, b) {
          a = a.$index || 0;
          b = b.$index || 0;
          return a > b ? 1 : (a < b ? -1 : 0);
        },
        filter: function (id, task) {
          return true;
        }
      })
    }
  }

  onDurationPlan(Gantt = this.Gantt, settingsGantt = this.settingsGantt) {
    Gantt.attachEvent("onBeforeTaskUpdate", function (id, task) {
      if (task.planned_start && task.planned_end) {
        task.planned_duration = Gantt.calculateDuration({start_date: task.planned_start, end_date: task.planned_end})
      }
    }, { id: 'duration_plan_on_drag_task' });
    Gantt.attachEvent("onBeforeTaskAdd", function (id, task) {
      if (task.planned_start && task.planned_end) {
        task.planned_duration = Gantt.calculateDuration({start_date: task.planned_start, end_date: task.planned_end})
      }
    }, { id: 'duration_plan_after_add_task' });
  }

  onShowPlanedGantt(value = true, Gantt = this.Gantt) {
    if (!value) {
      this.options.baseline = false;
      Gantt.config.bar_height = this.defaultHeightTask.bar_height;
      Gantt.config.row_height = this.defaultHeightTask.row_height;
      Gantt.detachEvent('convert_plan_on_task_loading')
      Gantt.removeTaskLayer(this.taskLayer.baseline)
      return true;
    }
    Gantt.locale.labels.section_baseline = this.i18n.labels.section_baseline;
    Gantt.locale.labels.baseline_enable_button = this.i18n.labels.baseline_enable_button;
    Gantt.locale.labels.baseline_disable_button = this.i18n.labels.baseline_disable_button;
    this.options.baseline = true;
    Gantt.config.bar_height = 21;
    Gantt.config.row_height = 40;
    this.taskLayer.baseline = Gantt.addTaskLayer({
      renderer: {
        render: function draw_planned(task) {
          /* Add baseline. */
          if (task.planned_start && task.planned_end) {
            let sizes = Gantt.getTaskPosition(task, task.planned_start, task.planned_end);
            let el = document.createElement('div');
            el.className = 'baseline';
            el.innerHTML = `<span>${task.text}</span>`;
            el.style.left = sizes.left + 'px';
            el.style.width = sizes.width + 'px';
            el.style.top = sizes.top + Gantt.config.bar_height + 13 + 'px';
            return el;
          }
          return false;
        },
        /* Define getRectangle in order to hook layer with the. */
        getRectangle: function (task, view) {
          if (task.planned_start && task.planned_end) {
            return Gantt.getTaskPosition(task, task.planned_start, task.planned_end);
          }
          return null;
        }
      }
    });

    Gantt.attachEvent("onTaskLoading", function (task) {
      task.planned_start = Gantt.date.parseDate(task.planned_start, "xml_date");
      task.planned_end = Gantt.date.parseDate(task.planned_end, "xml_date");
      return true;
    }, {id: 'convert_plan_on_task_loading'});
  }

  onHighlightCriticalPath(value = true, Gantt = this.Gantt) {
    this.options.critical_path = value;
    Gantt.config.highlight_critical_path = value;
  }

  setWorkTime(work_day = [], holidays = [], Gantt = this.Gantt) {
    Gantt.setWorkTime({ hours:["00:00-24:00"] });
    Gantt.config.work_time = true;
    Gantt.config.correct_work_time = false;
    /* Add holiday. */
    if (holidays.length) {
      holidays.map(dateString => {
        let dateParts = dateString.split('-');
        let year = parseInt(dateParts[0]);
        let month = parseInt(dateParts[1]) - 1;
        let day = parseInt(dateParts[2]);
        Gantt.setWorkTime({
          date: new Date(year, month, day),
          hours: false
        });
        return new Date(year, month, day);
      });
    }

    if (work_day.length) {
      //changes the working time of working days
      for (let i = 0; i < 7; i++) {
        if (i in work_day) {
          Gantt.setWorkTime({day: i, hours: false});
        }
        else {
          Gantt.setWorkTime({ day: i});
        }
      }
    }

    Gantt.templates.timeline_cell_class = (task, date) => {
      return Gantt.isWorkTime({ task, date }) ? "" : "weekend";
    };

  }

  markerToday(Gantt = this.Gantt) {
    let dateToStr = Gantt.date.date_to_str(Gantt.config.task_date);

    let id = Gantt.addMarker({
      start_date: new Date(),
      css: "today",
      title: dateToStr(new Date())
    });

    setInterval(function () {
      let today = Gantt.getMarker(id);
      if (today) {
        today.start_date = new Date();
        today.title = dateToStr(today.start_date);
        Gantt.updateMarker(id);
      }
    }, 1000 * 60);

    // Make resize marker for two columns.
    Gantt.attachEvent("onColumnResizeStart", function (ind, column) {
      if (!column.tree || ind === 0) {
        return;
      }

      setTimeout(function () {
        let marker = document.querySelector(".gantt_grid_resize_area");
        if (!marker) {
          return;
        }
        let cols = Gantt.getGridColumns();
        let delta = cols[ind - 1].width || 0;
        if (!delta) {
          return;
        }

        marker.style.boxSizing = "content-box";
        marker.style.marginLeft = -delta + "px";
        marker.style.paddingRight = delta + "px";
      }, 1);
    });
  };

  toggleGrid(Gantt = this.Gantt) {
    Gantt.config.show_grid = !Gantt.config.show_grid;
  }

  toggleChart(Gantt = this.Gantt) {
    Gantt.config.show_chart = !Gantt.config.show_chart;
  }

  highLight() {
    this.Gantt.templates.task_class = function (start, end, task) {
      if (this.Gantt.isCriticalTask(task)) {
        return "critical_task";
      }
      return "";
    };

    this.Gantt.templates.link_class = function (link) {
      if (this.Gantt.isCriticalLink(link)) {
        return "critical_link";
      }
      return "";
    };
  }

  renderTemplate() {
    let options = this.options;
    let Gantt = this.Gantt;

    /* progress text*/
    this.Gantt.templates.progress_text = function (start, end, task) {
      if (options.progress_text) {
        return "<span style='text-align:left;'>" + Math.round(task.progress * 100) + "% </span>";
      }
      return "";
    };

    /* task css */
    Gantt.templates.task_class = function (start, end, task) {
      let result = [];
      // baseline
      if (options.baseline) {
        result.push('exist_base_line_bar');
      }
      if (options.lock_completed_task && task.progress === 1) {
        result.push('completed_task');
      }

      // group
      if (task.$virtual) {
        result.push('summary-bar');
      }
      return result.join(' ');
    };

    /* link css */
    Gantt.templates.link_class = function (link) {
      let result = [];
      // baseline
      if (options.baseline) {
        result.push('exist_base_line_link');
      }
      return result.join(' ');
    };

    /* grid row css */
    Gantt.templates.grid_row_class = function (start, end, task) {
      let result = [];
      if (Number.isInteger(options.hide_add_task_level)) {
        if (task.$level >= options.hide_add_task_level) {
          result.push('nested_task');
        }
      }
      if (task.$virtual) {
        return "summary-row"
      }
      return result.join(' ');
    };

    /* task row css */
    Gantt.templates.task_row_class = function (start, end, task) {
      if (task.$virtual)
        return "summary-row"
    };

    /* date task formatted */
    Gantt.templates.task_end_date = function (date) {
      // Option Last of the day.
      if (options.last_of_the_day && Gantt.config.duration_unit === 'day') {
        return Gantt.templates.task_date(new Date(date.valueOf() - 1));
      }
      return Gantt.templates.task_date(new Date(date.valueOf()));
    };

    /* date grid formatted */
    let gridDateToStr = Gantt.date.date_to_str(Gantt.config.date_grid);
    Gantt.templates.grid_date_format = function (date, column) {
      if (column === "end_date") {
        // Option Last of the day.
        if (options.last_of_the_day && Gantt.config.duration_unit === 'day') {
          return gridDateToStr(new Date(date.valueOf() - 1));
        }
        return gridDateToStr(new Date(date.valueOf()));
      }
      else {
        return gridDateToStr(date);
      }
    }
  }

  render() {
    this.renderTemplate();
    this.Gantt.render();
  }

  init(element_id, data = [], links = []) {
    if (this.settingsGantt.order) {
      data.sort(function(a, b) {return a.order - b.order})
    }
    this.renderTemplate();
    this.Gantt.init(element_id);
    this.Gantt.parse({
      "data": data,
      "links": links
    });
  }
}
