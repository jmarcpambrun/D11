<?php

declare(strict_types=1);

namespace Drupal\phpmailer_smtp\Install\Requirements;

use Drupal\Core\Extension\InstallRequirementsInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Install-time requirements for the PHPMailer SMTP module.
 */
class PhpmailerSmtpRequirements implements InstallRequirementsInterface {

  /**
   * {@inheritdoc}
   */
  public static function getRequirements(): array {
    $requirements = [];
    if (!class_exists(PHPMailer::class)) {
      $requirements['phpmailer_smtp'] = [
        'title' => t('PHPMailer library'),
        'value' => t('Missing'),
        'severity' => RequirementSeverity::Error,
        'description' => t("Please install the PHPMailer library by executing 'composer update' in your site's root directory."),
      ];
    }
    return $requirements;
  }

}
