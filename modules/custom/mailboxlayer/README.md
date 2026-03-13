CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Recommended modules
 * Installation
 * Configuration
 * Maintainers


INTRODUCTION
------------

This module integrates the Mailboxlayer API (https://mailboxlayer.com/) with the
Webforms (Containing email fields ).
This will enable a checkbox in all the Email fields in your webform. Just check
the checkbox and save the webform. And you're ready to go. (Of course, you will
have to provide the Mailboxlayer API KEY).

 * For a full description of the module, visit the project page:
   https://www.drupal.org/project/mailboxlayer

 * To submit bug reports and feature suggestions, or track changes:
   https://www.drupal.org/project/issues/mailboxlayer


REQUIREMENTS
------------

This module requires the following modules:

 * Webform (https://www.drupal.org/project/webform)


RECOMMENDED MODULES
-------------------

No recommended modules.


INSTALLATION
------------

 * Install as you would normally install a contributed Drupal module. Visit
   https://www.drupal.org/docs/8/extending-drupal-8/installing-drupal-8-modules
   for further information.


CONFIGURATION
-------------

 * Visit the mailboxlayer settings form at
   (/admin/config/mailboxlayer) or Configuration >> Mailboxlayer Configuration
   >> Mailboxlayer Configuration Form.

 * The API key should vary from instance to instance. We should not use the same
   API key on our development and production site(because the the number of API
   request is limited for each key per day).
   We have option to add the API key in the configuration form, but that field
   should be used only for the testing purposes(for Dev and Local environment).
   When you export the configuration, the API key will also get exported. So to
   avoid this either you can ignore the `mailboxlayer.settings` file during
   Configuration import or you can override the API key from settings.php
   file(which is the recommended approach).

   Add the following line to settings.php:
   `$config['mailboxlayer.settings']['mailbox_layer']['api_key'] = 'API KEY';`
   This will also make sure that your API key is secure.


MAINTAINERS
-----------

Current maintainers:
 * Kunal Singh - https://www.drupal.org/u/kunal_singh
