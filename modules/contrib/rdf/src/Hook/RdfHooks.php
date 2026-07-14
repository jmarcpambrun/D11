<?php

namespace Drupal\rdf\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\rdf\RdfMappingHelper;

/**
 * Hook implementations for rdf.
 */
class RdfHooks {

  use StringTranslationTrait;

  public function __construct(
    protected readonly RdfMappingHelper $mappingHelper,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match): ?string {
    if ($route_name === 'help.page.rdf') {
      $output = '<h3>' . $this->t('About') . '</h3>';
      $output .= '<p>' . $this->t('The RDF module enriches your content with metadata to let other applications (e.g., search engines, aggregators, and so on) better understand its relationships and attributes. This semantically enriched, machine-readable output for your website uses the <a href=":rdfa">RDFa specification</a>, which allows RDF data to be embedded in HTML markup. Other modules can define mappings of their data to RDF terms, and the RDF module makes this RDF data available to the theme. The core modules define RDF mappings for their data model, and the core themes output this RDF metadata information along with the human-readable visual information. For more information, see the <a href=":rdf">online documentation for the RDF module</a>.', [
        ':rdfa' => 'http://www.w3.org/TR/xhtml-rdfa-primer/',
        ':rdf' => 'https://www.drupal.org/documentation/modules/rdf',
      ]) . '</p>';
      return $output;
    }
    return NULL;
  }

  /**
   * Implements hook_rdf_namespaces().
   */
  #[Hook('rdf_namespaces')]
  public function rdfNamespaces(): array {
    return [
      'content' => 'http://purl.org/rss/1.0/modules/content/',
      'dc' => 'http://purl.org/dc/terms/',
      'foaf' => 'http://xmlns.com/foaf/0.1/',
      'og' => 'http://ogp.me/ns#',
      'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
      'schema' => 'http://schema.org/',
      'sioc' => 'http://rdfs.org/sioc/ns#',
      'sioct' => 'http://rdfs.org/sioc/types#',
      'skos' => 'http://www.w3.org/2004/02/skos/core#',
      'xsd' => 'http://www.w3.org/2001/XMLSchema#',
    ];
  }

  /**
   * Implements hook_entity_prepare_view().
   */
  #[Hook('entity_prepare_view')]
  public function entityPrepareView($entity_type, array $entities, array $displays): void {
    // Iterate over the RDF mappings for each entity and prepare the RDFa
    // attributes to be added inside field formatters.
    foreach ($entities as $entity) {
      $mapping = $this->mappingHelper->getMapping($entity_type, $entity->bundle());
      foreach ($displays[$entity->bundle()]->getComponents() as $name => $options) {
        $field_mapping = $mapping->getPreparedFieldMapping($name);
        if ($field_mapping) {
          foreach ($entity->get($name) as $item) {
            if (!isset($item->_attributes)) {
              $item->_attributes = [];
            }
            $item->_attributes += $this->mappingHelper->rdfaAttributes($field_mapping, $item->toArray());
          }
        }
      }
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_storage_load() for comment entities.
   */
  #[Hook('comment_storage_load')]
  public function commentStorageLoad($comments): void {
    foreach ($comments as $comment) {
      // Pages with many comments can show poor performance. This information
      // isn't needed until rdf preprocess_comment is called, but set it here to
      // optimize performance for websites that implement an entity cache.
      $created_mapping = $this->mappingHelper->getMapping('comment', $comment->bundle())->getPreparedFieldMapping('created');
      /** @var \Drupal\comment\CommentInterface $comment */
      $comment->rdf_data['date'] = $this->mappingHelper->rdfaAttributes($created_mapping, $comment->get('created')->first()->toArray());
      if ($entity = $comment->getCommentedEntity()) {
        // Storage-level hook — avoid bubbling metadata outside of a render
        // context.
        $comment->rdf_data['entity_uri'] = $entity->toUrl()->toString(TRUE)->getGeneratedUrl();
      }
      if ($parent = $comment->getParentComment()) {
        $comment->rdf_data['pid_uri'] = $parent->toUrl()->toString(TRUE)->getGeneratedUrl();
      }
    }
  }

}
