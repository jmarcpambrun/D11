<?php

declare(strict_types=1);

namespace Drupal\gitlab_api\Controller;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Merge;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\gitlab_api\Entity\GitlabProjectInterface;
use Drupal\gitlab_api\Event\GitlabCommentEvent;
use Drupal\gitlab_api\Event\GitlabIssueClosedEvent;
use Drupal\gitlab_api\Event\GitlabIssueCreatedEvent;
use Drupal\gitlab_api\Event\GitlabIssueReopenedEvent;
use Drupal\gitlab_api\Event\GitlabIssueUpdatedEvent;
use Drupal\gitlab_api\Event\GitlabMrEvent;
use Drupal\gitlab_api\Event\GitlabPipelineEvent;
use Drupal\gitlab_api\Event\GitlabPushEvent;
use Drupal\gitlab_api\Event\GitlabTagPushEvent;
use Drupal\gitlab_api\Service\SignatureVerifier;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Public POST endpoint for GitLab webhooks.
 *
 * Synchronous flow: flood → load project → verify token → coarse event filter
 * → atomic idempotency insert → dispatch the matching Symfony event → return
 * 200. ECA listens directly on the events; there is no queue.
 *
 * Budget: GitLab cancels the request after ~10 s by default. Heavy or slow
 * actions (LLM calls, etc.) should be deferred from inside the ECA model
 * (e.g. via the Advanced Queue module) rather than blocking this controller.
 */
final class WebhookController implements ContainerInjectionInterface {

  private const FLOOD_LIMIT = 50;
  private const FLOOD_WINDOW_SECONDS = 60;

  /**
   * White listed X-Gitlab-Event values; everything else 200-drops silently.
   */
  private const ACCEPTED_EVENTS = [
    'Push Hook',
    'Tag Push Hook',
    'Note Hook',
    'Issue Hook',
    'Merge Request Hook',
    'Pipeline Hook',
  ];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FloodInterface $flood,
    private readonly SignatureVerifier $verifier,
    private readonly Connection $database,
    private readonly EventDispatcherInterface $dispatcher,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    /** @var \Drupal\Core\Logger\LoggerChannelFactoryInterface $logFactory */
    $logFactory = $container->get('logger.factory');

    return new self(
      $container->get('entity_type.manager'),
      $container->get('flood'),
      $container->get('gitlab_api.signature_verifier'),
      $container->get('database'),
      $container->get('event_dispatcher'),
      $logFactory->get('gitlab_api'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function receive(string $project, Request $request): Response {
    $clientIp = $request->getClientIp() ?? '0.0.0.0';
    $floodKey = sprintf('gitlab_api.webhook.%s.%s', $project, $clientIp);

    if (!$this->flood->isAllowed($floodKey, self::FLOOD_LIMIT, self::FLOOD_WINDOW_SECONDS)) {
      return new Response('', 429);
    }

    /** @var \Drupal\gitlab_api\Entity\GitlabProjectInterface|NULL $projectEntity */
    $projectEntity = $this->entityTypeManager->getStorage('gitlab_project')->load($project);
    if ($projectEntity === NULL || !$projectEntity->status()) {
      $this->flood->register($floodKey, self::FLOOD_WINDOW_SECONDS);
      return new Response('', 404);
    }

    $token = $request->headers->get('X-Gitlab-Token');
    $eventType = (string) $request->headers->get('X-Gitlab-Event', '');
    $eventUuid = (string) $request->headers->get('X-Gitlab-Event-UUID', '');
    $body = (string) $request->getContent();

    if (!$this->verifier->verify($projectEntity, $token)) {
      $this->flood->register($floodKey, self::FLOOD_WINDOW_SECONDS);
      $this->logger->notice('Webhook signature mismatch for project @p from @ip.', [
        '@p' => $project,
        '@ip' => $clientIp,
      ]);
      return new Response('', 401);
    }

    if (!in_array($eventType, self::ACCEPTED_EVENTS, TRUE)) {
      return new Response('', 200);
    }

    $eventId = $eventUuid !== ''
      ? hash('sha256', $project . ':' . $eventUuid)
      : hash('sha256', $project . ':body:' . $body);

    // Atomic idempotency insert. STATUS_INSERT only on a genuine insert; any
    // other result means we've already processed this event_id.
    $insertResult = $this->database->merge('gitlab_seen_event')
      ->key('event_id', $eventId)
      ->insertFields(['event_id' => $eventId, 'seen_at' => time()])
      ->execute();
    if ($insertResult !== Merge::STATUS_INSERT) {
      return new Response('', 200);
    }

    $payload = json_decode($body, TRUE) ?? [];
    $event = $this->buildEvent($eventType, $projectEntity, is_array($payload) ? $payload : []);
    if ($event === NULL) {
      return new Response('', 200);
    }

    $eventShort = (new \ReflectionClass($event))->getShortName();
    $this->logger->info('Dispatching @t (@class) for @p (event @e).', [
      '@t' => $eventType,
      '@class' => $eventShort,
      '@p' => $project,
      '@e' => $eventId,
    ]);

    $start = microtime(TRUE);
    try {
      $this->dispatcher->dispatch($event);
    }
    catch (\Throwable $e) {
      $this->logger->error('Webhook dispatch failure for event @e: @msg at @file:@line<pre>@trace</pre>', [
        '@e' => $eventId,
        '@msg' => $e->getMessage(),
        '@file' => $e->getFile(),
        '@line' => $e->getLine(),
        '@trace' => $e->getTraceAsString(),
      ]);
      return new Response('', 500);
    }

    $duration = (int) ((microtime(TRUE) - $start) * 1000);
    $this->logger->info('Dispatched @t for @p (event @e) in @ms ms.', [
      '@t' => $eventType,
      '@p' => $project,
      '@e' => $eventId,
      '@ms' => $duration,
    ]);

    return new Response('', 200);
  }

  /**
   * Picks the right Symfony event class for a given X-Gitlab-Event header.
   */
  private function buildEvent(string $eventType, GitlabProjectInterface $project, array $payload): ?object {
    return match ($eventType) {
      'Push Hook' => new GitlabPushEvent($project, $payload),
      'Tag Push Hook' => new GitlabTagPushEvent($project, $payload),
      'Note Hook' => new GitlabCommentEvent($project, $payload),
      'Merge Request Hook' => new GitlabMrEvent($project, $payload),
      'Pipeline Hook' => new GitlabPipelineEvent($project, $payload),
      'Issue Hook' => $this->buildIssueEvent($project, $payload),
      default => NULL,
    };
  }

  /**
   * Issue Hook splits into Created/Updated/Closed/Reopened by action.
   */
  private function buildIssueEvent(GitlabProjectInterface $project, array $payload): object {
    $action = (string) ($payload['object_attributes']['action'] ?? 'update');
    return match ($action) {
      'open' => new GitlabIssueCreatedEvent($project, $payload),
      'close' => new GitlabIssueClosedEvent($project, $payload),
      'reopen' => new GitlabIssueReopenedEvent($project, $payload),
      default => new GitlabIssueUpdatedEvent($project, $payload),
    };
  }

}
