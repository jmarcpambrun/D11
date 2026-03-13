<?php

namespace Drupal\gantt\Plugin\QueueWorker;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Auto end date.
 *
 * @QueueWorker(
 *   id = "queue_task_gantt",
 *   title = @Translation("Queue task gantt"),
 *   cron = {"time" = 360}
 * )
 */
class QueueTaskGantt extends QueueWorkerBase implements ContainerFactoryPluginInterface {
  use StringTranslationTrait;

  /**
   * Query POST.
   *
   * @var mixed
   */
  protected $post;

  /**
   * The user takes the action.
   *
   * @var object
   */
  protected $currentUser;

  /**
   * Constructs a WorkTimekeeper instance.
   *
   * @param array $configuration
   *   The configuration for the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The plugin translation.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cacheTags
   *   The service cache tags.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected EntityTypeManagerInterface $entityTypeManager, protected TranslationInterface $string_translation, protected CacheTagsInvalidatorInterface $cacheTags) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('string_translation'),
      $container->get('cache_tags.invalidator'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $this->post = $data['data'];
    $this->currentUser = $this->entityTypeManager->getStorage('user')->load($data['current_user']);
    $this->actionEntityTask($data['action'], $data['entity_type'], $data['field_settings']);
  }

  /**
   * Process Entity Task.
   *
   * {@inheritDoc}
   */
  protected function actionEntityTask($action, $entityType, $field_settings) {
    if (empty($field_settings['format_date'])) {
      $field_settings['format_date'] = "Y-m-d\TH:i:s";
    }
    $output = ['action' => $action];
    $parent_type = $this->post['parent_type_entity'] ?? NULL;
    $parent_field = $this->post['parent_field_name_entity'] ?? NULL;
    $parent_entity = NULL;
    $entity = !empty($this->post['id']) ? $entityType->load($this->post['id']) : NULL;

    if (!empty($entity)) {
      if ($entity->getEntityTypeId() === 'paragraph') {
        $parent_entity = $entity->getParentEntity();
      }

      if ($action == 'deleted') {
        $output['tid'] = $this->deleteParagraph($entity, $parent_field, $parent_entity);
        return $output;
      }

      $fieldDefinitions = $entity->getFieldDefinitions();
      // Timezone current user.
      $user_timezone = $this->currentUser->getTimeZone() ?? "UTC";
      $time_timezone = new \DateTimeZone($user_timezone);
      $utc_timezone = $user_timezone == 'UTC' ? $time_timezone : new \DateTimeZone('UTC');

      // Set start date.
      $type_date = $this->getTypeDateField($fieldDefinitions, $field_settings);

      $actual_date = $this->processDate('start_date', 'end_date', $type_date, $field_settings, $time_timezone, $utc_timezone);
      // Set plan date.
      $planned_date = $this->processDate('planned_start', 'planned_end', $type_date, $field_settings, $time_timezone, $utc_timezone);

      // Set actual date and plan date.
      if ($actual_date) {
        $this->setEntityDates($entity, $field_settings, $type_date, 'start_date', 'end_date', $actual_date, $fieldDefinitions);
        $this->setEntityDates($entity, $field_settings['baseline'], $type_date, 'planned_date', 'planned_end_date', $planned_date, $fieldDefinitions);
      }
      // Set duration.
      $value_duration = !empty($this->post['duration']) ? $this->post['_duration'] : NULL;
      if (!empty($field_settings["duration"]) && !empty($fieldDefinitions[$field_settings['duration']])) {
        $entity->set($field_settings['duration'], $value_duration);
      }
      // Set planned duration.
      $value_planned_duration = !empty($this->post['planned_duration']) ? $this->post['planned_duration'] : NULL;
      if (!empty($field_settings['baseline']['planned_duration']) && !empty($fieldDefinitions[$field_settings['baseline']['planned_duration']])) {
        $entity->set($field_settings['baseline']['planned_duration'], $value_planned_duration);
      }
      // Set text.
      if (!empty($field_settings['text']) && !empty($fieldDefinitions[$field_settings['text']])) {
        $entity->set($field_settings['text'], $this->post['text'] ?? 'Undefined');
      }
      // Set type.
      $value_type = !empty($this->post["type"]) ? $this->post["type"] : NULL;
      if (!empty($field_settings["type"]) && !empty($fieldDefinitions[$field_settings['type']])) {
        $entity->set($field_settings['type'], $value_type);
      }
      // Set progress.
      $value_process = !empty($this->post['progress']) ? $this->post['progress'] : NULL;
      if (!empty($field_settings['progress']) && !empty($fieldDefinitions[$field_settings['progress']])) {
        $entity->set($field_settings['progress'], $value_process);
      }
      // Set parent.
      if (!empty($field_settings['parent']) && !empty($fieldDefinitions[$field_settings['parent']])) {
        $entity->set($field_settings['parent'], $this->post['parent'] ?? NULL);
      }
      // Set open.
      if (!empty($field_settings['open']) && !empty($fieldDefinitions[$field_settings['open']])) {
        $entity->set($field_settings['open'], $this->post['open'] ?? 0);
      }
      // Set order.
      if (!empty($field_settings['order']) && !empty($fieldDefinitions[$field_settings['order']])) {
        $entity->set($field_settings['order'], $this->post['order'] ?? 0);
      }
      // Set resource.
      if (!empty($field_settings["custom_resource"])) {
        foreach ($field_settings["custom_resource"] as $field_resource) {
          if ($field_settings["creator"] == $field_resource || empty($fieldDefinitions[$field_resource])) {
            continue;
          }
          $resource = [];
          $this->post[$field_resource] = !empty($this->post[$field_resource]) ? json_decode($this->post[$field_resource]) : [];
          if (is_array($this->post[$field_resource])) {
            foreach ($this->post[$field_resource] as $resource_id) {
              if (!empty($resource_id)) {
                $resource[] = ['target_id' => $resource_id];
              }
            }
          }
          else {
            $resource = [['target_id' => $this->post[$field_resource]]];
          }
          $entity->set($field_resource, $resource);
        }
      }
      // Set custom field.
      $value_custom_field = !empty($this->post["custom_field"]) ? $this->post["custom_field"] : NULL;
      if (!empty($field_settings["custom_field"]) && !empty($fieldDefinitions[$field_settings['custom_field']])) {
        $entity->set($field_settings['custom_field'], $value_custom_field);
      }
      // Set creator.
      if (!empty($field_settings["creator"]) && $action == 'inserted' && !empty($fieldDefinitions[$field_settings['creator']])) {
        $entity->set($field_settings["creator"], $this->currentUser->id());
      }
      // Set priority.
      $value_priority = !empty($this->post['priority']) ? $this->post['priority'] : NULL;
      if (!empty($field_settings["priority"]) && !empty($fieldDefinitions[$field_settings['priority']])) {
        $entity->set($field_settings["priority"], $value_priority);
      }
      // Set constraint.
      if (!empty($field_settings['baseline']["constraint"]) && !empty($this->post['constraint_type']) && !empty($fieldDefinitions[$field_settings['baseline']['constraint']])) {
        $entity->set($field_settings['baseline']["constraint"], [
          'first' => $this->post['constraint_type'] ?? NULL,
          'second' => $this->post['constraint_date'] ?? NULL,
        ]);
      }

      // Update schedule planned.
      if (!empty($this->post['listSchedulePlanned'])) {
        $this->updateSchedulePlanned($entityType, $field_settings, $type_date, $time_timezone, $utc_timezone);
      }

      $entity->save();
      $output['tid'] = $entity->id();
      // Reset cache.
      if (!empty($parent_entity)) {
        $this->entityTypeManager->getStorage($parent_type)->resetCache([$parent_entity->id()]);
      }
    }
    return $output;
  }

  /**
   * {@inheritDoc}
   */
  public function getTypeDateField($fieldDefinitions, $setting) {
    $setting['planned_date'] = $setting['baseline']['planned_date'];
    $setting['planned_end_date'] = $setting['baseline']['planned_end_date'];
    // Get type date field.
    $type_date = [
      'start_date_actually' => ['date_type' => '', 'field_type' => ''],
      'end_date_actually' => ['date_type' => '', 'field_type' => ''],
      'start_date_planned' => ['date_type' => '', 'field_type' => ''],
      'end_date_planned' => ['date_type' => '', 'field_type' => ''],
    ];
    $populateDateType = function ($settingKey, $typeKey) use (&$type_date, $fieldDefinitions, $setting) {
      if (!empty($setting[$settingKey]) && !empty($fieldDefinitions[$setting[$settingKey]])) {
        $dateSettings = $fieldDefinitions[$setting[$settingKey]]->getSettings();
        $type_date[$typeKey]['date_type'] = $dateSettings["datetime_type"];
        $type_date[$typeKey]['field_type'] = $fieldDefinitions[$setting[$settingKey]]->getType();
      }
    };
    $populateDateType('start_date', 'start_date_actually');
    $populateDateType('end_date', 'end_date_actually');
    $populateDateType('planned_date', 'start_date_planned');
    $populateDateType('planned_end_date', 'end_date_planned');

    return $type_date;
  }

  /**
   * Process Dates.
   *
   * {@inheritDoc}
   */
  private function processDate($start_key, $end_key, $type_date, $field_settings, $time_timezone, $utc_timezone, $start = NULL, $end = NULL) {
    $type_key_start = 'start_date_planned';
    $type_key_end = 'end_date_planned';
    if ($start_key == 'start_date') {
      $type_key_start = 'start_date_actually';
      $type_key_end = 'end_date_actually';
    }
    $dateFormatStart = $dateFormatEnd = 'Y-m-d\TH:i:s';
    if ($type_date[$type_key_start]['field_type'] == 'daterange' && $type_date[$type_key_start]['date_type'] == 'date') {
      $dateFormatStart = $dateFormatEnd = 'Y-m-d';
    }
    elseif ($type_date[$type_key_end]['date_type'] == 'date') {
      $dateFormatEnd = 'Y-m-d';
    }

    if (empty($start)) {
      $start = $this->post[$start_key] ?? NULL;
    }
    if (empty($end)) {
      $end = $this->post[$end_key] ?? NULL;
    }

    if (!empty($start) && $start != 'undefined') {
      $start_date = new DrupalDateTime($start, $time_timezone);
      $end_date = new DrupalDateTime($end, $time_timezone);

      if ($type_date[$type_key_start]['field_type'] == 'daterange' && $type_date[$type_key_start]['date_type'] == 'datetime') {
        $start_date->setTimezone($utc_timezone);
        $end_date->setTimezone($utc_timezone);
      }
      elseif ($type_date[$type_key_start]['date_type'] == 'datetime') {
        $start_date->setTimezone($utc_timezone);
        if (!empty($type_date[$type_key_end]['date_type']) && $type_date[$type_key_end]['date_type'] == 'datetime') {
          $end_date->setTimezone($utc_timezone);
        }
      }

      $type = $type_date[$type_key_start]["field_type"] == 'daterange' ? $type_date[$type_key_start]["date_type"] : $type_date[$type_key_end]["date_type"];
      if ($field_settings['last_of_the_day'] && $type == 'date') {
        $end_date->modify("-1 day");
      }

      return [
        'value' => $start_date->format($dateFormatStart),
        'end_value' => $end_date->format($dateFormatEnd),
      ];
    }
    return [
      'value' => NULL,
      'end_value' => NULL,
    ];
  }

  /**
   * Update schedule planned.
   *
   * {@inheritDoc}
   */
  protected function updateSchedulePlanned($entityType, $field_settings, $type_date, $time_timezone, $utc_timezone) {
    $listUpdate = Json::decode($this->post['listSchedulePlanned']);
    if (!empty($listUpdate)) {
      foreach ($listUpdate as $item_planned) {
        $entity_update = $entityType->load($item_planned['id']);
        if ($entity_update) {
          $planned_date = $this->processDate('planned_start', 'planned_end', $type_date, $field_settings, $time_timezone, $utc_timezone, $item_planned['planned_start'], $item_planned['planned_end']);
          $this->setEntityDates($entity_update, $field_settings['baseline'], $type_date, 'planned_date', 'planned_end_date', $planned_date);
          $entity_update->save();
        }
      }
    }
  }

  /**
   * Set Entity dates.
   *
   * {@inheritDoc}
   */
  private function setEntityDates($entity, $field_settings, $type_date, $start_key, $end_key, $date_values, $fieldDefinitions = NULL) {
    if (empty($fieldDefinitions)) {
      $fieldDefinitions = $entity->getFieldDefinitions();
    }
    if ($type_date['start_date_actually']['field_type'] == 'daterange') {
      if (!empty($fieldDefinitions[$field_settings[$start_key]])) {
        $entity->set($field_settings[$start_key], $date_values);
      }
    }
    else {
      if (!empty($field_settings[$start_key]) && !empty($fieldDefinitions[$field_settings[$start_key]])) {
        $entity->set($field_settings[$start_key], $date_values['value']);
      }
      if (!empty($field_settings[$end_key]) && !empty($fieldDefinitions[$field_settings[$end_key]])) {
        $entity->set($field_settings[$end_key], $date_values['end_value']);
      }
    }
  }

  /**
   * Delete paragraphs.
   *
   * {@inheritDoc}
   */
  protected function deleteParagraph($entity, $parent_field, $parent_entity) {
    $entity_id = $entity->id();
    if (!empty($parent_entity) && !empty($parent_field)) {
      $getField = $parent_entity->get($parent_field);
      $array_of_referenced_items = $getField->getValue();
      $index_to_remove = array_search($entity_id, array_column($array_of_referenced_items, 'target_id'));
      $getField->removeItem($index_to_remove);
      $parent_entity->save();
    }
    $entity->delete();
    return $entity_id;
  }

}
