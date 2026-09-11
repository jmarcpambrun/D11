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
3. The controller sends the prompt to the configured AI provider through the AI module and streams the response back into the dialog's response field. Chat calls carry provider request tags `ai_ckeditor` (which type) and `ai_ckeditor:{plugin_id}` (which tool).
4. Saving the dialog writes the response into the editor with an `EditorDialogSave` command.

Both routes require the **Use AI CKEditor plugin** permission (`use ai ckeditor`).

## Context Control Center

[AI Context](https://www.drupal.org/project/ai_context) (1.0.0-beta5 or later) can push selected site context into an AI system prompt. That UI is the Context Control Center (CCC). This module ships a CCC **consumer type** plugin so each CKEditor AI tool can receive that context. The plugin lives in the main module. It is discovered only when AI Context is enabled. There is no extra submodule and no hard dependency.

A **context consumer** is one tool that can receive pushed context. Canonical context consumer IDs use the short type prefix, the same pattern as `agent:` and `automator:`:

- `ckeditor:ai_ckeditor_completion` is Generate with AI
- `ckeditor:ai_ckeditor_tone` is Tone

Provider request tags use the module machine name (`ai_ckeditor`), not that short type prefix. That split is intentional and matches Agent and Automator.

CCC matches a chat call only when the provider request tags include exactly one `ai_ckeditor:{plugin_id}` for a real chat tool. The coarse `ai_ckeditor` tag only makes the CKEditor type eligible. The text format is not used: one format can enable several tools, so the format cannot identify which consumer should receive context.

On **Context consumers**, a tool appears only when at least one enabled text format has it enabled. Disabled formats do not count. The consumer description keeps the plugin text and adds which formats enable that tool (for example `Enabled on "Full HTML" and "Restricted HTML" text formats.`). Each tool name links to the **Text formats and editors** page (`/admin/config/content/formats`). If exactly one text format has that tool enabled, the name links to that format instead (for example `/admin/config/content/formats/manage/full_html`). A tool enabled on two or more formats stays on the overview. The link is hidden when the user cannot administer filters.

Automatic push is off for every tool until an admin turns on **Push context automatically** on that tool under **Context consumers**. Help and Support is not a consumer; it never calls the provider.

Scope subscriptions that filter by entity type, taxonomy terms, or a specific entity work automatically when the editor is on an entity form — this module attaches the entity being edited to each chat request.

## Entity context for other modules

When the editor is on an entity form, `ai_ckeditor_form_alter()` records the editing entity's type and id in `drupalSettings`, keyed by the form's HTML id. The `AiRequest` controller reads that, loads the entity, and attaches it to the chat request as metadata under `entity_context`. Any subscriber to the AI module's `PreGenerateResponseEvent` can read `$event->getMetadata('entity_context')` to do bundle-scoped context injection without wiring anything specific to this module.

## Prompts

Default prompts are config entities under `ai.ai_prompt.*`. Each action stores the id of the prompt it uses in `ai_ckeditor.settings` (`prompts.<action>`). Prompt text uses placeholder tokens such as `{inputText}`, and some actions (Translate) render the prompt through Twig for conditional logic.

See [Adding an AI action](plugins.md) for a worked example.
