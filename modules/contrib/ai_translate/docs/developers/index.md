# For developers

AI Translate is built around a small plugin type and two services.

## Field text extractor plugins

Field text extractors decide which fields are translated, pull the translatable
text out of them, and write the translation back. The module ships extractors for
text, text-with-summary, link, image, file, entity reference, and Layout Builder
fields. Add your own to support a custom field type. See
[Field text extractors](field-text-extractors.md).

## Services

| Service ID | Class | Purpose |
|---|---|---|
| `ai_translate.text_extractor` | `Drupal\ai_translate\TextExtractor` | Collects text metadata from an entity's fields and writes translations back. |
| `ai_translate.text_translator` | `Drupal\ai_translate\TextTranslator` | Sends text to the AI provider and returns the translation. |
| `plugin.manager.text_extractor` | `Drupal\ai_translate\FieldTextExtractorPluginManager` | Discovers and loads field text extractor plugins. |

The public entry points are `TextExtractorInterface` and
`TextTranslatorInterface`. The Drush commands and the Translate tab controller
both use these services, so calling them directly gives the same behavior.

## AI provider plugin

The module also ships an AI provider plugin, `chat_translation` (*Chat proxy to
LLM*, `Drupal\ai_translate\Plugin\AiProvider\ChatTranslationProvider`). It
implements the AI module's translation operation by delegating to a chat model,
which is how a plain chat provider can serve as the translator. This is a normal
[AI provider plugin](https://www.drupal.org/project/ai); refer to the AI module's
developer docs to build your own.
