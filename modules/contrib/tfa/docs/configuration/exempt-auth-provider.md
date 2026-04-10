# Exempting Authentication Providers from TFA

## TFA and Authentication Providers
Drupal Core utilized services tagged as ```authentication_provider``` for
authenticating user requests.

TFA intercepts core operations immediately after an Authentication Provider
determines the user.

Authentication requests that do not utilize the ```user.auth``` service or
the ```session``` provider will be rejected by TFA due to the lack of a
second factor credential being provided with the request.

## Reasons to exempt a provider
Common reasons to exempt an Authentication Provider:

* Authentication provided by a 3rd party (SSO)
* Authentication provided by an API token intended for use by automated
  tooling.

## Configuring exempt providers
:warning: Exempting an Authentication provider will allow access without
Two-Factor authentication being enforced.

:information: This feature requires that the Authentication Provider only
implement AuthenticationProviderInterface and
AuthenticationProviderChallengeInterface.

To exempt an Authentication Provider the ```tfa.auth_provider_bypass```
container parameter may be set with an array of the provider_id's that should
not be subject to enforcement of TFA.

```yml title='local.services.yml'
tfa.auth_provider_bypass:
  - 'exempt_provider_1'
  - 'exempt_provider_2'
```

### Mandatory providers
The following providers may not be exempted:

* cookie
* http_basic
