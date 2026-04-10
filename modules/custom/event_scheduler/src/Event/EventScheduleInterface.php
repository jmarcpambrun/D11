<?php
/**
 * Created by PhpStorm.
 * User: steve
 * Date: 23/08/18
 * Time: 09:37
 */

namespace Drupal\event_scheduler\Event;

/**
 * Interface EventScheduleInterface
 *
 * @package Drupal\event_scheduler\Event
 */
interface EventScheduleInterface extends EventCommonInterface {

  /**
   * Initialise the launch time and tag for this event.
   *
   * @param int $launch
   * @param string $tag
   *
   * @return EventScheduleInterface
   */
  public function initialise(int $launch, string $tag = ''): EventScheduleInterface;

  /**
   * @param int $id
   *
   * @return static
   */
  public function setId(int $id): static;

  /**
   * @return int
   */
  public function id(): int;

  /**
   * @param string $name
   *
   * @return static
   */
  public function setName(string $name): static;

  /**
   * @param string $event_class
   *
   * @return static
   */
  public function setClass(string $event_class): static;

  /**
   * @return string
   */
  public function getClass(): string;

  /**
   * @param string $tag
   *
   * @return static
   */
  public function setTag(string $tag): static;

  /**
   * @return string
   */
  public function getTag(): string;

  /**
   * @param int $when UTC timestamp
   *
   * @return static
   */
  public function setLaunch(int $when): static;

  /**
   * @return int UTC timestamp
   */
  public function getLaunch(): int;

  /**
   * @param bool $processed
   *
   * @return static
   */
  public function setProcessed(bool $processed = TRUE): static;

  /**
   * @return bool
   */
  public function isProcessed(): bool;

}