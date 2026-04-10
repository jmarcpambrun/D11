<?php

namespace Drupal\drd\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use mikehaertl\shellcommand\Command as ShellCommand;

/**
 * Defines the Script entity.
 *
 * @ConfigEntityType(
 *   id = "drd_script",
 *   label = @Translation("Script"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\drd\Entity\ListBuilder\Script",
 *
 *     "form" = {
 *       "default" = "Drupal\drd\Entity\Form\Script",
 *       "add" = "Drupal\drd\Entity\Form\Script",
 *       "edit" = "Drupal\drd\Entity\Form\Script",
 *       "delete" = "Drupal\drd\Entity\Form\ScriptDelete"
 *     },
 *   },
 *   config_prefix = "script_code",
 *   admin_permission = "drd.administer script entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/drd/settings/scripts/script/{drd_script}",
 *     "add-form" = "/drd/settings/scripts/add",
 *     "edit-form" = "/drd/settings/scripts/script/{drd_script}/edit",
 *     "delete-form" = "/drd/settings/scripts/script/{drd_script}/delete",
 *     "collection" = "/drd/settings/scripts"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "type",
 *     "code"
 *   }
 * )
 */
class Script extends ConfigEntityBase implements ScriptInterface {

  /**
   * The list with scripts for selection.
   *
   * @var array
   */
  private static array $selectList;

  /**
   * Get a list of scripts.
   *
   * @param bool $code
   *   If TRUE this return a list of scripts and their code, otherwise a list
   *   for a form API select element will be returned.
   *
   * @return array
   *   List of scripts.
   */
  public static function getSelectList(bool $code = TRUE): array {
    $mode = 'script_' . ($code ? 'code' : 'type');
    if (!isset(self::$selectList)) {
      self::$selectList = [
        'script_code' => [
          '' => '-',
        ],
        'script_type' => [],
      ];
      $config = \Drupal::config('drd.' . $mode);
      foreach ($config->getStorage()->listAll('drd.' . $mode) as $key) {
        $script = $config->getStorage()->read($key);
        self::$selectList[$mode][$script['id']] = $script['label'];
      }
    }
    return self::$selectList[$mode];
  }

  /**
   * Script ID.
   *
   * @var string
   */
  protected string $id;

  /**
   * Script label.
   *
   * @var string
   */
  protected string $label;

  /**
   * Script type id.
   *
   * @var string
   */
  protected string $type = '';

  /**
   * Script code.
   *
   * @var string
   */
  protected string $code = '';

  /**
   * Script type entity.
   *
   * @var ScriptType|null
   */
  protected ?ScriptType $scriptType;

  /**
   * Prepared code.
   *
   * @var string|null
   */
  protected ?string $preparedCode;

  /**
   * Output of script execution.
   *
   * @var string
   */
  protected string $output = '';

  /**
   * {@inheritdoc}
   */
  public function type(): string {
    return $this->type;
  }

  /**
   * {@inheritdoc}
   */
  public function code(): string {
    return $this->code;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $arguments, string $workingDir): void {
    $this->scriptType = ScriptType::load($this->type);
    $this->prepareCode();

    $filename = \Drupal::service('file_system')->tempnam('temporary://', 'drdscript') . '.' . $this->scriptType->extension();
    $filecontent = implode(PHP_EOL, [
      '#!' . $this->scriptType->interpreter(),
      $this->scriptType->prefix(),
      $this->preparedCode,
      $this->scriptType->suffix(),
    ]);
    file_put_contents($filename, $filecontent);
    chmod($filename, 0755);
    $command = new ShellCommand($filename);
    // @todo Get the working dir from calling plugin.
    $command->procCwd = $workingDir;
    $success = $command->execute();
    $this->output = $command->getOutput();
    if (!$success) {
      throw new \RuntimeException($command->getError());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getOutput(): string {
    return $this->output ?: '';
  }

  /**
   * Prepare script code for execution.
   */
  private function prepareCode(): void {
    $this->preparedCode = $this->code;

    // @todo tokenize the script.
    // Append a line prefix if required.
    $linePrefix = $this->scriptType->linePrefix();
    if (!empty($linePrefix)) {
      $lines = [];
      foreach (explode(PHP_EOL, $this->preparedCode) as $item) {
        $lines[] = implode(' ', [$linePrefix, $item]);
      }
      $this->preparedCode = implode(PHP_EOL, $lines);
    }
  }

}
