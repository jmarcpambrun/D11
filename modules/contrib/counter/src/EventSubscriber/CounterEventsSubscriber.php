<?php

namespace Drupal\counter\EventSubscriber;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\counter\CounterUtility;
use Drupal\node\NodeInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class CounterEventSubscriber.
 *
 * @package Drupal\counter\EventSubscriber
 */
class CounterEventsSubscriber implements EventSubscriberInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The account interface for accessing current user.
   *
   * @var Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The counter utility variable for accessing helper functions.
   *
   * @var Drupal\counter\CounterUtility
   */
  protected $counterUtility;

  /**
   * The module handler.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Constructs an event subscriber.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory for the form.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account interface for accessing current user.
   * @param \Drupal\counter\CounterUtility $counter_utility
   *   The counter utility variable to access helper functions.
   * @param  \Drupal\Core\Extension\ModuleHandler $module_handler
   */
  public function __construct(ConfigFactoryInterface $config_factory, AccountInterface $account, CounterUtility $counter_utility, ModuleHandlerInterface $module_handler) {
    $this->configFactory = $config_factory;
    $this->account = $account;
    $this->counterUtility = $counter_utility;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The event names to listen for, and the methods that should be executed.
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => 'counterInsert',
    ];
  }

  /**
   * React to a HTTP event.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   Request event.
   */
  public function counterInsert(RequestEvent $event) {
    // Get configuration related to skipping admin counts.
    $config = $this->configFactory->get('counter.settings');
    $counter_skip_admin = $config->get('counter_skip_admin');
    $counter_only_pages = $config->get('counter_only_pages');

    // Return in case counter has to be skipped for administrators.
    if (in_array('administrator', $this->account->getRoles()) && $counter_skip_admin) {
      return;
    }
    $request = $event->getRequest();

    if ($counter_only_pages && (!$event->isMainRequest() || !$request->isMethod('GET'))) {
        return;
    }

    $path = $request->getPathInfo();

    $skip = strpos($path, '/sites/') === 0 || preg_match('/^\/history\/\d+\/read$/', $path);

    $this->moduleHandler->alter('counter_request', $event, $skip);

    if ($skip) {
      return;
    }

    $data['ip'] = $request->getClientIp();
    $data['url'] = Html::escape($request->getRequestUri());
    $data['uid'] = $this->account->id();

    // Get Node ID, content type, browser name, browser version and platform.
    $node = $request->attributes->get('node');
    $data['nid'] = 0;
    $data['type'] = '';
    if ($node instanceof NodeInterface) {
      $data['nid'] = $node->id();
      $data['type'] = $node->bundle();
    }
    $browser = $this->counterUtility->getBrowserInformation();
    $data['browser_name'] = $browser['browser_name'];
    $data['browser_version'] = $browser['browser_version'];
    $data['platform'] = $browser['platform'];

    $this->moduleHandler->alter('counter_data', $data, $skip);

    if ($skip) {
      return;
    }

    $this->counterUtility->insertCounterData($data);
  }

}
