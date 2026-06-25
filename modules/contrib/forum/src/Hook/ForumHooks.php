<?php

namespace Drupal\forum\Hook;

use Drupal\comment\CommentInterface;
use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigInstallerInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\FieldConfigInterface;
use Drupal\forum\ForumIndexStorageInterface;
use Drupal\migrate\Plugin\MigrateSourceInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\node\NodeTypeInterface;
use Drupal\taxonomy\VocabularyInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Hook implementations for forum.
 */
final class ForumHooks {
  use StringTranslationTrait;

  public function __construct(
    private readonly RendererInterface $renderer,
    private readonly ForumIndexStorageInterface $forumIndexStorage,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly RequestStack $requestStack,
    private readonly ConfigInstallerInterface $configInstaller,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      case 'help.page.forum':
        $output = '';
        $output .= '<h2>' . $this->t('About') . '</h2>';
        $output .= '<p>' . $this->t('The Forum module lets you create threaded discussion forums with functionality similar to other message board systems. In a forum, users post topics and threads in nested hierarchies, allowing discussions to be categorized and grouped.') . '</p>';
        $output .= '<p>' . $this->t('The Forum module adds and uses a content type called <em>Forum topic</em>. For background information on content types, see the <a href=":node_help">Node module help page</a>.', [
          ':node_help' => Url::fromRoute('help.page', [
            'name' => 'node',
          ])->toString(),
        ]) . '</p>';
        $output .= '<p>' . $this->t('A forum is represented by a hierarchical structure, consisting of:');
        $output .= '<ul>';
        $output .= '<li>' . $this->t('<em>Forums</em> (for example, <em>Recipes for cooking vegetables</em>)') . '</li>';
        $output .= '<li>' . $this->t('<em>Forum topics</em> submitted by users (for example, <em>How to cook potatoes</em>), which start discussions.') . '</li>';
        $output .= '<li>' . $this->t('Threaded <em>comments</em> submitted by users (for example, <em>You wash the potatoes first and then...</em>).') . '</li>';
        $output .= '<li>' . $this->t('Optional <em>containers</em>, used to group similar forums. Forums can be placed inside containers, and vice versa.') . '</li>';
        $output .= '</ul>';
        $output .= '</p>';
        $output .= '<p>' . $this->t('For more information, see the <a href=":forum">online documentation for the Forum module</a>.', [
          ':forum' => 'https://www.drupal.org/documentation/modules/forum',
        ]) . '</p>';
        $output .= '<h2>' . $this->t('Uses') . '</h2>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('Setting up the forum structure') . '</dt>';
        $output .= '<dd>' . $this->t('Visit the <a href=":forums">Forums page</a> to set up containers and forums to hold your discussion topics.', [
          ':forums' => Url::fromRoute('forum.overview')->toString(),
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Starting a discussion') . '</dt>';
        $output .= '<dd>' . $this->t('The <a href=":create-topic">Forum topic</a> link on the <a href=":content-add">Add content</a> page creates the first post of a new threaded discussion, or thread.', [
          ':create-topic' => Url::fromRoute('node.add', [
            'node_type' => 'forum',
          ])->toString(),
          ':content-add' => Url::fromRoute('node.add_page')->toString(),
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Navigating in the forum') . '</dt>';
        $output .= '<dd>' . $this->t('Installing the Forum module provides a default <em>Forums</em> menu link in the Tools menu that links to the <a href=":forums">Forums page</a>.', [
          ':forums' => Url::fromRoute('forum.index')->toString(),
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Moving forum topics') . '</dt>';
        $output .= '<dd>' . $this->t('A forum topic (and all of its comments) may be moved between forums by selecting a different forum while editing a forum topic. When moving a forum topic between forums, the <em>Leave shadow copy</em> option creates a link in the original forum pointing to the new location.') . '</dd>';
        $output .= '<dt>' . $this->t('Locking and disabling comments') . '</dt>';
        $output .= '<dd>' . $this->t('Selecting <em>Closed</em> under <em>Comment settings</em> while editing a forum topic will lock (prevent new comments on) the thread. Selecting <em>Hidden</em> under <em>Comment settings</em> while editing a forum topic will hide all existing comments on the thread, and prevent new ones.') . '</dd>';
        $output .= '</dl>';
        return $output;

      case 'forum.overview':
        $output = '<p>' . $this->t('Forums contain forum topics. Use containers to group related forums.') . '</p>';
        $more_help_link = [
          '#type' => 'link',
          '#url' => Url::fromRoute('help.page', [
            'name' => 'forum',
          ]),
          '#title' => $this->t('More help'),
          '#attributes' => [
            'class' => [
              'icon-help',
            ],
          ],
        ];
        $container = [
          '#theme' => 'container',
          '#children' => $more_help_link,
          '#attributes' => [
            'class' => [
              'more-link',
            ],
          ],
        ];
        $output .= DeprecationHelper::backwardsCompatibleCall(
          \Drupal::VERSION,
          '10.3',
          fn() => $this->renderer->renderInIsolation($container),
          fn() => $this->renderer->renderPlain($container),
        );
        return $output;

      case 'forum.add_container':
        return '<p>' . $this->t('Use containers to group related forums.') . '</p>';

      case 'forum.add_forum':
        return '<p>' . $this->t('A forum holds related forum topics.') . '</p>';

      case 'forum.settings':
        return '<p>' . $this->t('Adjust the display of your forum topics. Organize the forums on the <a href=":forum-structure">forum structure page</a>.', [
          ':forum-structure' => Url::fromRoute('forum.overview')->toString(),
        ]) . '</p>';
    }
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public static function theme() {
    return [
      'forums' => [
        'variables' => [
          'forums' => [],
          'topics' => [],
          'topics_pager' => [],
          'parents' => NULL,
          'term' => NULL,
          'sortby' => NULL,
          'forum_per_page' => NULL,
          'header' => [],
        ],
        'initial preprocess' => 'template_preprocess_forums',
      ],
      'forum_list' => [
        'variables' => [
          'forums' => NULL,
          'parents' => NULL,
          'tid' => NULL,
        ],
        'initial preprocess' => 'template_preprocess_forum_list',
      ],
      'forum_icon' => [
        'variables' => [
          'new_posts' => NULL,
          'num_posts' => 0,
          'comment_mode' => 0,
          'sticky' => 0,
          'first_new' => FALSE,
        ],
        'initial preprocess' => 'template_preprocess_forum_icon',
      ],
      'forum_submitted' => [
        'variables' => [
          'topic' => NULL,
        ],
        'initial preprocess' => 'template_preprocess_forum_submitted',
      ],
      'forum_topic' => [
        'variables' => [
          'title_link' => NULL,
          'submitted' => NULL,
        ],
        'initial preprocess' => 'template_preprocess_forum_topic',
      ],
    ];
  }

  /**
   * Implements hook_entity_type_build().
   */
  #[Hook('entity_type_build')]
  public static function entityTypeBuild(array &$entity_types) {
    /** @var \Drupal\Core\Entity\EntityTypeInterface[] $entity_types */
    // Register forum specific forms.
    $entity_types['taxonomy_term']->setFormClass('forum', 'Drupal\forum\Form\ForumForm')->setFormClass('container', 'Drupal\forum\Form\ContainerForm')->setLinkTemplate('forum-edit-container-form', '/admin/structure/forum/edit/container/{taxonomy_term}')->setLinkTemplate('forum-delete-form', '/admin/structure/forum/delete/forum/{taxonomy_term}')->setLinkTemplate('forum-edit-form', '/admin/structure/forum/edit/forum/{taxonomy_term}');
  }

  /**
   * Implements hook_entity_bundle_info_alter().
   */
  #[Hook('entity_bundle_info_alter')]
  public static function entityBundleInfoAlter(&$bundles) {
    // Take over URI construction for taxonomy terms that are forums.
    if ($vid = \Drupal::config('forum.settings')->get('vocabulary')) {
      if (isset($bundles['taxonomy_term'][$vid])) {
        $bundles['taxonomy_term'][$vid]['uri_callback'] = 'forum_uri';
      }
    }
  }

  /**
   * Implements hook_entity_bundle_field_info_alter().
   */
  #[Hook('entity_bundle_field_info_alter')]
  public static function entityBundleFieldInfoAlter(&$fields, EntityTypeInterface $entity_type, $bundle) {
    if ($entity_type->id() == 'node' && !empty($fields['taxonomy_forums'])) {
      $fields['taxonomy_forums']->addConstraint('ForumLeaf', []);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_presave() for node entities.
   *
   * Assigns the forum taxonomy when adding a topic from within a forum.
   */
  #[Hook('node_presave')]
  public static function nodePresave(EntityInterface $node) {
    if (\Drupal::service('forum_manager')->checkNodeType($node)) {
      // Make sure all fields are set properly:
      $node->icon = !empty($node->icon) ? $node->icon : '';
      if (!$node->taxonomy_forums->isEmpty()) {
        $node->forum_tid = $node->taxonomy_forums->target_id;
        // Only do a shadow copy check if this is not a new node.
        if (!$node->isNew()) {
          $old_tid = \Drupal::service('forum.index_storage')->getOriginalTermId($node);
          if ($old_tid && isset($node->forum_tid) && $node->forum_tid != $old_tid && !empty($node->shadow)) {
            // A shadow copy needs to be created. Retain new term and add old
            // term.
            $node->taxonomy_forums[count($node->taxonomy_forums)] = [
              'target_id' => $old_tid,
            ];
          }
        }
      }
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for node entities.
   */
  #[Hook('node_update')]
  public static function nodeUpdate(EntityInterface $node) {
    if (\Drupal::service('forum_manager')->checkNodeType($node)) {
      // If this is not a new revision and does exist, update the forum record,
      // otherwise insert a new one.
      /** @var \Drupal\forum\ForumIndexStorageInterface $forum_index_storage */
      $forum_index_storage = \Drupal::service('forum.index_storage');
      if ($node->getRevisionId() == DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.2.0', fn() => $node->getOriginal(), fn() => $node->original)->getRevisionId() && $forum_index_storage->getOriginalTermId($node)) {
        if (!empty($node->forum_tid)) {
          $forum_index_storage->update($node);
        }
        else {
          $forum_index_storage->delete($node);
        }
      }
      elseif (!empty($node->forum_tid)) {
        $forum_index_storage->create($node);
      }
      // If the node has a shadow forum topic, update the record for this
      // revision.
      if (!empty($node->shadow)) {
        $forum_index_storage->deleteRevision($node);
        $forum_index_storage->create($node);
      }
      // If the node is published, update the forum index.
      if ($node->isPublished()) {
        $forum_index_storage->deleteIndex($node);
        $forum_index_storage->createIndex($node);
      }
      else {
        $forum_index_storage->deleteIndex($node);
      }
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_insert() for node entities.
   */
  #[Hook('node_insert')]
  public static function nodeInsert(EntityInterface $node) {
    if (\Drupal::service('forum_manager')->checkNodeType($node)) {
      /** @var \Drupal\forum\ForumIndexStorageInterface $forum_index_storage */
      $forum_index_storage = \Drupal::service('forum.index_storage');
      if (!empty($node->forum_tid)) {
        $forum_index_storage->create($node);
      }
      // If the node is published, update the forum index.
      if ($node->isPublished()) {
        $forum_index_storage->createIndex($node);
      }
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_predelete() for node entities.
   */
  #[Hook('node_predelete')]
  public static function nodePredelete(EntityInterface $node) {
    if (\Drupal::service('forum_manager')->checkNodeType($node)) {
      /** @var \Drupal\forum\ForumIndexStorageInterface $forum_index_storage */
      $forum_index_storage = \Drupal::service('forum.index_storage');
      $forum_index_storage->delete($node);
      $forum_index_storage->deleteIndex($node);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_storage_load() for node entities.
   */
  #[Hook('node_storage_load')]
  public static function nodeStorageLoad($nodes) {
    $node_vids = [];
    foreach ($nodes as $node) {
      if (\Drupal::service('forum_manager')->checkNodeType($node)) {
        $node_vids[] = $node->getRevisionId();
      }
    }
    if (!empty($node_vids)) {
      $result = \Drupal::service('forum.index_storage')->read($node_vids);
      foreach ($result as $record) {
        $nodes[$record->nid]->forum_tid = $record->tid;
      }
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for comment entities.
   */
  #[Hook('comment_update')]
  public static function commentUpdate(CommentInterface $comment) {
    if ($comment->getCommentedEntityTypeId() == 'node') {
      \Drupal::service('forum.index_storage')->updateIndex($comment->getCommentedEntity());
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_insert() for comment entities.
   */
  #[Hook('comment_insert')]
  public static function commentInsert(CommentInterface $comment) {
    if ($comment->getCommentedEntityTypeId() == 'node') {
      \Drupal::service('forum.index_storage')->updateIndex($comment->getCommentedEntity());
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_delete() for comment entities.
   */
  #[Hook('comment_delete')]
  public function commentDelete(CommentInterface $comment) {
    if ($comment->getCommentedEntityTypeId() == 'node') {
      $this->forumIndexStorage->updateIndex($comment->getCommentedEntity());
    }
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for \Drupal\taxonomy\VocabularyForm.
   */
  #[Hook('form_taxonomy_vocabulary_form_alter')]
  public function formTaxonomyVocabularyFormAlter(&$form, FormStateInterface $form_state, $form_id) {
    $vid = $this->configFactory->get('forum.settings')->get('vocabulary');
    $vocabulary = $form_state->getFormObject()->getEntity();
    if ($vid == $vocabulary->id()) {
      $form['help_forum_vocab'] = [
        '#markup' => $this->t('This is the designated forum vocabulary. Some of the normal vocabulary options have been removed.'),
        '#weight' => -1,
      ];
      // Forum's vocabulary always has single hierarchy. Forums and containers
      // have only one parent or no parent for root items. By default this value
      // is 0.
      $form['hierarchy']['#value'] = VocabularyInterface::HIERARCHY_SINGLE;
      // Do not allow to delete forum's vocabulary.
      $form['actions']['delete']['#access'] = FALSE;
      // Do not allow to change a vid of forum's vocabulary.
      $form['vid']['#disabled'] = TRUE;
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter() for \Drupal\taxonomy\TermForm.
   */
  #[Hook('form_taxonomy_term_form_alter')]
  public function formTaxonomyTermFormAlter(&$form, FormStateInterface $form_state, $form_id) {
    $vid = $this->configFactory->get('forum.settings')->get('vocabulary');
    if (isset($form['vid']['#value']) && $form['vid']['#value'] == $vid) {
      // Hide multiple parents select from forum terms.
      $form['relations']['parent']['#access'] = FALSE;
    }
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for \Drupal\node\NodeForm.
   */
  #[Hook('form_node_form_alter')]
  public function formNodeFormAlter(&$form, FormStateInterface $form_state, $form_id) {
    $node = $form_state->getFormObject()->getEntity();
    if (isset($node->taxonomy_forums) && !$node->isNew()) {
      $forum_terms = $node->taxonomy_forums;
      // If editing, give option to leave shadows.
      $shadow = count($forum_terms) > 1;
      $form['shadow'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Leave shadow copy'),
        '#default_value' => $shadow,
        '#description' => $this->t('If you move this topic, you can leave a link in the old forum to the new forum.'),
      ];
      $form['forum_tid'] = [
        '#type' => 'value',
        '#value' => $node->forum_tid,
      ];
    }
    if (isset($form['taxonomy_forums'])) {
      $widget =& $form['taxonomy_forums']['widget'];
      $widget['#multiple'] = FALSE;
      if (empty($widget['#default_value'])) {
        // If there is no default forum already selected, try to get the forum
        // ID from the URL (e.g., if we are on a page like node/add/forum/2, we
        // expect "2" to be the ID of the forum that was requested).
        $requested_forum_id = $this->requestStack->getCurrentRequest()->query->get('forum_id');
        $widget['#default_value'] = is_numeric($requested_forum_id) ? $requested_forum_id : '';
      }
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for block templates.
   */
  #[Hook('preprocess_block')]
  public static function preprocessBlock(&$variables) {
    if ($variables['configuration']['provider'] == 'forum') {
      $variables['attributes']['role'] = 'navigation';
    }
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_forums')]
  public static function themeSuggestionsForums(array $variables) {
    $suggestions = [];
    $tid = $variables['term']->id();
    // Provide separate template suggestions based on what's being output. Topic
    // ID is also accounted for. Check both variables to be safe then the
    // inverse. Forums with topic IDs take precedence.
    if ($variables['forums'] && !$variables['topics']) {
      $suggestions[] = 'forums__containers';
      $suggestions[] = 'forums__' . $tid;
      $suggestions[] = 'forums__containers__' . $tid;
    }
    elseif (!$variables['forums'] && $variables['topics']) {
      $suggestions[] = 'forums__topics';
      $suggestions[] = 'forums__' . $tid;
      $suggestions[] = 'forums__topics__' . $tid;
    }
    else {
      $suggestions[] = 'forums__' . $tid;
    }
    return $suggestions;
  }

  /**
   * Implements hook_migrate_prepare_row().
   */
  #[Hook('migrate_prepare_row')]
  public static function migratePrepareRow(Row $row, MigrateSourceInterface $source, MigrationInterface $migration) {
    $source_plugin = $migration->getSourcePlugin();
    if (is_a($source_plugin, '\Drupal\taxonomy\Plugin\migrate\source\d6\Term') || is_a($source_plugin, '\Drupal\taxonomy\Plugin\migrate\source\d7\Term') || is_a($source_plugin, '\Drupal\taxonomy\Plugin\migrate\source\d7\TermEntityTranslation')) {
      $connection = $source_plugin->getDatabase();
      if ($connection) {
        if ($connection->schema()->tableExists('variable')) {
          $query = $connection->select('variable', 'v')->fields('v', [
            'value',
          ])->condition('name', 'forum_containers');
          $result = $query->execute()->fetchCol();
          if ($result) {
            $forum_container_tids = unserialize($result[0], [
              'allowed_classes' => FALSE,
            ]);
            $current_tid = $row->getSourceProperty('tid');
            $row->setSourceProperty('is_container', in_array($current_tid, $forum_container_tids));
          }
        }
      }
    }
  }

  /**
   * Implements hook_migrate_MIGRATION_ID_prepare_row().
   */
  #[Hook('migrate_d7_taxonomy_vocabulary_prepare_row')]
  public static function migrateD7TaxonomyVocabularyPrepareRow(Row $row, MigrateSourceInterface $source, MigrationInterface $migration) {
    // If the vocabulary being migrated is the one defined in the
    // 'forum_nav_vocabulary' variable, set the 'forum_vocabulary' source
    // property to true so we know this is the vocabulary used by Forum.
    $connection = $migration->getSourcePlugin()->getDatabase();
    if ($connection) {
      if ($connection->schema()->tableExists('variable')) {
        $query = $connection->select('variable', 'v')->fields('v', [
          'value',
        ])->condition('name', 'forum_nav_vocabulary');
        $result = $query->execute()->fetchCol();
        if ($result) {
          $forum_nav_vocabulary = unserialize($result[0], [
            'allowed_classes' => FALSE,
          ]);
          if ($forum_nav_vocabulary == $row->getSourceProperty('vid')) {
            $row->setSourceProperty('forum_vocabulary', TRUE);
          }
        }
      }
    }
  }

  /**
   * Implements hook_migration_plugins_alter().
   */
  #[Hook('migration_plugins_alter')]
  public static function migrationPluginsAlter(array &$migrations) {
    // Function to append the forum_vocabulary process plugin.
    $merge_forum_vocabulary = function ($process) {
      $process[] = [
        'plugin' => 'forum_vocabulary',
        'machine_name' => 'forums',
      ];
      return $process;
    };
    $merge_forum_field_name = function ($process) {
      $process[] = [
        'plugin' => 'forum_vocabulary',
        'machine_name' => 'taxonomy_forums',
      ];
      return $process;
    };
    foreach ($migrations as $migration_id => $migration) {
      // Add process for forum_nav_vocabulary.
      /** @var \Drupal\migrate\Plugin\migrate\source\SqlBase $source_plugin */
      $source_plugin = \Drupal::service('plugin.manager.migration')->createStubMigration($migration)->getSourcePlugin();
      if (is_a($source_plugin, '\Drupal\taxonomy\Plugin\migrate\source\d6\Vocabulary') || is_a($source_plugin, '\Drupal\taxonomy\Plugin\migrate\source\d6\VocabularyPerType')) {
        if (isset($migration['process']['vid'])) {
          $migrations[$migration_id]['process']['vid'] = $merge_forum_vocabulary($migration['process']['vid']);
        }
        if (isset($migration['process']['field_name'])) {
          $migrations[$migration_id]['process']['field_name'] = $merge_forum_field_name($migration['process']['field_name']);
        }
      }
      if (is_a($source_plugin, '\Drupal\taxonomy\Plugin\migrate\source\d7\Vocabulary') && !is_a($source_plugin, '\Drupal\taxonomy\Plugin\migrate\source\d7\VocabularyTranslation') && !is_a($source_plugin, '\Drupal\language\Plugin\migrate\source\d7\LanguageContentSettingsTaxonomyVocabulary')) {
        if (isset($migration['process']['vid'])) {
          $process[] = $migrations[$migration_id]['process']['vid'];
          $migrations[$migration_id]['process']['vid'] = $merge_forum_vocabulary($process);
        }
      }
      // Add process for forum_container.
      if (is_a($source_plugin, '\Drupal\taxonomy\Plugin\migrate\source\d6\Term') || is_a($source_plugin, '\Drupal\taxonomy\Plugin\migrate\source\d7\Term') || is_a($source_plugin, '\Drupal\taxonomy\Plugin\migrate\source\d7\TermEntityTranslation')) {
        $migrations[$migration_id]['process']['forum_container'] = 'is_container';
      }
    }
  }

  /**
   * Implements hook_entity_form_display_presave().
   */
  #[Hook('entity_form_display_presave')]
  public function onFormDisplaySave(EntityFormDisplay $display): void {
    if (!$display->isNew() ||
      $display->getTargetEntityTypeId() !== 'node' ||
      $display->getTargetBundle() !== 'forum' ||
      $display->getMode() !== 'default' ||
      \version_compare(\Drupal::VERSION, '11.3.999', '<=')
    ) {
      return;
    }
    $component = $display->getComponent('body');
    $component['type'] = 'text_textarea';
    unset($component['settings']['show_summary'], $component['settings']['summary_rows']);
    $display->setComponent('body', $component);
  }

  /**
   * Implements hook_entity_view_display_presave().
   */
  #[Hook('entity_view_display_presave')]
  public function onViewDisplaySave(EntityViewDisplay $display): void {
    if (!$display->isNew() ||
      $display->getTargetEntityTypeId() !== 'node' ||
      $display->getTargetBundle() !== 'forum' ||
      $display->getMode() !== 'default' ||
      \version_compare(\Drupal::VERSION, '11.3.999', '<=')
    ) {
      return;
    }
    $component = $display->getComponent('body');
    $component['type'] = 'text_trimmed';
    $display->setComponent('body', $component);
  }

  /**
   * Implements hook_field_config_presave().
   */
  #[Hook('field_config_presave')]
  public function onFieldConfigSave(FieldConfigInterface $field): void {
    if (!$field->isNew() ||
      $field->getTargetEntityTypeId() !== 'node' ||
      $field->getTargetBundle() !== 'forum' ||
      $field->getName() !== 'body' ||
      \version_compare(\Drupal::VERSION, '11.3.999', '<=')
    ) {
      return;
    }
    $field->set('field_type', 'text_long');
    $settings = $field->getSettings();
    unset($settings['required_summary'], $settings['display_summary']);
    $field->setSettings($settings);
  }

  /**
   * Implements hook_node_type_insert().
   */
  #[Hook('node_type_insert')]
  public function onNodeTypeCreate(NodeTypeInterface $nodeType): void {
    if ($nodeType->id() !== 'forum' || $this->configInstaller->isSyncing() || !$this->entityTypeManager->hasDefinition('field_storage_config')) {
      return;
    }
    if (!FieldStorageConfig::load('node.body')) {
      // Add the node body field storage.
      FieldStorageConfig::create([
        'field_name' => 'body',
        'type' => \version_compare(\Drupal::VERSION, '11.3.999', '<=') ? 'text_with_summary' : 'text_long',
        'entity_type' => 'node',
      ])->save();
    }
  }

}
