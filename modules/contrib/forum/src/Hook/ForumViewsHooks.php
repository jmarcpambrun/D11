<?php

namespace Drupal\forum\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for forum.
 */
class ForumViewsHooks {
  use StringTranslationTrait;
  /**
   * @file
   * Provide views data for forum.module.
   */

  /**
   * Implements hook_views_data().
   */
  #[Hook('views_data')]
  public function viewsData() {
    $data['forum_index']['table']['group'] = $this->t('Forum');
    $data['forum_index']['table']['base'] = [
      'field' => 'nid',
      'title' => $this->t('Forum content'),
      'access query tag' => 'node_access',
    ];
    $data['forum_index']['nid'] = [
      'title' => $this->t('Nid'),
      'help' => $this->t('The content ID of the forum index entry.'),
      'field' => [
        'id' => 'numeric',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'relationship' => [
        'base' => 'node',
        'base field' => 'nid',
        'label' => $this->t('Node'),
      ],
    ];
    $data['forum_index']['title'] = [
      'title' => $this->t('Title'),
      'help' => $this->t('The content title.'),
      'field' => [
        'id' => 'standard',
        'link_to_node default' => TRUE,
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
    ];
    $data['forum_index']['tid'] = [
      'title' => $this->t('Has taxonomy term ID'),
      'help' => $this->t('Display content if it has the selected taxonomy terms.'),
      'argument' => [
        'id' => 'taxonomy_index_tid',
        'name table' => 'taxonomy_term_data',
        'name field' => 'name',
        'empty field name' => $this->t('Uncategorized'),
        'numeric' => TRUE,
        'skip base' => 'taxonomy_term_data',
      ],
      'field' => [
        'id' => 'numeric',
      ],
      'filter' => [
        'title' => $this->t('Has taxonomy term'),
        'id' => 'taxonomy_index_tid',
        'hierarchy table' => 'taxonomy_term__parent',
        'numeric' => TRUE,
        'skip base' => 'taxonomy_term_data',
        'allow empty' => TRUE,
      ],
      'relationship' => [
        'base' => 'taxonomy_term',
        'base field' => 'tid',
        'label' => $this->t('Term'),
      ],
    ];
    $data['forum_index']['created'] = [
      'title' => $this->t('Post date'),
      'help' => $this->t('The date the content was posted.'),
      'field' => [
        'id' => 'date',
      ],
      'sort' => [
        'id' => 'date',
      ],
      'filter' => [
        'id' => 'date',
      ],
    ];
    $data['forum_index']['sticky'] = [
      'title' => $this->t('Sticky'),
      'help' => $this->t('Whether or not the content is sticky.'),
      'field' => [
        'id' => 'boolean',
        'click sortable' => TRUE,
        'output formats' => [
          'sticky' => [
            $this->t('Sticky'),
            $this->t('Not sticky'),
          ],
        ],
      ],
      'filter' => [
        'id' => 'boolean',
        'label' => $this->t('Sticky'),
        'type' => 'yes-no',
      ],
      'sort' => [
        'id' => 'standard',
        'help' => $this->t('Whether or not the content is sticky. To list sticky content first, set this to descending.'),
      ],
    ];
    $data['forum_index']['last_comment_timestamp'] = [
      'title' => $this->t('Last comment time'),
      'help' => $this->t('Date and time of when the last comment was posted.'),
      'field' => [
        'id' => 'comment_last_timestamp',
      ],
      'sort' => [
        'id' => 'date',
      ],
      'filter' => [
        'id' => 'date',
      ],
    ];
    $data['forum_index']['comment_count'] = [
      'title' => $this->t('Comment count'),
      'help' => $this->t('The number of comments a node has.'),
      'field' => [
        'id' => 'numeric',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'argument' => [
        'id' => 'standard',
      ],
    ];
    return $data;
  }

}
