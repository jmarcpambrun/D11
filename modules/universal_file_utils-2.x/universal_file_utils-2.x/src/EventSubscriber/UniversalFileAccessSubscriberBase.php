<?php

namespace Drupal\universal_file_utils\EventSubscriber;

use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\universal_file_utils\Event\UniversalFileAccessEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class UniversalFileAccessSubscriberBase.
 *
 * Extend this class in modules that might want to check access
 * and downloadability of certain files.
 */
abstract class UniversalFileAccessSubscriberBase implements EventSubscriberInterface {

  use LoggerChannelTrait;

  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents() {
    return [
      UniversalFileAccessEvent::NAME => ['onUniversalFileAccess', 300],
    ];
  }

  /**
   * Check file access.
   *
   * This sorts out whether a file can be loaded, and makes sure to
   * identify it thoroughly first.
   *
   * @param UniversalFileAccessEvent $event
   */
  public function onUniversalFileAccess(UniversalFileAccessEvent $event) {
    // Correct type of operation...
    if (in_array($event->getOperation(), ['download', 'view'])) {
      // User is logged in...
      if ($event->getAccount()->isAuthenticated()) {
        // Right sort of file...
        $fileUsage = $event->getFileUsage();
        // Fetch the source module for this derived class.
        $srcModule = $this->getSourceModule();
        // Do we have anything for this?
        if (!empty($fileUsage['file'][$srcModule])) {

          [$uriNeedles, $source] = $this->getData();
          // Located in the right place...
          $furi = $event->getFile()->getFileUri();

          // We allow multiple needles.
          if (!is_array($uriNeedles)) {
            $uriNeedles = [$uriNeedles];
          }
          foreach ($uriNeedles as $uriNeedle) {
            if (stripos($furi, $uriNeedle) !== FALSE) {
              // Access is granted.
              $event->allowed($source);
              break;
            }
          }
        }
      }
    }
  }

  /**
   * The source module we're looking for, probably the one the event subscriber is in.
   *
   * @return string
   */
  abstract protected function getSourceModule(): string;

  /**
   * The other matching data - the file locations.
   *
   * @return string[]
   */
  abstract protected function getData(): array;

}
