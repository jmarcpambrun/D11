<?php

namespace Drupal\drd;

use Drupal\Core\Http\ClientFactory;
use Drupal\drd\Entity\DomainInterface;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Psr\Http\Message\ResponseInterface;

/**
 * Provides services for http requests..
 *
 * @package Drupal\drd
 */
class HttpRequest {

  /**
   * Get the current library version for the installed DRD module.
   */
  public static function getVersion(): string {
    return LibraryBuild::DRD_LIBRARY_VERSION;
  }

  /**
   * The domain entity to communicate with.
   *
   * @var \Drupal\drd\Entity\DomainInterface
   */
  protected DomainInterface $domain;

  /**
   * The query to submit.
   *
   * @var string
   */
  protected string $query = '';

  /**
   * The options to use for request.
   *
   * @var array
   */
  protected array $options = [];

  /**
   * The response for the request.
   *
   * @var \Psr\Http\Message\ResponseInterface|null
   */
  protected ?ResponseInterface $response = NULL;

  /**
   * The return status code.
   *
   * @var int
   */
  protected int $statusCode;

  /**
   * Flag if the remote domain properly supports DRD.
   *
   * @var bool
   */
  protected bool $remoteIsDrd;

  /**
   * The logging channel.
   *
   * @var \Drupal\drd\Logging
   */
  protected Logging $logging;

  /**
   * The http client factory.
   *
   * @var \Drupal\Core\Http\ClientFactory
   */
  protected ClientFactory $httpClientFactory;

  /**
   * HttpRequest constructor.
   *
   * @param \Drupal\drd\Logging $logging
   *   The logging channel.
   * @param \Drupal\Core\Http\ClientFactory $clientFactory
   *   The client factory.
   */
  public function __construct(Logging $logging, ClientFactory $clientFactory) {
    $this->logging = $logging;
    $this->httpClientFactory = $clientFactory;
  }

  /**
   * Set the DRD domain entity.
   *
   * @param \Drupal\drd\Entity\DomainInterface $domain
   *   The domain entity.
   *
   * @return $this
   */
  public function setDomain(DomainInterface $domain): self {
    $this->domain = $domain;
    return $this;
  }

  /**
   * Set the query.
   *
   * @param string $query
   *   The query.
   *
   * @return $this
   */
  public function setQuery(string $query): self {
    $this->query = $query;
    return $this;
  }

  /**
   * Set a request option.
   *
   * @param string $key
   *   Key for the option.
   * @param string $value
   *   Value of the option.
   *
   * @return $this
   */
  public function setOption(string $key, string $value): self {
    $this->options[$key] = $value;
    return $this;
  }

  /**
   * Get the request's response.
   *
   * @return string|bool
   *   The body of the response if successful, FALSE otherwise.
   *
   * @throws \Exception
   */
  public function getResponse(): bool|string {
    if ($this->response === NULL) {
      // We haven't received anything from remote.
      return FALSE;
    }
    if (!$this->isRemoteDrd()) {
      // We received something from remote, but not from DRD remote.
      throw new \RuntimeException('Remote domain does not respond as DRD.');
    }

    // Let's decode the response from DRD remote.
    $body = base64_decode($this->response->getBody()->getContents());
    if (!$body) {
      // Reponse can not be decoded.
      throw new \RuntimeException('Received unexpected content.');
    }

    // Return the actual response.
    return $body;
  }

  /**
   * Get the response headers.
   *
   * @return string[][]|bool
   *   The response headers.
   */
  public function getResponseHeaders(): array|bool {
    if ($this->response === NULL) {
      // We haven't received anything from remote.
      return FALSE;
    }
    return $this->response->getHeaders();
  }

  /**
   * Get the response status code.
   *
   * @return int
   *   The status code.
   */
  public function getStatusCode(): int {
    return $this->statusCode;
  }

  /**
   * Gets the http client factory.
   *
   * @return \Drupal\Core\Http\ClientFactory
   *   The http client factory.
   */
  public function getHttpClientFactory(): ClientFactory {
    return $this->httpClientFactory;
  }

  /**
   * Get the flag if remote domain properly supports DRD.
   *
   * @return bool
   *   TRUE if everything is working fine.
   */
  public function isRemoteDrd(): bool {
    return $this->remoteIsDrd;
  }

  /**
   * Submit the request and analyse the response.
   */
  public function request(): void {
    $url = $this->domain->buildUrl($this->query);
    $this->options['headers'] = $this->domain->getHeader();
    $this->options['headers']['X-Drd-Version'] = self::getVersion();
    $jar = new CookieJar();
    $cookies = $this->domain->getCookies();
    foreach ($cookies as $cookie) {
      $jar->setCookie(SetCookie::fromString($cookie));
    }
    $this->options['cookies'] = $jar;
    $this->statusCode = -1;
    $this->remoteIsDrd = FALSE;
    try {
      $client = $this->httpClientFactory->fromOptions(['base_uri' => $url->toUriString()]);
      $this->response = $client->request('post', $url->toUriString(), $this->options);
      $this->statusCode = $this->response->getStatusCode();
      $this->remoteIsDrd = (
        $this->statusCode === 200 &&
        $this->response->getHeaderLine('content-type') === 'text/plain; charset=utf-8' &&
        $this->response->getHeaderLine('x-drd-agent') === self::getVersion()
      );
      $new_cookies = $this->response->getHeader('set-cookie');
      if (empty($new_cookies)) {
        $new_cookies = $cookies;
      }
      $this->domain->setCookies($new_cookies);
    }
    catch (\Exception $ex) {
      $this->statusCode = $ex->getCode();
    }
  }

}
