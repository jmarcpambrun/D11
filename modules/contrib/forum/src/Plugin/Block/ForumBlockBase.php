<?php

namespace Drupal\forum\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base class for Forum blocks.
 */
abstract class ForumBlockBase extends BlockBase implements ContainerFactoryPluginInterface {

  final public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly EntityRepositoryInterface $entityRepository,
    protected readonly RendererInterface $renderer,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  final public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(EntityTypeManagerInterface::class),
      $container->get(EntityRepositoryInterface::class),
      $container->get(RendererInterface::class),
    );
  }

  /**
   * Builds a node title list.
   *
   * @param \Drupal\Core\Database\StatementInterface $result
   *   Query result.
   * @param string|null $title
   *   Optional title.
   *
   * @return array|bool
   *   Title list.
   */
  protected function nodeTitleList(StatementInterface $result, $title = NULL): array|bool {
    $items = [];
    $num_rows = FALSE;
    $nids = [];
    foreach ($result as $row) {
      // Do not use $node->label() or $node->toUrl() here, because we only have
      // database rows, not actual nodes.
      $nids[] = $row->nid;
      $options = !empty($row->comment_count) ? [
        'attributes' => [
          'title' => $this->formatPlural($row->comment_count, '1 comment', '@count comments'),
        ],
      ] : [];
      $items[] = Link::fromTextAndUrl($row->title, Url::fromRoute('entity.node.canonical', ['node' => $row->nid], $options))
        ->toString();
      $num_rows = TRUE;
    }

    return $num_rows ? [
      '#theme' => 'item_list__node',
      '#items' => $items,
      '#title' => $title,
      '#cache' => ['tags' => Cache::mergeTags(['node_list'], Cache::buildTags('node', $nids))],
    ] : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $result = $this->buildForumQuery()->execute();
    $elements = [];
    if ($node_title_list = $this->nodeTitleList($result)) {
      $elements['forum_list'] = $node_title_list;
      $elements['forum_more'] = [
        '#type' => 'more_link',
        '#url' => Url::fromRoute('forum.index'),
        '#attributes' => ['title' => $this->t('Read the latest forum topics.')],
      ];
    }
    return $elements;
  }

  /**
   * Builds the select query to use for this forum block.
   *
   * @return \Drupal\Core\Database\Query\Select
   *   A Select object.
   */
  abstract protected function buildForumQuery();

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'properties' => [
        'administrative' => TRUE,
      ],
      'block_count' => 5,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'access content');
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $range = range(2, 20);
    $form['block_count'] = [
      '#type' => 'select',
      '#title' => $this->t('Number of topics'),
      '#default_value' => $this->configuration['block_count'],
      '#options' => array_combine($range, $range),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['block_count'] = $form_state->getValue('block_count');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['user.node_grants:view']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), ['node_list']);
  }

}
