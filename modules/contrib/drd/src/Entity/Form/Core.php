<?php

namespace Drupal\drd\Entity\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\drd\Crypt\Base as CryptBase;
use Drupal\drd\Entity\CoreInterface;
use Drupal\drd\Entity\Domain as DomainEntity;
use Drupal\drd\Plugin\Auth\Manager;
use Drupal\drd\Update\ManagerStorageInterface;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Form controller for Core edit forms.
 *
 * @ingroup drd
 */
class Core extends ContentEntityForm {

  private const HIDDEN_CLASS = 'visually-hidden';

  /**
   * The authentication manager.
   *
   * @var \Drupal\drd\Plugin\Auth\Manager
   */
  protected Manager $authManager;

  /**
   * The http request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $request;

  /**
   * The storage manager for DRD updates.
   *
   * @var \Drupal\drd\Update\ManagerStorageInterface
   */
  protected ManagerStorageInterface $managerStorage;

  /**
   * The http client factory.
   *
   * @var \Drupal\Core\Http\ClientFactory
   */
  protected ClientFactory $httpClientFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, Manager $auth_manager, Request $request, ManagerStorageInterface $manager_storage, ClientFactory $http_client_factory) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->authManager = $auth_manager;
    $this->request = $request;
    $this->managerStorage = $manager_storage;
    $this->httpClientFactory = $http_client_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): Core {
    return new Core(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('plugin.manager.drd_auth'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('plugin.manager.drd_update.storage'),
      $container->get('http_client_factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    /** @var \Drupal\drd\Entity\CoreInterface $core */
    $core = $this->entity;

    if ($core->isNew()) {
      // We are coming from a specific host's Add Core action - pre-select it.
      $form['host']['widget']['#default_value'] = [$this->request->get('drd_host') ?: 1];

      // Adding a new core means we need the URL to initially contact that site
      // to grab all the details about that Drupal installation.
      $form['drd-new-core-wrapper'] = [
        '#type' => 'container',
        '#weight' => -99,
      ];
      $form['drd-new-core-wrapper']['url'] = [
        '#title' => $this->t('URL'),
        '#type' => 'url',
        '#default_value' => '',
        '#description' => $this->t('Provide the URL including scheme (e.g. https://www.example.com) and then press the TAB key (or leave the field otherwise) so that DRD will validate the URL and provide you with more setting fields.'),
        '#required' => TRUE,
        '#ajax' => [
          'callback' => [$this, 'validateUrlAjax'],
          'event' => 'change',
          'progress' => [
            'type' => 'throbber',
            'message' => t('Verifying url...'),
          ],
        ],
      ];
      $form['drd-new-core-wrapper']['url-message'] = [
        '#type' => 'container',
      ];

      // Container for domain specific settings.
      $form['drd-new-core-wrapper']['drd'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => self::HIDDEN_CLASS,
        ],
      ];
      $form['drd-new-core-wrapper']['drd']['drd_auth'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Authentication type'),
      ];
      $form['drd-new-core-wrapper']['drd']['drd_auth']['description'] = [
        '#markup' => $this->t('The method how DRD should authenticate each request on the remote domains on this core.'),
      ];
      $form['drd-new-core-wrapper']['drd']['drd_auth']['drd_auth_type'] = [
        '#type' => 'select',
        '#options' => $this->authManager->selectList(),
        '#default_value' => 'shared_secret',
      ];
      foreach ($this->authManager->getDefinitions() as $def) {
        /** @var \Drupal\drd\Plugin\Auth\BaseInterface $auth */
        try {
          $auth = $this->authManager->createInstance($def['id']);
        }
        catch (PluginException) {
          continue;
        }
        $condition = ['select#edit-drd-auth-type' => ['value' => $def['id']]];
        $form['drd-new-core-wrapper']['drd']['drd_auth'][$def['id']] = [
          '#type' => 'container',
          '#states' => [
            'visible' => $condition,
          ],
        ];
        $auth->settingsForm($form['drd-new-core-wrapper']['drd']['drd_auth'][$def['id']], $condition);
      }

      $form['drd-new-core-wrapper']['drd']['drd_crypt'] = [
        '#type' => 'fieldset',
        '#title' => t('Encryption type'),
      ];
      $form['drd-new-core-wrapper']['drd']['drd_crypt']['description'] = [
        '#markup' => t('The method how DRD should encrypt the data sent to and received from the remote domains on this core.'),
      ];
      $form['drd-new-core-wrapper']['drd']['drd_crypt']['drd_crypt_type'] = [
        '#type' => 'select',
        '#default_value' => 'OpenSsl',
      ];
      $options = [];
      /** @var string $key */
      /** @var \Drupal\drd\Crypt\BaseMethodInterface $method */
      foreach (CryptBase::getMethods(TRUE) as $key => $method) {
        $options[$key] = $key;
        $condition = ['select#edit-drd-crypt-type' => ['value' => $key]];
        $form['drd-new-core-wrapper']['drd']['drd_crypt'][$key] = [
          '#type' => 'container',
          '#states' => [
            'visible' => $condition,
          ],
        ];
        $method->settingsForm($form['drd-new-core-wrapper']['drd']['drd_crypt'][$key], $condition);
      }
      $form['drd-new-core-wrapper']['drd']['drd_crypt']['drd_crypt_type']['#options'] = $options;

      // Hide the actions until the domain specific settings will be displayed.
      $form['actions']['#attributes']['class'][] = self::HIDDEN_CLASS;
    }
    else {
      $form['host']['#disabled'] = TRUE;
    }

    $this->managerStorage->buildGlobalForm($form, $form_state, $core->getUpdateSettings());

    return $form;
  }

  /**
   * Validates that the url field points to a drd_agent enabled domain.
   *
   * @param array $form
   *   Form definition.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   *
   * @return bool|string|array
   *   If the URI is not provided yet, return FALSE. If we can't receive a list
   *   of crypt methods from remote, return a message as a string explaining the
   *   reason. If all goes allright, we return an array with the list of
   *   available crypt methods.
   */
  private function validateUrl(array &$form, FormStateInterface $form_state): bool|array|string {
    $uri = trim($form_state->getValue('url'), ' /');
    if (empty($uri)) {
      return FALSE;
    }

    $client = $this->httpClientFactory->fromOptions();
    try {
      $response = $client->head($uri, ['allow_redirects' => FALSE]);
    }
    catch (GuzzleException $e) {
      return $this->t(
        'Trying to connect to the site returned the following error: @error',
        ['@error' => $e->getMessage()]
      )->render();
    }
    $status_code = $response->getStatusCode();
    if ($status_code >= 301 && $status_code <= 302) {
      $uri = $response->getHeaderLine('location');
    }
    elseif ($status_code > 302) {
      return FALSE;
    }

    /** @var \Drupal\drd\Entity\CoreInterface $core */
    $core = $this->entity;

    $values = [
      'auth' => $form_state->getValue('drd_auth_type'),
      'authsetting' => [],
      'crypt' => $form_state->getValue('drd_crypt_type'),
      'cryptsetting' => [],
    ];
    foreach ($this->authManager->getDefinitions() as $def) {
      /** @var \Drupal\drd\Plugin\Auth\BaseInterface $auth */
      try {
        $auth = $this->authManager->createInstance($def['id']);
        $values['authsetting'][$def['id']] = $auth->settingsFormValues($form_state);
      }
      catch (PluginException) {
        // Can be ignored, we checked for types right before.
      }
    }
    foreach (CryptBase::getMethods(TRUE) as $key => $method) {
      $values['cryptsetting'][$key] = $method->settingsFormValues($form_state);
    }

    try {
      $domain = DomainEntity::instanceFromUrl($core, $uri, $values);
    }
    catch (\Exception) {
      return FALSE;
    }

    if (!$domain->isNew()) {
      return $this->t('This domain is already known to the dashboard.')
        ->render();
    }

    $crypt_methods = $domain->getSupportedCryptMethods();
    if ($crypt_methods === FALSE) {
      return $this->t('Can not connect to this domain.')->render();
    }
    if (empty($crypt_methods)) {
      return $this->t('There is no DRD Agent available at this domain.')
        ->render();
    }
    if (CryptBase::countAvailableMethods($crypt_methods) === 0) {
      return $this->t('The remote site has DRD Agent installed but does not support any encryption methods matching those of the dashboard.')
        ->render();
    }

    $form_state->setTemporaryValue('drd_domain', $domain);
    return $crypt_methods;
  }

  /**
   * Ajax callback for checking remote domain.
   *
   * Ajax callback to lookup a remote domain and receive their supported crypt
   * methods which will be integrated into the settings form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Response object with instructions on how to adjust the form.
   */
  public function validateUrlAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $crypt_methods = $this->validateUrl($form, $form_state);
    if (is_array($crypt_methods)) {
      $command = 'removeClass';
      $message = '';
      foreach (CryptBase::getMethods() as $key => $value) {
        $remote_key = $this->getRemoteKey($key, $crypt_methods);
        if ($remote_key !== NULL) {
          // This method is supported on the remote site.
          $response->addCommand(new InvokeCommand('#edit-drd-crypt-type option[value="' . $remote_key . '"]', 'prop', [
            'disabled',
            !isset($crypt_methods[$remote_key]),
          ]));
        }
      }
    }
    else {
      $command = 'addClass';
      if ($crypt_methods === FALSE) {
        $crypt_methods = $this->t('Unknown error!')->render();
      }
      $message = '<div class="messages--error">' . $crypt_methods . '</div>';
    }
    $response->addCommand(new InvokeCommand('#edit-drd', $command, [self::HIDDEN_CLASS]));
    $response->addCommand(new InvokeCommand('#edit-actions', $command, [self::HIDDEN_CLASS]));
    $response->addCommand(new HtmlCommand('#edit-url-message', $message));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): CoreInterface {
    /** @var \Drupal\drd\Entity\CoreInterface $core */
    $core = parent::validateForm($form, $form_state);
    if (!$form_state->hasAnyErrors() && $core->isNew()) {
      $error = $this->validateUrl($form, $form_state);
      if (!empty($error) && is_string($error)) {
        $form_state->setErrorByName('url', $error);
      }
    }
    $this->managerStorage->validateGlobalForm($form, $form_state);
    return $core;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\drd\Entity\CoreInterface $core */
    $core = $this->entity;

    $core->set('updsettings', $this->managerStorage->globalFormValues($form, $form_state));

    $status = $core->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addMessage($this->t('Created the @label core.', [
        '@label' => $core->label(),
      ]));

      /** @var \Drupal\drd\Entity\DomainInterface $domain */
      $domain = $form_state->getTemporaryValue('drd_domain');
      $domain->setCore($core);
      $domain->save();
      $this->messenger()
        ->addMessage($this->t('Now you should @configure your remote domain. Make sure you are logged in to the remote site before you click the link!', [
          '@configure' => $domain->getRemoteSetupLink($this->t('configure'), TRUE),
        ]));
    }
    else {
      $this->messenger()->addMessage($this->t('Saved the @label Core.', [
        '@label' => $core->label(),
      ]));
    }
    $form_state->setRedirect('entity.drd_core.canonical', ['drd_core' => $core->id()]);
    return $status;
  }

  /**
   * Find the correct key from the remote list, case insensitive.
   *
   * This is necessary as some encryption methods have different
   * capitalization on different remote sites.
   *
   * @param string $key
   *   The local key to search for.
   * @param array $remote_encryption_methods
   *   The list of remote encryption methods.
   *
   * @return string|null
   *   The found key or NULL if not found.
   */
  private function getRemoteKey(string $key, array $remote_encryption_methods): ?string
  {
    foreach ($remote_encryption_methods as $remote_key => $method) {
      // Look for a case-insensitive match.
      if (strtolower($remote_key) === strtolower($key)) {
        // Return the exact key as it is on the remote site.
        return $remote_key;
      }
    }
    return NULL;
  }

}
