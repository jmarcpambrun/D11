<?php

declare(strict_types=1);

namespace Drupal\tfa\Form;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\tfa\TfaLoginContext;
use Drupal\tfa\TfaLoginContextFactory;
use Drupal\tfa\TfaLoginTrait;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Helpers for modifying the User login forms.
 *
 * @internal
 */
final class TfaLoginFormHelper {
  use DependencySerializationTrait;
  use TfaLoginTrait;
  use StringTranslationTrait;

  /**
   * Construct a new TfaLoginFormHelper class.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TfaLoginContextFactory $tfaLoginContextFactory,
    protected CacheBackendInterface $memoryCache,
    TranslationInterface $translation,
    protected MessengerInterface $messenger,
    protected RequestStack $requestStack,
    protected PrivateTempStoreFactory $privateTempStoreFactory,
    PrivateKey $private_key,
    protected SessionInterface $session,
    protected RedirectDestinationInterface $destination,
  ) {
    $this->setStringTranslation($translation);
    $this->setPrivateKey($private_key);
  }

  /**
   * Alters a Login form array for use with TFA.
   *
   * TfaUserSetSubscriber provides fallback security if another module alters
   * the array to exclude TFA processing.
   *
   * Should TFA processing be removed a user can still directly provide a
   * token as part of the password field and TfaUserAuth will validate the
   * request.
   */
  public function alterLoginForm(array &$form): void {
    $config = $this->configFactory->get('tfa.settings');

    // TFA disabled no need to modify the form.
    if ($config->get('enabled') === FALSE) {
      return;
    }

    /*
     * Instruct TfaUserAuth that tfaValidateSubmit will validate tokens.
     * If contrib adds a validator before tfaLoginFormPreValidation or after
     * tfaLoginFormPostValidation users will be required to provide a token
     * to validate passwords or login without token if skips allowed.
     * Non-password based solutions will be able to validate users.
     * TfaUserSetSubscriber will prevent user_login() calls during validate
     * stage from being accepted.
     *
     * self::tfaLoginFormPreValidation() is intended (though not required) to
     *  be the first validator before any other (core or contrib).
     *
     * self::tfaLoginFormPostValidation() is intended (though not required) to
     * be the last validator after any other (core or contrib).
     */
    array_unshift($form['#validate'], [$this, 'tfaLoginFormPreValidation']);
    $form['#validate'][] = [$this, 'tfaLoginFormPostValidation'];

    /*
     * Allow TFA to redirect to the TfaEntryForm if required.This should be
     * early to avoid UserLogin::submitForm() from attempting a user_login()
     * call before TFA redirect can occur.
     */
    array_unshift($form['#submit'], [$this, 'tfaValidateSubmit']);

    // After UserLogin::submitForm() set the form redirect destination.
    $form['#submit'][] = [$this, 'tfaSetRedirectAfterSubmit'];
  }

  /**
   * Prepare TfaUserAuth for UserLogin::validateAuthentication callback.
   */
  public function tfaLoginFormPreValidation(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\user\UserInterface[] $accounts */
    $accounts = $this->entityTypeManager
      ->getStorage('user')
      ->loadByProperties(
        [
          'name' => $form_state->getValue('name'),
          'status' => 1,
        ]
      );

    if (count($accounts) !== 1) {
      return;
    }
    $account = reset($accounts);

    // Notifies TfaUserAuth that TFA validation will be done by TfaEntryForm.
    $this->memoryCache->set('bypass_tfa_auth_for_user', $account->getAccountName());
  }

  /**
   * Cleanup after UserLogin::validateAuthentication is complete.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The formState.
   */
  public function tfaLoginFormPostValidation(array &$form, FormStateInterface $form_state): void {
    // Clear TfaUserAuth password processing exemption.
    $this->memoryCache->delete('bypass_tfa_auth_for_user');
  }

  /**
   * Login submit handler.
   *
   * Determine if TFA process applies. If not, call the parent form submit.
   */
  public function tfaValidateSubmit(array &$form, FormStateInterface $form_state): void {

    $uid = $form_state->get('uid');

    /** @var ?\Drupal\user\UserInterface $user */
    $user = $this->entityTypeManager
      ->getStorage('user')
      ->load($uid);

    // Should only occur if the user is deleted after validation and before
    // our method. There is no action for us to take if the account doesn't
    // exist.
    if ($user == NULL) {
      return;
    }

    // TFA Already validated. This may occur if the TfaUserAuth validates the
    // user may login, either self::tfaLoginFormPreValidation missing from
    // form, or password validation occurred by a 3rd party.
    $tfa_complete_this_request = $this->memoryCache->get('tfa_complete');
    if ($tfa_complete_this_request !== FALSE) {
      $user_auth_as_id = $tfa_complete_this_request->data;
      if (is_int($user_auth_as_id) && $user_auth_as_id === (int) $user->id()) {
        // Early return, allow the rest of normal Drupal UI to process.
        return;
      }
    }

    $login_context = $this->tfaLoginContextFactory->createContextFromUser($user);

    if ($login_context->isTfaDisabled()) {
      $this->memoryCache->set('tfa_complete', (int) $login_context->getUser()->id());
      $this->setRedirectDestinationBasedOnPermissions($form_state, $login_context);
      return;
    }

    if ($login_context->isReady()) {
      $this->loginWithTfa($form_state, $login_context);
    }
    else {
      $this->loginWithoutTfa($form_state, $login_context);
    }

  }

  /**
   * Set redirect in form_state.
   *
   * Should be last invoked form submit handler for forms user_login and
   * user_login_block so that when the TFA process is applied the user will be
   * redirected to the desired destination.
   *
   * @param array $form
   *   The current form api array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public function tfaSetRedirectAfterSubmit(array $form, FormStateInterface $form_state): void {
    $route = $form_state->get('tfa_redirect_target');
    if (isset($route) && is_array($route) && !empty($route['route_name'])) {
      $current_request = $this->requestStack->getCurrentRequest();
      if ($current_request?->query?->has('destination')) {
        $current_request->query->remove('destination');
      }
      $form_state->setRedirect($route['route_name'], $route['route_parameters'] ?? [], $route['options'] ?? []);
    }
  }

  /**
   * Handle login when TFA is set up for the user.
   *
   * If any of the TFA plugins allows login, then finalize the login. Otherwise,
   * set a redirect to enter a second factor.
   */
  private function loginWithTfa(FormStateInterface $form_state, TfaLoginContext $login_context): void {
    $user = $login_context->getUser();
    if ($login_context->pluginAllowsLogin()) {
      // Plugin allows login, Set the complete flag.
      $this->memoryCache->set('tfa_complete', (int) $user->id());
      $this->messenger->addStatus($this->t('You have logged in on a trusted browser.'));
      $this->storeRedirectDestination($form_state, '<front>');
      // UserLoginForm::submitForm() makes the user_login_finalize() call.
      return;
    }

    /*
     * Unset the UID to prevent UserLoginForm::submitForm() attempting
     * to call user_login_finalize().
     */
    $this->unsetUidFormState($form_state);

    // Regenerate the session ID to prevent against session fixation attacks.
    $this->session->migrate();

    // Begin TFA and set process context.
    $current_request = $this->requestStack->getCurrentRequest();
    if ($current_request && $current_request->query->has('destination')) {
      $parameters = $this->destination->getAsArray();
      $current_request->query->remove('destination');
    }
    else {
      $parameters = [];
    }
    $parameters['uid'] = $user->id();
    $parameters['hash'] = $this->getLoginHash($user);
    $this->storeRedirectDestination($form_state, 'tfa.entry', $parameters);

    // Store UID in order to later verify access to entry form.
    $this->privateTempStoreFactory->get('tfa')->set('tfa-entry-uid', $user->id());
  }

  /**
   * Handle the case where TFA is not yet set up.
   *
   * If the user has any remaining logins, then finalize the login with a
   * message to set up TFA. Otherwise, leave the user logged out.
   */
  private function loginWithoutTfa(FormStateInterface $form_state, TfaLoginContext $login_context): void {

    if ($login_context->canLoginWithoutTfa()) {
      // User does not require TFA. Set the complete flag.
      $this->memoryCache->set('tfa_complete', (int) $login_context->getUser()->id());
      $login_context->hasSkipped();
      $this->setRedirectDestinationBasedOnPermissions($form_state, $login_context);
      return;
    }

    /*
     * Unset the UID to prevent UserLoginForm::submitForm() attempting
     * to call user_login_finalize().
     *
     * TfaLoginContext::canLoginWithoutTfa() has already set a warning message.
     */
    $this->unsetUidFormState($form_state);
  }

  /**
   * Removes the 'validated' UID from the $form_state.
   *
   * This prevents UserLoginForm::submitForm() from processing the user login.
   */
  private function unsetUidFormState(FormStateInterface $form_state): void {
    $storage = $form_state->getStorage();
    unset($storage['uid']);
    $form_state->setStorage($storage);
  }

  /**
   * Set destination for form redirect based on user permission.
   */
  private function setRedirectDestinationBasedOnPermissions(FormStateInterface $form_state, TfaLoginContext $login_context): void {
    $user = $login_context->getUser();
    $redirect_config = $this->configFactory->get('tfa.settings')->get('users_without_tfa_redirect');
    if ($redirect_config && $user->hasPermission("setup own tfa")) {
      $this->storeRedirectDestination($form_state, 'tfa.overview', ['user' => $user->id()]);
    }
    else {
      $this->storeRedirectDestination($form_state, '<front>');
    }
  }

  /**
   * Store redirect destination for use in tfaSetRedirectAfterSubmit()
   */
  private function storeRedirectDestination(FormStateInterface $form_state, string $route_name, array $route_parameters = [], array $options = []): void {
    $form_state->set(
      'tfa_redirect_target',
      [
        'route_name' => $route_name,
        'route_parameters' => $route_parameters,
        'options' => $options,
      ]
    );
  }

}
