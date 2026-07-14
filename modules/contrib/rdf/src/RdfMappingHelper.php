<?php

namespace Drupal\rdf;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\rdf\Entity\RdfMapping;

/**
 * Helper service exposing RDF mapping and RDFa attribute utilities.
 */
class RdfMappingHelper {

  public function __construct(
    protected readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Returns the RDF mapping object associated with a bundle.
   */
  public function getMapping(string $entity_type, string $bundle): RdfMapping {
    $mapping = RdfMapping::load($entity_type . '.' . $bundle);

    if (!$mapping) {
      $mapping = RdfMapping::create([
        'targetEntityType' => $entity_type,
        'bundle' => $bundle,
      ]);
    }

    return $mapping;
  }

  /**
   * Retrieves RDF namespaces from every module implementing the hook.
   *
   * @throws \Exception
   *   When two modules map the same prefix to different URIs.
   */
  public function getNamespaces(): array {
    $namespaces = [];
    // In order to resolve duplicate namespaces by using the earliest defined
    // namespace, do not use invokeAll().
    $this->moduleHandler->invokeAllWith(
      'rdf_namespaces',
      function (callable $hook, string $module) use (&$namespaces): void {
        $namespacesFromHook = $hook();
        foreach ($namespacesFromHook as $prefix => $namespace) {
          if (array_key_exists($prefix, $namespaces) && $namespace !== $namespaces[$prefix]) {
            throw new \Exception("Tried to map '$prefix' to '$namespace', but '$prefix' is already mapped to '{$namespaces[$prefix]}'.");
          }
          $namespaces[$prefix] = $namespace;
        }
      }
    );
    return $namespaces;
  }

  /**
   * Builds an array of RDFa attributes for a given mapping.
   */
  public function rdfaAttributes(array $mapping, mixed $data = NULL): array {
    $attributes = [];

    $type = $mapping['mapping_type'] ?? 'property';

    switch ($type) {
      case 'rel':
      case 'rev':
        $attributes[$type] = $mapping['properties'];
        break;

      case 'property':
        if (!empty($mapping['properties'])) {
          $attributes['property'] = $mapping['properties'];
          if (isset($data) && !empty($mapping['datatype_callback'])) {
            $callback = $mapping['datatype_callback']['callable'];
            $arguments = $mapping['datatype_callback']['arguments'] ?? NULL;
            $attributes['content'] = call_user_func($callback, $data, $arguments);
          }
          if (isset($mapping['datatype'])) {
            $attributes['datatype'] = $mapping['datatype'];
          }
          break;
        }
    }

    return $attributes;
  }

  /**
   * Swaps the field property attribute for a rel attribute.
   *
   * Keeps RDFa markup valid when the field output is just a link (e.g. the
   * node uid field).
   */
  public function setFieldRelAttribute(array &$variables): void {
    if (!empty($variables['attributes']['property'])) {
      $variables['attributes']['rel'] = $variables['attributes']['property'];
      unset($variables['attributes']['property']);
    }
  }

}
