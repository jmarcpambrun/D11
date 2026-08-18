# AI actions

Each action is a separate plugin. Enable the ones you want per text format in the [configuration](../configuration.md). The plugin id in brackets is what you will see in code and config.

## Completion (`ai_ckeditor_completion`)

Generate new text from a prompt and insert it at the cursor. This action does not need a text selection, so it works as a starting point on an empty editor.

## Modify with a prompt (`ai_ckeditor_modify_prompt`)

Select text, give a free-form instruction ("make this shorter", "turn this into a bulleted list"), and the action rewrites the selection accordingly.

## Tone (`ai_ckeditor_tone`)

Rewrite the selected text in a different tone, for example formal, friendly, or confident. The available tones come from a taxonomy vocabulary you configure.

## Translate (`ai_ckeditor_translate`)

Translate the selected text into another language. The language list is sourced from the site's configured languages or from a taxonomy vocabulary. When it uses a vocabulary, a term description can be passed to the model as extra translation context. Requires the core Taxonomy module.

## Summarize (`ai_ckeditor_summarize`)

Produce a shorter version of the selected content.

## Fix spelling (`ai_ckeditor_spellfix`)

Correct spelling and grammar in the selected text without otherwise rewriting it.

## Reformat HTML (`ai_ckeditor_reformat_html`)

Clean up the markup of the selected content. Useful for tidying pasted HTML.

## Help and Support (`ai_ckeditor_help`)

Show in-editor documentation of the available actions. This action does not call an AI provider.
