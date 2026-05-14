<?php

declare(strict_types=1);

namespace Drupal\eca_gitlab_api\Plugin\ECA\Condition;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaCondition;
use Drupal\eca\Plugin\ECA\Condition\ConditionBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * ECA condition: a body follows a template (length, headings, required titles).
 *
 * All three template checks are optional; each one that is set must pass for
 * the condition to be true. Headings are detected as Markdown ATX-style.
 * Heading title comparisons are case-insensitive and level-agnostic.
 */
#[EcaCondition(
  id: 'eca_gitlab_api_body_follows_template',
  label: new TranslatableMarkup('GitLab: body follows template'),
  category: new TranslatableMarkup('GitLab API'),
  description: new TranslatableMarkup('Validates that a token-supplied body has a minimum length, contains at least N Markdown headings, and includes a list of required heading titles. Each check is optional; only the ones you set are evaluated.'),
  version_introduced: '3.0.0',
)]
class BodyFollowsTemplateCondition extends ConditionBase {

  /**
   * The gitlab_api logger channel.
   */
  protected ?LoggerInterface $logger = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    /** @var \Drupal\Core\Logger\LoggerChannelFactoryInterface $logFactory */
    $logFactory = $container->get('logger.factory');
    $instance->logger = $logFactory->get('gitlab_api');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function logger(): LoggerInterface {
    if (!isset($this->logger)) {
      // @phpstan-ignore-next-line
      $this->logger = \Drupal::service('logger.factory')->get('gitlab_api');
    }
    return $this->logger;
  }

  /**
   * {@inheritdoc}
   */
  private function logVerbose(string $message, array $context = []): void {
    $this->logger()->info('BodyFollowsTemplate: ' . $message, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'body' => '[event:body]',
      'min_length' => 0,
      'min_headings' => 0,
      'required_headings' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['body'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Body'),
      '#description' => $this->t('The text to validate. Defaults to <code>[event:body]</code> (issue description on issue events, comment text on comment events).'),
      '#default_value' => $this->configuration['body'],
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
      '#weight' => -30,
    ];
    $form['min_length'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum character count'),
      '#description' => $this->t('Body must contain at least this many characters. Set to <code>0</code> to skip the length check.'),
      '#default_value' => (int) ($this->configuration['min_length'] ?? 0),
      '#min' => 0,
      '#weight' => -20,
    ];
    $form['min_headings'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum heading count'),
      '#description' => $this->t('Body must contain at least this many Markdown headings (lines starting with <code>#</code>, <code>##</code>, ..., up to <code>######</code>). Set to <code>0</code> to skip.'),
      '#default_value' => (int) ($this->configuration['min_headings'] ?? 0),
      '#min' => 0,
      '#weight' => -15,
    ];
    $form['required_headings'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Required headings'),
      '#description' => $this->t('One heading title per line. Each must appear as a Markdown heading somewhere in the body (case-insensitive, any level). Leave empty to skip this check.'),
      '#default_value' => $this->configuration['required_headings'],
      '#rows' => 4,
      '#weight' => -10,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['body'] = (string) $form_state->getValue('body');
    $this->configuration['min_length'] = max(0, (int) $form_state->getValue('min_length'));
    $this->configuration['min_headings'] = max(0, (int) $form_state->getValue('min_headings'));
    $this->configuration['required_headings'] = (string) $form_state->getValue('required_headings');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    $body = (string) $this->tokenService->replace((string) ($this->configuration['body'] ?? ''));
    $minLength = (int) ($this->configuration['min_length'] ?? 0);
    $minHeadings = (int) ($this->configuration['min_headings'] ?? 0);
    $requiredRaw = (string) ($this->configuration['required_headings'] ?? '');

    $bodyLength = mb_strlen($body);
    $headings = $this->extractHeadings($body);

    if ($minLength > 0 && $bodyLength < $minLength) {
      $this->logVerbose('fail; body length @n < min_length @ml', ['@n' => $bodyLength, '@ml' => $minLength]);
      return $this->negationCheck(FALSE);
    }

    if ($minHeadings > 0 && count($headings) < $minHeadings) {
      $this->logVerbose('fail; heading count @h < min_headings @mh', ['@h' => count($headings), '@mh' => $minHeadings]);
      return $this->negationCheck(FALSE);
    }

    $required = $this->parseLines($requiredRaw);
    if ($required !== []) {
      $headingsLc = array_map(static fn (string $h): string => mb_strtolower($h), $headings);
      foreach ($required as $needle) {
        if (!in_array(mb_strtolower($needle), $headingsLc, TRUE)) {
          $this->logVerbose('fail; required heading "@h" not found', ['@h' => $needle]);
          return $this->negationCheck(FALSE);
        }
      }
    }

    return $this->negationCheck(TRUE);
  }

  /**
   * Extracts ATX-style Markdown heading titles from a body.
   *
   * @return list<string>
   *   Trimmed heading titles, in document order.
   */
  private function extractHeadings(string $body): array {
    $headings = [];
    foreach (preg_split("/\r\n|\n|\r/", $body) ?: [] as $line) {
      // CommonMark ATX heading: up to 3 leading spaces, 1-6 #'s, required
      // whitespace, title, optional trailing #'s + whitespace.
      if (preg_match('/^\s{0,3}#{1,6}\s+(.+?)\s*#*\s*$/', $line, $m) === 1) {
        $headings[] = trim($m[1]);
      }
    }
    return $headings;
  }

  /**
   * Splits a textarea value into trimmed non-empty lines.
   *
   * @return list<string>
   *   Trimmed non-empty lines, in input order.
   */
  private function parseLines(string $raw): array {
    $out = [];
    foreach (preg_split("/\r\n|\n|\r/", $raw) ?: [] as $line) {
      $line = trim($line);
      if ($line !== '') {
        $out[] = $line;
      }
    }
    return $out;
  }

}
