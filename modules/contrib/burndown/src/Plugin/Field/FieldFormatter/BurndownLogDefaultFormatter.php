<?php

namespace Drupal\burndown\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

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

    $output = [];

    foreach ($items as $delta => $item) {

      $build = [];

      $build['type'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['type'],
        ],
        'label' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['field__label'],
          ],
          '#markup' => $this->t('Log Type'),
        ],
        'value' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['field__item'],
          ],
          '#plain_text' => $item->type,
        ],
      ];

      $build['created'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['burndown_log__created'],
        ],
        'label' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['field__label'],
          ],
          '#markup' => $this->t('Date'),
        ],
        'value' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['field__item'],
          ],
          '#plain_text' => $this->dateFormatter->format($item->created),
        ],
      ];

      $user = $this->entityTypeManager->getStorage('user')->load($item->uid);

      $build['uid'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['burndown_log__created'],
        ],
        'label' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['field__label'],
          ],
          '#markup' => $this->t('User'),
        ],
        'value' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['field__item'],
          ],
          '#plain_text' => isset($user) ? $user->getDisplayName() : '',
        ],
      ];

      $build['comment'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['burndown_log__comment'],
        ],
        'label' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['field__label'],
          ],
          '#markup' => $this->t('Comment'),
        ],
        'value' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['field__item'],
          ],
          '#plain_text' => $item->comment,
        ],
      ];

      $build['work_done'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['burndown_log__work_down'],
        ],
        'label' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['field__label'],
          ],
          '#markup' => $this->t('Work Done'),
        ],
        'value' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['field__item'],
          ],
          '#plain_text' => $item->work_done,
        ],
      ];

      $build['description'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['burndown_log__description'],
        ],
        'label' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['field__label'],
          ],
          '#markup' => $this->t('Description'),
        ],
        'value' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['field__item'],
          ],
          '#plain_text' => $item->description,
        ],
      ];

      $output[$delta] = $build;
    }

    return $output;
  }

}
