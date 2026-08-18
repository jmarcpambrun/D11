# Adding an AI action

An AI action is an AiCKEditor plugin. Put a class in your module's `src/Plugin/AiCKEditor/`, give it the `AiCKEditor` attribute, and extend `AiCKEditorPluginBase`. The base class provides the dialog (selected text field, generate button, editable response, and save action), so you implement only what your action adds.

## Minimal example

```php
<?php

namespace Drupal\my_module\Plugin\AiCKEditor;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_ckeditor\AiCKEditorPluginBase;
use Drupal\ai_ckeditor\Attribute\AiCKEditor;
use Drupal\ai_ckeditor\Command\AiRequestCommand;

/**
 * Rewrites the selected text as a list.
 */
#[AiCKEditor(
  id: 'my_module_listify',
  label: new TranslatableMarkup('Turn into a list'),
  description: new TranslatableMarkup('Rewrite the selected text as a bulleted list.'),
  module_dependencies: [],
)]
final class Listify extends AiCKEditorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'provider' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $options = $this->aiProviderManager->getSimpleProviderModelOptions('chat', FALSE);
    $form['provider'] = [
      '#type' => 'select',
      '#title' => $this->t('AI provider'),
      '#empty_option' => $this->t('-- Default from AI module (chat) --'),
      '#options' => $options,
      '#default_value' => $this->configuration['provider'] ?? $this->aiProviderManager->getSimpleDefaultProviderOptions('chat'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['provider'] = $form_state->getValue('provider');
  }

  /**
   * Generate callback wired to the dialog's generate button.
   */
  public function ajaxGenerate(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $prompt = 'Rewrite the following as a bulleted HTML list: ' . $values['plugin_config']['selected_text'];

    $response = new AjaxResponse();
    $response->addCommand(new AiRequestCommand(
      $prompt,
      $values['editor_id'],
      $this->pluginDefinition['id'],
      'ai-ckeditor-response',
    ));
    return $response;
  }

}
```

## What the base class gives you

`AiCKEditorPluginBase::buildCkEditorModalForm()` builds the standard dialog: a disabled field showing the selected text, a generate button wired to your `ajaxGenerate()`, an editable rich text field for the response, and a save action that writes the response back into the editor. Override the following when you need to:

- `defaultConfiguration()`: declare your stored settings.
- `buildConfigurationForm()` / `submitConfigurationForm()`: add and persist per-format settings (at minimum a provider select).
- `ajaxGenerate()`: build the prompt from the submitted values and return an `AiRequestCommand`. This is the one method every action must provide.
- `buildCkEditorModalForm()`: call the parent, then append action-specific fields (see the Translate plugin for language selection).
- `needsSelectedText()`: return `FALSE` if your action works without a selection (as Completion does).
- `getGenerateButtonLabel()`, `getSelectedTextLabel()`, `getAiResponseLabel()`, `getNoSelectedTextMessage()`: override the dialog labels.

## AiRequestCommand

`AiRequestCommand($prompt, $editor_id, $plugin_id, $wrapper_id)` tells the client to POST the prompt to the request controller and stream the model's answer into the response field identified by `$wrapper_id` (`ai-ckeditor-response` in the standard dialog). The controller resolves the provider from the plugin's saved configuration, so you do not call the provider directly.

## Prompts and services

To make your prompt configurable, store its text as an `ai.ai_prompt.*` config entity and read the chosen id from your own settings, following the pattern in the built-in actions. Inject any extra services by overriding the constructor and `create()`; the base class already provides the AI provider manager, entity type manager, current user, request stack, logger factory, and language manager.

## Declaring dependencies

Use the attribute's `module_dependencies` to require modules your action needs. The Translate plugin, for example, declares `['taxonomy']`. An action whose dependencies are not met is not offered.
