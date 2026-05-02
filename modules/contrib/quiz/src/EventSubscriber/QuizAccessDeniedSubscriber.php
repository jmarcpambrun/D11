<?php

declare(strict_types=1);

namespace Drupal\quiz\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Drupal\Core\EventSubscriber\DefaultExceptionHtmlSubscriber;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\quiz\Services\QuizSessionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;

/**
 * If quiz session data becomes unavailable during a quiz go to quiz/quiz id.
 */
final class QuizAccessDeniedSubscriber extends DefaultExceptionHtmlSubscriber {

  use LoggerChannelTrait;

  /**
   * QuizAccessDeniedSubscriber constructor.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   The wrapped HTTP kernel.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $redirect_destination
   *   The redirect destination service.
   * @param \Symfony\Component\Routing\Matcher\UrlMatcherInterface $access_unaware_router
   *   A router implementation which does not check access.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param \Drupal\quiz\Services\QuizSessionInterface $quizSession
   *   The quiz session service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   An immutable configuration object.
   */
  public function __construct(
    HttpKernelInterface $http_kernel,
    LoggerInterface $logger,
    RedirectDestinationInterface $redirect_destination,
    UrlMatcherInterface $access_unaware_router,
    protected RouteMatchInterface $routeMatch,
    protected QuizSessionInterface $quizSession,
    protected ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($http_kernel, $logger, $redirect_destination, $access_unaware_router);
  }

  /**
   * {@inheritdoc}
   */
  protected static function getPriority(): int {
    return 10;
  }

  /**
   * {@inheritdoc}
   */
  public function onException(ExceptionEvent $event) {
    // Only handle 403 exceptions.
    $exception = $event->getThrowable();
    if ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 403) {
      parent::onException($event);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function on403(ExceptionEvent $event): void {
    $route_name = $this->routeMatch->getRouteName();
    $quiz_id = (int) $this->routeMatch->getRawParameter('quiz');
    $session_sound = $this->quizSession->isSessionSound($quiz_id);

    if ($route_name === 'quiz.question.take' && !$session_sound) {
      $url = Url::fromRoute('entity.quiz.canonical', ['quiz' => $quiz_id]);
      $url = $url->toString();
      $response = new RedirectResponse($url);
      $event->setResponse($response);
      $event->stopPropagation();

    }
  }

}
