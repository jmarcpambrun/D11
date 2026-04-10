<?php

namespace Drupal\eca_active_trail\Plugin\Action;

use Drupal\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Menu\MenuParentFormSelectorInterface;
use Drupal\eca\Plugin\Action\ActionBase;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;

/**
 * Configure and set active trail.
 *
 * @Action(
 *   id = "eca_set_active_trail",
 *   label = @Translation("Active Trail"),
 *   description = @Translation("Set the active trail.")
 * )
 */
class ActiveTrail extends ConfigurableActionBase {
  /**
   * The menu link selector.
   *
   * @var \Drupal\Core\Menu\MenuParentFormSelectorInterface
   */
  protected $menuLinkSelector;

  /**
   * The menu active trail service.
   *
   * @var \Drupal\Core\Menu\MenuActiveTrailInterface
   */
  protected $menuActiveTrail;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface|\Symfony\Component\DependencyInjection\ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    /** @var \Drupal\eca_active_trail\Plugin\Action\ActiveTrail $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    /** @var \Drupal\Core\Menu\MenuParentFormSelectorInterface $menuLinkSelector */
    $menuLinkSelector = $container->get('menu.parent_form_selector');
    $instance->setMenuLinkSelector($menuLinkSelector);

    /** @var \Drupal\Core\Menu\MenuActiveTrailInterface $menuActiveTrail */
    $menuActiveTrail = $container->get('menu.active_trail');
    $instance->setMenuActiveTrail($menuActiveTrail);

    return $instance;
  }

  /**
   * Set the menu link selector.
   *
   * @param \Drupal\Core\Menu\MenuParentFormSelectorInterface $menuLinkSelector
   *   The menu link selector.
   */
  public function setMenuLinkSelector(MenuParentFormSelectorInterface $menuLinkSelector) {
    $this->menuLinkSelector = $menuLinkSelector;
  }

  /**
   * Set the menu link selector.
   *
   * @param \Drupal\Core\Menu\MenuActiveTrailInterface $menuActiveTrail
   *   The menu active trail service.
   */
  public function setMenuActiveTrail(MenuActiveTrailInterface $menuActiveTrail) {
    $this->menuActiveTrail = $menuActiveTrail;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'trail' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $trail = $this->configuration['trail'];
    $form['trail'] = $this->menuLinkSelector->parentSelectElement($trail);
    $form['trail']['#description'] = $this->t('Select the modified active trail.');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['trail'] = $form_state->getValue('trail');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    // Get altered trail menu name.
    $menuName = $this->getMenuName();
    // Set altered trail.
    $this->menuActiveTrail->getActiveLink($menuName, $this);
  }

  /**
   * Get the menu name.
   *
   * @return string
   *   The menu name.
   */
  public function getMenuName() {
    if (!isset($this->configuration['trail'])) {
      return NULL;
    }
    [$menuName] = explode(':', $this->configuration['trail']);
    return $menuName;
  }

  /**
   * Get the link plugin ID.
   *
   * @return string
   *   The link ID.
   */
  public function getLinkId() {
    if (!isset($this->configuration['trail'])) {
      return NULL;
    }
    [, $link_id] = explode(':', $this->configuration['trail'], 2);
    return $link_id;
  }

}
