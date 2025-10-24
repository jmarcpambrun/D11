<?php
/**
 * Created by PhpStorm.
 * User: steve
 * Date: 20/07/18
 * Time: 14:23
 */

namespace Drupal\webform_entity_builder\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\event_scheduler\Event\EventDelayInterface;

/**
 * Class EntityBuildEvent
 *
 * @package Drupal\webform_entity_builder\Event
 */
class EntityBuildEvent extends Event implements EventDelayInterface, EntityBuildEventInterface {

  use StringTranslationTrait;
  use LoggerChannelTrait;

  const NAME = 'webform_entity.build';

  const GROUP = 'webform-entity';

  /**
   * Create and dispatch the relevant activity build event.
   *
   * @param mixed[] $data
   */
  public static function Dispatch($data) {
    $event = new static($data);

    static::doDispatch($event, static::NAME);
  }

  /**
   * Dispatch the event, and make a note of it.
   *
   * @param EntityBuildEventInterface|Event $event
   */
  static protected function doDispatch(EntityBuildEventInterface $event, string $name) {
    static::slogger()->debug(new TranslatableMarkup('Dispatching event: %x', ['%x' => $name]));
    \Drupal::service('event_scheduler.dispatcher')->dispatch($event, $name);
  }

  /**
   * @var mixed[]
   */
  protected $data;

  /**
   * EntityBuildEvent constructor.
   *
   * @param mixed[] $data
   */
  protected function __construct(array $data) {
    $this->data = $data;
  }

  /**
   * @inheritDoc
   */
  public function getName(): string {
    return static::NAME;
  }

  /**
   * @return mixed[]
   */
  public function getData() {
    return $this->data;
  }

  /**
   * @param string $key
   *
   * @return mixed
   */
  public function getKeyedData($key) {
    return $this->data[$key] ?? NULL;
  }

  /**
   * @return mixed
   */
  public function getEntityType() {
    return $this->getKeyedData('_build_entity') ??  '';
  }

  /**
   * @return mixed
   */
  public function getEntityTypeId() {
    [$entity_type, ] = explode(':', $this->getEntityType());
    return $entity_type;
  }

  /**
   * @return mixed
   */
  public function getBundle() {
    [, $bundle, ] = explode(':', $this->getEntityType() . ':');
    return $bundle ?: '*';
  }

  /**
   * @return int
   */
  public function getEntityId() {
    return (int) $this->getKeyedData('_entity_id') ?? 0;
  }

  /**
   * @return \Psr\Log\LoggerInterface
   */
  protected static function slogger() {
    $bits = explode('\\', static::class);
    return \Drupal::logger(static::GROUP . ':' . array_pop($bits));
  }

  /**
   * @return \Psr\Log\LoggerInterface
   */
  protected function logger() {
    $bits = explode('\\', get_class($this));
    return $this->getLogger(static::GROUP . ':' . array_pop($bits));
  }
}
