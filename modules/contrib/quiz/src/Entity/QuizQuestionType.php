<?php

namespace Drupal\quiz\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\quiz\Config\Entity\QuizQuestionTypeListBuilder;
use Drupal\quiz\Form\QuizQuestionTypeForm;

/**
 * Defines the quiz question type entity class.
 */
#[ConfigEntityType(
  id: 'quiz_question_type',
  label: new TranslatableMarkup('Quiz question type'),
  label_collection: new TranslatableMarkup('Quiz question types'),
  label_singular: new TranslatableMarkup('quiz question type'),
  label_plural: new TranslatableMarkup('quiz question types'),
  config_prefix: 'question.type',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
  ],
  handlers: [
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
    ],
    'list_builder' => QuizQuestionTypeListBuilder::class,
    'form' => [
      'default' => QuizQuestionTypeForm::class,
      'add' => QuizQuestionTypeForm::class,
      'edit' => QuizQuestionTypeForm::class,
      'delete' => EntityDeleteForm::class,
    ],
  ],
  links: [
    'add-form' => '/admin/quiz/quiz-question-types/add',
    'edit-form' => '/admin/quiz/quiz-question-types/manage/{quiz_question_type}',
    'delete-form' => '/admin/quiz/quiz-question-types/manage/{quiz_question_type}/delete',
    'collection' => '/admin/quiz/quiz-question-types',
  ],
  admin_permission: 'administer quiz',
  bundle_of: 'quiz_question',
  label_count: [
    'singular' => '@count quiz question type',
    'plural' => '@count quiz question types',
  ],
  config_export: [
    'id',
    'label',
  ],
)]
class QuizQuestionType extends ConfigEntityBundleBase {

}
