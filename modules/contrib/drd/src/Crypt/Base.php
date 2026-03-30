<?php

namespace Drupal\drd\Crypt;

/**
 * Provides base encryption functionality.
 *
 * @ingroup drd
 */
abstract class Base implements BaseInterface {

  /**
   * {@inheritdoc}
   */
  public static function getInstance($method, array $settings): BaseMethodInterface {
    self::getMethods();
    $classname = "\\Drupal\\drd\\Crypt\\Method\\$method";
    return new $classname(\Drupal::getContainer(), $settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function getMethods($instances = FALSE): array {
    $dir = __DIR__ . '/Method';
    $methods = [];
    foreach (['Mcrypt', 'OpenSsl', 'Tls'] as $item) {
      /* @noinspection PhpIncludeInspection */
      include_once $dir . '/' . $item . '.php';
      $classname = "\\Drupal\\drd\\Crypt\\Method\\$item";
      /** @var BaseMethodInterface $method */
      $method = new $classname(\Drupal::getContainer());
      if ($method instanceof BaseMethodInterface && $method->isAvailable()) {
        if ($instances) {
          $methods[$method->getLabel()] = $method;
        }
        else {
          $methods[$method->getLabel()] = [
            'classname' => $classname,
            'cipher' => $method->getCipherMethods(),
          ];
        }
      }
    }
    return $methods;
  }

  /**
   * {@inheritdoc}
   */
  public static function countAvailableMethods(?array $remote = NULL): int {
    $local = self::getMethods();
     $new_local = [];
    foreach ($local as $key => $value) {
      $new_local[strtolower($key)] = $value;
    }
    $count = 0;
    $local = $new_local;
    foreach ($remote as $key => $value) {
      if (isset($local[strtolower($key)])) {
        $count++;
      }
    }
    return $count;
  }

}
