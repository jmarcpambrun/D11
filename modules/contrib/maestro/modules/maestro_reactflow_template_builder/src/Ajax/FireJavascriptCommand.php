<?php

namespace Drupal\maestro_reactflow_template_builder\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * An AJAX command for calling the builder's own javascript functions.
 *
 * The function named here must be defined on Drupal.AjaxCommands.prototype,
 * see js/template_bridge.js (e.g. maestroRemoveTask).
 */
class FireJavascriptCommand implements CommandInterface {

  /**
   * The Drupal.AjaxCommands.prototype function to call.
   *
   * @var string
   */
  protected $function;

  /**
   * Keyed array of the values passed back to the javascript function.
   *
   * @var array
   */
  protected $values;

  /**
   * Constructs a FireJavascriptCommand.
   *
   * @param string $function
   *   The javascript function you wish to call.
   * @param array $values
   *   Keyed array of values you wish to pass back to the javascript function being called.
   */
  public function __construct($function, array $values) {
    $this->function = $function;
    $this->values = $values;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return ['command' => $this->function] + $this->values;
  }

}
