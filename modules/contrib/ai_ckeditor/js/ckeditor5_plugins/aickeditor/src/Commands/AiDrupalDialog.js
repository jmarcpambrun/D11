import { Command } from 'ckeditor5/src/core';

/**
 * Reads entity context that ai_ckeditor_form_alter() attached to the
 * host entity form via drupalSettings. The server-side hook keys each
 * host form's entity info by the form's HTML id, which is the most
 * reliable handle we can reach from a CKEditor instance.
 *
 * Returns empty strings when no context is available. Entity context is
 * optional: subscribers that care (like ai_context) will fall back to
 * global context when it is missing.
 *
 * @param {object} editor
 *   The CKEditor instance.
 *
 * @return {{entityType: string, entityId: string}}
 *   Entity type and id, or empty strings when none is available.
 */
function getEntityContext(editor) {
  const result = { entityType: '', entityId: '' };

  try {
    const hostForm = editor.sourceElement?.closest('form');
    const formId = hostForm?.getAttribute('id');
    const registry = drupalSettings?.aiCkeditor?.entityContext;
    if (!formId || !registry) {
      return result;
    }
    const entry = registry[formId];
    if (!entry) {
      return result;
    }
    result.entityType = entry.entity_type || '';
    result.entityId = entry.id || '';
  } catch (e) {
    // Silently fail - entity context is optional.
  }

  return result;
}

export default class AiDrupalDialog extends Command {
  execute(_groupName, pluginId, pluginLabel) {
    const { config } = this.editor;
    const options = config.get('ai_ckeditor_ai');
    const { dialogURL, openDialog, dialogSettings = {} } = options;

    if (!dialogURL || typeof openDialog !== 'function') {
      return;
    }

    const selected = this.editor.editing.model.getSelectedContent(
      this.editor.model.document.selection,
    );
    const selectedText = this.editor.data.stringify(selected) ?? '';

    dialogSettings.title = `${dialogSettings.title} - ${pluginLabel}`;

    const url = new URL(dialogURL, document.baseURI);

    const entityInfo = getEntityContext(this.editor);

    openDialog(
      url.toString(),
      ({ attributes }) => {
        const { model } = this.editor;
        model.change((writer) => {
          const { selection } = model.document;

          // If the insert position is a selection, remove the selection.
          if (selection.hasOwnRange) {
            const range = selection.getFirstRange();
            writer.remove(range);
          }

          if (
            typeof attributes.returnsHtml !== 'undefined' &&
            attributes.returnsHtml
          ) {
            // Convert the value to html and insert it.
            const viewFragment = this.editor.data.processor.toView(
              attributes.value,
            );
            const modelFragment = this.editor.data.toModel(viewFragment);
            this.editor.model.insertContent(modelFragment);
          } else {
            this.editor.model.insertContent(
              writer.createText(attributes.value),
            );
          }
        });
      },
      dialogSettings,
      {
        selected_text: selectedText,
        editor_id: this.editor.sourceElement.dataset.editorActiveTextFormat,
        plugin_id: pluginId,
        entity_type: entityInfo.entityType,
        entity_id: entityInfo.entityId,
      },
    );
  }

  /**
   * If the dialog is active, disable the AI plugin.
   */
  refresh() {
    const el = document.getElementsByClassName(
      'ckeditor5-ai-ckeditor-dialog-form',
    );
    this.isEnabled = el.length === 0;
    this.isOn = this.isEnabled;
    this.isReadOnly = this.isEnabled;
  }
}
