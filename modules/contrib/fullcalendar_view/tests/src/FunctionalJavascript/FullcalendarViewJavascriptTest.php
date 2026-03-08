<?php

namespace Drupal\Tests\fullcalendar_view\FunctionalJavascript;

use Drupal\node\Entity\Node;

/**
 * Tests the Fullcalendar View JavaScript functionality.
 *
 * @group fullcalendar_view
 */
class FullcalendarViewJavascriptTest extends FullcalendarViewJavascriptTestBase {

  /**
   * Tests the event title.
   */
  public function testEventTitle() {
    $date_format = 'Y-m-d\\TH:i:s';

    $title_1 = '<script>alert("Hi")</script>';
    $title_2 = "'Test Event & 2'";
    // Create events.
    $this->createEvent($title_1, date($date_format), date($date_format, strtotime('+1 hour')));
    $this->createEvent($title_2, date($date_format, strtotime('+3 hour')), date($date_format, strtotime('+4 hour')));

    $this->drupalGet('/fullcalendar-view-page');
    $assert = $this->assertSession();
    // Check that the calendar contains the events.
    $assert->waitForText($title_1);
    $assert->linkByHrefExists('/node/1');
    $assert->waitForText($title_2);
    $assert->linkByHrefExists('/node/2');

    $list_view = $assert->waitForButton('list');
    $list_view->click();
    $assert->pageTextContains($title_2);
  }

  /**
   * Tests adding event function.
   */
  public function testAddEvent() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/structure/views/view/fullcalendar_view_page/edit/page_1');
    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();
    // Set up the off-canvas function.
    $page->findLink('Settings')->click();
    $assert->waitForButton('Apply');
    $page->find('css', 'details[data-drupal-selector=edit-style-options-display]')->click();
    $page->findField('Create a new event via the Off-Canvas dialog.')->click();
    // Event bundle setting.
    $bundle_field = $assert->waitForField('Event bundle (Content) type');
    $bundle_field->setValue('event');
    $dialog_buttons = $page->find('css', '.ui-dialog-buttonset');
    $dialog_buttons->pressButton('Apply');
    $page->findButton('Save')->click();
    // Go to the calendar view.
    $this->drupalGet('/fullcalendar-view-page');
    $assert->waitForLink('Add event');
    $page->clickLink('Add event');
    // Check if the save button exists.
    $assert->waitForButton('Save');
    $assert->buttonExists('Save');
  }

/**
 * Tests if times appear correctly.
 */
  public function testTimes() {

    // Set timezone for UTC to make sure the test is consistent.
    date_default_timezone_set('UTC');

    $date_format = 'Y-m-d\\TH:i:s';
    $start_date = date($date_format);
    $end_date = date($date_format, strtotime('+1 hour'));
    $this->createEvent('Event Timezone Test', $start_date, $end_date);

    $this->drupalGet('/fullcalendar-view-page');
    $assert = $this->assertSession();
    $page = $this->getSession()->getPage();

    $assert->waitForElement('css', '.fc-time');

    // Format start date to match the expected format.
    $start_time = date('h:i a', strtotime($start_date));
    $displayed_start_time = $page->find('css', '.fc-time')->getText();
    $this->assertEquals($start_time, $displayed_start_time, 'Start time is displayed correctly.');
  }

  /**
   * Tests that unrelated AJAX calls do not reset the calendar.
   */
  public function testAjaxCall() {
    // Create an event to ensure the view has results and the block is rendered.
    $date_format = 'Y-m-d\\TH:i:s';
    $this->createEvent('Test Event', date($date_format), date($date_format, strtotime('+1 hour')));
    $this->createEvent('Another Event', date($date_format, strtotime('+2 days')), date($date_format, strtotime('+2 days +1 hour')));
    $this->createEvent('Event 3', date($date_format, strtotime('+3 days')), date($date_format, strtotime('+3 days +2 hours')));
    $this->createEvent('Event 4', date($date_format, strtotime('-1 day')), date($date_format, strtotime('-1 day +1 hour')));
    $this->createEvent('Event 5', date($date_format, strtotime('+1 week')), date($date_format, strtotime('+1 week +3 hours')));
    $this->createEvent('Event 6', date($date_format, strtotime('+2 weeks')), date($date_format, strtotime('+2 weeks +1 hour')));
    $this->createEvent('The Final Event', date($date_format, strtotime('-5 days')), date($date_format, strtotime('-5 days +2 hours')));

    // Place the calendar view as a block on the event list page.
    $this->drupalPlaceBlock('views_block:fullcalendar_view_page-block_1', [
      'region' => 'content',
    ]);

    // Go to the event list page.
    $this->drupalGet('/event-list');

    $assert_session = $this->assertSession();

    // Wait for the calendar to appear and get its wrapper element.
    $calendar = $assert_session->waitForElement('css', '[data-calendar-display="block_1"]');
    $this->assertNotNull($calendar);

    // Get the current month from the calendar's header.
    $month_header_selector = '.fc-header-toolbar .fc-center h2';
    $calendar_header = $assert_session->waitForElement('css', $month_header_selector);
    $this->assertNotNull($calendar_header);
    $initial_month = $calendar_header->getText();

    // Navigate to the next month.
    $calendar->find('css', '.fc-next-button')->click();

    // Wait for the month to change. The default format is 'Month Year'.
    $next_month_string = date('F Y', strtotime('+1 month'));
    $this->assertSession()->waitForText($next_month_string, 10000, '.block-views-block-fullcalendar-view-page-block-1 .fc-center h2');
    $next_month_text = $calendar->find('css', $month_header_selector)->getText();
    $this->assertNotSame($initial_month, $next_month_text);

    // Trigger an unrelated AJAX action on the page's view.
    $this->clickLink('Go to page 2');
    $assert_session->assertWaitOnAjaxRequest();

    // Check that the calendar has not been reset to the initial month.
    $calendar_header = $calendar->find('css', $month_header_selector);
    $this->assertSame($next_month_text, $calendar_header->getText());
  }

  /**
   * Tests dragging and dropping an event to a new date.
   */
  public function testDragAndDrop() {
    $this->drupalLogin($this->adminUser);
    
    // Update the view configuration to use the all-day date fields.
    $this->changeToAllDayEventView();

    // Date format for all-day events.
    $date_format = 'Y-m-d';

    // Create an event for a known date. Using a future date avoids issues with
    // the current date being at the end of a month.
    $start_date = date($date_format);
    $end_date = date($date_format, strtotime('+1 day'));

    // For a single all-day event, start and end dates are the same.
    $this->createAllDayEvent('Draggable Event', $start_date, $end_date);
    $node = $this->drupalGetNodeByTitle('Draggable Event');
    $this->assertNotNull($node, 'Event node created.');
    $nid = $node->id();

    // Go to the calendar page, with the correct date displayed.
    $this->drupalGet('/fullcalendar-view-page', ['query' => ['initialDate' => $start_date]]);

    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();

    $event_element_wrapper = $assert->waitForElement('css', '.fc-event');
    $this->assertTrue($event_element_wrapper->hasClass('fc-day-grid-event'), 'Event is an all-day event.');

    // Find the event element on the calendar.
    // Select by the title so dragging works predictably.
    $event_selector = '.fc-event .fc-title';
    $event_element = $assert->waitForElement('css', $event_selector);
    $this->assertNotNull($event_element);
    $this->assertStringContainsString('Draggable Event', $event_element->getText());

    // Find the drop target cell. We'll move the event 1 day forward.
    $target_date = date($date_format, strtotime('+1 day'));
    // In FullCalendar v4, the data-date attribute is on the td.
    $target_cell_selector = 'td[data-date="' . $target_date . '"]';
    $target_cell = $page->find('css', $target_cell_selector);
    $this->assertNotNull($target_cell, "Target cell for {$target_date} not found.");

    // Use JavaScript-based drag simulation because Selenium's
    // native dragTo() silently fails in headless Chrome.
    $this->jsDragTo($event_selector, $target_cell_selector);

    // Wait for the alert to appear and then accept it.
    $this->waitForAlertAndAccept();

    $assert->assertWaitOnAjaxRequest(5000, 'AJAX request did not complete in time.');
    
    $expected_end_date = date($date_format, strtotime('+2 day'));

    // Verify the change in the database.
    $this->container->get('entity_type.manager')->getStorage('node')->resetCache([$nid]);
    $updated_node = Node::load($nid);
    
    $new_start_value = $updated_node->get('field_all_day_start_date')->value;
    $new_end_value = $updated_node->get('field_all_day_end_date')->value;

    $this->assertEquals($target_date, $new_start_value, 'All-day start date was updated correctly in the database.');
    $this->assertEquals($expected_end_date, $new_end_value, 'All-day end date was updated correctly in the database.');
  }

  /**
   * Tests resizing an event to extend its duration.
   */
  public function testResizeEvent() {
    $this->drupalLogin($this->adminUser);

    // Update the view configuration to use the all-day date fields.
    $this->changeToAllDayEventView();

    // Date format for all-day events.
    $date_format = 'Y-m-d';

    // Create an event for a known date. Using a future date avoids issues with
    // the current date being at the end of a month.
    $start_date = date($date_format);
    $end_date = date($date_format, strtotime('+1 day'));

    // For a single all-day event, the end date is the day after the start date.
    $this->createAllDayEvent('Resizable Event', $start_date, $end_date);
    $node = $this->drupalGetNodeByTitle('Resizable Event');
    $this->assertNotNull($node, 'Event node created.');
    $nid = $node->id();

    // Go to the calendar page, with the correct date displayed.
    $this->drupalGet('/fullcalendar-view-page', ['query' => ['initialDate' => $start_date]]);

    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();

    // Find the event element on the calendar.
    // For a multi-day event, we must find the end segment to get the handle.
    $event_element = $assert->waitForElement('css', '.fc-event.fc-end:contains("Resizable Event")');
    $this->assertNotNull($event_element);

    // Hover over the event to make the resize handle visible.
    $event_element->mouseOver();

    // Find the resize handle. In month view, this is on the right edge.
    $resize_handle = $event_element->find('css', '.fc-end-resizer');
    $this->assertNotNull($resize_handle, 'Resize handle found.');

    // Find the drop target cell. We'll resize the event to end two days later.
    $target_date_string = date($date_format, strtotime('+2 day'));
    $target_cell_selector = 'td[data-date="' . $target_date_string . '"]';
    $target_cell = $page->find('css', $target_cell_selector);
    $this->assertNotNull($target_cell, "Target cell for {$target_date_string} not found.");

    // Use JavaScript-based drag simulation because Selenium's
    // native dragTo() silently fails in headless Chrome.
    $resize_selector = '.fc-event.fc-end .fc-end-resizer';
    $this->jsDragTo($resize_selector, $target_cell_selector);

    // Wait for the alert to appear and then accept it.
    $this->waitForAlertAndAccept();

    // Wait for the AJAX request that saves the event to complete.
    $assert->assertWaitOnAjaxRequest(5000, 'AJAX request did not complete in time.');

    // Verify the change in the database.
    $this->container->get('entity_type.manager')->getStorage('node')->resetCache([$nid]);
    $updated_node = Node::load($nid);
    $new_end_value = $updated_node->get('field_all_day_end_date')->value;

    // The end date for all-day events is inclusive. When we resize to include
    // the cell for $target_date_string, the new end date is that date.
    $this->assertEquals($target_date_string, $new_end_value, 'End date was updated correctly in the database after resize.');

    // Also verify the start date has not changed.
    $new_start_value = $updated_node->get('field_all_day_start_date')->value;
    $this->assertEquals($start_date, $new_start_value, 'Start date was not changed after resize.');
  }
}
