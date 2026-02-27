<?php

namespace Drupal\webform_quiz\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\webform_quiz\QuizResults;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\Core\Cache\CacheableMetadata;

/**
 * Provides a Custom Resource.
 *
 * @RestResource(
 *   id = "webform_quiz_results",
 *   label = @Translation("Webform Submission Quiz Results"),
 *   uri_paths = {
 *     "canonical" = "/webform_quiz/results/{sid}",
 *   }
 * )
 */
class WebformQuizResults extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a new CustomResource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   A current user instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('webform_quiz_results'),
      $container->get('current_user')
    );
  }


  /**
   * Responds to GET requests.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the data.
   */
  public function get($sid) {
    $sub = null;
    if(preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $sid))
      $sub = \Drupal::service('entity.repository')->loadEntityByUuid('webform_submission', $sid);
    else
      $sub = WebformSubmission::load($sid);
    if($sub) {
      $res = new QuizResults($sub);
      $data = [
        'number_of_points_received' => $res->getNumberOfPointsReceived(),
        'number_of_points_available' => $res->getNumberOfPointsAvailable(),
        'percentage_correct' => $res->getPercentageCorrect(),
        'stat_per_section' => $res->getStatPerSection(),
      ];

      $response = new ResourceResponse($data);

      // Add cache metadata for the Webform submission.
      $cache_metadata = new CacheableMetadata();
      $cache_metadata->addCacheableDependency($sub);

      // Attach cacheable metadata to the response.
      $response->addCacheableDependency($cache_metadata);
    } else {
      $response = new ResourceResponse([
        'message' => 'Webform submission not found',
      ], 404);
    }

    return $response;
  }


}
