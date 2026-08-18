# Installation

## Requirements

- Drupal core CKEditor 5 (`ckeditor5`).
- The [AI (Artificial Intelligence)](https://www.drupal.org/project/ai) module.
- At least one AI provider module configured with a working API key, for example [OpenAI](https://www.drupal.org/project/ai_provider_openai). The AI actions call whichever provider you configure, so set one up before you enable the plugins.

## Install with Composer

```bash
composer require drupal/ai_ckeditor
drush en ai_ckeditor
```

Installing with Composer pulls in the AI module as a dependency. Configure a provider at `Administration > Configuration > AI > Provider settings` before you continue.

## Grant the permission

The AI actions call an endpoint that is gated by a permission. Give the **Use AI CKEditor plugin** permission to any role that should be able to run the actions in the editor. Without it the plugins load but return nothing.

## Enable it on a text format

The plugins are configured per text format. See [Configuration](configuration.md) for the full walkthrough. In short:

1. Edit a text format at `Administration > Configuration > Content authoring > Text formats and editors`.
2. Drag the **AI Assistant** button into the CKEditor 5 toolbar. Add the **AI Balloon Menu** button too if you want the contextual menu on text selection.
3. Open the **AI tools** settings that appear, enable the actions you want, and choose a provider for each.

## Upgrading from the AI 1.4.x submodule

If you used this integration as part of the AI module on its 1.4.x branch, upgrade the AI module to 1.5.0 and run the database updates:

```bash
composer update drupal/ai
drush updb
```

You can also run the updates at `/update.php`. The update moves you to this standalone module and keeps your existing text format and plugin configuration.
