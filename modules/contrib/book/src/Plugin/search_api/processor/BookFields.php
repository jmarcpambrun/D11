<?php

namespace Drupal\book\Plugin\search_api\processor;

use Drupal\book\BookInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Adds book fields to search_api index.
 */
#[SearchApiProcessor(
  id: 'book_fields',
  label: new TranslatableMarkup('Book fields'),
  description: new TranslatableMarkup("Add book data to the index."),
  stages: [
    'add_properties' => 0,
  ],
  locked: TRUE,
  hidden: TRUE,
)]
class BookFields extends ProcessorPluginBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    $properties = [];

    if ($datasource && $datasource->getEntityTypeId() === 'node') {
      $definition = [
        'label' => $this->t('Book ID'),
        'description' => $this->t('Root book ID (bid) for book pages.'),
        'type' => 'integer',
        'processor_id' => $this->getPluginId(),
        'is_list' => FALSE,
      ];
      $properties['book_bid'] = new ProcessorProperty($definition);

      $definition = [
        'label' => $this->t('Parent ID'),
        'description' => $this->t('Root book parent ID (pid).'),
        'type' => 'integer',
        'processor_id' => $this->getPluginId(),
        'is_list' => FALSE,
      ];
      $properties['book_pid'] = new ProcessorProperty($definition);

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
        $definition = [
          'label' => $parent_label,
          'type' => 'integer',
          'processor_id' => $this->getPluginId(),
          'is_list' => FALSE,
        ];
        $properties['book_p' . $i] = new ProcessorProperty($definition);
      }
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function addFieldValues(ItemInterface $item): void {
    $original = $item->getOriginalObject()->getValue();
    if (!($original instanceof BookInterface)) {
      return;
    }

    $book_info = $original->getBook();
    if (empty($book_info)) {
      return;
    }
    $fields_helper = $this->getFieldsHelper();

    $book_bid = $book_info['bid'];
    $fields = $fields_helper->filterForPropertyPath($item->getFields(), 'entity:node', 'book_bid');
    if ($book_bid && !empty($fields)) {
      foreach ($fields as $field) {
        $field->addValue($book_bid);
      }
    }

    $book_pid = $book_info['pid'];
    $fields = $fields_helper->filterForPropertyPath($item->getFields(), 'entity:node', 'book_pid');
    if ($book_pid && !empty($fields)) {
      foreach ($fields as $field) {
        $field->addValue($book_pid);
      }
    }

    foreach ($book_info as $key => $value) {
      if (str_starts_with($key, 'p')) {
        $fields = $fields_helper->filterForPropertyPath($item->getFields(), 'entity:node', 'book_p' . substr($key, 1));
        if ($value && !empty($fields)) {
          foreach ($fields as $field) {
            $field->addValue($value);
          }
        }
      }
    }
  }

}
