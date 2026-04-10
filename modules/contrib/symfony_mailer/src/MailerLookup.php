<?php

declare(strict_types=1);

namespace Drupal\symfony_mailer;

use Drupal\Component\Plugin\Discovery\StaticDiscovery;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\symfony_mailer\Component\ComponentMailerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the mailer info lookup service.
 */
class MailerLookup extends DefaultPluginManager implements MailerLookupInterface {

  /**
   * Array of definitions and sub-definitions indexed by tag.
   */
  protected ?array $tagDefinitions = NULL;

  /**
   * Constructs the MailerLookup object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleList
   *   The module extension list.
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The current service container.
   *
   * @internal
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly ModuleExtensionList $moduleList,
    protected readonly ContainerInterface $container,
  ) {
    parent::__construct(FALSE, $namespaces, $module_handler);
    $this->setCacheBackend($cache_backend, 'symfony_mailer_info');
    $this->alterInfo('symfony_mailer_info');
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id): void {
    // Look up the related entity or module, which can be used to generate the
    // label and provider.
    [$base_id] = explode('.', $plugin_id);
    if ($meta_key = $definition['metadata_key']) {
      if ($entity_def = $this->entityTypeManager->getDefinition($meta_key, FALSE)) {
        $definition['label'] ??= $entity_def->getLabel();
        $definition['provider'] = $entity_def->getProvider();
      }
    }
    elseif ($this->moduleHandler->moduleExists($base_id)) {
      $definition['label'] ??= $this->moduleList->getName($base_id);
    }

    // Normally, the provider is defaulted from the namespace, but we prefer
    // instead to set from the ID. This allows one module to proxy a
    // definition for another, and it will be ignored if the target module
    // isn't enabled.
    $definition['provider'] = $base_id;

    $definition['labels'] = [$definition['label']];
    $this->checkSubDef($definition);
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []) {
    throw new \LogicException("");
  }

  /**
   * {@inheritdoc}
   */
  public function clearCachedDefinitions(): void {
    parent::clearCachedDefinitions();
    $this->tagDefinitions = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getTagDefinition(string $tag): array {
    if ($this->tagDefinitions == NULL) {
      $this->tagDefinitions = [];
      $todo = array_values($this->getDefinitions());
      while ($def = array_shift($todo)) {
        $this->tagDefinitions[$def['base_tag']] = $def;
        $todo = array_merge($todo, array_values($def['sub_defs']));
      }
    }
    // Also match a wildcard to support definitions with unknown sub_defs.
    return $this->tagDefinitions[$tag] ??
      $this->tagDefinitions[$this->parentTag($tag) . ".*"] ??
      [];
  }

  /**
   * {@inheritdoc}
   */
  public function getMailerService(string $tag): ComponentMailerInterface {
    $definition = $this->getTagDefinition($tag);
    if (!isset($definition['service_id'])) {
      throw new \LogicException("Cannot create service for $tag");
    }
    return $this->container->get($definition['service_id']);
  }

  /**
   * {@inheritdoc}
   */
  public function parentTag(string $tag): string {
    if ($tag == '_') {
      return '';
    }
    $pos = strrpos($tag, '.');
    return $pos ? substr($tag, 0, $pos) : '_';
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscovery() {
    if (!$this->discovery) {
      $this->discovery = new StaticDiscovery();
      foreach ($this->container->getParameter('symfony_mailer.mailers') as $definition) {
        $this->discovery->setDefinition($definition['base_tag'], $definition);
      }
    }
    return $this->discovery;
  }

  /**
   * Recursively checks definitions, adding defaults and normalising.
   *
   * @param mixed $definition
   *   Definition to check.
   */
  protected function checkSubDef(&$definition): void {
    foreach ($definition['sub_defs'] as $id => &$sub_def) {
      if (!is_array($sub_def)) {
        $sub_def = ['label' => $sub_def];
      }
      $sub_def['base_tag'] = $definition['base_tag'] . ".$id";
      $sub_def['sub_defs'] ??= [];
      $sub_def['metadata_key'] ??= $definition['metadata_key'];
      $sub_def['required_config'] ??= $definition['required_config'];
      $sub_def['labels'] = array_merge($definition['labels'], [$sub_def['label']]);
      $sub_def['token_types'] ??= [];
      $sub_def['token_types'] += $definition['token_types'];
      $sub_def['variables'] ??= [];
      $sub_def['variables'] += $definition['variables'];
      $this->checkSubDef($sub_def);
    }
  }

}
