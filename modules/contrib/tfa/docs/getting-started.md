# Getting started

This module requires a few dependencies to be setup before it can be configured.

The following is intended as a "Quick Start" guide. You should review all
options prior to deploying in production.

Note: Any changes to the Encryption Method or Encrypt Key after initial
configuration will place TFA in an undefined state. There is no encryption
migration feature at this time. TFA should be reset for all users if the
encryption method or key is changed.

## Encryption method - [Real AES](https://www.drupal.org/project/real_aes)

An encryption method module is required to be able to use the Key and Encrypt
modules. Several encryption plugins are available, Real AES is not the only
method available. For more options please see the
[Encrypt Project Page](https://www.drupal.org/project/encrypt) or
[Encrypt Ecosystem List](https://www.drupal.org/project/encrypt/ecosystem).

## Install Key, Encrypt, and the Encryption method plugin

Before proceeding to the next steps install Key, Encrypt, and your chosen
Encryption plugin according to their instructions. With a composer install of
TFA these modules should already be download to your contrib module directory
and just need to be installed/enabled inside of Drupal.

## [Key](https://www.drupal.org/project/key)

The key module provides Drupal access to an encryption key you create.

Setting up the key module:

* Generate a new key:
    * Real AES requires a 256 `bit key, other plugins may require a different
      key length.
    * On Linux/OSX a 256 bit(32 byte) base64 encoded key can be created with
      the following CLI command:

      `dd if=/dev/urandom bs=32 count=1 | base64 -i - > /path/to/my/encrypt.key`

      :warning: Ensure that the file is not inside the server docroot and
      can be read only by yourself and the account Drupal executes as.

* Visit the Keys module's configuration page and "Add Key"
    * Name your Key
    * Key type: "Encryption"
    * Provider: "File"
    * File location: `/path/to/my/encrypt.key` as generated above.
    * Base64-encoded: Checked
    * Save

## [Encrypt](https://www.drupal.org/project/encrypt)

The encrypt module allows the site owner to define encryption profiles that
can be reused throughout Drupal. The TFA module requires an encryption profile
to be defined to be configured properly.

* Visit the Encrypt module's configuration page and "Add Encryption Profile"
    * Label your Encryption Profile
    * Encryption method: "Authenticated AES (Real AES)" - or the encryption
      method of your choice.
    * Encryption Key: Select the Key you created in the previous step.
    * Save

## TFA configuration

Now you should be ready to configure the TFA module.

* Install the TFA module (if not already installed).
* (Optional) Install a module providing an extra TFA plugin.
* Visit admin/people/permissions and grant the "Authenticated user' role
  the "Set up TFA for account" permission.
* Visit the TFA module's configuration page.
    * Enable TFA.
    * Select your desired Validation Plugin(s).
    * Encryption Profile: Select the Encryption Profile you created in the
      previous step.
    * Adjust other settings as desired.
    * Consider selecting "Roles required to set up TFA" for some roles,
      especially Administrator roles.
    * Save

* Visit your account's TFA tab: `user/[uid]/security/tfa`
    * Configure the selected Validation Plugins as desired for your account.

## HOTP/TOTP applications

Users will need an application for their phone, tablet or computer that is
capable of generating authentication codes.  As of the date of this ReadMe,
some of the more popular options include:

* Google Authenticator (Android, iOS)
* Microsoft Authenticator (Android, iOS)
* Twilio Authy (Android, iOS)
* FreeOTP (Android, iOS)
* WinAuth (Windows)
* GAuth Authenticator (Chrome browser extension and Chrome OS)
