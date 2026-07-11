<?php

namespace Drupal\quiz\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\quiz\Config\Entity\QuizTypeListBuilder;
use Drupal\quiz\Form\QuizTypeEntityForm;

/**
 * Defines the quiz type entity class.
 */
#[ConfigEntityType(
  id: 'quiz_type',
  label: new TranslatableMarkup('Quiz type'),
  label_collection: new TranslatableMarkup('Quiz types'),
  label_singular: new TranslatableMarkup('quiz type'),
  label_plural: new TranslatableMarkup('quiz types'),
  config_prefix: 'type',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
  ],
  handlers: [
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
    ],
    'list_builder' => QuizTypeListBuilder::class,
    'form' => [
      'default' => QuizTypeEntityForm::class,
      'delete' => EntityDeleteForm::class,
    ],
  ],
  links: [
    'add-form' => '/admin/quiz/quiz-types/add',
    'edit-form' => '/admin/quiz/quiz-types/manage/{quiz_type}',
    'delete-form' => '/admin/quiz/quiz-types/manage/{quiz_type}/delete',
    'collection' => '/admin/quiz/quiz-types',
  ],
  admin_permission: 'administer quiz',
  bundle_of: 'quiz',
  label_count: [
    'singular' => '@count quiz type',
    'plural' => '@count quiz types',
  ],
  config_export: [
    'id',
    'label',
  ],
)]
class QuizType extends ConfigEntityBundleBase {

}
