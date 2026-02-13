# Two-factor Authentication (TFA) module for Drupal

TFA is a base module for providing two-factor authentication for your Drupal
site. As a base module, TFA handles the Drupal integration work,
providing flexible and well tested interfaces to enable seamless, and
configurable, choice of various two-factor authentication solutions like
Time-based One Time Passwords, SMS-delivered codes, recovery codes, or
integrations with third-party suppliers like Authy, Duo and others.

[Project Page](https://www.drupal.org/project/tfa)  
[Issue Tracker](https://www.drupal.org/project/issues/tfa)  
[Online Documentation](https://project.pages.drupalcode.org/tfa/)  
<!-- This documentation is also available in the docs/ folder -->

## Requirements

This module requires the following modules:

* [Encrypt](https://www.drupal.org/project/encrypt)
* [Key](https://www.drupal.org/project/key) (Dependency of Encrypt)

## Installation and use

TFA module can be installed like other Drupal modules by placing this directory
in the Drupal file system (for example, under modules/) and enabling on
the Drupal modules page.

## Configuration

TFA can be configured on your Drupal site at Administration - Configuration -
People - Two-factor Authentication. Available plugins will be listed along with
their type and configured use, if set.

### Default validation plugin

The plugin that will be used by default during user authentication. The plugin
must be ready for use by the authenticating account. If "Require TFA" is marked
then an account that has not setup TFA with the validation plugin will be unable
to log in.

### Getting started

See [Getting started](https://project.pages.drupalcode.org/tfa/getting-started/)
for a guide on setting up TFA.

## Testing and development with TFA enabled

It can be hard to test user authentication in automated tests with the TFA
module enabled. Development environments also will likely struggle to login
unless they disable TFA or reset the secrets for an account. One solution is
to disable the module in the development and testing environment. To quickly
disable the module you can run these drush commands to set some config:

* Disable TFA with `drush config-set tfa.settings enabled 0`
* Enable TFA with `drush config-set tfa.settings enabled 1`
