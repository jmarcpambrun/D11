<?php

declare(strict_types=1);

namespace Drupal\group\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;

/**
 * Theme hook implementations for Group.
 */
final class ThemeHooks {

  public function __construct(protected RouteMatchInterface $routeMatch) {}

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'group' => [
        'render element' => 'elements',
        'initial preprocess' => self::class . ':preprocessGroup',
      ],
      'group_relationship' => [
        'render element' => 'elements',
        'initial preprocess' => self::class . ':preprocessGroupRelationship',
      ],
    ];
  }

  /**
   * Prepares variables for the group template.
   *
   * Default template: group.html.twig.
   *
   * @param array $variables
   *   - elements: An array of elements to display in view mode.
   *   - group: The group object.
   *   - view_mode: View mode; e.g., 'full', 'teaser', etc.
   */
  public function preprocessGroup(&$variables): void {
    $group = $variables['elements']['#group'];
    assert($group instanceof GroupInterface);

    $variables['group'] = $group;
    $variables['view_mode'] = $variables['elements']['#view_mode'];
    $variables['label'] = $group->label();

    $variables['attributes']['class'][] = 'group';
    $variables['attributes']['class'][] = Html::cleanCssIdentifier('group--' . $variables['view_mode']);
    $variables['attributes']['class'][] = Html::cleanCssIdentifier('group--' . $variables['group']->bundle());

    // Set variables that depend on the group being saved.
    $variables['url'] = '';
    $variables['page'] = FALSE;

    if (!$group->isNew()) {
      $variables['url'] = $group->toUrl('canonical', ['language' => $group->language()]);

      // See if we are rendering the group at its canonical route.
      if ($this->routeMatch->getRouteName() == 'entity.group.canonical') {
        $page_group = $this->routeMatch->getParameter('group');
      }
      $is_page = !empty($page_group) && $page_group->id() == $group->id();
      $variables['page'] = $variables['view_mode'] == 'full' && $is_page;
    }

    // Helpful $content variable for templates.
    $variables += ['content' => []];
    foreach (Element::children($variables['elements']) as $key) {
      $variables['content'][$key] = $variables['elements'][$key];
    }
  }

  /**
   * Prepares variables for the group relationship template.
   *
   * Default template: group-relationship.html.twig.
   *
   * @param array $variables
   *   - elements: An array of elements to display in view mode.
   *   - group_relationship: The group relationship object.
   *   - view_mode: View mode; e.g., 'full', 'teaser', etc.
   */
  public function preprocessGroupRelationship(&$variables): void {
    $group_relationship = $variables['elements']['#group_relationship'];
    assert($group_relationship instanceof GroupRelationshipInterface);

    $variables['group_relationship'] = $group_relationship;
    $variables['view_mode'] = $variables['elements']['#view_mode'];
    $variables['label'] = $group_relationship->label();
    $variables['url'] = $group_relationship->toUrl('canonical', ['language' => $group_relationship->language()]);

    $variables['attributes']['class'][] = 'group-content';
    $variables['attributes']['class'][] = Html::cleanCssIdentifier('group-content--' . $variables['view_mode']);

    // See if we are rendering the group at its canonical route.
    if ($this->routeMatch->getRouteName() == 'entity.group_relationship.canonical') {
      $page_group_relationship = $this->routeMatch->getParameter('group_relationship');
    }
    $is_page = (!empty($page_group_relationship) ? $page_group_relationship->id() == $group_relationship->id() : FALSE);
    $variables['page'] = $variables['view_mode'] == 'full' && $is_page;

    // Helpful $content variable for templates.
    $variables += ['content' => []];
    foreach (Element::children($variables['elements']) as $key) {
      $variables['content'][$key] = $variables['elements'][$key];
    }
  }

  /**
   * Implements hook_element_info_alter().
   */
  #[Hook('element_info_alter')]
  public function elementInfoAlter(array &$types): void {
    // Attach our extra CSS for toolbar icons.
    if (isset($types['toolbar'])) {
      $types['toolbar']['#attached']['library'][] = 'group/toolbar';
    }
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_group')]
  public function themeSuggestionsGroup(array $variables): array {
    $suggestions = [];

    $group = $variables['elements']['#group'];
    assert($group instanceof GroupInterface);

    $sanitized_view_mode = strtr($variables['elements']['#view_mode'], '.', '_');

    $suggestions[] = 'group__' . $sanitized_view_mode;
    $suggestions[] = 'group__' . $group->bundle();
    $suggestions[] = 'group__' . $group->bundle() . '__' . $sanitized_view_mode;
    $suggestions[] = 'group__' . $group->id();
    $suggestions[] = 'group__' . $group->id() . '__' . $sanitized_view_mode;

    return $suggestions;
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_group_relationship')]
  public function themeSuggestionsGroupRelationship(array $variables): array {
    $suggestions = [];

    $group_relationship = $variables['elements']['#group_relationship'];
    assert($group_relationship instanceof GroupRelationshipInterface);

    $sanitized_view_mode = strtr($variables['elements']['#view_mode'], '.', '_');
    $sanitized_bundle = strtr($group_relationship->bundle(), '-', '_');

    $suggestions[] = 'group_relationship__' . $sanitized_view_mode;
    $suggestions[] = 'group_relationship__' . $sanitized_bundle;
    $suggestions[] = 'group_relationship__' . $sanitized_bundle . '__' . $sanitized_view_mode;
    $suggestions[] = 'group_relationship__' . $group_relationship->id();
    $suggestions[] = 'group_relationship__' . $group_relationship->id() . '__' . $sanitized_view_mode;

    return $suggestions;
  }

}
