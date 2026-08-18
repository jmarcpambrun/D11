# For developers

The module is built around one extension point: the **AiCKEditor** plugin type. Each AI action in the editor is an AiCKEditor plugin. Add your own to put a new action in the toolbar dropdown and balloon menu.

## Architecture

- **Plugin type**: `AiCKEditor`, defined by the attribute `Drupal\ai_ckeditor\Attribute\AiCKEditor` and discovered from `src/Plugin/AiCKEditor/`.
- **Plugin manager**: the `plugin.manager.ai_ckeditor` service (`Drupal\ai_ckeditor\PluginManager\AiCKEditorPluginManager`).
- **Base class**: `Drupal\ai_ckeditor\AiCKEditorPluginBase`, which implements the shared dialog form (selected text field, generate button, editable response, save action) so a plugin only fills in the parts that differ.
- **Interface**: `Drupal\ai_ckeditor\PluginInterfaces\AiCKEditorPluginInterface`.

## Request flow

1. The CKEditor 5 UI opens the dialog form at `/api/ai-ckeditor/dialog` (`AiCKEditorDialogForm`).
2. The plugin's `ajaxGenerate()` builds the prompt and returns an `AiRequestCommand`, which posts to `/api/ai-ckeditor/request/{editor}/{ai_ckeditor_plugin}` (`AiRequest` controller).
3. The controller sends the prompt to the configured AI provider through the AI module and streams the response back into the dialog's response field.
4. Saving the dialog writes the response into the editor with an `EditorDialogSave` command.

Both routes require the **Use AI CKEditor plugin** permission (`use ai ckeditor`).

## Entity context for other modules

When the editor is on an entity form, `ai_ckeditor_form_alter()` records the editing entity's type and id in `drupalSettings`, keyed by the form's HTML id. The `AiRequest` controller reads that, loads the entity, and attaches it to the chat request as metadata under `entity_context`. Any subscriber to the AI module's `PreGenerateResponseEvent` can read `$event->getMetadata('entity_context')` to do bundle-scoped context injection without wiring anything specific to this module.

## Prompts

Default prompts are config entities under `ai.ai_prompt.*`. Each action stores the id of the prompt it uses in `ai_ckeditor.settings` (`prompts.<action>`). Prompt text uses placeholder tokens such as `{inputText}`, and some actions (Translate) render the prompt through Twig for conditional logic.

See [Adding an AI action](plugins.md) for a worked example.
