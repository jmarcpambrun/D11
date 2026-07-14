<?php

namespace Drupal\rdf\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\comment\CommentManagerInterface;
use Drupal\rdf\RdfMappingHelper;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Preprocess hook implementations for rdf.
 */
class RdfPreprocessHooks {

  public function __construct(
    protected readonly RdfMappingHelper $mappingHelper,
    protected readonly RendererInterface $renderer,
    protected readonly RouteMatchInterface $routeMatch,
    protected readonly AccountInterface $currentUser,
    #[Autowire(service: 'comment.manager')]
    protected readonly ?CommentManagerInterface $commentManager = NULL,
  ) {}

  /**
   * Implements hook_preprocess_HOOK() for HTML document templates.
   */
  #[Hook('preprocess_html')]
  public function preprocessHtml(array &$variables): void {
    if (!isset($variables['html_attributes']['prefix'])) {
      $variables['html_attributes']['prefix'] = [];
    }
    foreach ($this->mappingHelper->getNamespaces() as $prefix => $uri) {
      $variables['html_attributes']['prefix'][] = $prefix . ': ' . $uri . " ";
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for UID field templates.
   */
  #[Hook('preprocess_field__node__uid')]
  public function preprocessFieldNodeUid(array &$variables): void {
    $this->mappingHelper->setFieldRelAttribute($variables);
  }

  /**
   * Implements hook_preprocess_HOOK() for node templates.
   */
  #[Hook('preprocess_node')]
  public function preprocessNode(array &$variables): void {
    $bundle = $variables['node']->bundle();
    $mapping = $this->mappingHelper->getMapping('node', $bundle);
    $bundle_mapping = $mapping->getPreparedBundleMapping('node', $bundle);
    $variables['attributes']['about'] = empty($variables['url']) ? NULL : $variables['url'];
    $variables['attributes']['typeof'] = empty($bundle_mapping['types']) ? NULL : $bundle_mapping['types'];

    $title_mapping = $mapping->getPreparedFieldMapping('title');
    if ($title_mapping) {
      $title_attributes['property'] = empty($title_mapping['properties']) ? NULL : $title_mapping['properties'];
      $title_attributes['content'] = $variables['node']->label();
      $variables['title_suffix']['rdf_meta_title'] = [
        '#theme' => 'rdf_metadata',
        '#metadata' => [$title_attributes],
      ];
    }

    $created_mapping = $mapping->getPreparedFieldMapping('created');
    if (!empty($created_mapping)) {
      $date_attributes = $this->mappingHelper->rdfaAttributes($created_mapping, $variables['node']->get('created')->first()->toArray());
      $rdf_metadata = [
        '#theme' => 'rdf_metadata',
        '#metadata' => [$date_attributes],
      ];
      if (!empty($variables['display_submitted'])) {
        $variables['metadata'] = $this->renderer->render($rdf_metadata);
      }
      elseif (isset($variables['elements']['created'])) {
        $variables['title_suffix']['rdf_meta_created'] = $rdf_metadata;
      }
    }

    if ($this->commentManager !== NULL && $this->currentUser->hasPermission('access comments')) {
      $comment_count_mapping = $mapping->getPreparedFieldMapping('comment_count');
      if (!empty($comment_count_mapping['properties'])) {
        $fields = array_keys($this->commentManager->getFields('node'));
        $definitions = array_keys($variables['node']->getFieldDefinitions());
        $valid_fields = array_intersect($fields, $definitions);
        foreach ($valid_fields as $field_name) {
          $comment_count_attributes = $this->mappingHelper->rdfaAttributes($comment_count_mapping, $variables['node']->get($field_name)->comment_count);
          $variables['title_suffix']['rdf_meta_comment_count'] = [
            '#theme' => 'rdf_metadata',
            '#metadata' => [$comment_count_attributes],
          ];
        }
      }
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for user templates.
   */
  #[Hook('preprocess_user')]
  public function preprocessUser(array &$variables): void {
    /** @var \Drupal\user\UserInterface $account */
    $account = $variables['elements']['#user'];
    if ($account->isNew()) {
      return;
    }
    $uri = $account->toUrl();
    $mapping = $this->mappingHelper->getMapping('user', 'user');
    $bundle_mapping = $mapping->getPreparedBundleMapping();
    if (!empty($bundle_mapping['types'])) {
      $variables['attributes']['typeof'] = $bundle_mapping['types'];
      $variables['attributes']['about'] = $account->toUrl()->toString();
    }
    if ($this->routeMatch->getRouteName() == $uri->getRouteName()) {
      $name_mapping = $mapping->getPreparedFieldMapping('name');
      if (!empty($name_mapping['properties'])) {
        $username_meta = [
          '#tag' => 'meta',
          '#attributes' => [
            'about' => $account->toUrl()->toString(),
            'property' => $name_mapping['properties'],
            'content' => $account->getAccountName(),
            'lang' => '',
          ],
        ];
        $variables['#attached']['html_head'][] = [$username_meta, 'rdf_user_username'];
      }
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for username.html.twig.
   */
  #[Hook('preprocess_username')]
  public function preprocessUsername(array &$variables): void {
    if (empty($variables['attributes']['lang'])) {
      $variables['attributes']['lang'] = '';
    }
    if ($variables['uid'] > 0) {
      $variables['attributes']['about'] = Url::fromRoute('entity.user.canonical', [
        'user' => $variables['uid'],
      ])->toString();
    }
    $mapping = $this->mappingHelper->getMapping('user', 'user');
    $bundle_mapping = $mapping->getPreparedBundleMapping();
    if (!empty($bundle_mapping['types'])) {
      $variables['attributes']['typeof'] = $bundle_mapping['types'];
    }
    $name_mapping = $mapping->getPreparedFieldMapping('name');
    if (!empty($name_mapping)) {
      $variables['attributes']['property'] = $name_mapping['properties'];
      $variables['attributes']['datatype'] = '';
    }
    $homepage_mapping = $mapping->getPreparedFieldMapping('homepage');
    if (!empty($variables['homepage']) && !empty($homepage_mapping)) {
      $variables['attributes']['rel'] = $homepage_mapping['properties'];
    }
    if ($variables['truncated']) {
      $variables['attributes']['content'] = $variables['name_raw'];
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for comment templates.
   */
  #[Hook('preprocess_comment')]
  public function preprocessComment(array &$variables): void {
    $comment = $variables['comment'];
    $mapping = $this->mappingHelper->getMapping('comment', $comment->bundle());
    $bundle_mapping = $mapping->getPreparedBundleMapping();
    if (!empty($bundle_mapping['types']) && !isset($comment->in_preview)) {
      $variables['attributes']['about'] = $comment->toUrl()->toString();
      $variables['attributes']['typeof'] = $bundle_mapping['types'];
    }
    $author_mapping = $mapping->getPreparedFieldMapping('uid');
    if (!empty($author_mapping)) {
      $author_attributes = ['rel' => $author_mapping['properties']];
      $variables['author'] = [
        '#theme' => 'rdf_wrapper',
        '#content' => $variables['author'],
        '#attributes' => $author_attributes,
      ];
      $variables['submitted'] = [
        '#theme' => 'rdf_wrapper',
        '#content' => $variables['submitted'],
        '#attributes' => $author_attributes,
      ];
    }
    $created_mapping = $mapping->getPreparedFieldMapping('created');
    if (!empty($created_mapping) && isset($comment->rdf_data)) {
      $date_attributes = $comment->rdf_data['date'];
      $rdf_metadata = [
        '#theme' => 'rdf_metadata',
        '#metadata' => [$date_attributes],
      ];
      $created = !is_array($variables['created'])
        ? ['#markup' => $variables['created']]
        : $variables['created'];
      $submitted = !is_array($variables['submitted'])
        ? ['#markup' => $variables['submitted']]
        : $variables['submitted'];
      $variables['created'] = [$created, $rdf_metadata];
      $variables['submitted'] = [$submitted, $rdf_metadata];
    }
    $title_mapping = $mapping->getPreparedFieldMapping('subject');
    if (!empty($title_mapping)) {
      $variables['title_attributes']['property'] = $title_mapping['properties'];
      $variables['title_attributes']['datatype'] = '';
    }
    $pid_mapping = $mapping->getPreparedFieldMapping('pid');
    if (!empty($pid_mapping)) {
      $parent_entity_attributes['rel'] = $pid_mapping['properties'];
      $parent_entity_attributes['resource'] = $comment->rdf_data['entity_uri'];
      $variables['rdf_metadata_attributes'][] = $parent_entity_attributes;
      if ($comment->hasParentComment()) {
        $parent_comment_attributes['rel'] = $pid_mapping['properties'];
        $parent_comment_attributes['resource'] = $comment->rdf_data['pid_uri'];
        $variables['rdf_metadata_attributes'][] = $parent_comment_attributes;
      }
    }
    if (!empty($variables['rdf_metadata_attributes']) && isset($variables['content']['comment_body'])) {
      $rdf_metadata = [
        '#theme' => 'rdf_metadata',
        '#metadata' => $variables['rdf_metadata_attributes'],
      ];
      if (!empty($variables['content']['comment_body']['#prefix'])) {
        $rdf_metadata['#suffix'] = $variables['content']['comment_body']['#prefix'];
      }
      $variables['content']['comment_body']['#prefix'] = $this->renderer->render($rdf_metadata);
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for taxonomy term templates.
   */
  #[Hook('preprocess_taxonomy_term')]
  public function preprocessTaxonomyTerm(array &$variables): void {
    $term = $variables['term'];
    if ($term->isNew()) {
      return;
    }
    $mapping = $this->mappingHelper->getMapping('taxonomy_term', $term->bundle());
    $bundle_mapping = $mapping->getPreparedBundleMapping();
    $variables['attributes']['about'] = $term->toUrl()->toString();
    $variables['attributes']['typeof'] = empty($bundle_mapping['types']) ? NULL : $bundle_mapping['types'];
    $name_field_mapping = $mapping->getPreparedFieldMapping('name');
    if (!empty($name_field_mapping) && !empty($name_field_mapping['properties'])) {
      $name_attributes = [
        'property' => $name_field_mapping['properties'],
        'content' => $term->getName(),
      ];
      $variables['title_suffix']['taxonomy_term_rdfa'] = [
        '#theme' => 'rdf_metadata',
        '#metadata' => [$name_attributes],
      ];
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for image.html.twig.
   */
  #[Hook('preprocess_image')]
  public function preprocessImage(array &$variables): void {
    $variables['attributes']['typeof'] = ['foaf:Image'];
  }

  /**
   * Implements hook_preprocess_HOOK() for RSS views.
   */
  #[Hook('preprocess_views_view_rss')]
  public function preprocessViewsViewRss(array &$variables): void {
    /** @var \Drupal\Core\Template\Attribute $namespaces */
    $namespaces = $variables['namespaces'];
    foreach ($this->mappingHelper->getNamespaces() as $prefix => $uri) {
      $key = 'xmlns:' . $prefix;
      if (!$namespaces->hasAttribute($key)) {
        $namespaces->setAttribute($key, $uri);
      }
    }
  }

}
