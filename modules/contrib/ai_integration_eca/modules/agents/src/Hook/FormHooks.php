<?php

namespace Drupal\ai_integration_eca_agents\Hook;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\eca\Entity\Eca;

/**
 * Provides hook implementations for form alterations.
 */
class FormHooks {

  use StringTranslationTrait;

  /**
   * Constructs a FormHooks instance.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match.
   */
  public function __construct(
    protected RouteMatchInterface $routeMatch,
  ) {
  }

  /**
   * Alters the ECA Modeller form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('form_bpmn_io_modeller_alter')]
  public function formModellerAlter(array &$form, FormStateInterface $formState): void {
    // Take the options from the export-link.
    /** @var \Drupal\Core\Url $exportLink */
    $exportLink = $form['actions']['export_archive']['#url'];
    $options = $exportLink->getOptions();

    $eca = $this->routeMatch->getParameter('eca');
    if ($eca instanceof Eca) {
      $eca = $eca->id();
    }
    $options['query']['model-id'] = $eca;

    $form['actions']['ask_ai'] = [
      '#type' => 'link',
      '#title' => $this->t('Ask AI'),
      '#url' => Url::fromRoute('ai_integration_eca_agents.ask_ai', [], $options),
      '#attributes' => [
        'class' => ['button', 'ai-eca-ask-ai', 'use-ajax'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode([
          'width' => 1000,
          'dialogClass' => 'ui-dialog-off-canvas',
        ]),
      ],
      '#attached' => [
        'library' => [
          'core/drupal.dialog.ajax',
        ],
      ],
    ];
  }

}
