<?php

namespace Drupal\quiz\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\BundleEntityFormBase;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\quiz\Config\Entity\QuizResultAnswerTypeListBuilder;

/**
 * Defines the quiz result answer type entity class.
 */
#[ConfigEntityType(
  id: 'quiz_result_answer_type',
  label: new TranslatableMarkup('Quiz result answer type'),
  label_collection: new TranslatableMarkup('Quiz result answer types'),
  label_singular: new TranslatableMarkup('quiz result answer type'),
  label_plural: new TranslatableMarkup('quiz result answer types'),
  config_prefix: 'result.answer.type',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
  ],
  handlers: [
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
    ],
    'list_builder' => QuizResultAnswerTypeListBuilder::class,
    'form' => [
      'default' => BundleEntityFormBase::class,
      'add' => BundleEntityFormBase::class,
      'edit' => BundleEntityFormBase::class,
      'delete' => EntityDeleteForm::class,
    ],
  ],
  links: [
    'add-form' => '/admin/quiz/quiz-result-answer-types/add',
    'edit-form' => '/admin/quiz/quiz-result-answer-types/manage/{quiz_result_answer_type}',
    'delete-form' => '/admin/quiz/quiz-result-answer-types/manage/{quiz_result_answer_type}/delete',
    'collection' => '/admin/quiz/quiz-result-answer-types',
  ],
  admin_permission: 'administer quiz',
  bundle_of: 'quiz_result_answer',
  label_count: [
    'singular' => '@count quiz result answer type',
    'plural' => '@count quiz result answer types',
  ],
  config_export: [
    'id',
    'label',
  ],
)]
class QuizResultAnswerType extends ConfigEntityBundleBase {

}
