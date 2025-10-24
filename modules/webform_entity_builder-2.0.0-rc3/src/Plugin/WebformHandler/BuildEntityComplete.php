<?php
/**
 * Created by PhpStorm.
 * User: steve
 * Date: 03/08/18
 * Time: 08:00
 */

namespace Drupal\webform_entity_builder\Plugin\WebformHandler;

use Drupal\Core\Annotation\Translation;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\webform\Annotation\WebformHandler;
use Drupal\webform_entity_builder\Event\EntityBuildEvent;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Form submission handler.
 *
 * THIS HANDLER SHOULD ONLY BE ATTACHED TO WEBFORMS FOR CREATING ENTITIES.
 *
 * @WebformHandler(
 *   id = "webform_entity_builder_build",
 *   label = @Translation("Webform Entity Builder"),
 *   category = @Translation("Webform Entity Builder"),
 *   description = @Translation("Sends an event to build an entity from the webform data provided, and deletes the webform submission afterwards."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED,
 * )
 *
 * =======================================================
 *  - Debugging: Does your webform have a '_build_entity' or 'entity type'
 *    field? A value (or hidden) field called '_build_entity' specifies the
 *    type of the entity to be created. (Using 'entity_type' is deprecated.)
 *  - Is there an actual plugin to perform the build process?
 *  - If there is, has the module it's in been enabled?
 * =======================================================
 *
 */
class BuildEntityComplete extends WebformHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    // Only do this if the user has actually finished.
    if ($webform_submission->isCompleted()) {
      $data = $webform_submission->getData();
      // Include the source entity because we want to delete it when finished.
      $data['source_entity'] = $webform_submission;
      // Make sure we have an _build_entity/entity_type field (right sort of form).
      if (!empty($data['_build_entity']) || !empty('entity_type')) {
        // And fly my pretties...
        EntityBuildEvent::Dispatch($data);
      }
    }
  }
}
