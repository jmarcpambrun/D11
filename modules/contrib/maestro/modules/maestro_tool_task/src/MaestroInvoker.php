<?php

namespace Drupal\maestro_tool_task;

use Drupal\tool\Tool\Invoker;

/**
 * Builds the invoker identifying Maestro as the caller of a tool.
 */
final class MaestroInvoker {

  /**
   * The invoker ID.
   */
  public const ID = 'maestro';

  /**
   * Creates the Maestro invoker.
   *
   * The invoker carries no capabilities. Maestro runs tools in-process and
   * stores their outputs into process variables and AI storage entities, so
   * it wants the real output values, not entities downcast to handles.
   *
   * @return \Drupal\tool\Tool\Invoker
   *   The invoker.
   */
  public static function create(): Invoker {
    return new Invoker(self::ID);
  }

}
