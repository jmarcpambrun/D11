## CONTENTS OF THIS FILE

- Introduction
- Requirements
- Installation
- Configuration
- Troubleshooting
- FAQ
- Maintainers

## INTRODUCTION

Another anti-spam module! Not really. Protect Form Flood Control is not really
an anti-spam module like the Honeypot, Antibot or (re)Captcha modules can be.
Protect Form Flood Control does not provide any protection on forms like classic
anti-spam. It is a kind of last line of defense against sufficiently
sophisticated bots that have managed to get past spam protection, by allowing
you to set a maximum number of submissions over a given time interval. So in
this case, this module will prevent bots from submitting tens of thousands of
submissions to your site's forms in a few hours and turning your site into a
SPAM email relay.

This module add flood control protection on any form. This prevent mass
submission on contact form or any form, using the native flood control provided
by Drupal Core.

## REQUIREMENTS

None

## INSTALLATION

 * Install as you would normally install a contributed Drupal module. See:
   https://drupal.org/documentation/install/modules-themes/modules-7
   for further information.
 * And voila


## CONFIGURATION

### General settings

You can configure the followings general settings :

* Protect all forms

When enabled, all the form are protected. Be carefully with this option, all
forms, included all forms related to the site configuration, will be protected
by flood control. This option is not recommended unless you are well aware of
the implications. With this option enabled, it is highly recommended that you
set the IP addresses of the administrators in the allowed IP list, or to give
the Bypass permission to these users.

* Window

The window interval of the flood control.

* Threshold

If a user submit more than the threshold set, during the window interval, then the
user will be blocked.

* Protected form IDs

Specify the form IDs that should be protected With Flood Control. Each form ID
should be on a separate line. Wildcard (*) characters can be used. Base form
IDs are supported. Examples of form id : comment_*, contact_*,
simplenews_subscriber_form, node_form, node_article_edit_form, etc.

* Unprotected form IDs

If the options Protect all forms is checked you can configure form IDS (or Base
form IDS) that should be NOT protected With Flood Control.

* Allowed IP addresses

You can set IP addresses that can bypass the flood control.

* Log submissions blocked

When enabled, the form submissions blocked will be logged.

### Individual settings

You can configure individual settings for each form ID.
For each form ID(s), you can specify a custom window and threshold.
These individuals settings per form IDs allows the administrator to override
general settings for a specific form.

### Find form IDs

With this option enabled, users with the
'View form Ids for protect form flood control configuration' permission
could see form ID and Base form ID displayed in a message on any
page on the site.


## TROUBLESHOOTING


## FAQ

- Forms are not protected by flood control

Check if the user do not have the permission 'bypass protect form flood control'
or if its IP address is not in the allowed IP list.

## MAINTAINERS

Current maintainers:

 - flocondetoile - https://drupal.org/u/flocondetoile
