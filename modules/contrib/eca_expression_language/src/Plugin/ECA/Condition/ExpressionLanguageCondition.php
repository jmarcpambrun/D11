<?php

namespace Drupal\eca_expression_language\Plugin\ECA\Condition;

use Symfony\Component\ExpressionLanguage\SyntaxError;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaCondition;
use Drupal\eca\Plugin\ECA\Condition\ConditionBase;
use Drupal\eca\Plugin\ECA\PluginFormTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * ECA condition plugin for expression language.
 */
#[EcaCondition(
  id: 'eca_el',
  label: new TranslatableMarkup('Expression language condition'),
  description: new TranslatableMarkup('Evaluates an expression language expression.'),
  version_introduced: '1.0.0',
)]
class ExpressionLanguageCondition extends ConditionBase {
  use PluginFormTrait;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * This flag indicates whether Token replacement should be applied beforehand.
   *
   * @var bool
   */
  protected static bool $replaceTokens = TRUE;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container,
        $configuration,
        $plugin_id,
        $plugin_definition);
    $instance->loggerFactory = $container->get('logger.factory');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'el' => '',
      'error_fallback' => 'no',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['el'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Expression'),
      '#default_value' => $this->getEl(),
      '#weight' => -90,
      '#description' => $this->t('The expression to be evaluated.'),
    ];
    $form['error_fallback'] = [
      '#type' => 'select',
      '#title' => $this->t('Fallback value'),
      '#options' => [
        'yes' => $this->t('True'),
        'no' => $this->t('False'),
      ],
      '#default_value' => $this->getFallbackValue(),
      '#weight' => -89,
      '#description' => $this->t('Condition value in case
        the expression processing fails, false otherwise.'),
      '#eca_token_select_option' => FALSE,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['el'] = $form_state->getValue('el');
    $this->configuration['error_fallback'] = $form_state->getValue('error_fallback');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritDoc}
   */
  public function evaluate(): bool {
    $processedExpression = $this->getEl();
    if (static::$replaceTokens) {
      $processedExpression = $this->tokenService->replace($this->getEl());
    }
    $expressionLanguage = new ExpressionLanguage();
    try {
      $result = $expressionLanguage->evaluate($processedExpression);
      if (!is_bool($result)) {
        $this->loggerFactory->get('default')->error(sprintf("The expression
          '%s' must return a boolean value. Returning fallback value.",
          $processedExpression));
        return $this->getFallbackValue() === 'yes';
      }
    }
    catch (SyntaxError $e) {
      $this->loggerFactory->get('default')->error(sprintf("Wrong syntax for
        expression '%s'. Returning fallback value.", $processedExpression));
      return $this->getFallbackValue() === 'yes';
    }
    return $result;
  }

  /**
   * Get the fallback value in case of error.
   *
   * @return string
   *   The fallback 'yes' for returning true, 'no' otherwise.
   */
  protected function getFallbackValue(): string {
    return $this->configuration['error_fallback'] ?? 'no';
  }

  /**
   * Returns the expression.
   *
   * @return string
   *   The expression.
   */
  protected function getEl(): string {
    return $this->configuration['el'] ?? '';
  }

}
