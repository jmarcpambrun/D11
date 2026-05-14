This module integrates to GitLab utilizing the GitLab API version 4.

All API resources are available through simple calls like

```php
$api = \Drupal::service('gitlab_api.api');
$groups = $api->getClient()->groups();
```

to receive a list of all groups on a GitLab instance.

The module supports GitLab server profiles as config entities, so you can create
as many you like and talk to all the GitLab instances that matter to you from
within your Drupal site.

Beware, if you're using this together with Drupal's GitLab instance at
git.drupalcode.org, you may have limited access due to restricted permissions,
see [Allow full access to Gitlab API (subject to user permission
limits)](https://www.drupal.org/project/infrastructure/issues/3379836) for
further details.

This module utilizes the [GitLabPHP Client](https://github.com/GitLabPHP/Client)
and dynamically provides all the methods, that are implemented there, to the
API service of this module.

Full documentation of the GitLab API can be found at
https://docs.gitlab.com/ee/api/api_resources.html

With the ECA GitLab API submodule, you can also leverage all API methods from
within [ECA](https://www.drupal.org/project/eca) models.

This module also comes with a [webform](https://www.drupal.org/project/webform)
handler plugin which allows you to create a new project from a webform
submission.
