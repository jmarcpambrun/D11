<?php

namespace Drupal\burndown\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;

/**
 * Field formatter "burndown_log_default".
 *
 * @FieldFormatter(
 *   id = "burndown_log_default",
 *   label = @Translation("Burndown Log default"),
 *   field_types = {
 *     "burndown_log",
 *   }
 * )
 */
class BurndownLogDefaultFormatter extends FormatterBase {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a StringFormatter instance.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entityTypeManager.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, DateFormatterInterface $date_formatter, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->dateFormatter = $date_formatter;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('date.formatter'),
      $container->get('entity_type.manager'),
    );
  }

  /*
   * @todo: Once issue is resolved in https://www.drupal.org/node/2053415
   * then implement dependency injection here.
   */

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $logs_by_type = [
      'all' => [],
      'comment' => [],
      'changed' => [],
      'work' => [],
    ];

    foreach ($items as $item) {
      $user = $this->entityTypeManager->getStorage('user')->load($item->uid);
      $type = $this->normalizeLogValue($item->type);

      $entry = [
        'type' => $type,
        'created' => $this->dateFormatter->format((int) $item->created),
        'user' => $user ? $user->getDisplayName() : '',
        'comment' => $this->normalizeLogValue($item->comment),
        'work_done' => $this->normalizeLogValue($item->work_done),
        'description' => $this->normalizeLogValue($item->description),
      ];

      $logs_by_type['all'][] = $entry;
      if (isset($logs_by_type[$type])) {
        $logs_by_type[$type][] = $entry;
      }
    }

    $output = [];
    $output[0] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['burndown-log-tabs'],
      ],
      '#attached' => [
        'library' => ['burndown/drupal.burndown.task_log_tabs'],
      ],
      'tabs' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['log_tabs']],
        'comment' => [
          '#type' => 'link',
          '#title' => $this->t('Comments'),
          '#url' => Url::fromUserInput('#'),
          '#attributes' => [
            'class' => ['comment', 'is-active'],
            'data-log-tab' => 'comment',
          ],
        ],
        'changed' => [
          '#type' => 'link',
          '#title' => $this->t('Changes'),
          '#url' => Url::fromUserInput('#'),
          '#attributes' => [
            'class' => ['changed'],
            'data-log-tab' => 'changed',
          ],
        ],
        'work' => [
          '#type' => 'link',
          '#title' => $this->t('Work Logs'),
          '#url' => Url::fromUserInput('#'),
          '#attributes' => [
            'class' => ['work'],
            'data-log-tab' => 'work',
          ],
        ],
        'all' => [
          '#type' => 'link',
          '#title' => $this->t('All'),
          '#url' => Url::fromUserInput('#'),
          '#attributes' => [
            'class' => ['all'],
            'data-log-tab' => 'all',
          ],
        ],
      ],
      'panel_all' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['burndown-task-log-panel', 'is-hidden'],
          'data-log-panel' => 'all',
        ],
        'content' => [
          '#theme' => 'burndown_log_items',
          '#data' => $logs_by_type['all'],
        ],
      ],
      'panel_comment' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['burndown-task-log-panel', 'is-active'],
          'data-log-panel' => 'comment',
        ],
        'content' => [
          '#theme' => 'burndown_log_items',
          '#data' => $logs_by_type['comment'],
        ],
      ],
      'panel_changed' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['burndown-task-log-panel', 'is-hidden'],
          'data-log-panel' => 'changed',
        ],
        'content' => [
          '#theme' => 'burndown_log_items',
          '#data' => $logs_by_type['changed'],
        ],
      ],
      'panel_work' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['burndown-task-log-panel', 'is-hidden'],
          'data-log-panel' => 'work',
        ],
        'content' => [
          '#theme' => 'burndown_log_items',
          '#data' => $logs_by_type['work'],
        ],
      ],
    ];

    return $output;
  }

  /**
   * Normalize burndown log field values to a plain string.
   *
   * The custom burndown_log field stores `comment` as serialized `any`, so
   * existing records may contain arrays (for example ['value' => '...']).
   * `#plain_text` must always receive a string scalar.
   *
   * @param mixed $value
   *   The raw field property value.
   *
   * @return string
   *   A safe string representation.
   */
  protected function normalizeLogValue($value) {
    if (is_array($value)) {
      if (isset($value['value']) && is_scalar($value['value'])) {
        return (string) $value['value'];
      }
      return '';
    }

    if (is_scalar($value)) {
      return (string) $value;
    }

    return '';
  }

}
