<?php

namespace Drupal\eca_gitlab_api\Plugin\Action;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Gitlab\Api\AbstractApi;
use Gitlab\Client;

/**
 * Deriver for http_client_manager command actions.
 */
class CommandDeriver extends DeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    $this->derivatives = [];
    foreach (get_class_methods(Client::class) as $clientMethod) {
      try {
        $reflection = new \ReflectionMethod(Client::class, $clientMethod);
      }
      catch (\ReflectionException) {
        continue;
      }
      if (!$reflection->hasReturnType()) {
        continue;
      }
      $returnType = $reflection->getReturnType();
      if (!($returnType instanceof \ReflectionNamedType)) {
        continue;
      }
      if (!is_subclass_of($returnType->getName(), AbstractApi::class)) {
        continue;
      }
      $class = $returnType->getName();
      $parts = explode('\\', $class);
      $className = array_pop($parts);
      /** @var array $parts */
      $parts = preg_split('/(?=[A-Z])/', $className);
      $name = implode(' ', $parts);
      $classId = strtolower($className);
      foreach (get_class_methods($class) as $method) {
        try {
          $reflection = new \ReflectionMethod($class, $method);
        }
        catch (\ReflectionException) {
          continue;
        }
        if (!$reflection->isPublic()) {
          continue;
        }
        $id = $reflection->getName();
        if ($id === '__construct') {
          continue;
        }
        /** @var array $parts */
        $parts = preg_split('/(?=[A-Z])/', $id);
        $methodName = implode(' ', $parts);
        $id = strtolower($id);
        $params = [];
        foreach ($reflection->getParameters() as $parameter) {
          $default = '';
          if (($type = $parameter->getType()) && $type instanceof \ReflectionNamedType && $type->getName() === 'array') {
            $default = [];
          }
          $params[] = [
            'position' => $parameter->getPosition(),
            'name' => $parameter->getName(),
            'optional' => $parameter->isOptional(),
            'default' => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : $default,
            'omittable' => $parameter->allowsNull(),
          ];
        }
        $this->derivatives[$classId . '_' . $id] = [
          'label' => $name . ': ' . $methodName,
          'client_method' => $clientMethod,
          'api_method' => $reflection->getName(),
          'params' => $params,
        ] + $base_plugin_definition;
      }
    }
    return $this->derivatives;
  }

}
