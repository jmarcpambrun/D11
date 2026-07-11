<?php

namespace Drupal\quiz\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\quiz\Config\Entity\QuizFeedbackTypeListBuilder;
use Drupal\quiz\Form\QuizFeedbackTypeForm;

/**
 * Defines the quiz feedback type entity class.
 */
#[ConfigEntityType(
  id: 'quiz_feedback_type',
  label: new TranslatableMarkup('Quiz feedback type'),
  label_collection: new TranslatableMarkup('Quiz feedback types'),
  label_singular: new TranslatableMarkup('Quiz feedback type'),
  label_plural: new TranslatableMarkup('Quiz feedback type'),
  config_prefix: 'feedback.type',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
  ],
  handlers: [
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
    ],
    'list_builder' => QuizFeedbackTypeListBuilder::class,
    'form' => [
      'default' => QuizFeedbackTypeForm::class,
      'delete' => EntityDeleteForm::class,
    ],
  ],
  links: [
    'add-form' => '/admin/quiz/feedback/type/add',
    'edit-form' => '/admin/quiz/feedback/type/{quiz_feedback_type}/edit',
    'delete-form' => '/admin/quiz/feedback/type/{quiz_feedback_type}/delete',
    'collection' => '/admin/quiz/feedback',
  ],
  admin_permission: 'administer quiz',
  label_count: [
    'singular' => '@count quiz feedback type',
    'plural' => '@count quiz feedback types',
  ],
  config_export: [
    'id',
    'label',
    'description',
    'component',
  ],
)]
class QuizFeedbackType extends ConfigEntityBase {

}
