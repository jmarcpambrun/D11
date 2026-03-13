<?php

namespace Drupal\book\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for book.
 */
class BookViewsHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_views_data().
   */
  #[Hook('views_data')]
  public function viewsData(): array {
    $data = [];
    $data['book'] = [];
    $data['book']['table'] = [];
    $data['book']['table']['group'] = $this->t('Book');

    $data['book']['table']['join'] = [
      'node_field_data' => [
        'left_field' => 'nid',
        'field' => 'nid',
      ],
    ];

    $data['book']['nid'] = [
      'title' => $this->t('Page'),
      'help' => $this->t('The book page node.'),
      'relationship' => [
        'base' => 'node_field_data',
        'id' => 'standard',
        'label' => $this->t('Book Page'),
      ],
    ];

    $data['book']['bid'] = [
      'title' => $this->t('Top level book'),
      'help' => $this->t('The book the node is in.'),
      'relationship' => [
        'base' => 'node_field_data',
        'id' => 'standard',
        'label' => $this->t('Book'),
      ],
    ];

    $data['book']['pid'] = [
      'title' => $this->t('Parent'),
      'help' => $this->t('The parent book node.'),
      'relationship' => [
        'base' => 'node_field_data',
        'id' => 'standard',
        'label' => $this->t('Book Parent'),
      ],
    ];

    $data['book']['has_children'] = [
      'title' => $this->t('Page has Children'),
      'help' => $this->t('Flag indicating whether this book page has children'),
      'field' => [
        'id' => 'boolean',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'boolean',
        'label' => $this->t('Has Children'),
      ],
      'argument' => [
        'id' => 'numeric',
      ],
    ];

    $data['book']['weight'] = [
      'title' => $this->t('Weight'),
      'help' => $this->t('The weight of the book page.'),
      'field' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
    ];

    $data['book']['depth'] = [
      'title' => $this->t('Depth'),
      'help' => $this->t('The depth of the book page in the hierarchy; top level book pages have a depth of 1.'),
      'field' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'standard',
      ],
    ];
    $parents = [
      1 => $this->t('1st parent'),
      2 => $this->t('2nd parent'),
      3 => $this->t('3rd parent'),
      4 => $this->t('4th parent'),
      5 => $this->t('5th parent'),
      6 => $this->t('6th parent'),
      7 => $this->t('7th parent'),
      8 => $this->t('8th parent'),
      9 => $this->t('9th parent'),
    ];
    foreach ($parents as $i => $parent_label) {
      $data['book']["p$i"] = [
        'title' => $parent_label,
        'help' => $this->t('The @parent of book node.', ['@parent' => $parent_label]),
        'relationship' => [
          'base' => 'node_field_data',
          'id' => 'standard',
          'label' => $this->t('Book @parent', ['@parent' => $parent_label]),
        ],
        'field' => [
          'id' => 'numeric',
        ],
        'sort' => [
          'id' => 'standard',
        ],
        'filter' => [
          'id' => 'numeric',
        ],
        'argument' => [
          'id' => 'standard',
        ],
      ];
    }

    return $data;
  }

}
