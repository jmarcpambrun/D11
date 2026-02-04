<?php

namespace Drupal\Tests\maestro\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\maestro\Entity\MaestroProcess;
use Drupal\maestro\Entity\MaestroQueue;
use Drupal\maestro\Entity\MaestroTemplate;
use Drupal\maestro\Plugin\EngineTasks\MaestroInteractiveTask;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;

/**
 * Tests for the MaestroEngine class.
 *
 * @group maestro
 */
class MaestroEngineTest extends KernelTestBase {

  /**
   * The MaestroEngine instance.
   *
   * @var \Drupal\maestro\Engine\MaestroEngine
   */
  protected $maestroEngine;

  /**
   * The template used when testing tasks, queues, processes etc.
   */
  protected $template_machine_name = 'simple_parallel_to_serial';

  /**
   * The task machine name when testing tasks, queues, processes etc.
   */
  protected $template_task_machine_name = 'int3';

  /**
   * A test user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $testUser;

  /**
   * Modules to install.
   *
   * @var string[]
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
  ];

  /**
   * Set up the kernel test environment.
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
      'maestro'
    ]);

    // Clear permissions for authenticated users.
    $this->config('user.role.' . RoleInterface::AUTHENTICATED_ID)
      ->set('permissions', [])
      ->save();

    $this->maestroEngine = new MaestroEngine();

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
   * Tests the constructor.
   */
  public function testConstructor() {
    $this->assertInstanceOf(MaestroEngine::class, $this->maestroEngine);
  }

  /**
   * Tests getTemplate() static method.
   * 
   * Ensures that the getTemplate method after install can load the Parallel to Serial
   * template and that the template is a MaestroTemplate object.
   */
  public function testGetTemplate() {
    $template = MaestroEngine::getTemplate($this->template_machine_name);
    $this->assertInstanceOf(MaestroTemplate::class, $template, 'The template should be an instance of MaestroTemplate.');
    $this->assertNotEmpty($template, 'The template should not be empty.');
  }

  /**
   * Tests getTemplates() static method.
   * 
   * Ensures that the getTemplates method after install can load all of the templates 
   * and that the templates are MaestroTemplate objects.
   */
  public function testGetTemplates() {
    $templates = MaestroEngine::getTemplates();
    $this->assertIsArray($templates, 'The templates should be an array.');
    foreach($templates as $template) {
      $this->assertInstanceOf(\Drupal\maestro\Entity\MaestroTemplate::class, $template, 'The template should be an instance of MaestroTemplate.');
    }
  }

  /**
   * Tests getTemplateTaskByID() static method.
   * 
   * Test that the method returns the appropriate task ID based on installed Maestro
   * configs.
   */
  public function testGetTemplateTaskByID() {
    $template_task = MaestroEngine::getTemplateTaskByID($this->template_machine_name, $this->template_task_machine_name);
    $this->assertNotEmpty($template_task, 'The template task should not be empty.');
    $this->assertEquals($this->template_task_machine_name, $template_task['id'], 'The template task ID should be int3.');
    $this->assertIsArray($template_task, 'The template task should be an array.');
  }

   /**
   * Tests getTemplateTaskByQueueID() static method.
   * 
   * Generate a new process and get the task by queue ID.
   * First task ID will be the Start task, thus ID 1.
   * 
   */
  public function testGetTemplateTaskByQueueID() {
    $process_id = $this->maestroEngine->newProcess($this->template_machine_name);
    $this->assertEquals(1, $process_id, 'The process ID should be 1.');
    
    $template_task = MaestroEngine::getTemplateTaskByQueueID(1);
    $this->assertIsArray($template_task, 'The template task should be an array.');
    $this->assertEquals('start', $template_task['id'], 'The template task ID should be start.');
  }

  /**
   * Tests getTemplateVariables() static method.
   * 
   * Get the template variables from the installed Maestro template.
   * The "initiator" variable is installed with the simple_parallel_to_serial template.
   */
  public function testGetTemplateVariables() {
    $template_variables = MaestroEngine::getTemplateVariables($this->template_machine_name); 
    $this->assertIsArray($template_variables, 'The template task should be an array.');
    $this->assertIsArray($template_variables['initiator'], 'The template variable should be an array.');
    $this->assertEquals('initiator', $template_variables['initiator']['variable_id'], 'The template variable ID should be initiator.');
  }
  
  /**
   * Tests getTemplateIdFromProcessId() static method.
   * 
   * Generate a new process and get the template ID by process ID.
   * First process ID will always be 1.
   * 
   */
  public function testGetTemplateIdFromProcessId() {
    $process_id = $this->maestroEngine->newProcess($this->template_machine_name);
    $this->assertEquals(1, $process_id, 'The process ID should be 1.');
    
    $template_id = MaestroEngine::getTemplateIdFromProcessId($process_id);
    $this->assertEquals($this->template_machine_name, $template_id, 'The template ID should be simple_parallel_to_serial.');
  }

  /**
   * Tests saveTemplateTask() static method.
   * 
   * Load the Parallel to Serial template and save a task by changing its label.
   * The label should be different after saving.
   * 
   */
  public function testSaveTemplateTask() {
    $template_task = MaestroEngine::getTemplateTaskByID($this->template_machine_name, $this->template_task_machine_name);
    $this->assertNotEmpty($template_task, 'The template task should not be empty.');
    $this->assertEquals('Interactive 3', $template_task['label'], 'The template label should be Interactive 3.');
    $this->assertIsArray($template_task, 'The template task should be an array.');

    // Now change the name of the task
    $template_task['label'] = 'New Task Name';
    // Save the template task label change
    MaestroEngine::saveTemplateTask($this->template_machine_name, $this->template_task_machine_name, $template_task);
    // Reload the task from the saved template
    $template_task = MaestroEngine::getTemplateTaskByID($this->template_machine_name, $this->template_task_machine_name);
    $this->assertEquals('New Task Name', $template_task['label'], 'The template label should be New Task Name.');
  }

  /**
   * Tests removeTemplateTask() static method.
   * 
   * Load the Parallel to Serial template and remove a task.
   * 
   */
  public function testRemoveTemplateTask() {
    $template_task = MaestroEngine::getTemplateTaskByID($this->template_machine_name, $this->template_task_machine_name);
    $this->assertNotEmpty($template_task, 'The template task should not be empty.');
    $this->assertEquals('Interactive 3', $template_task['label'], 'The template label should be Interactive 3.');
    $this->assertIsArray($template_task, 'The template task should be an array.');

    // Now remove the task
    $remove_return = MaestroEngine::removeTemplateTask($this->template_machine_name, $this->template_task_machine_name);
    $this->assertIsNumeric($remove_return, 'The remove return should not be false.');
    $template_task = MaestroEngine::getTemplateTaskByID($this->template_machine_name, $this->template_task_machine_name);
    $this->assertEmpty($template_task, 'The template task should be empty.');
  }

  /**
   * Tests getTaskPointersFromTemplate() static method.
   * 
   * Load the Parallel to Serial template and get task pointers.
   * 
   */
  public function testGetTaskPointersFromTemplate() {
    $task_pointers = MaestroEngine::getTaskPointersFromTemplate($this->template_machine_name, $this->template_task_machine_name);
    $this->assertIsArray($task_pointers, 'The task pointers should be an array.');
    $this->assertNotEmpty($task_pointers, 'The task pointers should not be empty.');
  }

  /**
   * Tests getTaskIdFromQueueId() static method.
   * 
   * Start a Maestro process and get the task ID from the queue ID.
   * The queueID will be 1 for the Start task.
   * 
   */
  public function testGetTaskIdFromQueueId() {
    $this->maestroEngine->newProcess($this->template_machine_name);
    $task_id = MaestroEngine::getTaskIdFromQueueId(1);
    $this->assertEquals('start', $task_id, 'The task ID should be start.');
  }

  /**
   * Tests getTokenFromQueueId() static method.
   * 
   * Start a Maestro process and get the token from the queue ID.
   * The queueID will be 1 for the Start task.
   * 
   */
  public function testGetTokenFromQueueId() {
    $this->maestroEngine->newProcess($this->template_machine_name);
    $token = MaestroEngine::getTokenFromQueueId(1);
    $this->assertNotEmpty($token, 'The token should not be empty.');
  }

  /**
   * Tests getQueueIdFromToken() static method.
   * 
   * Start a Maestro process and get the token.
   * Then use that token to get the queue ID.
   * 
   */
  public function testGetQueueIdFromToken() {
    $this->maestroEngine->newProcess($this->template_machine_name);
    $token = MaestroEngine::getTokenFromQueueId(1);
    $queue_id = MaestroEngine::getQueueIdFromToken($token);
    $this->assertEquals(1, $queue_id, 'The queue ID should be 1.');
  }

  /**
   * Tests getProcessIdFromQueueId() static method.
   * 
   * Start a Maestro process. The process ID will be 1 for a queue ID of 1.
   * 
   */
  public function testGetProcessIdFromQueueId() {
    $this->maestroEngine->newProcess($this->template_machine_name);
    $process_id = MaestroEngine::getProcessIdFromQueueId(1);
    $this->assertEquals(1, $process_id, 'The process ID should be 1.');
  }

  /**
   * Tests getProcessVariable() static method.
   * 
   * Start a Maestro process. Get the value of the initiator variable.
   * Since this is all anonymous, the initiator will be ''.
   * 
   */
  public function testGetProcessVariable() {
    $process_id = $this->maestroEngine->newProcess($this->template_machine_name);
    $initiator = MaestroEngine::getProcessVariable('initiator', $process_id);

    $this->assertEquals('maestrouser', $initiator, 'The initiator should be maestrouser.');
    $this->assertIsNotBool($initiator, 'The initiator should not be a boolean.');
  }

  /**
   * Tests setProcessVariable() static method.
   * 
   * Start a Maestro process. Set the value of the initiator variable.
   * 
   */
  public function testSetProcessVariable() {
    $process_id = $this->maestroEngine->newProcess($this->template_machine_name);
    MaestroEngine::setProcessVariable('initiator', 'test', $process_id);  
    $initiator = MaestroEngine::getProcessVariable('initiator', $process_id);

    $this->assertEquals('test', $initiator, 'The initiator should be "test".');
    $this->assertIsNotBool($initiator, 'The initiator should not be a boolean.');
  }

  /**
   * Tests getProcessVariableID() static method.
   * 
   * Start a Maestro process. Get the ID of the initiator variable.
   * The ID should be 1 as the initiator is the first variable created.
   * 
   * 
   */
  public function testGetProcessVariableID() {
    $process_id = $this->maestroEngine->newProcess($this->template_machine_name);
    $initiator_id = MaestroEngine::getProcessVariableID('initiator', $process_id);

    $this->assertIsNumeric($initiator_id, 'The initiator ID should be numeric.');
    $this->assertEquals(1, $initiator_id, 'The initiator ID should be 1.');
  }

  /**
   * Tests setProductionTaskLabel() static method.
   * 
   * Start a Maestro process. Then set the label of the first task.
   * 
   */
  public function testSetProductionTaskLabel() {
    $process_id = $this->maestroEngine->newProcess($this->template_machine_name);
    MaestroEngine::setProductionTaskLabel(1, 'New Task Label');
    $queue_entry = MaestroEngine::getQueueEntryById(1);
    $task_label = $queue_entry->get('task_label')->value;
    $this->assertEquals('New Task Label', $task_label, 'The task label should be "New Task Label".');
  }

  /**
   * Tests completeTask() static method.
   * 
   * Start a Maestro process. Complete the first task and check its status.
   * 
   */
  public function testCompleteTask() {
    $process_id = $this->maestroEngine->newProcess($this->template_machine_name);
    MaestroEngine::completeTask(1);
    $queue_entry = MaestroEngine::getQueueEntryById(1);
    $task_status = $queue_entry->get('status')->value;
    $this->assertEquals(TASK_STATUS_SUCCESS, $task_status, 'The task status should be "completed".');
  }

  /**
   * Tests setTaskStatus() static method.
   * 
   * Start a Maestro process. Set the status of the first task to ASK_STATUS_ABORTED.
   * 
   */
  public function testSetTaskStatus() {
    $process_id = $this->maestroEngine->newProcess($this->template_machine_name);
    MaestroEngine::setTaskStatus(1, TASK_STATUS_ABORTED);
    $queue_entry = MaestroEngine::getQueueEntryById(1);
    $task_status = $queue_entry->get('status')->value;
    $this->assertEquals(TASK_STATUS_ABORTED, $task_status, 'The task status should be aborted.');
  }

  /**
   * Tests archiveTask() static method.
   * 
   * Start a Maestro process. Archive the first task.
   * 
   */
  public function testArchiveTask() {
    $process_id = $this->maestroEngine->newProcess($this->template_machine_name);
    MaestroEngine::archiveTask(1);
    $queue_entry = MaestroEngine::getQueueEntryById(1);
    $task_status = $queue_entry->get('archived')->value;
    
    $this->assertEquals(TASK_ARCHIVE_NORMAL, $task_status, 'The task status should be archived.');
  }

  /**
   * Tests unArchiveTask() static method.
   * 
   * Start a Maestro process. Archive the first task. Then Unarchive it.
   * 
   */
  public function testUnArchiveTask() {
    $process_id = $this->maestroEngine->newProcess($this->template_machine_name);
    MaestroEngine::archiveTask(1);
    $queue_entry = MaestroEngine::getQueueEntryById(1);
    $task_status = $queue_entry->get('archived')->value;
    $this->assertEquals(TASK_ARCHIVE_NORMAL, $task_status, 'The task status should be archived.');

    MaestroEngine::unArchiveTask(1);
    $queue_entry = MaestroEngine::getQueueEntryById(1);
    $task_status = $queue_entry->get('archived')->value;
    $this->assertEquals(TASK_ARCHIVE_ACTIVE, $task_status, 'The task status should be unarchived.');
  }

  /**
   * Tests endProcess() static method.
   * 
   * Start a Maestro process. End the process then test its values.
   * 
   */
  public function testEndProcess() {
    $process_id = $this->maestroEngine->newProcess($this->template_machine_name);
    MaestroEngine::endProcess($process_id);
    $process = MaestroEngine::getProcessEntryById($process_id);
   
    $complete_flag = $process->get('complete')->value;
    $complete_timestamp = $process->get('completed')->value;
    $this->assertEquals(PROCESS_STATUS_COMPLETED, $complete_flag, 'The process status should be completed.');
    $this->assertNotEmpty($complete_timestamp, 'The completed timestamp should not be empty.');
  }

  /**
   * Tests abortProcess() static method.
   * 
   * Start a Maestro process. Abort the process then test its values.
   * 
   */
  public function testAbortProcess() {
    $process_id = $this->maestroEngine->newProcess($this->template_machine_name);
    MaestroEngine::abortProcess($process_id);
    $process = MaestroEngine::getProcessEntryById($process_id);
   
    $complete_flag = $process->get('complete')->value;
    $complete_timestamp = $process->get('completed')->value;
    $this->assertEquals(PROCESS_STATUS_ABORTED, $complete_flag, 'The process status should be aborted.');
    $this->assertNotEmpty($complete_timestamp, 'The completed timestamp should not be empty.');
  }
  
  /**
   * Tests setProcessLabel() static method.
   * 
   * Start a Maestro process. Set the process label and test.
   * 
   */
  public function testSetProcessLabel() {
    $process_id = $this->maestroEngine->newProcess($this->template_machine_name);
    MaestroEngine::setProcessLabel($process_id, 'New Process Label');
    $process = MaestroEngine::getProcessEntryById($process_id);
    $process_label = $process->get('process_name')->value;
    $this->assertEquals('New Process Label', $process_label, 'The process label should be "New Process Label".');
  }

  /**
   * Tests getQueueEntryById() static method.
   * 
   * Start a Maestro process. Get the Maestro Queue entity by ID 1.
   * 
   */
  public function testGetQueueEntryById() {
    $process_id = $this->maestroEngine->newProcess($this->template_machine_name);
    $queue_entry = MaestroEngine::getQueueEntryById(1);
    
    $this->assertNotEmpty($queue_entry, 'The queue entry should not be empty.');
    $this->assertInstanceOf(MaestroQueue::class, $queue_entry, 'The queue entry should be an instance of MaestroQueue.');
  }
  
  /**
   * Tests getProcessEntryById() static method.
   * 
   * Start a Maestro process. Get the Maestro Process entity by ID 1.
   * 
   */
  public function testGetProcessEntryById() {
    $process_id = $this->maestroEngine->newProcess($this->template_machine_name);
    $process_entry = MaestroEngine::getProcessEntryById(1);
    
    $this->assertNotEmpty($process_entry, 'The process entry should not be empty.');
    $this->assertInstanceOf(MaestroProcess::class, $process_entry, 'The process entry should be an instance of MaestroProcess.');
  }

  /**
   * Tests getPluginTask() static method.
   * 
   * Start a Maestro process. Get the plugin task for MaestroInteractiveTask.
   * 
   */
   public function testGetPluginTask() {
    $process_id = $this->maestroEngine->newProcess($this->template_machine_name);
    $plugin_task = MaestroEngine::getPluginTask('MaestroInteractive');
    $this->assertNotEmpty($plugin_task, 'The plugin task should not be empty.');
    $this->assertInstanceOf(MaestroInteractiveTask::class, $plugin_task, 'The plugin task should be an instance of MaestroInteractiveTask.');
   }

  /**
   * Tests getAssignedTaskQueueIds() static method.
   * 
   * Start a Maestro process. Run the orchestrator. Get the assigned task queue IDs for the known
   * assignments based on setting the initiator.
   * We have maestrouser as the user context so the initiator will be maestrouser.
   * 
   */
  public function testGetAssignedTaskQueueIds() {
    $process_id = $this->maestroEngine->newProcess($this->template_machine_name);
    $this->maestroEngine->cleanQueue(); // We run the orchestrator one time
    // Based on our template, we know that the second set of tasks will be assigned to the initiator
    // The tasks should have IDs of 2 and 3.

    $assigned_tasks = MaestroEngine::getAssignedTaskQueueIds($this->testUser->id());
    $this->assertIsArray($assigned_tasks, 'The assigned tasks should be an array.');

    // Now, for each of the assigned tasks, we should have a queue ID of 2 and 3.
    $this->assertEquals([2, 3], $assigned_tasks, 'Assigned tasks should be [2, 3]');
  }

  /**
   * Tests getAssignedTaskQueueIds() static method.
   * 
   * Start a Maestro process. Run the orchestrator. The initiator should be maestrouser
   * and that user should be assigned to queue IDs 2 and 3.
   * 
   */
  public function testCanUserExecuteTask() {
    $process_id = $this->maestroEngine->newProcess($this->template_machine_name);
    $this->maestroEngine->cleanQueue(); // We run the orchestrator one time
    // Queue IDs 2 and 3 are assigned to the initiator, maestrouser

    $can_execute = MaestroEngine::canUserExecuteTask(2, $this->testUser->id());
    $this->assertTrue($can_execute, 'The user should be able to execute task 2.');

    $can_execute = MaestroEngine::canUserExecuteTask(3, $this->testUser->id());
    $this->assertTrue($can_execute, 'The user should be able to execute task 3.');
  }

  /**
   * Tests getAssignedNamesOfQueueItem() static method.
   * 
   * Start a Maestro process. Run the orchestrator. The initiator should be maestrouser
   * and that user should be assigned to queue IDs 2 and 3. We should be able to get the
   * assigned names of the queue items and it should be maestrouser.
   * 
   */
  public function testGetAssignedNamesOfQueueItem() {
    $process_id = $this->maestroEngine->newProcess($this->template_machine_name);
    $this->maestroEngine->cleanQueue(); // We run the orchestrator one time
    // Queue IDs 2 and 3 are assigned to the initiator, maestrouser

    $assigned_names = MaestroEngine::getAssignedNamesOfQueueItem(2);
    $this->assertIsArray($assigned_names, 'The assigned names should be an array.');
    $this->assertEquals(['maestrouser' => 'maestrouser:Variable'], $assigned_names, 'The assigned names should be maestrouser.');

    $assigned_names = MaestroEngine::getAssignedNamesOfQueueItem(3);
    $this->assertIsArray($assigned_names, 'The assigned names should be an array.');
    $this->assertEquals(['maestrouser' => 'maestrouser:Variable'], $assigned_names, 'The assigned names should be maestrouser.');
  }

  /**
   * Tests performTemplateValidityCheck() static method.
   * 
   * Using our template, we should be able to check the validity of the template.
   * First, check if the template is valid. Then, change the template to be invalid.
   * 
   */
  public function testPerformTemplateValidityCheck() {
    $valid = MaestroEngine::performTemplateValidityCheck($this->template_machine_name);
    // The $valid variable will be an array that has no keys in the failures key if the template is valid.
    $this->assertEmpty($valid['failures'], 'The template should be valid.');

    // Now, make the template invalid by deleting the task "int3".
    // This should create a condition where there are missing pointers.
    // $remove_return will be of value SAVED_UPDATED.
    $remove_return = MaestroEngine::removeTemplateTask($this->template_machine_name, $this->template_task_machine_name);
    $this->assertEquals(SAVED_UPDATED, $remove_return, 'The remove return should not be  SAVED_UPDATED.');

    // This time, the template should be invalid.
    $valid = MaestroEngine::performTemplateValidityCheck($this->template_machine_name);
    // The $valid variable will be an array that now has keys in the failures key if the template is valid.
    $this->assertNotEmpty($valid['failures'], 'The template should be invalid.');
  }

  /**
   * Tests setTemplateToUnvalidated() static method.
   * 
   * Using our template, test that is is validated, then set it to unvalidated and test again.
   * 
   */
  public function testSetTemplateToUnvalidated() {
    $valid = MaestroEngine::performTemplateValidityCheck($this->template_machine_name);
    // The $valid variable will be an array that has no keys in the failures key if the template is valid.
    $this->assertEmpty($valid['failures'], 'The template should be valid.');

    MaestroEngine::setTemplateToUnvalidated($this->template_machine_name);
    // Load the template and check the validated value
    $template = MaestroEngine::getTemplate($this->template_machine_name);
    $this->assertEquals(FALSE, $template->validated, 'The template should be unvalidated.');
  }

  /**
   * Tests createEntityIdentifier() static method.
   * 
   * We will create an entity identifier for the process ID 1.
   * The entity type will be "node", the bundle will be "page", the entity ID will be 1,
   * the task Unique ID will be "node_identifier".  All those should be present when retrieving
   * the entity identifier.
   * 
   */
  public function testCreateEntityIdentifier() {
    $return = MaestroEngine::createEntityIdentifier(1, 'node', 'page','node_identifier', 1);
    $this->assertNotNull($return, 'The return should not be null.');
    $this->assertEquals(1, $return, 'Newly created entity identifier entity ID should be 1.');
  }

/**
   * Tests updateEntityIdentifierByEntityTableID() static method.
   * 
   * We will create an entity identifier for the process ID 1.
   * The entity type will be "node", the bundle will be "page", the entity ID will be 1,
   * the task Unique ID will be "node_identifier".  All those should be present when retrieving
   * the entity identifier.
   * 
   * Then we alter it and test the saved value
   * 
   */
  public function testUpdateEntityIdentifierByEntityTableID() {
    $return = MaestroEngine::createEntityIdentifier(1, 'node', 'page','node_identifier', 1);
    $this->assertNotNull($return, 'The return should not be null.');
    $this->assertEquals(1, $return, 'Newly created entity identifier entity ID should be 1.');

    MaestroEngine::updateEntityIdentifierByEntityTableID(1, 2, 'node', 'page', 'node_identifier');
    $entity_identifier = MaestroEngine::getEntityIdentiferByUniqueID(1, 'node_identifier');
    $this->assertEquals(2, $entity_identifier, 'The entity ID should be 2.');
  }

  /**
   * Tests getEntityIdentiferByUniqueID() static method.
   * 
   * We will create an entity identifier for the process ID 1.
   * The entity type will be "node", the bundle will be "page", the entity ID will be 1,
   * the task Unique ID will be "node_identifier".  All those should be present when retrieving
   * the entity identifier.
   * 
   * The retrieved value should equal the created value.
   * 
   */
  public function testGetEntityIdentiferByUniqueID() {
    $return = MaestroEngine::createEntityIdentifier(1, 'node', 'page','node_identifier', 1);
    $this->assertNotNull($return, 'The return should not be null.');
    $this->assertEquals(1, $return, 'Newly created entity identifier entity ID should be 1.');

    $entity_identifier = MaestroEngine::getEntityIdentiferByUniqueID(1, 'node_identifier');
    $this->assertEquals($return, $entity_identifier, 'The entity ID should be equal (1).');
  }

  /**
   * Tests getEntityIdentiferByIdentifierRowID() static method.
   * 
   * We will create an entity identifier for the process ID 1.
   * The entity type will be "node", the bundle will be "page", the entity ID will be 1,
   * the task Unique ID will be "node_identifier".  All those should be present when retrieving
   * the entity identifier.
   * 
   * Then we alter it and test the saved value
   * 
   */
  public function testGetEntityIdentiferByIdentifierRowID() {
    $return = MaestroEngine::createEntityIdentifier(1, 'node', 'page','node_identifier', 1);
    $this->assertNotNull($return, 'The return should not be null.');
    $this->assertEquals(1, $return, 'Newly created entity identifier entity ID should be 1.');

    $entity_identifier = MaestroEngine::getEntityIdentiferByIdentifierRowID($return);
    $this->assertNotNull($entity_identifier, 'The entity identifier should not be null.');
    $this->assertEquals(1, $entity_identifier, 'The entity ID should be 1.');
  }

  /**
   * Tests getAllEntityIdentifiersForProcess() static method.
   * 
   * We will create an entity identifier for the process ID 1.
   * The entity type will be "node", the bundle will be "page", the entity ID will be 1,
   * the task Unique ID will be "node_identifier".  
   * 
   * We then retrieve all entity identifiers for the process ID 1.
   * 
   */
  public function testGetAllEntityIdentifiersForProcess() {
    $return = MaestroEngine::createEntityIdentifier(1, 'node', 'page','node_identifier', 1);
    //$this->assertNotNull($return, 'The return should not be null.');
    //$this->assertEquals(1, $return, 'Newly created entity identifier entity ID should be 1.');

    $entity_identifiers = MaestroEngine::getAllEntityIdentifiersForProcess(1);
    $this->assertIsArray($entity_identifiers, 'The entity identifiers should be an array.');
    $this->assertNotEmpty($entity_identifiers, 'The entity identifiers should not be empty.');
    // Also have to make sure the first key is "node_identifier" as that's the unique ID we set
    $this->assertArrayHasKey('node_identifier', $entity_identifiers, 'The entity identifiers should have a key of 1.');
  }

  /**
   * Tests getAllStatusEntriesForProcess() static method.
   * 
   * Start a Maestro process. Get all status entries for the process.
   * 
   */
  public function testGetAllStatusEntriesForProcess() {
    $process_id = $this->maestroEngine->newProcess($this->template_machine_name);
    // Get a new process started.
    $status_entries = MaestroEngine::getAllStatusEntriesForProcess(1);
    // The status entries should be an array and should have two keys of 1 and 2
    $this->assertIsArray($status_entries, 'The status entries should be an array.');
    $this->assertArrayHasKey(1, $status_entries, 'The status entries should have a key of 1.');
    $this->assertArrayHasKey(2, $status_entries, 'The status entries should have a key of 2.');
    // The status entries should not have an array key of 3
    $this->assertArrayNotHasKey(3, $status_entries, 'The status entries should not have a key of 3.');
  }

  /**
   * Tests deleteProcess() static method.
   * 
   * Start a Maestro process. Then delete it and the process should not exist.
   * 
   */
  public function testDeleteProcess() {
    $process_id = $this->maestroEngine->newProcess($this->template_machine_name);
    // $process_id should be 1
    $this->assertEquals(1, $process_id, 'The process ID should be 1.');

    MaestroEngine::deleteProcess(1);
    // Now try to load the process ID
    $process = MaestroEngine::getProcessEntryById(1);
    // $process should be the MaestroProcess entity if it exists, but it does not.
    $this->assertEmpty($process, 'The process should be empty.');
  }

  /**
   * Tests getQueueItemTaskData() and setQueueItemTaskData() static methods.
   * 
   * Start a Maestro process. Then set task data in the first open task.
   * 
   */
    public function testTaskDataFetchAndSet() {
    $process_id = $this->maestroEngine->newProcess($this->template_machine_name);
    $this->maestroEngine->cleanQueue(); // We run the orchestrator one time
    // Based on our template, we know that the second set of tasks will be assigned to the initiator
    // These two tasks are interactive tasks which save the task data without a sub-array
    $queue_task_data = MaestroEngine::getQueueItemTaskData(2);
    $this->assertIsArray($queue_task_data, 'The task data return should be an array.');

    // The array of task data should also contain 2 keys: modal and redirect_to
    $this->assertArrayHasKey('modal', $queue_task_data[0], 'The modal key should exist in task data.');
    $this->assertArrayHasKey('redirect_to', $queue_task_data[0], 'The redirect_to key should exist in task data.');

    // We now set a value
    MaestroEngine::setQueueItemTaskData(2, 'testkey', 'testvalue');
    $queue_task_data = MaestroEngine::getQueueItemTaskData(2);
    $this->assertIsArray($queue_task_data, 'After setting the task data, the task data return should be an array.');
    // The array of task data should now contain 3 keys: modal, redirect_to and testkey
    $this->assertArrayHasKey('modal', $queue_task_data[0], 'The modal key should exist in task data.');
    $this->assertArrayHasKey('redirect_to', $queue_task_data[0], 'The redirect_to key should exist in task data.');
    $this->assertArrayHasKey('testkey', $queue_task_data[0], 'The testkey key was not successfully created.');

    // Now set a value in a sub array
    MaestroEngine::setQueueItemTaskData(2, 'testkey2', 'testvalue', 'sub_config');
    $queue_task_data = MaestroEngine::getQueueItemTaskData(2, 'sub_config');
    $this->assertIsArray($queue_task_data, 'After setting the sub_config task data, the task data return should be an array.');
    $this->assertArrayHasKey('testkey2', $queue_task_data, 'The testkey2 key should exist in sub-config task data.');
  }
}