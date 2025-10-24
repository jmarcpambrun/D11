<?php

namespace Drupal\drd\Drush\Generators;

use DrupalCodeGenerator\Asset\AssetCollection as Assets;
use DrupalCodeGenerator\Attribute\Generator;
use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\GeneratorType;
use DrupalCodeGenerator\Utils;

/**
 * Action Plugin generator for Drupal Code Generator 3.x.
 */
#[Generator(
  name: 'drd-action-plugin',
  description: 'Generates an action plugin for DRD.',
  aliases: ['drdap'],
  templatePath: __DIR__ . '/templates',
  type: GeneratorType::MODULE_COMPONENT,
)]
class ActionPlugin extends BaseGenerator {

  /**
   * Get extra vars depending on the selected action plugin type.
   *
   * @param array $vars
   *   Collected vars.
   *
   * @return array
   *   The extra vars.
   */
  private function typeVars(array $vars): array {
    $type_vars = [
      'drd' => [
        'dc_base_class' => 'BaseSystem',
      ],
      'drd_host' => [
        'dc_base_class' => 'BaseHost',
        'drush_callback' => 'hosts',
      ],
      'drd_core' => [
        'dc_base_class' => 'BaseCore',
        'drush_callback' => 'cores',
      ],
      'drd_domain' => [
        'dc_base_class' => 'BaseDomain',
        'drush_callback' => 'domains',
      ],
    ];

    return $type_vars[$vars['type']];
  }

  /**
   * Set a file for rendering.
   *
   * @param \DrupalCodeGenerator\Asset\AssetCollection $assets
   *   The assets collection.
   * @param string $path
   *   The sub-path within the templates directory.
   * @param string $filename
   *   The destination filename.
   * @param array $vars
   *   Collected vars.
   * @param bool $append
   *   Whether to append (TRUE) or replace (FALSE) the destination file.
   * @param string|null $twigfile
   *   If null, the source filename will be the destination filename with the
   *   appended ".twig" extension, and if the source filename is different, this
   *   should be stated here.
   */
  private function setOrAppendFile(Assets $assets, string $path, string $filename, array $vars, bool $append = TRUE, ?string $twigfile = NULL): void {
    if (!isset($twigfile)) {
      $twigfile = $filename . '.twig';
    }
    $callback = $append ? 'addServicesFile' : 'addFile';
    $assets->{$callback}(
      trim($path . '/' . $filename, '/'),
      trim($path . '/' . $twigfile, '/'),
      $vars
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function generate(array &$vars, Assets $assets): void {
    $ir = $this->createInterviewer($vars);
    $vars['machine_name'] = $ir->askMachineName();
    $vars['id'] = $ir->ask('Action ID');
    $vars['label'] = $ir->ask('Action Label');
    $action_types = [
      'drd',
      'drd_host',
      'drd_core',
      'drd_domain',
    ];
    $vars['type'] = $ir->choice('Action Type', \array_combine($action_types, $action_types), 'drd_domain');

    $vars['class'] = Utils::camelize($vars['id']);
    $vars += $this->typeVars($vars);

    $this->setOrAppendFile($assets, '', 'drush.services.yml', $vars);
    $this->setOrAppendFile($assets, 'config/optional', 'system.action.' . $vars['machine_name'] . '_drd_action_' . $vars['id'] . '.yml', $vars, FALSE, 'system.action.drd_action.yml.twig');
    foreach (['6', '7', '8'] as $version) {
      $this->setOrAppendFile($assets, 'src/Agent/Action/V' . $version, $vars['class'] . '.php', $vars, FALSE, 'Class.php.twig');
    }
    $this->setOrAppendFile($assets, 'src/Commands', $vars['class'] . 'Commands.php', $vars, FALSE, 'ClassCommands.php.twig');
    $this->setOrAppendFile($assets, 'src/Plugin/Action', $vars['class'] . '.php', $vars, FALSE, $vars['type'] . '.php.twig');
  }

}
