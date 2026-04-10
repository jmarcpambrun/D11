<?php

namespace Drupal\drd\Entity\Form;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\drd\Entity\CoreInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for reviewing Core update logs.
 *
 * @ingroup drd
 */
class CoreUpdateLog extends FormBase {

  /**
   * DRD Core entity for which we handle update logs.
   *
   * @var \Drupal\drd\Entity\CoreInterface
   */
  protected CoreInterface $core;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * CoreUpdateLog constructor.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(DateFormatterInterface $date_formatter) {
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): CoreUpdateLog {
    return new CoreUpdateLog(
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'drd_core_updatelog';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?CoreInterface $drd_core = NULL, int $timestamp = 0): array {
    $this->core = $drd_core;
    $logList = $this->core->getUpdateLogList();
    if (empty($logList)) {
      $form['info'] = [
        '#markup' => $this->t('No update logs available'),
      ];
      return $form;
    }

    $current = FALSE;
    $item = FALSE;
    $select = [];
    foreach ($logList as $item) {
      $select[$item['timestamp']] = $this->dateFormatter->format($item['timestamp']);
      if ($item['timestamp'] === $timestamp) {
        $current = $item;
      }
    }
    if (empty($current)) {
      // Default to latest item.
      $current = $item;
    }

    $form['select'] = [
      '#type' => 'select',
      '#options' => $select,
      '#default_value' => $current['timestamp'],
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Select'),
    ];
    $form['log'] = [
      '#type' => 'textarea',
      '#default_value' => $this->core->getUpdateLog($current['timestamp']),
      '#rows' => 30,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $form_state->setRedirect('entity.drd_core.updatelog', [
      'drd_core' => $this->core->id(),
      'timestamp' => $form_state->getValue('select'),
    ]);
  }

}
