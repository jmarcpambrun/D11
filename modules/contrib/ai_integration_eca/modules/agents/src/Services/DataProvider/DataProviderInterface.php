<?php

namespace Drupal\ai_integration_eca_agents\Services\DataProvider;

/**
 * Interface for ECA data provider.
 */
interface DataProviderInterface {

  /**
   * Get all the events.
   *
   * @return array
   *   Returns the events.
   */
  public function getEvents(): array;

  /**
   * Get all the conditions.
   *
   * @return array
   *   Returns the conditions.
   */
  public function getConditions(): array;

  /**
   * Get all the actions.
   *
   * @return array
   *   Returns the actions.
   */
  public function getActions(): array;

  /**
   * A wrapper that returns the events, conditions and actions.
   *
   * @param array $filterIds
   *   An optional array of IDs to filter by.
   *
   * @return array
   *   The collection of events, conditions and actions.
   */
  public function getComponents(array $filterIds = []): array;

  /**
   * Get models.
   *
   * @param array $filterIds
   *   An optional array of IDs to filter by.
   *
   * @return array
   *   Returns the models.
   */
  public function getModels(array $filterIds = []): array;

  /**
   * Allows overriding the view mode.
   *
   * This is used for controlling how much details are exposed for the different
   * components.
   *
   * @param \Drupal\ai_integration_eca_agents\Services\DataProvider\DataViewModeEnum $viewMode
   *   The view mode.
   *
   * @return \Drupal\ai_integration_eca_agents\Services\DataProvider\DataProviderInterface
   *   Returns the altered instance.
   */
  public function setViewMode(DataViewModeEnum $viewMode): DataProviderInterface;

}
