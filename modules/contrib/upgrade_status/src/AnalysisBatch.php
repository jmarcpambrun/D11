<?php

namespace Drupal\upgrade_status;

use Drupal\Core\Extension\Extension;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Psr\Log\LoggerInterface;

/**
 * Runs the analysis of projects as a batch in the user interface.
 *
 * Each project is analyzed in a request of its own where that is possible, so
 * a fatal error in the analysis of one project does not stop the batch.
 */
class AnalysisBatch {

  use StringTranslationTrait;

  /**
   * Constructs a new analysis batch.
   *
   * @param \Drupal\upgrade_status\ProjectCollector $projectCollector
   *   The project collector.
   * @param \Drupal\upgrade_status\DeprecationAnalyzer $deprecationAnalyzer
   *   The deprecation analyzer.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(
    protected ProjectCollector $projectCollector,
    protected DeprecationAnalyzer $deprecationAnalyzer,
    protected LoggerInterface $logger,
    protected MessengerInterface $messenger,
  ) {}

  /**
   * Sets the batch that analyzes projects.
   *
   * Nothing is set when the environment of the analyzer is not ready, or when
   * there are no projects. A message explains why.
   *
   * @param \Drupal\Core\Extension\Extension[] $list
   *   The projects to analyze.
   */
  public function start(array $list): void {
    try {
      $this->deprecationAnalyzer->initEnvironment();
    }
    catch (\Exception $e) {
      // Without this the batch would run and store no results. The message is
      // not translated, because it comes from the exception.
      $this->messenger->addError($e->getMessage() . ' Analysis is not possible until this is resolved.');
      return;
    }

    // Reset extension lists for better Drupal compatibility info.
    $this->projectCollector->resetLists();
    $operations = [];

    // It is not possible to make an HTTP request to this same webserver
    // if the host server is PHP itself, because it is single-threaded.
    // See https://www.php.net/manual/en/features.commandline.webserver.php
    $use_http = php_sapi_name() != 'cli-server';
    $php_server = !$use_http;
    if ($php_server) {
      // Log the selected processing method for project support purposes.
      $this->logger->notice('Starting Upgrade Status on @count projects without HTTP sandboxing because the built-in PHP webserver does not allow for that.', ['@count' => count($list)]);
    }
    else {
      // Attempt to do an HTTP request to the frontpage of this Drupal instance.
      // If that does not work then we'll not be able to process projects over
      // HTTP. Processing projects directly is less safe (in case of PHP fatal
      // errors the batch process may halt), but we have no other choice here
      // but to take a chance.
      [, $message, $data] = static::doHttpRequest('upgrade_status_request_test', 'upgrade_status_request_test');
      if (empty($data) || !is_array($data) || ($data['message'] != 'Request test success')) {
        $use_http = FALSE;
        $this->logger->notice(
          'Starting Upgrade Status on @count projects without HTTP sandboxing. @error',
          [
            '@error' => $message,
            '@count' => count($list),
          ]
          );
      }
    }

    if ($use_http) {
      // Log the selected processing method for project support purposes.
      $this->logger->notice('Starting Upgrade Status on @count projects with HTTP sandboxing.', ['@count' => count($list)]);
    }

    foreach ($list as $item) {
      $operations[] = [
        static::class . '::analyzeProject',
        [$item, $use_http],
      ];
    }
    if (!empty($operations)) {
      $batch = [
        'title' => $this->t('Analyzing projects'),
        'operations' => $operations,
        'finished' => static::class . '::finished',
      ];
      batch_set($batch);
    }
    else {
      $this->messenger->addError($this->t('No projects selected to analyze.'));
    }
  }

  /**
   * Batch callback to analyze a project.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   The extension to analyze.
   * @param bool $use_http
   *   Whether to use HTTP to execute the processing or execute locally. HTTP
   *   processing could fail in some container setups. Local processing may
   *   fail due to timeout or memory limits.
   * @param array $context
   *   Batch context.
   */
  public static function analyzeProject(Extension $extension, $use_http, &$context) {
    $context['message'] = t('Analysis complete for @project.', ['@project' => $extension->getName()]);

    if (!$use_http) {
      \Drupal::service('upgrade_status.deprecation_analyzer')->analyze($extension);
      return;
    }

    // Do the HTTP request to run processing.
    [$error, $message] = static::doHttpRequest($extension->getName());
    // The other request stored new results, and the storage keeps the totals
    // of the results it loaded.
    $storage = \Drupal::service('upgrade_status.result_storage');
    $storage->resetTotals($extension->getName());

    if ($error !== FALSE) {
      $storage->setFailure($extension->getName(), $error, $message);
    }
  }

  /**
   * Batch callback to finish parsing.
   *
   * @param bool $success
   *   TRUE if the batch operation was successful; FALSE if there were errors.
   * @param array $results
   *   An associative array of results from the batch operation.
   */
  public static function finished($success, $results) {
    $logger = \Drupal::logger('upgrade_status');
    if ($success) {
      $logger->notice('Finished Upgrade Status processing successfully.');
    }
    else {
      $logger->notice('Finished Upgrade Status processing with errors.');
    }
  }

  /**
   * Gets the URL that analyzes a project, with its CSRF token.
   *
   * While a page is built, the URL generator only adds a placeholder for the
   * token, which is replaced when the page is rendered. This URL is requested
   * right away, so the token is added here, for the same path that the access
   * check of core uses.
   *
   * @param string $project_machine_name
   *   The machine name of the project.
   *
   * @return \Drupal\Core\Url
   *   The URL.
   */
  public static function getAnalyzeUrl(string $project_machine_name): Url {
    $path = ltrim(\Drupal::service('router.route_provider')->getRouteByName('upgrade_status.analyze')->getPath(), '/');
    $path = str_replace('{project_machine_name}', $project_machine_name, $path);
    return Url::fromRoute(
      'upgrade_status.analyze',
      ['project_machine_name' => $project_machine_name],
      ['query' => ['token' => \Drupal::csrfToken()->get($path)]]
    );
  }

  /**
   * Do an HTTP request with the type and machine name.
   *
   * @param string $project_machine_name
   *   The machine name of the project.
   *
   * @return array
   *   A three item array with any potential errors, the error message and the
   *   returned data as the third item. Either of them will be FALSE if they are
   *   not applicable. Data may also be NULL if response JSON decoding failed.
   */
  public static function doHttpRequest(string $project_machine_name) {
    $error = $message = $data = FALSE;

    // Prepare for a POST request to scan this project. The separate HTTP
    // request is used to separate any PHP errors found from this batch process.
    // We can store any errors and gracefully continue if there was any PHP
    // errors in parsing.
    $url = static::getAnalyzeUrl($project_machine_name);

    // Pass over authentication information because access to this functionality
    // requires administrator privileges.
    /** @var \Drupal\Core\Session\SessionConfigurationInterface $session_config */
    $session_config = \Drupal::service('session_configuration');
    $request = \Drupal::request();
    $session_options = $session_config->getOptions($request);
    // Unfortunately DrupalCI testbot does not have a domain that would normally
    // be considered valid for cookie setting, so we need to work around that
    // by manually setting the cookie domain in case there was none. What we
    // care about is we get actual results, and cookie on the host level should
    // suffice for that.
    $cookie_domain = empty($session_options['cookie_domain']) ? '.' . $request->getHost() : $session_options['cookie_domain'];
    $cookie_jar = new CookieJar();
    $cookie = new SetCookie([
      'Name' => $session_options['name'],
      'Value' => $request->cookies->get($session_options['name']),
      'Domain' => $cookie_domain,
      'Secure' => $session_options['cookie_secure'],
    ]);
    $cookie_jar->setCookie($cookie);
    $options = [
      'cookies' => $cookie_jar,
      'timeout' => 0,
    ];

    // Try a POST request with the session cookie included. We expect valid JSON
    // back. In case there was a PHP error before that, we log that.
    try {
      $response = \Drupal::httpClient()->post($url->setAbsolute()->toString(), $options);
      $data = json_decode((string) $response->getBody(), TRUE);
      if (!$data) {
        $error = 'PHP Fatal Error';
        $message = (string) $response->getBody();
      }
    }
    catch (\Exception $e) {
      $error = 'Analysis exception';
      $message = $e->getMessage();
    }

    return [$error, $message, $data];
  }

}
