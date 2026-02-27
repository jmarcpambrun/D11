<?php

namespace Drupal\event_scheduler\Form;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Queue\QueueWorkerInterface;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\event_scheduler\EventSchedulerDispatcher;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Queue\QueueWorkerManagerInterface;

/**
 * Class ScheduledEventsForm.
 */
class ScheduledEventsForm extends FormBase
{

  use LoggerChannelTrait;

  protected QueueInterface $queueFactory;

  /**
   * @var QueueWorkerManagerInterface
   */
  protected QueueWorkerManagerInterface $queueManager;

  /**
   * Constructs a new ScheduledEventsForm object.
   *
   * @param QueueFactory $queue_factory
   * @param QueueWorkerManagerInterface $queue_manager
   */
  public function __construct(
      QueueFactory                $queue_factory,
      QueueWorkerManagerInterface $queue_manager
  ) {
    $this->queueFactory = $queue_factory;
    $this->queueManager = $queue_manager;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
        $container->get('queue'),
        $container->get('plugin.manager.queue_worker')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'scheduled_events_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /** @var QueueInterface $queue */
    $queue = $this->queueFactory->get(EventSchedulerDispatcher::QUEUE_NAME);

    $form['help'] = array(
        '#type' => 'markup',
        '#markup' => $this->t('Submitting this form will process the Manual Queue which contains @number items.', array('@number' => $queue->numberOfItems())),
    );
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Process queue'),
        '#button_type' => 'primary',
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    /** @var QueueInterface $queue */
    $queue = $this->queueFactory->get(EventSchedulerDispatcher::QUEUE_NAME);

    $logger = $this->getLogger('event_scheduler.manual');

    try {
      /** @var QueueWorkerInterface $queueWorker */
      $queueWorker = $this->queueManager->createInstance(EventSchedulerDispatcher::QUEUE_NAME);

      while ($item = $queue->claimItem()) {
        try {
          $queueWorker->processItem($item->data);
          $queue->deleteItem($item);
        } catch (SuspendQueueException $e) {
          $logger->debug($this->t('Failed to process queue item: %x', ['%x' => $e->getMessage()]));
          $queue->releaseItem($item);
          break;
        } catch (\Exception $e) {
          $logger->debug($this->t('Failed to process queue item: %x', ['%x' => $e->getMessage()]));
        }
      }
    } catch (PluginException $e) {
      $logger->debug($this->t('Could not create queue worker: %x', ['%x' => $e->getMessage()]));
    }
  }

}
