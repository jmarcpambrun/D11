<?php

declare(strict_types=1);

namespace Drupal\forms_steps\Service;

use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Forms Steps helper service.
 *
 * @package Drupal\forms_steps\Service
 */
class FormsStepsHelper {

  use StringTranslationTrait;

  /**
   * FormsStepsManager.
   *
   * @var \Drupal\forms_steps\Service\FormsStepsManager
   */
  protected FormsStepsManager $formsStepsManager;

  /**
   * CurrentRouteMatch.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  private CurrentRouteMatch $currentRouteMatch;

  /**
   * FormsStepsHelper constructor.
   *
   * @param \Drupal\forms_steps\Service\FormsStepsManager $forms_steps_manager
   *   Injected FormsStepsManager instance.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $current_route_match
   *   Injected current route match instance.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   The translation service.
   */
  public function __construct(FormsStepsManager $forms_steps_manager, CurrentRouteMatch $current_route_match, TranslationInterface $translation) {
    $this->formsStepsManager = $forms_steps_manager;
    $this->currentRouteMatch = $current_route_match;
    $this->setStringTranslation($translation);
  }

  /**
   * Get the workflow instance ID if in a current forms steps route.
   *
   * @return false|string
   *   Return the Instance ID or FALSE otherwise.
   */
  public function getWorkflowInstanceIdFromRoute() {
    $step = $this->formsStepsManager->getStepByRoute($this->currentRouteMatch->getRouteName());

    // Only return the workflow instance id if the current route is a forms
    // steps route.
    if ($step) {
      return $this->currentRouteMatch->getParameter('instance_id');
    }

    return FALSE;
  }

  /**
   * Return the available theme types.
   *
   * @return array
   *   Return an array of the available theme types.
   */
  public function getThemes() {
    return [
      -1 => $this->t('Same as collection'),
      0 => $this->t('Default theme'),
      1 => $this->t('Administration theme')
    ];
  }

}
