<?php

namespace Drupal\counter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class that provides utility functions for the counter module.
 *
 * @package Drupal\counter
 */
class CounterUtility {

  /**
   * The database connection.
   *
   * @var Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The request stack.
   *
   * @var Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The account interface for accessing current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs an event subscriber.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection variable for executing queries.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack for accessing request information.
   * @param \Drupal\Core\Session\AccountInterface $account
   *    The current user account.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *    The config factory.
   */
  public function __construct(
    Connection $database,
    RequestStack $request_stack,
    AccountInterface $account,
    ConfigFactoryInterface $config_factory
  ) {
    $this->database = $database;
    $this->requestStack = $request_stack;
    $this->account = $account;
    $this->configFactory = $config_factory;
  }

  /**
   * Retrieves counter last date from database.
   */
  public function getCounterLastDate($operator = '<>', $order = 'DESC') {
    $query = $this->database->select('counter', 'c');
    $query->condition('c.created', 0, $operator);
    $query->fields('c', ['created']);
    $query->orderBy('created', $order);
    $query->range(0, 1);
    $result = $query->execute();

    return $result->fetchField();
  }

  /**
   * Retrieves visitors from the counter table.
   */
  public function getVisitorData() {
    $query = $this->database->select('counter', 'c');
    $query->fields('c');
    $result = $query->countQuery()->execute()->fetchField();

    return $result;
  }

  /**
   * Retrieves unique visitors from the counter table.
   */
  public function getUniqueVisitorData() {
    $query = $this->database->select('counter', 'c');
    $query->fields('c', ['ip']);
    $query->groupBy('c.ip');
    $result = $query->countQuery()->execute()->fetchField();

    return $result;
  }

  /**
   * Retrieves unique visitors from the counter table.
   */
  public function getUniqueVisitorTimeRangeData($date1 = 0, $date2 = 0) {
    $query = $this->database->select('counter', 'c');
    $query->fields('c', ['ip']);
    if ($date1 != 0) {
      $query->condition('c.created', $date1, '>');
    }
    if ($date2 != 0) {
      $query->condition('c.created', $date2, '<');
    }
    $query->groupBy('c.ip');
    $result = $query->countQuery()->execute()->fetchField();

    return $result;
  }

  /**
   * Retrieves data related to users in the website.
   */
  public function getTotalUsers($operator = '<>', $status = 1) {
    $query = $this->database->select('users_field_data', 'u');
    $query->fields('u');
    $query->condition('u.access', 0, $operator);
    $query->condition('u.uid', 0, '<>');
    $query->condition('u.status', $status);
    $result = $query->countQuery()->execute()->fetchField();

    return $result;
  }

  /**
   * Retrieves data related to nodes in the website.
   */
  public function getTotalNodes($status = 1) {
    if (\Drupal::moduleHandler()->moduleExists('node')) {
      $query = $this->database->select('node_field_data', 'n');
      $query->fields('n');
      $query->condition('n.status', $status);
      $result = $query->countQuery()->execute()->fetchField();
      return $result;
    }
    return 0;
  }

  /**
   * Retrieves data based on time span.
   */
  public function getTimeRangeData($date1, $date2 = 0) {
    $query = $this->database->select('counter', 'c');
    $query->fields('c');
    $query->condition('c.created', $date1, '>');
    if ($date2 != 0) {
      $query->condition('c.created', $date2, '<');
    }
    $result = $query->countQuery()->execute()->fetchField();

    return $result;
  }

  /**
   * Retrieves a list of nodes sorted by view count.
   *
   * @param int $limit
   *   The maximum number of nodes to return. Defaults to 20.
   *
   * @return array
   *   An array of objects, each containing:
   *   - nid: The node ID.
   *   - type: The node type.
   *   - views: The count of views for the node.
   */
  public function getTopNodes($limit = 20) {
    $query = $this->database->select('counter', 'c');
    $query->condition('c.nid', 0, '<>');
    $query->fields('c', ['nid', 'type']);
    $query->addExpression('COUNT(*)', 'views');
    $query->groupBy('c.nid');
    $query->groupBy('c.type');
    $query->orderBy('views', 'DESC');
    $query->range(0, $limit);

    $results = $query->execute()->fetchAll();

    $nodes = [];
    foreach ($results as $row) {
      /** @var \Drupal\node\NodeInterface $node */
      if ($node = Node::load($row->nid)) {
        $nodes[] = [
          'nid' => $row->nid,
          'type' => $row->type,
          'views' => $row->views,
          'title' => $node->label(),
          'url' => $node->toUrl()->toString(),
        ];
      }
    }

    return $nodes;
  }

  /**
   * Retrieves a list of URLs sorted by view count.
   *
   * @param int $limit
   *   The maximum number of URLs to return. Defaults to 20.
   *
   * @return array
   *   An array of objects, each containing:
   *   - url: The URL address.
   *   - views: The count of views for the URL.
   */
  public function getTopUrls($limit = 20) {
    $query = $this->database->select('counter', 'c');
    $query->condition('c.url', '/', '<>');
    $query->fields('c', ['url']);
    $query->addExpression('COUNT(*)', 'views');
    $query->groupBy('c.url');
    $query->orderBy('views', 'DESC');
    $query->range(0, $limit);
    return $query->execute()->fetchAll();
  }

  /**
   * Records counter data for the current request.
   *
   * This method replicates the logic from
   * CounterEventsSubscriber::counterInsert.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   */
  public function recordCounterData(Request $request) {
    //    // Return in case counter has to be skipped for administrators.
    if ($this->configFactory->get('counter.settings')
        ->get('counter_skip_admin') && in_array('administrator',
        $this->account->getRoles())) {
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
    $browser = $this->getBrowserInformation($request);
    $data['browser_name'] = $browser['browser_name'];
    $data['browser_version'] = $browser['browser_version'];
    $data['platform'] = $browser['platform'];

    $this->insertCounterData($data);
  }

  /**
   * Fetches browser and platform information.
   */
  public function getBrowserInformation($request = NULL) {
    $default_info = [
      'browser_name' => 'Unknown',
      'browser_version' => '',
      'platform' => 'Unknown',
    ];

    $request = $request ?? $this->requestStack->getCurrentRequest();
    if (!$request instanceof Request) {
      return $default_info;
    }

    $userAgent = $request->headers->get('User-Agent');
    if (empty($userAgent)) {
      return $default_info;
    }

    $platform = $this->detectPlatform($userAgent);
    [$browser, $version] = $this->detectBrowser($userAgent);

    return [
      'browser_name' => $browser,
      'browser_version' => $version,
      'platform' => $platform,
    ];
  }

  private function detectPlatform(string $userAgent): string {
    switch (TRUE) {
      case preg_match('/linux/i', $userAgent):
        return 'Linux';
      case preg_match('/macintosh|mac os x/i', $userAgent):
        return 'Mac';
      case preg_match('/windows|win32/i', $userAgent):
        return 'Windows';
      default:
        return 'Unknown';
    }
  }

  private function detectBrowser(string $userAgent): array {
    switch (TRUE) {
      case preg_match('/MSIE (\d+\.\d+)/i', $userAgent, $matches):
        return ['Internet Explorer', $matches[1]];
      case preg_match('/Trident.*rv:(\d+\.\d+)/i', $userAgent, $matches):
        return ['Internet Explorer', $matches[1]];
      case preg_match('/OPR\/([\d\.]+)/i', $userAgent, $matches):
        return ['Opera', $matches[1]];
      case preg_match('/Edg\/([\d\.]+)/i', $userAgent, $matches):
        return ['Edge', $matches[1]];
      case preg_match('/Chrome\/([\d\.]+)/i', $userAgent, $matches):
        return ['Chrome', $matches[1]];
      case preg_match('/Firefox\/([\d\.]+)/i', $userAgent, $matches):
        return ['Firefox', $matches[1]];
      case preg_match('/Safari\/([\d\.]+)/i', $userAgent) &&
        preg_match('/Version\/([\d\.]+)/i', $userAgent, $matches):
        return ['Safari', $matches[1]];
      default:
        return ['Unknown', ''];
    }
  }

  /**
   * Inserts counter data to database.
   */
  public function insertCounterData(array $data) {
    $this->database->insert('counter')
      ->fields([
        'ip' => $data['ip'],
        'created' => time(),
        'url' => substr($data['url'], 0, 255),
        'uid' => $data['uid'],
        'nid' => $data['nid'],
        'type' => $data['type'],
        'browser_name' => $data['browser_name'],
        'browser_version' => $data['browser_version'],
        'platform' => $data['platform'],
      ])
      ->execute();
  }

}
