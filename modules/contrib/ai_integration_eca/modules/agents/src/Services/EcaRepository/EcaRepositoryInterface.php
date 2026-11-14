<?php

namespace Drupal\ai_integration_eca_agents\Services\EcaRepository;

use Drupal\eca\Entity\Eca;

/**
 * Repository for the ECA entity type.
 */
interface EcaRepositoryInterface {

  /**
   * Load a model by ID.
   *
   * @param string $id
   *   The ID to look for.
   *
   * @return \Drupal\eca\Entity\Eca|null
   *   The ECA-entity or NULL.
   */
  public function get(string $id): ?Eca;

  /**
   * Build the ECA-model.
   *
   * @param array $data
   *   The data to use.
   * @param bool $save
   *   Toggle to save the model.
   * @param string|null $id
   *   The ID of an existing model to load.
   *
   * @return \Drupal\eca\Entity\Eca
   *   Returns the ECA-model.
   *
   * @throws \Drupal\ai_integration_eca_agents\EntityViolationException
   * @throws \Drupal\ai_integration_eca_agents\MissingEventException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function build(array $data, bool $save = TRUE, ?string $id = NULL): Eca;

}
