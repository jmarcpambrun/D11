<?php

namespace Drupal\maestro\Controller;

use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Controller\ControllerBase;

/**
 * Maestro Autocomplete controller for roles, interactive
 * handlers and batch handlers.
 */
class MaestroAutoCompleteController extends ControllerBase {

  /**
   * Returns response for the autocompletion.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object containing the search string.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the autocomplete suggestions.
   */
  public function autocompleteRoles(Request $request) {
    $matches = [];
    $string = $request->query->get('q');
    $roles = Role::loadMultiple();
    unset($roles[RoleInterface::ANONYMOUS_ID]);
    $role_names = array_map(function ($item) {
      return $item->label();
    }, $roles);
    foreach ($role_names as $rid => $name) {
      if (stristr($name, $string) !== FALSE) {
        $matches[] = $name . " ({$rid})";
      }
    }

    return new JsonResponse($matches);
  }

  /**
   * Returns response for the autocompletion.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object containing the search string.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the autocomplete suggestions.
   */
  public function autocompleteInteractiveHandlers(Request $request) {
    $handlers = [];
    $matches = [];
    $string = $request->query->get('q');
    // Let modules signal the handlers they wish to share.
    $handlers = \Drupal::moduleHandler()->invokeAll('maestro_interactive_handlers', []);
    // Now what are our matches based on the incoming request.
    foreach ($handlers as $name => $desc) {
      if (stristr($name, $string) !== FALSE) {
        $matches[] = $name;
      }
    }

    return new JsonResponse($matches);
  }

  /**
   * Returns response for the autocompletion.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object containing the search string.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the autocomplete suggestions.
   */
  public function autocompleteBatchHandlers(Request $request) {
    $handlers = [];
    $matches = [];
    $string = $request->query->get('q');
    // Let modules signal the handlers they wish to share.
    $handlers = \Drupal::moduleHandler()->invokeAll('maestro_batch_handlers', []);
    // Now what are our matches based on the incoming request.
    foreach ($handlers as $name => $desc) {
      if (stristr($name, $string) !== FALSE) {
        $matches[] = $name;
      }
    }

    return new JsonResponse($matches);
  }

}
