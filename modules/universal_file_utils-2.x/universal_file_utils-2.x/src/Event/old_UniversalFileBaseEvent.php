<?php
/**
 * Created by PhpStorm.
 * User: steve
 * Date: 20/07/18
 * Time: 14:23
 */

namespace Drupal\universal_file_utils\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;

abstract class UniversalFileBaseEvent extends Event {

  use StringTranslationTrait;
  use LoggerChannelTrait;

  const NAME = 'universal_file_utils.abstract';

  /**
   * Create and dispatch the relevant activity event.
   *
   * @param FileInterface $file
   *
   * @param FileUsageInterface $fileUsage
   *
   * @return UniversalFileBaseEvent
   */
  static public function Dispatch(FileInterface $file, FileUsageInterface $fileUsage, array $extras = []) {
    $usage_list = $fileUsage->listUsage($file);

    $event = new static($file, $usage_list);

    $event->extras = $event->getDefaults($extras);

    return static::doDispatch(static::NAME, $event);
  }

  /**
   * @param string $name
   * @param UniversalFileBaseEvent $event
   *
   * @return UniversalFileBaseEvent
   */
  static protected function doDispatch($name, UniversalFileBaseEvent $event) {
    static::slogger()->info(new TranslatableMarkup('Dispatching event: %x', ['%x' => $name]));
    \Drupal::service('event_dispatcher')->dispatch($name, $event);

    return $event;
  }

  //---------------------------------------------------------------------------

  /**
   * @var FileInterface
   */
  protected $file;

  /**
   * @var mixed[][]
   */
  protected $fileUsage;

  /**
   * @var mixed[]
   */
  protected $extras;

  /**
   * ActivityDatesChangeEvent constructor.
   *
   * @param FileInterface $file
   * @param mixed[][] $fileUsage
   */
  public function __construct(FileInterface $file, array $fileUsage) {
    $this->file = $file;
    $this->fileUsage = $fileUsage;
  }

  /**
   * Set up any default extras values;
   *
   * @param mixed[] $extras
   *
   * @return mixed[]
   */
  protected function getDefaults(array $extras) {
    $extras += [
      'now' => \Drupal::time()->getCurrentTime(),
    ];

    return $extras;
  }

  //---------------------------------------------------------------------------

  /**
   * @return FileInterface
   */
  public function getFile() {
    return $this->file;
  }

  /**
   * @return mixed[][]
   */
  public function getFileUsage() {
    return $this->fileUsage;
  }

  /**
   * @return int
   */
  public function getNow() {
    return $this->get('now');
  }

  /**
   * @return mixed[]
   */
  public function getExtras() {
    return $this->extras;
  }

  /**
   * Get an event value.
   *
   * @param string $key
   * @param null | mixed $default
   *
   * @return mixed
   */
  public function get($key, $default = NULL) {
    return $this->extras[$key] ?? $default;
  }

  /**
   * Set an event value.
   *
   * @param string $key
   * @param mixed $value
   */
  public function set($key, $value) {
    $this->extras[$key] = $value;
  }

  //---------------------------------------------------------------------------

  /**
   * @return \Psr\Log\LoggerInterface
   */
  protected static function slogger() {
    return \Drupal::logger('file_event:' . static::class);
  }

  /**
   * @return \Psr\Log\LoggerInterface
   */
  protected function logger() {
    return $this->getLogger('file_event:' . get_class($this));
  }

}
