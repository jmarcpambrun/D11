<?php

namespace Drupal\drd\Plugin\Action;

use Drupal\drd\Crypt\Base as CryptBase;
use Drupal\drd\Crypt\BaseMethodInterface;
use Drupal\drd\Entity\BaseInterface as RemoteEntityInterface;
use Drupal\drd\Entity\DomainInterface;
use Drupal\drd\HttpRequest;

/**
 * Base class for DRD Remote Action plugins.
 */
abstract class BaseEntityRemote extends BaseEntity {

  /**
   * Contains FALSE or the json decoded response from remote entity.
   *
   * @var bool|array[]
   */
  protected mixed $response = FALSE;

  /**
   * The HTTP headers of the response from the remote entity.
   *
   * @var array
   */
  protected array $responseHeaders = [];

  /**
   * Crypt object for the remote entity.
   *
   * @var \Drupal\drd\Crypt\BaseMethodInterface|null
   */
  protected ?BaseMethodInterface $crypt = NULL;

  /**
   * {@inheritdoc}
   */
  protected function reset(): void {
    parent::reset();
    $this->response = FALSE;
    $this->crypt = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function executeAction(?RemoteEntityInterface $entity = NULL): bool|array|string {
    if (!($entity instanceof DomainInterface)) {
      return FALSE;
    }
    $this->reset();
    if (!$this->access($entity)) {
      return FALSE;
    }
    $this->remoteRequest();
    return $this->getResponse();
  }

  /**
   * Get the json decoded action response from remote entity.
   *
   * @return array|bool
   *   The json decoded response from the remote entity or FALSE, if execution
   *   failed.
   */
  public function getResponse(): array|bool {
    if (is_array($this->response) && !empty($this->response['failed'])) {
      return FALSE;
    }
    return $this->response;
  }

  /**
   * Set request options.
   *
   * By default, no additional options are required, hence this is an empty
   * default function which simply doesn't do anything. But any action can
   * overwrite this and add options to the request before this is being
   * submitted.
   *
   * @param \Drupal\drd\HttpRequest $request
   *   The request object.
   */
  protected function setRequestOptions(HttpRequest $request): void {}

  /**
   * Determine if the action response should be processed after execution.
   *
   * By default, this returns TRUE as we usually have to process the response,
   * i.e. decrypting and decoding. If an action has to avoid that, e.g. the
   * download action, this action can overwrite this function and return FALSE.
   *
   * @return bool
   *   TRUE, if the action response should be decrypted and decoded.
   */
  protected function processResponse(): bool {
    return TRUE;
  }

  /**
   * Finally prepare and submit the request object and process the response.
   */
  protected function remoteRequest(): void {
    /** @var \Drupal\drd\Entity\DomainInterface $domain */
    $domain = $this->drdEntity;
    $class = explode('\\', get_class($this));

    // Add authentication.
    $args = [
      'auth' => $domain->getAuth(),
      'authsetting' => $domain->getAuthSetting(),
      'action' => array_pop($class),
      'drd_action_module' => $class[1],
    ] + $this->arguments;

    if ($args['drd_action_module'] !== 'drd') {
      // This is a custom action and we need to post the action code remotely.
      if (($core = $domain->getCore()) && ($release = $core->getDrupalRelease()) && ($major = $release->getMajor())) {
        $coreVersion = $major->getCoreVersion();
      }
      else {
        $this->log('crtitical', 'Can not determine core version.');
        return;
      }
      $classFile = DRUPAL_ROOT . '/' . $this->moduleHandler->getModule($class[1])->getPath() . '/src/Agent/Action/V' . $coreVersion . '/' . $args['action'] . '.php';
      if (!file_exists($classFile)) {
        $this->log('crtitical', 'Remote code for action plugin @plugin does not exist.', ['@plugin' => $args['action']]);
        return;
      }
      $args['drd_action_plugin'] = file_get_contents($classFile);
    }

    // Encrypt the arguments.
    $method = $domain->getCrypt();
    if (empty($method)) {
      $this->log('alert', 'No encryption configured yet.');
      return;
    }
    $this->crypt = CryptBase::getInstance(
      $method,
      $domain->getCryptSetting()
    );

    $payload = [
      'uuid' => $domain->uuid(),
      'args' => base64_encode($this->crypt->encrypt($args)),
      'iv' => base64_encode($this->crypt->getIv()),
    ];
    if ($this->crypt->authBeforeDecrypt()) {
      $payload['auth'] = $domain->getAuth();
      $payload['authsetting'] = $domain->getAuthSetting();
    }
    try {
      $body = base64_encode(json_encode($payload, JSON_THROW_ON_ERROR));
    }
    catch (\JsonException) {
      return;
    }

    $request = $this->httpRequest;
    $request->setDomain($domain)
      ->setQuery('drd-agent')
      ->setOption('body', $body);
    $this->setRequestOptions($request);
    $request->request();
    if (!$request->isRemoteDrd()) {
      $this->log('warning', 'Remote instance does not support DRD.');
      return;
    }
    $this->responseHeaders = $request->getResponseHeaders();
    if ($this->processResponse()) {
      try {
        $this->response = $this->crypt->decrypt($request->getResponse(), $this->crypt->getIv());
      }
      catch (\Exception $ex) {
        $this->log('critical', 'Decrypt problem: @msg', ['@msg' => $ex->getMessage()]);
        return;
      }

      if (!empty($this->response['messages'])) {
        $domain->cacheRemoteMessages($this->response['messages']);
      }
      unset($this->response['messages']);
    }
    else {
      $this->response = TRUE;
    }
    $this->log('info', 'Success with response', ['@response' => $this->response]);

    if ($followup = $this->getFollowUpAction()) {
      if (is_string($followup)) {
        $followup = [$followup];
      }
      foreach ($followup as $item) {
        $this->queueManager->createItem($this->actionManager->instance($item), $domain);
      }
    }
  }

}
