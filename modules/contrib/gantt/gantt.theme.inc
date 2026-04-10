<?php

/**
 * @file
 * Theme for Gantt views.
 */

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;

/**
 * Template preprocess views gantt.
 *
 * @param array $variables
 *   Array variable.
 */
function template_preprocess_views_view_gantt(array &$variables) {
  $view = $variables['view'];
  $style = $view->style_plugin;
  $setting = $style->options;
  $setting['planned_date'] = $setting['baseline']['planned_date'] ?? '';
  $setting['planned_end_date'] = $setting['baseline']['planned_end_date'] ?? '';
  $setting['constraint'] = $setting['baseline']['constraint'] ?? '';
  $setting['auto_schedule_planned'] = $setting['baseline']['auto_schedule_planned'] ?? FALSE;
  // Entity info form view.
  $filter = $view->filter;
  $setting['view_type_entity'] = $view->getBaseEntityType()->id();
  $setting['view_bundle_entity'] = key($filter['type']->value);

  // Format date.
  if ($setting['format_date'] == 'custom') {
    $setting['format_date'] = !empty($setting['custom_format_date']) ? $setting['custom_format_date'] : 'd/m/Y';
  }
  $replace_map = [
    'Y' => '%Y',
    'm' => '%m',
    'd' => '%d',
    'H' => '%H',
    'i' => '%i',
    's' => '%s',
    'j' => '%j',
    'l' => '%l',
    'M' => '%M',
    'a' => '%a',
    'A' => '%A',
    'F' => '%F',
    'n' => '%n',
    'W' => '%W',
    'w' => '%w',
    'D' => '%D',
    'g' => '%g',
    'G' => '%G',
    'y' => '%y',
    'h' => '%h',
  ];
  $date_format = strtr($setting['format_date'], $replace_map);
  $pluginManage = \Drupal::entityTypeManager();
  $entityReferenceSelection = Drupal::service('plugin.manager.entity_reference_selection');
  $variables["view_id"] = $view->storage->id();
  $variables["display_id"] = $view->current_display;

  $fieldDefinitions = $entity = NULL;
  if (!empty($variables['rows'])) {
    $firstElement = current($variables['rows']);
    if (!empty($firstElement->_entity)) {
      $entity = $firstElement->_entity;
      $fieldDefinitions = $entity->getFieldDefinitions();
    }
  }

  // Get list creator resource.
  $listSource = [];
  $list_source = [];
  // Get list priority resource.
  if (!empty($setting['priority'])) {
    $field_type_priority = $fieldDefinitions[$setting['priority']]->getType();
    if ($field_type_priority == 'list_string') {
      $listSource[$setting['priority']][$setting['priority']] = $fieldDefinitions[$setting['priority']]->getSettings()['allowed_values'];
    }
    else {
      $entityDraft = $pluginManage->getStorage($entity->getEntityTypeId())
        ->create(['type' => $entity->bundle()]);
      $fieldDefinitions = $entityDraft->getFieldDefinitions();

      $listSource[$setting['priority']] = $entityReferenceSelection
        ->getSelectionHandler($fieldDefinitions[$setting['priority']], $entityDraft)
        ->getReferenceableEntities();
    }
  }

  // Get list creator resource.
  if (!empty($setting['creator'])) {
    $entityDraft = $pluginManage->getStorage($entity->getEntityTypeId())
      ->create(['type' => $entity->bundle()]);
    $fieldDefinitions = $entityDraft->getFieldDefinitions();

    $listSource[$setting['creator']] = $entityReferenceSelection
      ->getSelectionHandler($fieldDefinitions[$setting['creator']], $entityDraft)
      ->getReferenceableEntities();
  }

  // Get list custom resource.
  if (!empty($setting['custom_resource'])) {
    $entityDraft = $pluginManage->getStorage($entity->getEntityTypeId())
      ->create(['type' => $entity->bundle()]);
    $fieldDefinitions = $entityDraft->getFieldDefinitions();
    foreach ($setting['custom_resource'] as $field) {
      $listSource[$field] = $entityReferenceSelection
        ->getSelectionHandler($fieldDefinitions[$field], $entityDraft)
        ->getReferenceableEntities();
    }
  }

  // Merge resource.
  if (!empty($listSource)) {
    foreach ($listSource as $field => $bundleLinks) {
      $list_source[$field] = [
        'label' => $fieldDefinitions[$field]->getLabel(),
        'data' => [],
      ];
      if (!empty($bundleLinks)) {
        foreach ($bundleLinks as $bundle => $data) {
          if (!empty($listSource[$field][$bundle])) {
            foreach ($listSource[$field][$bundle] as $id => $name) {
              if ($id == 0) {
                continue;
              }
              $list_source[$field]['data'][] = [
                'key' => $id,
                'label' => strip_tags($name),
              ];
            }
          }
        }
      }
    }
  }

  // Get config field.
  $validateField = [];
  if (!empty($fieldDefinitions[$setting['text']])) {
    $validateField['text'] = gantt_get_validate_field($fieldDefinitions[$setting['text']], $entity);
  }

  if (!empty($fieldDefinitions[$setting['custom_field']])) {
    $validateField['custom_field'] = gantt_get_validate_field($fieldDefinitions[$setting['custom_field']], $entity);
  }

  if (!empty($fieldDefinitions[$setting['priority']])) {
    $validateField['priority'] = gantt_get_validate_field($fieldDefinitions[$setting['priority']], $entity);
  }

  if (!empty($fieldDefinitions[$setting['constraint']])) {
    $validateField['constraint'] = gantt_get_validate_field($fieldDefinitions[$setting['constraint']], $entity);
  }

  if (!empty($fieldDefinitions[$setting['planned_date']])) {
    $validateField['time_planned'] = gantt_get_validate_field($fieldDefinitions[$setting['planned_date']], $entity);
  }

  if (!empty($fieldDefinitions[$setting['start_date']])) {
    $validateField['time'] = gantt_get_validate_field($fieldDefinitions[$setting['start_date']], $entity);
  }

  if (!empty($setting['custom_resource'])) {
    foreach ($setting['custom_resource'] as $resource) {
      if (!empty($fieldDefinitions[$resource])) {
        $validateField['custom_resource'][$resource] = gantt_get_validate_field($fieldDefinitions[$resource], $entity);
      }
    }
  }

  // Get type date field.
  $default = ['date_type' => '', 'field_type' => ''];
  $type_date = [
    'start_date_actually' => $default,
    'end_date_actually' => $default,
    'start_date_planned' => $default,
    'end_date_planned' => $default,
  ];

  $format_date_actually = $format_date_planned = 'date';
  $getFieldTypeAndDateType = function ($field_name, $fieldTypeKey) use (&$type_date, &$format_date_actually, &$format_date_planned, $fieldDefinitions) {
    if (!empty($field_name) && !empty($fieldDefinitions[$field_name])) {
      $dateSettings = $fieldDefinitions[$field_name]->getSettings();
      $dateType = $dateSettings["datetime_type"];
      $fieldType = $fieldDefinitions[$field_name]->getType();
      if ($dateType == 'datetime') {
        if ($fieldTypeKey === 'start_date_actually' || $fieldTypeKey === 'end_date_actually') {
          $format_date_actually = $dateType;
        }
        else {
          $format_date_planned = $dateType;
        }
      }
      $type_date[$fieldTypeKey]['date_type'] = $dateType;
      $type_date[$fieldTypeKey]['field_type'] = $fieldType;
    }
  };

  $getFieldTypeAndDateType($setting['start_date'], 'start_date_actually');
  $getFieldTypeAndDateType($setting['end_date'], 'end_date_actually');
  $getFieldTypeAndDateType($setting['planned_date'], 'start_date_planned');
  $getFieldTypeAndDateType($setting['planned_end_date'], 'end_date_planned');

  // Custom field.
  if (!empty($setting['custom_field']) && !empty($fieldDefinitions[$setting['custom_field']])) {
    $setting['custom_field_name'] = $fieldDefinitions[$setting['custom_field']]->getLabel();
  }

  // Convert holiday to format Y-m-d.
  if (!empty($setting['holidays'])) {
    $current_year = date("Y");
    $new_date_array = [];
    foreach (explode(',', str_replace(' ', '', $setting['holidays'])) as $holiday) {
      $extract = explode('-', $holiday);
      $day = $extract[0];
      $month = $extract[1];
      $year = $extract[2] ?? $current_year;
      $new_date_array[] = date('Y-m-d', strtotime("$year-$month-$day"));
    }
    $setting['holidays'] = $new_date_array;
  }

  // Get patch css.
  $module_path = \Drupal::moduleHandler()->getModule('gantt')
    ->getPath();
  $arrSource = [
    $module_path,
    'css',
    'reset.css',
  ];
  $link_css = implode(DIRECTORY_SEPARATOR, $arrSource);
  $link_css = \Drupal::service('file_url_generator')
    ->generateAbsoluteString($link_css);

  $id = implode('-', ["gantt", $view->id(), $view->current_display]);
  $variables["id_gantt"] = $id;
  $currentPath = \Drupal::service('redirect.destination')->get();
  $destination = [
    'query' => ['destination' => $currentPath],
  ];

  // Get option control bar.
  $control_bar = $setting["control_bar"] ?? [];
  foreach ($control_bar as &$valueBar) {
    $valueBar = !empty($valueBar);
  }

  // Gen ical link.
  $contextual_filter_definitions = $view->argument;
  $context = [];
  if (!empty($contextual_filter_definitions)) {
    foreach ($contextual_filter_definitions as $key => $value_arg) {
      $context[$key] = $value_arg->argument;
    }
  }
  if (!empty($setting['calendar_ical'])) {
    $calendar_ical = [
      '#type' => 'inline_template',
      '#template' => $setting['calendar_ical'],
      '#context' => $context,
    ];

    $calendar_ical = \Drupal::service('renderer')->render($calendar_ical);
  }

  $variables['view']->element['#attached']['drupalSettings']['gantt'][$id] = [
    'id' => $id,
    'control_bar' => $control_bar,
    'control_bar_label' => [
      'round_dnd_dates' => t('Allows task start and end dates to be rounded to the smallest unit'),
      'show_column_wbs' => t('Show column WBS'),
      'lock_completed_task' => t('Limit editing of completed tasks'),
      'dynamic_progress' => t('Dynamic progress summary'),
      'progress_text' => t('Text progress'),
      'auto_type' => t('Auto type - Pro version'),
      'auto_schedule' => t('Auto schedule - Pro version'),
      'click_drag' => t('Enables advanced drag-n-drop - Pro version'),
      'critical_path' => t('Shows the critical path in the chart - Pro version'),
      'drag_project' => t('Drag and drop of line - Pro version'),
      'hide_weekend_scale' => t('Hide weekend scale - Pro version'),
      'highlight_drag_task' => t('Highlights drag task - Pro version'),
      'show_slack' => t('Show slack - Pro version'),
    ],
    'server_list_resource' => $list_source,
    'date_format' => $date_format,
    'setting_resource' => [
      'resource' => array_values($setting['custom_resource']) ?? [],
      'resource_column' => array_values($setting['show_column_resource']) ?? [],
      'resource_lightbox' => array_values($setting['show_lightbox_resource']) ?? [],
      'resource_has_edit' => array_values($setting['resource_has_edit']) ?? [],
      'resource_group' => array_values($setting['group_resource']) ?? [],
    ],
    'ajax' => Url::fromRoute('gantt.ajax', [
      'view_id' => $variables["view_id"],
      'display_id' => $variables["display_id"],
    ])->toString(),
    'import' => Url::fromRoute('gantt.import', [
      'view_id' => $variables["view_id"],
      'display_id' => $variables["display_id"],
    ])->toString(),
    'show_end' => !empty($setting['show_end']),
    'show_progress' => !empty($setting['show_progress']),
    'custom_field' => $setting['custom_field'] ?? NULL,
    'custom_field_name' => $setting['custom_field_name'] ?? NULL,
    'current_path' => 'destination=' . $currentPath,
    'add_task' => !empty($setting["add_task"]),
    'edit_task' => !empty($setting["edit_task"]),
    'native_dialog' => !empty($setting["native_dialog"]),
    'add_link' => '',
    'edit_link' => '',
    'work_time' => !empty($setting['work_time']),
    'work_day' => $setting['work_day'] ?? [],
    'planned_date' => !empty($setting['planned_date']),
    'has_permission' => \Drupal::currentUser()
      ->hasPermission('administer views gantt'),
    'show_button_detail' => !empty($setting['show_button_detail']),
    'permission_edit' => !empty($setting['permission_edit']),
    'creator' => $setting['creator'] ?? NULL,
    'holidays' => $setting['holidays'] ?? [],
    'use_cdn' => !empty($setting['cdn']),
    'group_field' => $setting['group_field'] ?? NULL,
    'last_of_the_day' => !empty($setting['last_of_the_day']),
    'select_parent' => !empty($setting['select_parent']),
    'order' => $setting['order'] ?? NULL,
    'constraint' => $setting['constraint'] ?? NULL,
    'priority' => $setting['priority'] ?? NULL,
    'hide_show_column' => !empty($setting['hide_show_column']),
    'validate_field' => $validateField,
    'format_date_actually' => $format_date_actually,
    'format_date_planned' => $format_date_planned,
    'type_date' => $type_date,
    'link_css' => $link_css,
    'hide_add_task_level' => !empty($setting['hide_add_task_level']),
    'hide_add_task_level_value' => $setting['hide_add_task_level_value'] ?? 0,
    'column_buttons' => !empty($setting['column_buttons']),
    'time_input_mode' => $setting['time_input_mode'] ?? NULL,
    'auto_schedule_planned' => !empty($setting['auto_schedule_planned']),
    'calendar_ical' => $calendar_ical ?? '',
  ];
  $variables['resource'] = !empty($list_source) ? $list_source : [];
  $variables["add_link"] = '';
  if (!empty($variables['rows'])) {
    $entityFirst = current($variables['rows'])->_entity;
    $setting['entity_type'] = $entityFirst ? $entityFirst->getEntityTypeId() : NULL;
    if ($setting["native_dialog"]) {
      $addLinkText = Markup::create('<i class="bi bi-plus-lg"></i> ' . t('Add'));

      if (!empty($entityFirst)) {
        $entity_type = $entityFirst->getEntityTypeId();
        $entity_bundle = $entityFirst->bundle();
        if ($entity_type == 'node') {
          $uriAdd = Url::fromRoute($setting['view_type_entity'] . '.add', [
            'node_type' => $entity_bundle,
          ], $destination);
        }
        if ($entity_type == 'paragraph') {
          $uriAdd = Url::fromRoute('gantt.add', [
            'paragraph_type' => $entity_bundle,
            'entity_type' => $entityFirst->get('parent_type')->value,
            'entity_field' => $entityFirst->get('parent_field_name')->value,
            'entity_id' => $entityFirst->get('parent_id')->value,
          ], $destination);
        }
      }

      if ($setting["add_task"]) {
        if (!empty($uriAdd)) {
          $variables["add_link"] = Link::fromTextAndUrl($addLinkText, $uriAdd)
            ->toString();
          $variables['view']->element['#attached']['drupalSettings']['gantt'][$id]["add_link"] = $uriAdd->toString();
        }
      }
    }
  }

  $data = convert_gantt_data($variables['rows'], $setting, $type_date, $destination, $view);
  $variables['view']->element['#attached']['drupalSettings']['gantt'][$id]['required_parent'] = $data['required_parent'] ?? TRUE;
  $variables['view']->element['#attached']['drupalSettings']['gantt'][$id]['data'] = $data;
  $variables["data"]["data"] = $data['data'];
  if (!empty($data['links'])) {
    $variables["data"]['links'] = $data['links'];
  }
  if (!empty($data['marker'])) {
    $variables["marker"] = $data['marker'];
  }
  $lib = $variables['view']->element['#attached']['library'];
  $lib[] = 'gantt/gantt-main' . (!empty($setting['cdn']) ? '.cdn' : '');
  if (!empty($setting['custom_resource']) || !empty($setting['creator'])) {
    $lib[] = 'gantt/gantt.chosen';
  }
  $base = !empty($setting["gantt_theme"]) ? $setting["gantt_theme"] : 'base';
  $lib[] = "gantt/gantt-theme-$base" . (!empty($setting['cdn']) ? '.cdn' : '');
  $variables['view']->element['#attached']['library'] = $lib;

  // Set session variable for import paragraphs.
  if (!empty($view->argument)) {
    foreach ($view->argument as $key => $arg) {
      if ($key == 'parent_id') {
        $variables["paragraph_parent_id"] = $view->args[$arg->position];
        if (!empty($entityFirst)) {
          if (!empty($entityFirst->parent_type)) {
            $variables["paragraph_parent_type"] = $entityFirst->parent_type->value;
          }
          if (!empty($entityFirst->parent_field_name)) {
            $variables["paragraph_parent_field_name"] = $entityFirst->parent_field_name->value;
          }
        }
      }
    }
  }
}

/**
 * Change color backgroup foreach user.
 *
 * @param int $index
 *   Index user in array.
 *
 * @return string
 *   Code color.
 */
function gantt_generate_color(int $index) {
  $listColor = [
    "#03A9F4",
    "#f57730",
    "#e157de",
    "#78909C",
    "#8D6E63",
    "#800000",
    "#008080",
    "#d2691e",
    "#ff8c00",
    "#daa520",
    "#4b0082",
    "#FF0000",
    "#800080",
    "#008000",
    "#808000",
    "#000080",
    "#0000FF",
  ];
  if (empty($listColor[$index])) {
    return $listColor[$index % count($listColor)];
  }
  return $listColor[$index];
}

/**
 * {@inheritDoc}
 */
function convert_gantt_data($items, array $setting, $type_date, $destination, $view = FALSE) {
  $links = [];
  $outData = [];
  $mappingGantt = [
    'text',
    'type',
    'start_date',
    'duration',
    'progress',
    'open',
    'parent',
    'planned_date',
    'planned_duration',
    'priority',
    'constraint',
    'custom_field',
    'creator',
    'custom_resource',
    'order',
  ];

  $user_timezone = \Drupal::currentUser()->getTimeZone() ?? "UTC";
  $time_timezone = new \DateTimeZone($user_timezone);

  if (!empty($setting['group_field'])) {
    $bundle_info = \Drupal::service('entity_type.bundle.info');
    $entity_types = $bundle_info->getBundleInfo($setting['entity_type']);
    $groupLabel = [];
  }

  $data = [];
  $lisEntityBundle = $lisParentEntity = $lisParentTypeEntity = $lisParentFieldNameEntity = [];
  foreach ($items as $delta => $item) {
    if (empty($item->_entity)) {
      continue;
    }
    $entity = $item->_entity;
    $group = 'group_';
    if (!empty($setting['group_field'])) {
      $group = 'group_' . $entity->get($setting["group_field"])->getString();
      if (empty($groupLabel[$group])) {
        $view->row_index = $delta;
        if ($view->field[$setting['group_field']]->options['alter']['alter_text']) {
          // Handle use rewrite result.
          $rewrite_text = $view->field[$setting['group_field']]->options['alter']['text'];
          preg_match_all('/{{\s*(.*?)\s*}}/', $rewrite_text, $matches);
          if (is_array($matches[1]) && $matches[1]) {
            foreach ($matches[1] as $field_rewrite) {
              if (!empty($view->field[$field_rewrite]) && $field_rewrite != $setting["group_field"]) {
                $view->field[$field_rewrite]->advancedRender($item);
              }
            }
          }
        }

        $groupLabel[$group] = strip_tags($view->field[$setting["group_field"]]->advancedRender($item));
      }
    }

    $data[$group][$delta] = ['id' => $entity->id()];
    foreach ($mappingGantt as $ganttField) {
      $field_name = $setting[$ganttField] ?? '';
      if (!empty($setting[$ganttField]) && !is_array($setting[$ganttField]) && !empty($entity->$field_name)) {
        $value = $entity->get($field_name)->getString();
        if (!empty($value)) {
          $data[$group][$delta][$ganttField] = $value;
        }

        // Format date.
        $format = 'Y-m-d H:i';

        // Process start date.
        if ($ganttField == 'start_date' && !$entity->get($field_name)->isEmpty()) {
          $dateValueStart = new DrupalDateTime($entity->get($field_name)->value, 'UTC');
          if ($type_date['start_date_actually']['date_type'] == 'datetime') {
            $dateValueStart->setTimezone($time_timezone);
          }
          $data[$group][$delta]['start_date'] = $dateValueStart->format($format);
          if ($type_date["start_date_actually"]["field_type"] !== 'daterange' && !empty($setting['end_date'])) {
            if (!$entity->get($setting['end_date'])->isEmpty()) {
              $dateValueEnd = new DrupalDateTime($entity->get($setting['end_date'])->value, 'UTC');
              if ($type_date['end_date_actually']['date_type'] == 'datetime') {
                $dateValueEnd->setTimezone($time_timezone);
              }
            }
            else {
              $dateValueEnd = clone $dateValueStart;
            }
          }
          else {
            $dateValueEnd = new DrupalDateTime($entity->get($field_name)->end_value, 'UTC');
            if ($type_date['start_date_actually']['date_type'] == 'datetime') {
              $dateValueEnd->setTimezone($time_timezone);
            }
          }
          $data[$group][$delta]['end_date'] = $dateValueEnd->format($format);

          // Option Last of the day.
          $type = $type_date["start_date_actually"]["field_type"] == 'daterange' ? $type_date["start_date_actually"]["date_type"] : $type_date["end_date_actually"]["date_type"];
          if ($setting['last_of_the_day'] && $type == 'date') {
            $dateValueEnd->modify('+1 day');
            $data[$group][$delta]['end_date'] = $dateValueEnd->format($format);
          }
        }

        // Process planned date.
        if ($ganttField == 'planned_date' && !$entity->get($field_name)->isEmpty()) {
          if (!$entity->get($field_name)->isEmpty()) {
            $dateValueStart = new DrupalDateTime($entity->get($field_name)->value, 'UTC');
            if ($type_date['start_date_planned']['date_type'] == 'datetime') {
              $dateValueStart->setTimezone($time_timezone);
            }
            $data[$group][$delta]['planned_start'] = $dateValueStart->format($format);
            if ($type_date["start_date_planned"]["field_type"] !== 'daterange' && !empty($setting['planned_end_date'])) {
              if (!$entity->get($setting['planned_end_date'])->isEmpty()) {
                $dateValueEnd = new DrupalDateTime($entity->get($setting['planned_end_date'])->value, 'UTC');
                if ($type_date['end_date_planned']['date_type'] == 'datetime') {
                  $dateValueEnd->setTimezone($time_timezone);
                }
              }
              else {
                $dateValueEnd = clone $dateValueStart;
              }
            }
            else {
              $dateValueEnd = new DrupalDateTime($entity->get($field_name)->end_value, 'UTC');
              if ($type_date['start_date_planned']['date_type'] == 'datetime') {
                $dateValueEnd->setTimezone($time_timezone);
              }
            }
            $data[$group][$delta]['planned_end'] = $dateValueEnd->format($format);

            // Option Last of the day.
            $type = $type_date["start_date_planned"]["field_type"] == 'daterange' ? $type_date["start_date_planned"]["date_type"] : $type_date["end_date_planned"]["date_type"];
            if ($setting['last_of_the_day'] && $type == 'date') {
              $dateValueEnd->modify('+1 day');
              $data[$group][$delta]['planned_end'] = $dateValueEnd->format($format);
            }
          }
        }

        // Process duration.
        if ($ganttField == 'duration') {
          $data[$group][$delta][$ganttField] = !empty($value) ? $value : 1;
        }

        // Process parent.
        if ($ganttField == 'parent') {
          $data[$group][$delta][$ganttField] = !empty($value) ? $value : '0';
        }
        // Process order.
        if ($ganttField == 'order') {
          $data[$group][$delta][$ganttField] = !empty($value) ? (float) $value : 0;
        }
        // Process creator.
        if ($ganttField == 'creator') {
          if (method_exists($entity, 'getOwnerId')) {
            $data[$group][$delta][$ganttField] = [$entity->getOwnerId()];
          }
          else {
            $data[$group][$delta][$ganttField] = [];
            $creators = !$entity->get($field_name)
              ->isEmpty() ? $entity->get($field_name)->getValue() : [];
            if (!empty($creators)) {
              $data[$group][$delta][$ganttField] = array_column($creators, 'target_id');
            }
          }
        }
        // Process custom field.
        if ($ganttField == 'custom_field') {
          $data[$group][$delta]['custom_field'] = $value;
        }
      }

      // Process resource.
      if ($ganttField == 'custom_resource') {
        foreach ($setting[$ganttField] as $item) {
          $data[$group][$delta][$item] = [];
          if (!$entity->get($item)->isEmpty()) {
            $values = $entity->get($item)->getValue();
            foreach ($values as $value) {
              if (!empty($value->entity)) {
                $data[$group][$delta][$item][] = $value->entity->id();
              }
              elseif (is_array($value) && !empty($value['target_id'])) {
                $data[$group][$delta][$item][] = $value['target_id'];
              }
            }
          }
        }
      }

      // Link detail of task.
      if ($setting['show_button_detail']) {
        switch ($entity->getEntityTypeId()) {
          case 'node':
            if (!empty($setting['edit_task'])) {
              $data[$group][$delta]["link_detail"] = Url::fromRoute('entity.node.edit_form', ['node' => $entity->id()], $destination)
                ->toString();
              if (!empty($setting['permission_edit'])) {
                $data[$group][$delta]["link_detail"] = Url::fromRoute('entity.node.canonical', ['node' => $entity->id()], $destination)
                  ->toString();
              }
            }
            break;

          case 'paragraph':
            if (!empty($setting['edit_task'])) {
              $data[$group][$delta]["link_detail"] = Url::fromRoute('gantt.edit', ['paragraph' => $entity->id()], $destination)
                ->toString();
              if (!empty($setting['permission_edit'])) {
                $data[$group][$delta]["link_detail"] = Url::fromRoute('gantt.display', ['paragraph' => $entity->id()], $destination)
                  ->toString();
              }
            }
            break;

          case 'work_time':
            if (!empty($setting['edit_task'])) {
              $data[$group][$delta]["link_detail"] = Url::fromRoute('entity.work_time.edit_form', ['work_time' => $entity->id()], $destination)
                ->toString();
              if (!empty($setting['permission_edit'])) {
                $data[$group][$delta]["link_detail"] = Url::fromRoute('entity.work_time.canonical', ['work_time' => $entity->id()], $destination)
                  ->toString();
              }
            }
            break;

          case 'taxonomy_term':
            if (!empty($setting['edit_task'])) {
              $data[$group][$delta]["link_detail"] = Url::fromRoute('entity.taxonomy_term.edit_form', ['taxonomy_term' => $entity->id()], $destination)
                ->toString();
              if (!empty($setting['permission_edit'])) {
                $data[$group][$delta]["link_detail"] = Url::fromRoute('entity.taxonomy_term.canonical', ['taxonomy_term' => $entity->id()], $destination)
                  ->toString();
              }
            }
            break;

        }
      }

      // Process priority.
      if ($ganttField == 'priority' && !empty($field_name = $setting[$ganttField]) && !$entity->get($field_name)->isEmpty()) {
        $data[$group][$delta][$ganttField] = [];
        $values = $entity->get($setting[$ganttField])->getValue();
        foreach ($values as $value) {
          if (!empty($value->entity)) {
            $data[$group][$delta][$ganttField] = $value->entity->id();
          }
          elseif (is_array($value) && (!empty($value['target_id']) || !empty($value['value']))) {
            $data[$group][$delta][$ganttField] = $value['target_id'] ?? $value['value'];
          }
        }
      }

      // Process constraint.
      if ($ganttField == 'constraint' && !empty($field_name = $setting[$ganttField]) && !$entity->get($field_name)->isEmpty()) {
        $valueConstraint = $entity->get($setting[$ganttField])->getValue();
        if ($valueConstraint) {
          $data[$group][$delta]['constraint_type'] = $valueConstraint[0]['first'] ?? NULL;
          $data[$group][$delta]['constraint_date'] = $valueConstraint[0]['second'] ?? NULL;
        }
      }
    }

    // Resource edit.
    $creators = $data[$group][$delta]['creator'] ?? [];
    if (!empty($setting['permission_edit']) && !empty($setting['resource_has_edit']) && !in_array($view->getUser()->id(), $creators)) {
      $data[$group][$delta]['readonly'] = TRUE;
      $data[$group][$delta]["link_detail"] = Url::fromRoute('gantt.display', ['paragraph' => $entity->id()])
        ->setOptions($destination)->toString();
      if (!empty($data[$group][$delta]['readonly'])) {
        foreach ($setting['resource_has_edit'] as $field_resource) {
          $resource_target = $entity->get($field_resource)->getValue();
          $resource_target = array_column($resource_target, 'target_id');
          if (in_array($view->getUser()->id(), $resource_target)) {
            $data[$group][$delta]['readonly'] = FALSE;
            switch ($entity->getEntityTypeId()) {
              case 'node':
                $data[$group][$delta]["link_detail"] = Url::fromRoute('entity.node.edit_form', ['node' => $entity->id()], $destination)
                  ->toString();
                break;

              case 'paragraph':
                $data[$group][$delta]["link_detail"] = Url::fromRoute('gantt.edit', ['paragraph' => $entity->id()], $destination)
                  ->toString();
                break;

              case 'work_time':
                $data[$group][$delta]["link_detail"] = Url::fromRoute('entity.work_time.edit_form', ['work_time' => $entity->id()], $destination)
                  ->toString();
                break;

              case 'taxonomy_term':
                $data[$group][$delta]["link_detail"] = Url::fromRoute('entity.taxonomy_term.edit_form', ['taxonomy_term' => $entity->id()], $destination)
                  ->toString();
                break;

            }
            break;
          }
        }
      }
    }

    // Process Links.
    if (!empty($setting['links'])) {
      $valueLinks = $entity->get($setting['links'])->getValue();
      $entityTarget = [];
      if (!empty($valueLinks)) {
        $entityTarget = \Drupal::entityTypeManager()
          ->getStorage($entity->getEntityTypeId())
          ->loadMultiple(array_column($valueLinks, 'first'));
      }

      if (!empty($entityTarget)) {
        foreach (array_values($entityTarget) as $index => $entity_target) {
          $key = implode('-', [$entity->id(), $entity_target->id()]);
          $links[$key] = [
            'id' => $key,
            'source' => (int) $entity->id(),
            'target' => (int) $entity_target->id(),
            'type' => $valueLinks[$index]['second'],
            'lag' => is_numeric($valueLinks[$index]['third']) ? $valueLinks[$index]['third'] : NULL,
          ];
          // Resource edit.
          if (!empty($setting['permission_edit']) && !empty($setting['resource_has_edit'])) {
            $links[$delta]['readonly'] = !empty($data[$group][$delta]['readonly']);
          }
        }
      }
    }

    // Get type item.
    $data[$group][$delta]['entity_type'] = $entity->getEntityTypeId();

    // Get bundle item.
    $data[$group][$delta]['entity_bundle'] = $entity->bundle();

    // Get parent_id item.
    if ($entity->getEntityTypeId() === "paragraph") {
      $data[$group][$delta]['parent_id_entity'] = $entity->get('parent_id')->value;
    }

    // Get parent_field_name item.
    if ($entity->getEntityTypeId() === "paragraph") {
      $data[$group][$delta]['parent_field_name_entity'] = $entity->get('parent_field_name')->value;
    }

    // Get parent_type item.
    if ($entity->getEntityTypeId() === "paragraph") {
      $data[$group][$delta]['parent_type_entity'] = $entity->get('parent_type')->value;
    }

    // Check required parent.
    $lisEntityBundle[$entity->bundle()] = $entity->bundle();
    if ($entity->getEntityTypeId() === "paragraph") {
      $lisParentEntity[$entity->get('parent_id')->value] = $entity->get('parent_id')->value;
    }
    if ($entity->getEntityTypeId() === "paragraph") {
      $lisParentTypeEntity[$entity->get('parent_type')->value] = $entity->get('parent_type')->value;
    }
    if ($entity->getEntityTypeId() === "paragraph") {
      $lisParentFieldNameEntity[$entity->get('parent_field_name')->value] = $entity->get('parent_field_name')->value;
    }
  }

  $required_parent = count($data) > 1 || count($lisParentFieldNameEntity) > 1 || count($lisParentTypeEntity) > 1 || count($lisParentEntity) > 1 || count($lisEntityBundle) > 1 ? TRUE : FALSE;

  if (count($data) == 1) {
    $data = current($data);
  }
  else {
    $originData = $data;
    $data = [];
    foreach ($originData as $group => $tasks) {
      $first_task = reset($tasks);
      $groupName = $group;
      if (!empty($groupLabel[$group])) {
        $groupName = $groupLabel[$group];
      }
      elseif (!empty($entity_types[$group])) {
        $groupName = $entity_types[$group]['label'];
      }
      $data[] = [
        'id' => $group,
        'text' => $groupName,
        'type' => 'project',
        'open' => TRUE,
        'readonly' => TRUE,
        'parent' => 0,
        'group_field' => $setting['group_field'],
        'entity_type' => $first_task['entity_type'],
        'entity_bundle' => $first_task['entity_bundle'],
        'parent_id_entity' => !empty($first_task['parent_id_entity']) ? $first_task['parent_id_entity'] : "",
        'parent_type_entity' => !empty($first_task['parent_type_entity']) ? $first_task['parent_type_entity'] : "",
        'parent_field_name_entity' => !empty($first_task['parent_field_name_entity']) ? $first_task['parent_field_name_entity'] : "",
      ];
      foreach ($tasks as $delta => $task) {
        if (empty($task['parent'])) {
          $task['parent'] = $group;
        }
        $data[] = $task;
      }
    }
  }
  if (!empty($data)) {
    $outData['required_parent'] = $required_parent;
    $outData['data'] = array_values($data);
    if (!empty($links)) {
      $outData['links'] = array_values($links);
    }
  }
  return $outData;
}

/**
 * Get connfig field.
 *
 * {@inheritDoc}
 */
function gantt_get_validate_field($field_definitions, $entity) {
  $type = $field_definitions->getType();
  $storageField = $field_definitions->getFieldStorageDefinition();
  $defaultValue = $field_definitions->getDefaultValue($entity);
  $validateField = [
    'label' => $field_definitions->getLabel(),
    'cardinality' => $storageField->getCardinality(),
    'required' => $field_definitions->isRequired(),
    'default_value' => NULL,
  ];

  if (in_array($type, ['integer', 'float', 'decimal'])) {
    $validateField['min'] = $storageField->getSettings()["min"] ?? NULL;
    $validateField['max'] = $storageField->getSettings()["max"] ?? NULL;
  }

  if (!empty($defaultValue) && in_array($type, ['entity_reference'])) {
    $validateField['default_value'] = array_column($defaultValue, 'target_id') ?? NULL;
  }

  if (!empty($defaultValue) && in_array($type, ['double_field', 'triples_field'])) {
    $validateField['default_value']['first'] = $defaultValue[0]['first'] ?? NULL;
    $validateField['default_value']['second'] = $defaultValue[0]['second'] ?? NULL;
    $validateField['default_value']['third'] = $defaultValue[0]['third'] ?? NULL;
  }

  if (!empty($defaultValue) && in_array($type, ['datetime', 'daterange'])) {
    $user_timezone = \Drupal::currentUser()->getTimeZone() ?? "UTC";
    $time_timezone = new \DateTimeZone($user_timezone);
    $date = $type == 'daterange' ? $defaultValue[0]["start_date"] : $defaultValue[0]["date"];
    if (!empty($date)) {
      $date->setTimezone($time_timezone);
      $validateField['default_value']['start'] = $date->format('Y-m-d\TH:i:s') ?? NULL;
      $date->modify('+1 minute');
      $validateField['default_value']['end'] = $date->format('Y-m-d\TH:i:s') ?? NULL;
    }
  }
  if (!empty($defaultValue) && empty($validateField['default_value'])) {
    $validateField['default_value'] = $defaultValue[0]['value'];
  }
  return $validateField;
}
