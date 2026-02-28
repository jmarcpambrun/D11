/**
 * @file
 * Simple data store for resolved template token entries.
 *
 * Provides a module-level lookup so any component can retrieve the full
 * entry data (label, config tokens, hidden_config) for a given object ID.
 *
 * Object IDs are composite keys: "modelOwnerId:modelId:componentId".
 */

/**
 * Map of object IDs to their full entry data.
 *
 * @type {Map<string, Object>}
 */
const entries = new Map();

/**
 * Registers an entry in the store.
 *
 * @param {Object} entry - The full entry from drupalSettings containing
 *   model_owner_id, model_id, component_id, select, config, label,
 *   hidden_config, etc.
 */
export function registerEntry(entry) {
  const id = entry.model_owner_id + ':' + entry.model_id + ':' + entry.component_id;
  entries.set(id, entry);
}

/**
 * Retrieves an entry by its composite object ID.
 *
 * @param {string} objectId - The composite key "modelOwnerId:modelId:componentId".
 * @returns {Object|undefined} The entry data, or undefined if not found.
 */
export function getEntry(objectId) {
  return entries.get(objectId);
}

/**
 * Retrieves multiple entries by their object IDs.
 *
 * @param {string[]} objectIds - An array of composite object IDs.
 * @returns {Object[]} The matched entries (skipping any not found).
 */
export function getEntries(objectIds) {
  const result = [];
  for (const id of objectIds) {
    const entry = entries.get(id);
    if (entry) {
      result.push(entry);
    }
  }
  return result;
}

/**
 * Previously applied template records from drupalSettings.
 *
 * Each record contains model_owner_id, component_id, target,
 * hidden_config, and config.
 *
 * @type {Array<Object>}
 */
let appliedTemplates = [];

/**
 * Registers the list of previously applied templates.
 *
 * @param {Array<Object>} templates - The applied template records from
 *   drupalSettings.modelerApiAppliedTemplates.
 */
export function registerAppliedTemplates(templates) {
  appliedTemplates = templates || [];
}

/**
 * Checks whether a template has been previously applied to a given target.
 *
 * Matching compares model_owner_id, component_id, target, and every
 * key/value pair in hidden_config. The config values are NOT compared
 * (they may have changed).
 *
 * @param {string} modelOwnerId - The model owner plugin ID.
 * @param {string} componentId - The component ID.
 * @param {string} target - The target value (e.g. form field name).
 * @param {Object} hiddenConfig - The hidden config key/value pairs.
 * @returns {Object|null} The matching applied template record (including
 *   its config), or null if not found.
 */
export function findAppliedTemplate(modelOwnerId, componentId, target, hiddenConfig) {
  for (var i = 0; i < appliedTemplates.length; i++) {
    var applied = appliedTemplates[i];

    if (applied.model_owner_id !== modelOwnerId) {
      continue;
    }
    if (applied.component_id !== componentId) {
      continue;
    }
    if (applied.target !== target) {
      continue;
    }

    // Compare every key/value pair in hidden_config.
    var appliedHidden = applied.hidden_config || {};
    var currentHidden = hiddenConfig || {};
    var appliedKeys = Object.keys(appliedHidden);
    var currentKeys = Object.keys(currentHidden);

    if (appliedKeys.length !== currentKeys.length) {
      continue;
    }

    var match = true;
    for (var j = 0; j < appliedKeys.length; j++) {
      var key = appliedKeys[j];
      if (appliedHidden[key] !== currentHidden[key]) {
        match = false;
        break;
      }
    }

    if (match) {
      return applied;
    }
  }

  return null;
}

/**
 * Clears all stored entries and applied templates.
 */
export function clearEntries() {
  entries.clear();
  appliedTemplates = [];
}
