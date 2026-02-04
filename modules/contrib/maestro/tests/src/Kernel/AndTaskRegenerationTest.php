<?php

declare(strict_types=1);

namespace Drupal\Tests\maestro\Kernel;

use Drupal;
use Drupal\KernelTests\KernelTestBase;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\maestro\Entity\MaestroQueue;
use const TASK_STATUS_FALSE_BRANCH;
use const TASK_STATUS_SUCCESS;

/**
 * And Task regeneration test.
 *
 * @group maestro
 */
final class AndTaskRegenerationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
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

    $this->installEntitySchema('maestro_template');
    $this->installEntitySchema('maestro_queue');
    $this->installEntitySchema('maestro_process');
    $this->installEntitySchema('maestro_entity_identifiers');
    $this->installEntitySchema('maestro_process_variables');
    $this->installEntitySchema('maestro_process_status');
    $this->installEntitySchema('maestro_production_assignments');

    $this->installConfig([
      'maestro_test_maestro_templates',
    ]);

    $this->maestroEngine = new MaestroEngine();
  }

  /**
   * This tests a template which ideally work.
   * For the template to work Maestro needs to persist transitions.
   */
  public function testAndTaskAfterLoop(): void {
    $process_id = $this->maestroEngine->newProcess("andtask_after_loop");
    $process = MaestroEngine::getProcessEntryById($process_id);

    /** @var MaestroQueue[] $allTasks */
    $allTasks = MaestroQueue::loadMultiple();
    self::assertCount(1, $allTasks);
    self::assertEquals('start', $allTasks[1]->task_id->value);
    self::assertEquals('MaestroStart', $allTasks[1]->task_class_name->value);


    $this->maestroEngine->cleanQueue();
    $allTasks = MaestroQueue::loadMultiple();
    self::assertCount(3, $allTasks);
    self::assertEquals('wait', $allTasks[2]->task_id->value);
    self::assertEquals('MaestroAnd', $allTasks[2]->task_class_name->value);
    self::assertEquals(TASK_STATUS_ACTIVE, $allTasks[2]->status->value);
    self::assertEquals('task1', $allTasks[3]->task_id->value);
    self::assertEquals('MaestroBatchFunction', $allTasks[3]->task_class_name->value);

    $this->maestroEngine->cleanQueue();
    $allTasks = MaestroQueue::loadMultiple();
    self::assertCount(4, $allTasks);
    self::assertEquals(TASK_STATUS_ACTIVE, $allTasks[2]->status->value);
    self::assertEquals('counter', $allTasks[4]->task_id->value);
    self::assertEquals('MaestroSetProcessVariable', $allTasks[4]->task_class_name->value);

    $this->maestroEngine->cleanQueue();
    $allTasks = MaestroQueue::loadMultiple();
    self::assertCount(5, $allTasks);
    self::assertEquals(TASK_STATUS_ACTIVE, $allTasks[2]->status->value);
    self::assertEquals('loop', $allTasks[5]->task_id->value);
    self::assertEquals('MaestroIf', $allTasks[5]->task_class_name->value);

    $this->maestroEngine->cleanQueue();
    $allTasks = MaestroQueue::loadMultiple();
    self::assertCount(6, $allTasks);
    self::assertEquals(TASK_STATUS_ACTIVE, $allTasks[2]->status->value);
    self::assertEquals('task1', $allTasks[6]->task_id->value);
    self::assertEquals('MaestroBatchFunction', $allTasks[6]->task_class_name->value);

    $this->maestroEngine->cleanQueue();
    $allTasks = MaestroQueue::loadMultiple();
    self::assertEquals(TASK_STATUS_ACTIVE, $allTasks[2]->status->value);
    self::assertCount(7, $allTasks);
    self::assertEquals('counter', $allTasks[7]->task_id->value);
    self::assertEquals('MaestroSetProcessVariable', $allTasks[7]->task_class_name->value);

    $this->maestroEngine->cleanQueue();
    $allTasks = MaestroQueue::loadMultiple();
    self::assertCount(8, $allTasks);
    self::assertEquals(TASK_STATUS_ACTIVE, $allTasks[2]->status->value);
    self::assertEquals('loop', $allTasks[8]->task_id->value);
    self::assertEquals('MaestroIf', $allTasks[8]->task_class_name->value);

    $this->maestroEngine->cleanQueue();
    self::assertEquals(TASK_STATUS_FALSE_BRANCH, $allTasks[8]->status->value);

    $this->maestroEngine->cleanQueue();
    $allTasks = MaestroQueue::loadMultiple();
    self::assertCount(9, $allTasks);
    self::assertEquals(TASK_STATUS_SUCCESS, $allTasks[2]->status->value);
    self::assertEquals('end', $allTasks[9]->task_id->value);
    self::assertEquals('MaestroEnd', $allTasks[9]->task_class_name->value);

    $this->maestroEngine->cleanQueue();
    self::assertTrue((bool) $process->complete->value);
  }

}
