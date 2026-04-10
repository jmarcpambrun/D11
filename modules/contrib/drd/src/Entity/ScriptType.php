<?php

namespace Drupal\drd\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the Script Type entity.
 *
 * @ConfigEntityType(
 *   id = "drd_script_type",
 *   label = @Translation("Script Type"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\drd\Entity\ListBuilder\ScriptType",
 *
 *     "form" = {
 *       "default" = "Drupal\drd\Entity\Form\ScriptType",
 *       "add" = "Drupal\drd\Entity\Form\ScriptType",
 *       "edit" = "Drupal\drd\Entity\Form\ScriptType",
 *       "delete" = "Drupal\drd\Entity\Form\ScriptTypeDelete"
 *     },
 *   },
 *   config_prefix = "script_type",
 *   admin_permission = "drd.administer script entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/drd/settings/script-types/script-type/{drd_script_type}",
 *     "add-form" = "/drd/settings/script-types/add",
 *     "edit-form" = "/drd/settings/script-types/script-type/{drd_script_type}/edit",
 *     "delete-form" = "/drd/settings/script-types/script-type/{drd_script_type}/delete",
 *     "collection" = "/drd/settings/script-types"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "interpreter",
 *     "extension",
 *     "prefix",
 *     "suffix",
 *     "linesuffix"
 *   }
 * )
 */
class ScriptType extends ConfigEntityBase implements ScriptTypeInterface {

  /**
   * The list with script types for selection.
   *
   * @var array
   */
  private static array $selectList;

  /**
   * Get a list of script types for a form API select element.
   */
  public static function getSelectList(): array {
    if (!isset(self::$selectList)) {
      self::$selectList = [
        '' => '-',
      ];
      $config = \Drupal::config('drd.script_type');
      foreach ($config->getStorage()->listAll('drd.script_type') as $key) {
        $script = $config->getStorage()->read($key);
        self::$selectList[$script['id']] = $script['label'];
      }
    }
    return self::$selectList;
  }

  /**
   * Script type id.
   *
   * @var string
   */
  protected string $id;

  /**
   * Script type label.
   *
   * @var string
   */
  protected string $label;

  /**
   * Script type intepreter.
   *
   * @var string
   */
  protected string $interpreter = '';

  /**
   * Script type file extension.
   *
   * @var string
   */
  protected string $extension = '';

  /**
   * Script type prefix.
   *
   * @var string
   */
  protected string $prefix = '';

  /**
   * Script type suffix.
   *
   * @var string
   */
  protected string $suffix = '';

  /**
   * Script type line prefix.
   *
   * @var string
   */
  protected string $lineprefix = '';

  /**
   * {@inheritdoc}
   */
  public function interpreter(): string {
    return $this->interpreter;
  }

  /**
   * {@inheritdoc}
   */
  public function extension(): string {
    return $this->extension;
  }

  /**
   * {@inheritdoc}
   */
  public function prefix(): string {
    return $this->prefix;
  }

  /**
   * {@inheritdoc}
   */
  public function suffix(): string {
    return $this->suffix;
  }

  /**
   * {@inheritdoc}
   */
  public function lineprefix(): string {
    return $this->lineprefix;
  }

}
