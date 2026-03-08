<?php

namespace Drupal\Tests\fullcalendar_view\FunctionalJavascript;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\NodeType;

/**
 * Provides a base class for Fullcalendar view JavaScript tests.
 */
abstract class FullcalendarViewJavascriptTestBase extends WebDriverTestBase {

  /**
   * The default theme used during test execution.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * The admin user account used in tests.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'datetime',
    'node',
    'views',
    'views_ui',
    'field',
    'field_ui',
    'fullcalendar_view',
    'fullcalendar_test',
    'user',
    'js_testing_ajax_request_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->getSession()->resizeWindow(1920, 1080);
    $this->createEventContentType();
    $this->adminUser = $this->createAdminUser();
  }

  /**
   * Creates the "event" content type and required fields.
   */
  protected function createEventContentType() {
    // Create a new content type for events if it doesn't exist.
    if (!NodeType::load('event')) {
      $event_type = NodeType::create([
        'type' => 'event',
        'name' => 'Event',
      ]);
      $event_type->save();

      // Add a start date field to the event content type.
      FieldStorageConfig::create([
        'field_name' => 'field_start_date',
        'entity_type' => 'node',
        'type' => 'datetime',
      ])->save();

      FieldConfig::create([
        'field_name' => 'field_start_date',
        'entity_type' => 'node',
        'bundle' => 'event',
        'label' => 'Start Date',
      ])->save();

      // Add an end date field to the event content type.
      FieldStorageConfig::create([
        'field_name' => 'field_end_date',
        'entity_type' => 'node',
        'type' => 'datetime',
      ])->save();

      FieldConfig::create([
        'field_name' => 'field_end_date',
        'entity_type' => 'node',
        'bundle' => 'event',
        'label' => 'End Date',
      ])->save();

      // Add an all day start date field to the event content type.
      FieldStorageConfig::create([
        'field_name' => 'field_all_day_start_date',
        'entity_type' => 'node',
        'type' => 'datetime',
        'settings' => [
          'datetime_type' => 'date',
        ],
      ])->save();

      FieldConfig::create([
        'field_name' => 'field_all_day_start_date',
        'entity_type' => 'node',
        'bundle' => 'event',
        'label' => 'All-day Start Date',
      ])->save();

      // Add an all day end date field to the event content type.
      FieldStorageConfig::create([
        'field_name' => 'field_all_day_end_date',
        'entity_type' => 'node',
        'type' => 'datetime',
        'settings' => [
          'datetime_type' => 'date',
        ],
      ])->save();

      FieldConfig::create([
        'field_name' => 'field_all_day_end_date',
        'entity_type' => 'node',
        'bundle' => 'event',
        'label' => 'All-day End Date',
      ])->save();
    }
  }

  /**
   * Creates an event for testing.
   *
   * @param string $title
   *   The title of the event.
   * @param string $start
   *   The start date of the event in 'Y-m-d\TH:i:s' format.
   * @param string $end
   *   The end date of the event in 'Y-m-d\TH:i:s' format.
   */
  protected function createEvent($title, $start, $end) {
    $event = [
      'type' => 'event',
      'title' => $title,
      'field_start_date' => $start,
      'field_end_date' => $end,
    ];
    $this->drupalCreateNode($event);
  }

  /**
   * Creates an all-day event for testing.
   *
   * @param string $title
   *   The title of the event.
   * @param string $start
   *   The start date of the event in 'Y-m-d' format.
   * @param string $end
   *   The end date of the event in 'Y-m-d' format.
   */
  protected function createAllDayEvent($title, $start, $end) {
    $event = [
      'type' => 'event',
      'title' => $title,
      'field_all_day_start_date' => $start,
      'field_all_day_end_date' => $end,
    ];
    $this->drupalCreateNode($event);
  }

  /**
   * Creates an admin user with necessary permissions.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   The admin user account.
   */
  protected function createAdminUser() {
    // Define the permissions required by the admin user for the tests.
    $permissions = [
      'administer site configuration',
      'administer content types',
      'bypass node access',
      'administer nodes',
      'administer blocks',
      'access content',
      'administer views',
      'create event content',
    ];

    return $this->drupalCreateUser($permissions, 'admin_user', TRUE);
  }

  /**
   * Changes the fullcalendar_view_page view to use all-day event fields.
   */
  protected function changeToAllDayEventView() {
    $view = \Drupal::configFactory()->getEditable('views.view.fullcalendar_view_page');
    $displays = $view->get('display');
    $display = $displays['default'];
    $fields = $display['display_options']['fields'];
    $fields['field_all_day_start_date'] = [
      'id' => 'field_all_day_start_date',
      'table' => 'node__field_all_day_start_date',
      'field' => 'field_all_day_start_date',
      'plugin_id' => 'field',
      'label' => "",
      'admin_label' => "",
    ];
    $fields['field_all_day_end_date'] = [
      'id' => 'field_all_day_end_date',
      'table' => 'node__field_all_day_end_date',
      'field' => 'field_all_day_end_date',
      'plugin_id' => 'field',
      'label' => "",
      'admin_label' => "",
    ];
    $display['display_options']['fields'] = $fields;

    $style_options = $display['display_options']['style'];
    $style_options['options']['start'] = 'field_all_day_start_date';
    $style_options['options']['end'] = 'field_all_day_end_date';
    $display['display_options']['style'] = $style_options;

    $displays['default'] = $display;
    $view->set('display', $displays);
    $view->save();
  }

  /**
   * Simulates drag-and-drop using JavaScript mouse events.
   *
   * Selenium's native dragTo() is unreliable in headless Chrome
   * and Drupal's DrupalSelenium2Driver::dragTo() silently
   * swallows all exceptions from it. This method dispatches
   * mousedown/mousemove/mouseup events directly in the browser,
   * which FullCalendar correctly responds to.
   *
   * @param string $source_css
   *   A CSS selector for the element to drag.
   * @param string $target_css
   *   A CSS selector for the drop target element.
   */
  protected function jsDragTo($source_css, $target_css) {
    $source_css = addslashes($source_css);
    $target_css = addslashes($target_css);
    $js = <<<JS
(function() {
  var src = document.querySelector('$source_css');
  var tgt = document.querySelector('$target_css');
  if (!src || !tgt) {
    throw new Error('Source or target not found');
  }
  var srcRect = src.getBoundingClientRect();
  var tgtRect = tgt.getBoundingClientRect();
  var srcX = Math.round(
    srcRect.left + srcRect.width / 2);
  var srcY = Math.round(
    srcRect.top + srcRect.height / 2);
  var tgtX = Math.round(
    tgtRect.left + tgtRect.width / 2);
  var tgtY = Math.round(
    tgtRect.top + tgtRect.height / 2);
  var opts = {
    bubbles: true,
    cancelable: true,
    view: window
  };
  src.dispatchEvent(new MouseEvent('mousedown',
    Object.assign({}, opts,
      {clientX: srcX, clientY: srcY})));
  var steps = 5;
  for (var i = 1; i <= steps; i++) {
    var x = srcX + (tgtX - srcX) * i / steps;
    var y = srcY + (tgtY - srcY) * i / steps;
    var el = document.elementFromPoint(x, y) || tgt;
    el.dispatchEvent(new MouseEvent('mousemove',
      Object.assign({}, opts,
        {clientX: x, clientY: y})));
  }
  tgt.dispatchEvent(new MouseEvent('mouseup',
    Object.assign({}, opts,
      {clientX: tgtX, clientY: tgtY})));
})();
JS;
    $this->getSession()->executeScript($js);
  }

  /**
   * Waits for a JavaScript alert and accepts it.
   *
   * @param int $timeout
   *   The timeout in seconds.
   *
   * @throws \Exception
   *   If the alert does not appear within the timeout.
   */
  protected function waitForAlertAndAccept(int $timeout = 15) {
    $session = $this->getSession();
    $page = $session->getPage();

    $result = $page->waitFor($timeout, function () use ($session) {
      try {
        $session->getDriver()
          ->getWebDriverSession()->alert()->getText();
        return TRUE;
      }
      catch (\Exception $e) {
        return FALSE;
      }
    });

    if (!$result) {
      throw new \Exception(
        "Timed out waiting for a JavaScript alert "
        . "after {$timeout} seconds."
      );
    }

    $session->getDriver()
      ->getWebDriverSession()->alert()->accept();
  }

}
