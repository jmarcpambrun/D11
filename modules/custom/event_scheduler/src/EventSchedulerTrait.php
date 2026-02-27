<?php
/**
 * Created by PhpStorm.
 * User: steve
 * Date: 23/08/18
 * Time: 14:35
 */

namespace Drupal\event_scheduler;

use Drupal\event_scheduler\Event\EventScheduleInterface;

trait EventSchedulerTrait {

  protected int $id = 0;

  protected string $name;

  protected string $class;

  protected string $tag;

  protected int $launch;

  protected bool $processed;

  /**
   * @param int $launch
   * @param string $tag
   * @return EventScheduleInterface
   */
  public function initialise(int $launch, string $tag = ''): EventScheduleInterface {
    return $this
      ->setLaunch($launch)
      ->setTag($tag)
      ->setName(static::NAME)
      ->setClass(get_class($this));
  }

  /**
   * @param int $id
   *
   * @return static
   */
  public function setId(int $id): static {
    $this->id = $id;
    return $this;
  }

  /**
   * @return int
   */
  public function id(): int {
    return $this->id;
  }

  /**
   * @param string $name
   *
   * @return static
   */
  public function setName(string $name): static {
    $this->name = $name;
    return $this;
  }

  /**
   * @return string
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * @param string $event_class
   *
   * @return static
   */
  public function setClass(string $event_class): static {
    $this->class = $event_class;
    return $this;
  }

  /**
   * @return string
   */
  public function getClass(): string {
    return $this->class;
  }

  /**
   * @param string $tag
   *
   * @return $this
   */
  public function setTag(string $tag): static {
    $this->tag = $tag;
    return $this;
  }

  /**
   * @return string
   */
  public function getTag(): string {
    return $this->tag;
  }

  /**
   * @param int $launch UTC timestamp
   *
   * @return static
   */
  public function setLaunch(int $launch): static {
    $this->launch = $launch;
    return $this;
  }

  /**
   * @return int UTC timestamp
   */
  public function getLaunch(): int {
    return $this->launch;
  }

  /**
   * @param bool $processed
   *
   * @return static
   */
  public function setProcessed(bool $processed = TRUE): static {
    $this->processed = $processed;
    return $this;
  }

  /**
   * @return bool
   */
  public function isProcessed(): bool {
    return $this->processed;
  }

}