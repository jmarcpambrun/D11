<?php
// phpcs:ignoreFile

/**
 * This file was generated via php core/scripts/generate-proxy-class.php 'Drupal\modeler_api\Hook\EntityHooks' "modules/contrib/modeler_api/src".
 */

namespace Drupal\modeler_api\ProxyClass\Hook {

    /**
     * Provides a proxy class for \Drupal\modeler_api\Hook\EntityHooks.
     *
     * @see \Drupal\Component\ProxyBuilder
     */
    class EntityHooks
    {

        use \Drupal\Core\DependencyInjection\DependencySerializationTrait;

        /**
         * The id of the original proxied service.
         *
         * @var string
         */
        protected $drupalProxyOriginalServiceId;

        /**
         * The real proxied service, after it was lazy loaded.
         *
         * @var \Drupal\modeler_api\Hook\EntityHooks
         */
        protected $service;

        /**
         * The service container.
         *
         * @var \Symfony\Component\DependencyInjection\ContainerInterface
         */
        protected $container;

        /**
         * Constructs a ProxyClass Drupal proxy object.
         *
         * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
         *   The container.
         * @param string $drupal_proxy_original_service_id
         *   The service ID of the original service.
         */
        public function __construct(\Symfony\Component\DependencyInjection\ContainerInterface $container, $drupal_proxy_original_service_id)
        {
            $this->container = $container;
            $this->drupalProxyOriginalServiceId = $drupal_proxy_original_service_id;
        }

        /**
         * Lazy loads the real service from the container.
         *
         * @return object
         *   Returns the constructed real service.
         */
        protected function lazyLoadItself()
        {
            if (!isset($this->service)) {
                $this->service = $this->container->get($this->drupalProxyOriginalServiceId);
            }

            return $this->service;
        }

        /**
         * {@inheritdoc}
         */
        public function entityTypeBuild(array &$entity_types): void
        {
            $this->lazyLoadItself()->entityTypeBuild($entity_types);
        }

        /**
         * {@inheritdoc}
         */
        public function entityOperation(\Drupal\Core\Entity\EntityInterface $entity): array
        {
            return $this->lazyLoadItself()->entityOperation($entity);
        }

        /**
         * {@inheritdoc}
         */
        public function modulesInstalled(array $modules, bool $is_syncing): void
        {
            $this->lazyLoadItself()->modulesInstalled($modules, $is_syncing);
        }

    }

}
