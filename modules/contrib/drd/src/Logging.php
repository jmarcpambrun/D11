<?php

namespace Drupal\drd;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Logging channel for DRD.
 *
 * @package Drupal\drd
 */
class Logging {

  /**
   * Whether debugging is turned on or off.
   *
   * @var bool
   */
  protected bool $debug = FALSE;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * The input-output console object for logging.
   *
   * @var \Symfony\Component\Console\Style\SymfonyStyle|null
   */
  protected ?SymfonyStyle $io = NULL;

  /**
   * Logging constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger channel.
   */
  public function __construct(ConfigFactoryInterface $configFactory, LoggerChannelFactoryInterface $loggerChannelFactory) {
    $this->debug = (bool) $configFactory->get('drd.general')->get('debug');
    $this->logger = $loggerChannelFactory->get('DRD');
  }

  /**
   * Enforce debugging even of CLI option wasn't set.
   */
  public function enforceDebug(): void {
    $this->debug = TRUE;
  }

  /**
   * Return whether debugging is enabled or disabled.
   *
   * @return bool
   *   TRUE if debugging is enabled, FALSE otherwise.
   */
  public function debugMode(): bool {
    return $this->debug;
  }

  /**
   * Set the input-output object for logging.
   *
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The input-output object.
   */
  public function setIo(SymfonyStyle $io): void {
    $this->io = $io;
  }

  /**
   * Log and output to console a message with arguments.
   *
   * @param string $severity
   *   The message severity.
   * @param string $message
   *   The message string.
   * @param array $args
   *   Arguments for the message.
   */
  public function log(string $severity, string $message, array $args = []): void {
    if (!method_exists($this->logger, $severity)) {
      $severity = 'emergency';
    }
    $arguments = [];

    $plugin_available = isset($args['@plugin_id']);
    $entity_available = isset($args['@entity_type']);

    if ($plugin_available && $entity_available) {
      $message = '@plugin_id [@entity_type/@entity_id]: ' . $message;
    }
    elseif ($plugin_available) {
      $message = '@plugin_id: ' . $message;
    }
    elseif ($entity_available) {
      $message = '[@entity_type/@entity_id]: ' . $message;
    }

    $loggerMessage = '';
    foreach ($args as $arg => $value) {
      if ($arg === 'link' || str_contains($message, $arg)) {
        $arguments[$arg] = $value;
      }
      elseif (is_scalar($value)) {
        $message .= ' ' . $arg;
        $arguments[$arg] = $value;
      }
      else {
        $loggerMessage .= ' ' . $arg;
        try {
          $arguments[$arg] = json_encode($value, JSON_THROW_ON_ERROR);
        }
        catch (\JsonException) {
          // @todo Log this exception.
        }
      }
    }

    if ($this->io === NULL) {
      $message .= ($entity_available ? '<br>@entity_name<br>' : '') . $loggerMessage;
      $this->logger->log($severity, $message, $arguments);
    }
    else {
      if ($this->debug) {
        $message .= $loggerMessage;
      }
      unset($arguments['link']);
      $output = new FormattableMarkup($message, $arguments);
      switch ($severity) {
        case 'emergency':
        case 'alert':
        case 'critical':
        case 'error':
          $this->io->error($output);
          break;

        case 'warning':
          $this->io->warning($output);
          break;

        case 'notice':
        case 'info':
        case 'debug':
        default:
          $this->io->note($output);
          break;

      }
    }
  }

  /**
   * Debug a message but only if debug mode is turned on.
   *
   * @param string $message
   *   The debug message.
   * @param array $args
   *   The message arguments.
   */
  public function debug(string $message, array $args = []): void {
    if (!$this->debug) {
      return;
    }
    $this->log('debug', $message, $args);
  }

}
