<?php

namespace Drupal\quiz\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\quiz\Config\Entity\QuizResultTypeListBuilder;
use Drupal\quiz\Form\QuizResultTypeForm;

/**
 * Defines the quiz result type entity class.
 */
#[ConfigEntityType(
  id: 'quiz_result_type',
  label: new TranslatableMarkup('Quiz result type'),
  label_collection: new TranslatableMarkup('Quiz result types'),
  label_singular: new TranslatableMarkup('quiz result type'),
  label_plural: new TranslatableMarkup('quiz result types'),
  config_prefix: 'result.type',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
  ],
  handlers: [
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
    ],
    'list_builder' => QuizResultTypeListBuilder::class,
    'form' => [
      'default' => QuizResultTypeForm::class,
      'delete' => EntityDeleteForm::class,
    ],
  ],
  links: [
    'add-form' => '/admin/quiz/quiz-result-types/add',
    'edit-form' => '/admin/quiz/quiz-result-types/manage/{quiz_result_type}',
    'delete-form' => '/admin/quiz/quiz-result-types/manage/{quiz_result_type}/delete',
    'collection' => '/admin/quiz/quiz-result-types',
  ],
  admin_permission: 'administer quiz',
  bundle_of: 'quiz_result',
  label_count: [
    'singular' => '@count quiz result type',
    'plural' => '@count quiz result types',
  ],
  config_export: [
    'id',
    'label',
  ],
)]
class QuizResultType extends ConfigEntityBundleBase {

}
