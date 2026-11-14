<?php

declare(strict_types=1);

namespace Drupal\ai_eca_interceptor\Plugin\ECA\Condition;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaCondition;

/**
 * Condition: AI event has tag.
 */
#[EcaCondition(
  id: 'ai_eca_interceptor_has_tag',
  label: new TranslatableMarkup('AI event: has tag'),
  description: new TranslatableMarkup('Checks whether the AI event has a specific tag.'),
  version_introduced: '2.0.0',
)]
class HasTagCondition extends AiEventConditionBase {

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    $event = $this->aiEvent();
    if (!$event) {
      return $this->negationCheck(FALSE);
    }
    $needle = trim((string) $this->tokenService->replace($this->configuration['tag']));
    $tags = $event->getTags();
    $has = in_array($needle, $tags, TRUE) || array_key_exists($needle, $tags);
    return $this->negationCheck($has);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['tag' => ''] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['tag'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tag'),
      '#default_value' => $this->configuration['tag'],
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['tag'] = $form_state->getValue('tag');
    parent::submitConfigurationForm($form, $form_state);
  }

}
