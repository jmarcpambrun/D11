<?php

declare(strict_types=1);

namespace Drupal\phpmailer_smtp\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Hook implementations for the PHPMailer SMTP module.
 */
final class PhpmailerSmtpHooks {

  use StringTranslationTrait;

  /**
   * The minimum required version of the PHPMailer library.
   */
  const REQUIRED_PHPMAILER_VERSION = '7.1.1';

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    switch ($route_name) {
      case 'help.page.phpmailer_smtp':
        $text = file_get_contents(dirname(__DIR__, 2) . '/README.md');
        return '<pre>' . Html::escape($text) . '</pre>';

      default:
        return NULL;
    }
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'phpmailer_smtp' => [
        'variables' => [
          'module' => '',
          'key' => '',
          'recipient' => '',
          'subject' => '',
          'body' => '',
        ],
        'initial preprocess' => static::class . ':preprocessPhpmailerSmtp',
      ],
    ];
  }

  /**
   * Initial preprocess callback for the phpmailer_smtp theme hook.
   *
   * Replaces template_preprocess_phpmailer_smtp().
   */
  public function preprocessPhpmailerSmtp(array &$variables): void {
    $variables['module'] = str_replace('_', '-', $variables['module']);
    $variables['key'] = str_replace('_', '-', $variables['key']);
  }

  /**
   * Implements hook_theme_suggestions_HOOK() for phpmailer_smtp.
   */
  #[Hook('theme_suggestions_phpmailer_smtp')]
  public function themeSuggestionsPhpmailerSmtp(array $variables): array {
    return [
      'phpmailer_smtp__' . $variables['module'],
      'phpmailer_smtp__' . $variables['module'] . '__' . $variables['key'],
    ];
  }

  /**
   * Implements hook_runtime_requirements().
   *
   * Replaces the runtime phase of hook_requirements().
   */
  #[Hook('runtime_requirements')]
  public function runtimeRequirements(): array {
    $requirements = [];

    if (class_exists(PHPMailer::class)) {
      $mail = new PHPMailer();
    }

    if (empty($mail)) {
      $requirements['phpmailer_smtp'] = [
        'title' => $this->t('PHPMailer library'),
        'value' => $this->t('Missing'),
        'severity' => RequirementSeverity::Error,
        'description' => $this->t("Please install the PHPMailer library by executing 'composer update' in your site's root directory."),
      ];
    }
    else {
      $installed_version = $mail::VERSION;
      $requirements['phpmailer_smtp'] = [
        'title' => $this->t('PHPMailer library'),
        'value' => $installed_version,
      ];
      if (!version_compare($installed_version, self::REQUIRED_PHPMAILER_VERSION, '>=')) {
        $requirements['phpmailer_smtp']['severity'] = RequirementSeverity::Error;
        $requirements['phpmailer_smtp']['description'] = $this->t("PHPMailer library @version or higher is required. Please install a newer version by executing 'composer update' in your site's root directory.", [
          '@version' => self::REQUIRED_PHPMAILER_VERSION,
        ]);
      }
      else {
        $requirements['phpmailer_smtp']['severity'] = RequirementSeverity::OK;
      }
    }

    return $requirements;
  }

}
