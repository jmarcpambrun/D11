<?php
/**
 * Created by PhpStorm.
 * User: steve
 * Date: 20/07/18
 * Time: 14:23
 */

namespace Drupal\webform_entity_builder\Event;

interface EntityBuildEventInterface {

  /**
   * @return mixed[]
   */
  public function getData();

  /**
   * @param string $key
   *
   * @return mixed
   */
  public function getKeyedData($key);

  /**
   * @return mixed
   */
  public function getEntityType();

  /**
   * @return mixed
   */
  public function getEntityTypeId();

  /**
   * @return mixed
   */
  public function getBundle();

  /**
   * @return int
   */
  public function getEntityId();
}
