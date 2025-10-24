This module integrates to GitLab utilizing the GitLab API version 4.

All API resources are available through simple calls like

```php
$this->api->getClient()->groups();
```

to receive a list of all groups on a GitLab instance.

The module supports GitLab server profiles as config entities, so you can create
as many you like and talk to all the GitLab instances that matter to you from
within your Drupal site.

This module also comes with a [webform](https://www.drupal.org/project/webform)
handler plugin which allows you to create a new project from a webform
submission.

With the ECA GitLab API submodule, you can also leverage all API methods from
within [ECA](https://www.drupal.org/project/eca) models.
