<?php

declare(strict_types=1);

namespace Drupal\Tests\maestro\Kernel;

use Drupal;
use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\maestro\Entity\MaestroQueue;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;

use const TASK_STATUS_FALSE_BRANCH;
use const TASK_STATUS_SUCCESS;

/**
 * Advanced Regeneration Test.
 *
 * @group maestro
 */
final class AdvancedRegenerationTest extends KernelTestBase {

  /**
   * A test user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $testUser;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field_ui',
    'field',
    'user',
    'text',
    'node',
    'system',
    'views',
    'views_ui',
    'maestro',
    'maestro_test_maestro_templates',
    'maestro_utilities',
  ];

  protected MaestroEngine $maestroEngine;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('maestro_template');
    $this->installEntitySchema('maestro_queue');
    $this->installEntitySchema('maestro_process');
    $this->installEntitySchema('maestro_entity_identifiers');
    $this->installEntitySchema('maestro_process_variables');
    $this->installEntitySchema('maestro_process_status');
    $this->installEntitySchema('maestro_production_assignments');

    $this->installConfig([
      'field',
      'node',
      'user',
      'views', 
      'views_ui',
      'maestro',
      'maestro_test_maestro_templates',
    ]);

    $this->maestroEngine = new MaestroEngine();

    // Clear permissions for authenticated users.
    $this->config('user.role.' . RoleInterface::AUTHENTICATED_ID)
      ->set('permissions', [])
      ->save();

    // Create a maestro user for testing.
    // We use this user's context for tests
    $this->testUser = User::create([
      'name' => 'maestrouser',
      'mail' => 'maestrouser@example.com',
      'status' => 1, // Active user.
    ]);
    $this->testUser->save();

    // Set the user as the current user.
    $this->setCurrentUser($this->testUser);
  }

  /**
   * Sets the provided user as the current user.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user account to set.
   */
  protected function setCurrentUser(AccountInterface $user): void {
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = $this->container->get('account_switcher');
    $account_switcher->switchTo($user);
  }

  /**
   * The advanced_regen template contains a triple parallel branch regen loop.
   * This test tests the left-most simple loop-back to the top-most ancestor.
   */
  public function testSimpleTopAncestorRegen() : void {
    $process_id = $this->maestroEngine->newProcess('advanced_regen');

    // This moves the process 2 steps to the first executable task.
    $this->maestroEngine->cleanQueue();
    $this->maestroEngine->cleanQueue();
    $assigned_tasks = MaestroEngine::getAssignedTaskQueueIds($this->testUser->id());
    $this->assertIsArray($assigned_tasks, 'The assigned tasks should be an array.');
    // Now, for each of the assigned tasks, we should have a queue ID 3.
    $this->assertEquals([3], $assigned_tasks, 'Assigned tasks should be [3]');
   
    // Complete this task, Tasks **labelled** 2 and 3 are created
    MaestroEngine::completeTask(3);
    $this->maestroEngine->cleanQueue();
    $assigned_tasks = MaestroEngine::getAssignedTaskQueueIds($this->testUser->id());
    // Queue IDs for **labelled** tasks 2,3 are 4,5
    $this->assertEquals([4,5], $assigned_tasks, 'Assigned tasks should be [4,5]');

    // We will complete both tasks
    MaestroEngine::completeTask(4);
    MaestroEngine::completeTask(5);
    $this->maestroEngine->cleanQueue();
    $assigned_tasks = MaestroEngine::getAssignedTaskQueueIds($this->testUser->id());
    // Queue IDs for **labelled** tasks 4,5 are 6,7
    $this->assertEquals([6,7], $assigned_tasks, 'Assigned tasks should be [6,7]');

    // At this point, task labelled "4" is a regen task. If completed, task
    // labelled "5" would be regenerated out of existence.  Let's try.
    MaestroEngine::completeTask(6);
    $this->maestroEngine->cleanQueue();
    // Regeneration should have happened as labelled task "4" points back
    // to the SPV task.  Let's test.
    $task_labelled_5 = MaestroEngine::getQueueEntryById(7);
    $this->assertEquals(TASK_ARCHIVE_NORMAL, $task_labelled_5->archived->value, 'Task labelled 5 should be archived, but is not.');
    $this->assertEquals(TASK_STATUS_SUCCESS, $task_labelled_5->status->value, 'Task labelled 5 should have a status of successful, but is not.');

    // There should be one open task. The SPV task at Queue ID 8.
    $spv_task = MaestroEngine::getQueueEntryById(8);
    $this->assertEquals(TASK_ARCHIVE_ACTIVE, $spv_task->archived->value, 'SPV task should be unarchived, but is not.');
    $this->assertEquals(TASK_STATUS_ACTIVE, $spv_task->status->value, 'SPV Task should have a status of active, but is not.');
  }

  /**
   * The advanced_regen template contains a triple parallel branch regen loop.
   * This test tests the rightmost simple loop-back to the top of the
   * third branch ancestor.
   */
  public function testRightmostRegen() : void {
    $process_id = $this->maestroEngine->newProcess('advanced_regen');

    // This moves the process 2 steps to the first executable task.
    $this->maestroEngine->cleanQueue();
    $this->maestroEngine->cleanQueue();
    MaestroEngine::completeTask(3); // Labelled task "1".
    $this->maestroEngine->cleanQueue();
    MaestroEngine::completeTask(4); // Labelled task "2".
    MaestroEngine::completeTask(5); // Labelled task "3".
    $this->maestroEngine->cleanQueue();
    MaestroEngine::completeTask(7); // Labelled task "5".
    $this->maestroEngine->cleanQueue();
    MaestroEngine::completeTask(9); // Labelled task "7".
    $this->maestroEngine->cleanQueue();
    MaestroEngine::completeTask(10); // Labelled task "8".
    $this->maestroEngine->cleanQueue();
    // At this point, open tasks should be labelled tasks 4,6,9.
    $all_queue_Tasks = MaestroQueue::loadMultiple();
    // Test if 4,6,9 are open.
    // They translate to Queue IDs 6,8,11
    $this::assertEquals(TASK_STATUS_ACTIVE, $all_queue_Tasks[6]->status->value, 'Task labelled "4" is not active when it should be.');
    $this::assertEquals(TASK_STATUS_ACTIVE, $all_queue_Tasks[8]->status->value, 'Task labelled "6" is not active when it should be.');
    $this::assertEquals(TASK_STATUS_ACTIVE, $all_queue_Tasks[11]->status->value, 'Task labelled "9" is not active when it should be.');
    
    // Now, complete task labelled "9".
    MaestroEngine::completeTask(11); // Labelled task "9".
    $this->maestroEngine->cleanQueue();
    // IF task created and ready.
    // Executing this next task means we loop back OVER 6 back to 5.
    // 6 should be archived and not active.  Task labelled "5" created.
    $this->maestroEngine->cleanQueue();
    $all_queue_Tasks = MaestroQueue::loadMultiple();

    $this::assertEquals(TASK_STATUS_ACTIVE, $all_queue_Tasks[6]->status->value, 'Task labelled "4" is not active when it should be.'); // Task labelled "4".
    $this::assertNotEquals(TASK_STATUS_ACTIVE, $all_queue_Tasks[8]->status->value, 'Task labelled "6" is active when it should not be.'); // Task labelled "6".
    $this::assertEquals(TASK_STATUS_ACTIVE, $all_queue_Tasks[13]->status->value, 'Task labelled "5" is not active when it should be.');
  }

  /**
   * The advanced_regen template contains a triple parallel branch regen loop.
   * This test tests the middle loop-back which would remove the rightmost
   * loop and regenerate only the parent of the middle and right branches.
   */
  public function testMiddleRegen() : void {
    $process_id = $this->maestroEngine->newProcess('advanced_regen');

    // This moves the process 2 steps to the first executable task.
    $this->maestroEngine->cleanQueue();
    $this->maestroEngine->cleanQueue();
    MaestroEngine::completeTask(3); // Labelled task "1".
    $this->maestroEngine->cleanQueue();
    MaestroEngine::completeTask(4); // Labelled task "2".
    MaestroEngine::completeTask(5); // Labelled task "3".
    $this->maestroEngine->cleanQueue();
    MaestroEngine::completeTask(7); // Labelled task "5".
    $this->maestroEngine->cleanQueue();
    MaestroEngine::completeTask(9); // Labelled task "7".
    $this->maestroEngine->cleanQueue();
    MaestroEngine::completeTask(10); // Labelled task "8".
    $this->maestroEngine->cleanQueue();
    // At this point, open tasks should be labelled tasks 4,6,9.
    $all_queue_Tasks = MaestroQueue::loadMultiple();
    // Test if 4,6,9 are open.
    // They translate to Queue IDs 6,8,11
    $this::assertEquals(TASK_STATUS_ACTIVE, $all_queue_Tasks[6]->status->value, 'Task labelled "4" is not active when it should be.');
    $this::assertEquals(TASK_STATUS_ACTIVE, $all_queue_Tasks[8]->status->value, 'Task labelled "6" is not active when it should be.');
    $this::assertEquals(TASK_STATUS_ACTIVE, $all_queue_Tasks[11]->status->value, 'Task labelled "9" is not active when it should be.');
    
    // Now, complete task labelled "6".
    MaestroEngine::completeTask(8); // Labelled task "6".
    $this->maestroEngine->cleanQueue();
    // This will now loop back to task labelled "3".
    // Task 9 should be closed and only labelled tasks 4 and 3 should exist.
    $all_queue_Tasks = MaestroQueue::loadMultiple();

    $this::assertEquals(TASK_STATUS_ACTIVE, $all_queue_Tasks[6]->status->value, 'Task labelled "4" is not active when it should be.'); // Task labelled "4".
    $this::assertEquals(TASK_STATUS_ACTIVE, $all_queue_Tasks[12]->status->value, 'Task labelled "3" is not active when it should be.');
  }

  /**
   * The advanced_regen template contains a triple parallel branch regen loop.
   * This test tests the left loop-back when the middle and right are fully
   * populated with tasks.  This regen will remove the middle and right
   * task branches and should only be left with the SPV.
   */
  public function testFullyPopulatedFullLoopBackRegen() : void {
    $process_id = $this->maestroEngine->newProcess('advanced_regen');

    // This moves the process 2 steps to the first executable task.
    $this->maestroEngine->cleanQueue();
    $this->maestroEngine->cleanQueue();
    MaestroEngine::completeTask(3); // Labelled task "1".
    $this->maestroEngine->cleanQueue();
    MaestroEngine::completeTask(4); // Labelled task "2".
    MaestroEngine::completeTask(5); // Labelled task "3".
    $this->maestroEngine->cleanQueue();
    MaestroEngine::completeTask(7); // Labelled task "5".
    $this->maestroEngine->cleanQueue();
    MaestroEngine::completeTask(9); // Labelled task "7".
    $this->maestroEngine->cleanQueue();
    MaestroEngine::completeTask(10); // Labelled task "8".
    $this->maestroEngine->cleanQueue();
    // At this point, open tasks should be labelled tasks 4,6,9.
    $assigned_tasks = MaestroEngine::getAssignedTaskQueueIds($this->testUser->id());
    $this::assertCount(3, $assigned_tasks, 'There should be three assigned tasks.');
    // Now, complete task labelled "4".
    MaestroEngine::completeTask(6); // Labelled task "4".
    $this->maestroEngine->cleanQueue();
    // This will now loop back to the first task after START: SPV task.
    // The only open task should be the new SPV (Queue ID 12).
    $all_queue_Tasks = MaestroQueue::loadMultiple();
    $assigned_tasks = MaestroEngine::getAssignedTaskQueueIds($this->testUser->id());

    $this::assertCount(0, $assigned_tasks, 'There should be zero assigned tasks, yet there are tasks assigned.');
    $this::assertEquals(TASK_STATUS_SUCCESS, $all_queue_Tasks[6]->status->value, 'Task labelled "4" is not completed when it should be completed.'); // Task labelled "4".
    $this::assertEquals(TASK_STATUS_ACTIVE, $all_queue_Tasks[12]->status->value, 'Task labelled "Increment Count" is not active when it should be.');
  }
}
