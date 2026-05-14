<?php

declare(strict_types=1);

namespace Drupal\eca_gitlab_api\Plugin\ECA\Condition;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eca\Attribute\EcaCondition;
use Drupal\eca\Plugin\ECA\Condition\ConditionBase;
use Drupal\eca_gitlab_api\Service\SlashCommandParser;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * ECA condition that matches a slash command in a GitLab comment body.
 *
 * The vocabulary is defined per-condition (per ECA model) — no project
 * configuration required. Reads the comment body from a token-replaceable
 * text field (default `[event:body]`). On match, exposes
 * [gitlab_slash:command] and [gitlab_slash:arg:<name>] tokens.
 */
#[EcaCondition(
  id: 'eca_gitlab_api_slash_command',
  label: new TranslatableMarkup('GitLab: comment matches slash command'),
  category: new TranslatableMarkup('GitLab API'),
  description: new TranslatableMarkup('Evaluates whether a GitLab comment body contains the configured slash command. On match, exposes [gitlab_slash:command] and [gitlab_slash:arg:*] tokens.'),
  version_introduced: '3.0.0',
)]
class SlashCommandCondition extends ConditionBase {

  /**
   * The slash-command parser.
   */
  protected ?SlashCommandParser $parser = NULL;

  /**
   * The gitlab_api logger channel.
   */
  protected ?LoggerInterface $logger = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->parser = $container->get('eca_gitlab_api.slash_command_parser');
    /** @var \Drupal\Core\Logger\LoggerChannelFactoryInterface $logFactory */
    $logFactory = $container->get('logger.factory');
    $instance->logger = $logFactory->get('gitlab_api');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function parser(): SlashCommandParser {
    if (!isset($this->parser)) {
      // @phpstan-ignore-next-line
      $this->parser = \Drupal::service('eca_gitlab_api.slash_command_parser');
    }
    return $this->parser;
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
    $this->logger()->info('SlashCommandCondition: ' . $message, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'body' => '[event:body]',
      'command' => '',
      'args' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['body'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Comment body'),
      '#description' => $this->t('The text to scan for the slash command. Defaults to <code>[event:body]</code>.'),
      '#default_value' => $this->configuration['body'],
      '#required' => TRUE,
      '#eca_token_replacement' => TRUE,
      '#weight' => -30,
    ];

    $form['command'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Slash command'),
      '#description' => $this->t('Command name to match (e.g. <code>triage</code>, <code>ai:needsWork</code>, <code>priority::high</code>, <code>needs-review</code>). The condition matches when a line in the body starts with <code>/&lt;command&gt;</code>. Allowed characters: letters, digits, <code>_</code>, <code>:</code>, <code>-</code>; must start with a letter, digit, or underscore.'),
      '#default_value' => $this->configuration['command'],
      '#required' => TRUE,
      '#weight' => -20,
    ];

    $form['args'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Arguments'),
      '#description' => $this->t('Comma-separated argument names in positional order (e.g. <code>priority, deadline</code>). Prefix a name with <code>*</code> to make it required (e.g. <code>*priority</code>). Leave empty if the command takes no arguments. Each value is exposed as <code>[gitlab_slash:arg:&lt;name&gt;]</code>.'),
      '#default_value' => $this->configuration['args'],
      '#weight' => -10,
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['body'] = (string) $form_state->getValue('body');
    $this->configuration['command'] = (string) $form_state->getValue('command');
    $this->configuration['args'] = (string) $form_state->getValue('args');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    $command = trim((string) ($this->configuration['command'] ?? ''));
    $argsRaw = (string) ($this->configuration['args'] ?? '');
    $body = (string) $this->tokenService->replace($this->configuration['body'] ?? '');

    $this->logVerbose('start; command="@c", args="@a", body_len=@n', [
      '@c' => $command,
      '@a' => $argsRaw,
      '@n' => strlen($body),
    ]);

    if ($command === '') {
      return $this->negationCheck(FALSE);
    }

    $argsSpec = $this->parseArgs($argsRaw);
    $vocab = [$command => ['label' => '', 'args' => $argsSpec]];
    $match = $this->parser()->parse($body, $vocab);

    if ($match === NULL || $match->command !== $command) {
      return $this->negationCheck(FALSE);
    }

    $this->tokenService->addTokenData('gitlab_slash:command', $match->command);
    foreach ($match->args as $name => $value) {
      $this->tokenService->addTokenData('gitlab_slash:arg:' . $name, $value);
    }

    return $this->negationCheck(TRUE);
  }

  /**
   * Parses the textual args spec into a positional list.
   *
   * @return list<array{name: string, required: bool}>
   *   One entry per declared argument, in positional order.
   */
  private function parseArgs(string $raw): array {
    $out = [];
    foreach (explode(',', $raw) as $token) {
      $token = trim($token);
      if ($token === '') {
        continue;
      }
      $required = FALSE;
      if (str_starts_with($token, '*')) {
        $required = TRUE;
        $token = ltrim($token, '*');
        $token = trim($token);
      }
      if ($token === '') {
        continue;
      }
      $out[] = ['name' => $token, 'required' => $required];
    }
    return $out;
  }

}
