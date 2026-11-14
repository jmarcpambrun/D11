<?php

/**
 * @file
 * Post update functions for AI Integration - ECA.
 */

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Moves the Chat system prompt into its own configuration field.
 *
 * The Chat action never had a field for the system prompt. The only way to set
 * one was to add "system_name" and "system_prompt" keys to the free-form YAML
 * of the "Specific configuration for the model" field, which the action then
 * pulled back out and appended as an extra chat message. That field is meant
 * for provider settings and is validated against the provider API, so both keys
 * had to be excused from that validation, and they were handed to the provider
 * anyway, which rejected them as unknown parameters.
 *
 * The action now has a "System prompt" field of its own, so the value is moved
 * there and both legacy keys are dropped. "system_name" allowed an arbitrary
 * role for the system message; ChatInput::setSystemPrompt() has no equivalent
 * and providers normalize that role themselves, so it is dropped without a
 * replacement.
 *
 * @return string
 *   A summary of what has been moved.
 *
 * @see https://www.drupal.org/project/ai_integration_eca/issues/3585026
 * @see https://www.drupal.org/project/ai_integration_eca/issues/3573445
 */
function ai_integration_eca_post_update_chat_system_prompt(): string {
  $configFactory = \Drupal::configFactory();
  $migrated = [];
  $unparsable = [];

  foreach ($configFactory->listAll('eca.eca.') as $name) {
    $config = $configFactory->getEditable($name);
    $actions = $config->get('actions') ?? [];
    $rewritten = [];

    foreach ($actions as $id => $action) {
      if (($action['plugin'] ?? '') !== 'ai_integration_eca_execute_chat') {
        continue;
      }
      $raw = $action['configuration']['config'] ?? '';
      if (!is_string($raw) || trim($raw) === '') {
        continue;
      }
      try {
        $modelConfig = Yaml::decode($raw);
      }
      catch (InvalidDataTypeException) {
        // Model configuration that does not even parse never reached a provider
        // and there is no way of telling here what it was meant to say. It is
        // reported and left alone rather than rewritten into something that is
        // broken in a different way.
        $unparsable[] = $name . ' / ' . $id;
        continue;
      }
      if (!is_array($modelConfig)) {
        continue;
      }
      if (!array_key_exists('system_name', $modelConfig) && !array_key_exists('system_prompt', $modelConfig)) {
        continue;
      }

      $systemPrompt = $modelConfig['system_prompt'] ?? '';
      unset($modelConfig['system_name'], $modelConfig['system_prompt']);

      $configuration = $action['configuration'];
      // A value already present in the new field wins: it is the only one the
      // action has been sending since that field exists.
      if (($configuration['system_prompt'] ?? '') === '' && is_scalar($systemPrompt)) {
        $configuration['system_prompt'] = (string) $systemPrompt;
      }
      // What is left of the model configuration is dumped anew, so comments and
      // formatting in this field are normalized. Only an action that carries a
      // legacy key gets this far, so every other one keeps its layout.
      $configuration['config'] = $modelConfig === [] ? '' : Yaml::encode($modelConfig);

      $actions[$id]['configuration'] = $configuration;
      $rewritten[$id] = $configuration;
      $migrated[] = $name . ' / ' . $id;
    }

    if ($rewritten === []) {
      continue;
    }
    $config->set('actions', $actions);
    _ai_integration_eca_post_update_migrate_diagram($configFactory, $config, $rewritten, '_ai_integration_eca_post_update_rewrite_diagram');
    $config->save();
  }

  if ($migrated === [] && $unparsable === []) {
    return 'No Chat action carried a system prompt in its model configuration.';
  }

  $message = [];
  if ($migrated !== []) {
    $message[] = sprintf(
      'Moved the system prompt of %d Chat action(s) into the dedicated field: %s.',
      count($migrated),
      implode(', ', $migrated)
    );
  }
  if ($unparsable !== []) {
    $message[] = sprintf(
      'Left %d Chat action(s) untouched because their model configuration is not valid YAML: %s.',
      count($unparsable),
      implode(', ', $unparsable)
    );
  }

  return implode(' ', $message);
}

/**
 * Applies the same change to the diagram the modeler stores for a model.
 *
 * A BPMN diagram carries its own copy of the action configuration and is the
 * document the modeler edits, so a model whose diagram still holds the legacy
 * keys would fall back to them the next time it is saved from the modeler. The
 * JSON modeler rebuilds its graph from the configuration entity on every edit
 * and needs nothing here, which is why only a camunda-flavoured diagram is
 * touched; anything else is left as it is.
 *
 * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
 *   The configuration factory.
 * @param \Drupal\Core\Config\Config $config
 *   The editable ECA configuration object. It is not saved here.
 * @param array $rewritten
 *   The rewritten action configuration, keyed by action ID.
 * @param callable $rewrite
 *   The diagram rewrite function, called with the raw diagram and the rewritten
 *   action configuration and returning the updated diagram.
 *
 * @see \Drupal\modeler_api\Plugin\ModelerApiModelOwner\ModelOwnerBase::getModelData()
 */
function _ai_integration_eca_post_update_migrate_diagram(ConfigFactoryInterface $configFactory, Config $config, array $rewritten, callable $rewrite): void {
  $data = $config->get('third_party_settings.modeler_api.data');
  if (!is_string($data) || $data === '') {
    return;
  }

  // The diagram is either embedded in the third-party settings or stored in a
  // data model of its own, in which case the settings only hold its hash.
  $dataConfig = NULL;
  if (strlen($data) === 37 && str_starts_with($data, 'hash:')) {
    $modelerId = $config->get('third_party_settings.modeler_api.modeler_id') ?? 'fallback';
    $storageId = implode('_', ['eca', $modelerId, substr($config->getName(), strlen('eca.eca.'))]);
    $dataConfig = $configFactory->getEditable('modeler_api.data_model.' . $storageId);
    $data = $dataConfig->get('data');
    if (!is_string($data) || $data === '') {
      return;
    }
  }

  $updated = $rewrite($data, $rewritten);
  if ($updated === $data) {
    return;
  }

  if ($dataConfig === NULL) {
    $config->set('third_party_settings.modeler_api.data', $updated);
    return;
  }
  $dataConfig->set('data', $updated)->save();
  $config->set('third_party_settings.modeler_api.data', 'hash:' . hash('md5', $updated));
}

/**
 * Rewrites the Chat action fields of a BPMN diagram.
 *
 * @param string $xml
 *   The raw diagram.
 * @param array $rewritten
 *   The rewritten action configuration, keyed by action ID.
 *
 * @return string
 *   The rewritten diagram, or the original one when it is not a BPMN diagram,
 *   could not be parsed, or held nothing to change.
 */
function _ai_integration_eca_post_update_rewrite_diagram(string $xml, array $rewritten): string {
  if (!str_contains($xml, 'camunda:field')) {
    return $xml;
  }

  $document = new \DOMDocument();
  $previous = libxml_use_internal_errors(TRUE);
  $loaded = $document->loadXML($xml);
  libxml_clear_errors();
  libxml_use_internal_errors($previous);
  if (!$loaded) {
    return $xml;
  }

  $xpath = new \DOMXPath($document);
  $changed = FALSE;
  // Every BPMN element that has extension elements. The element ID is the ID of
  // the action in the configuration entity.
  foreach ($xpath->query('//*[local-name() = "extensionElements"]/..') as $element) {
    if (!$element instanceof \DOMElement || !isset($rewritten[$element->getAttribute('id')])) {
      continue;
    }
    // The ID alone does not prove which plugin the element stands for.
    $properties = $xpath->query('.//*[local-name() = "property" and @name = "pluginid"]', $element);
    $property = $properties->item(0);
    if (!$property instanceof \DOMElement || $property->getAttribute('value') !== 'ai_integration_eca_execute_chat') {
      continue;
    }
    $extensions = $xpath->query('*[local-name() = "extensionElements"]', $element);
    $extension = $extensions->item(0);
    if (!$extension instanceof \DOMElement) {
      continue;
    }

    $configuration = $rewritten[$element->getAttribute('id')];
    $changed = _ai_integration_eca_post_update_set_diagram_field($document, $xpath, $extension, 'config', $configuration['config']) || $changed;
    // The new field is added in front of the prompt, where the action form has
    // it too.
    $changed = _ai_integration_eca_post_update_set_diagram_field($document, $xpath, $extension, 'system_prompt', $configuration['system_prompt'] ?? '', 'prompt') || $changed;
  }

  return $changed ? $document->saveXML() : $xml;
}

/**
 * Sets the value of a single camunda field, adding the field when it is absent.
 *
 * @param \DOMDocument $document
 *   The diagram.
 * @param \DOMXPath $xpath
 *   The XPath query object for the diagram.
 * @param \DOMElement $extension
 *   The extension elements of the BPMN element to work on.
 * @param string $name
 *   The name of the field.
 * @param string $value
 *   The value to set. An absent field is only added for a non-empty value.
 * @param string|null $before
 *   The name of the field to add a new field in front of, if any.
 *
 * @return bool
 *   TRUE if the diagram was changed, FALSE otherwise.
 */
function _ai_integration_eca_post_update_set_diagram_field(\DOMDocument $document, \DOMXPath $xpath, \DOMElement $extension, string $name, string $value, ?string $before = NULL): bool {
  $namespace = 'http://camunda.org/schema/1.0/bpmn';
  $field = $xpath->query(sprintf('*[local-name() = "field"][@name = "%s"]', $name), $extension)->item(0);
  if (!$field instanceof \DOMElement) {
    if ($value === '') {
      return FALSE;
    }
    $field = $document->createElementNS($namespace, 'camunda:field');
    $field->setAttribute('name', $name);
    $reference = $before === NULL
      ? NULL
      : $xpath->query(sprintf('*[local-name() = "field"][@name = "%s"]', $before), $extension)->item(0);
    $reference instanceof \DOMElement
      ? $extension->insertBefore($field, $reference)
      : $extension->appendChild($field);
  }

  $string = $xpath->query('*[local-name() = "string"]', $field)->item(0);
  if (!$string instanceof \DOMElement) {
    $string = $field->appendChild($document->createElementNS($namespace, 'camunda:string'));
  }
  if ($string->textContent === $value) {
    return FALSE;
  }
  $string->nodeValue = '';
  $string->appendChild($document->createTextNode($value));

  return TRUE;
}

/**
 * Removes the dead "token_input" configuration of Chat and Text to Image.
 *
 * The "token_input" field is inherited from the base class, but neither action
 * reads it. The Chat action resolves the tokens in its prompt and system prompt
 * against the full ECA token scope, and the Text to Image action has a "prompt"
 * field of its own. The base class still writes the field back on submit, so
 * every model that was ever saved through the form carries a useless empty
 * value. This update drops that key, for every action that has one.
 *
 * @return string
 *   A summary of what has been removed.
 */
function ai_integration_eca_post_update_remove_chat_token_input(): string {
  $configFactory = \Drupal::configFactory();
  $removed = [];

  foreach ($configFactory->listAll('eca.eca.') as $name) {
    $config = $configFactory->getEditable($name);
    $actions = $config->get('actions') ?? [];
    $rewritten = [];

    foreach ($actions as $id => $action) {
      $plugin = $action['plugin'] ?? '';
      if ($plugin !== 'ai_integration_eca_execute_chat' && $plugin !== 'ai_integration_eca_execute_text_to_image') {
        continue;
      }
      $configuration = $action['configuration'] ?? [];
      if (!is_array($configuration) || !array_key_exists('token_input', $configuration)) {
        continue;
      }
      unset($configuration['token_input']);
      $actions[$id]['configuration'] = $configuration;
      $rewritten[$id] = $configuration;
      $removed[] = $name . ' / ' . $id;
    }

    if ($rewritten === []) {
      continue;
    }
    $config->set('actions', $actions);
    _ai_integration_eca_post_update_migrate_diagram($configFactory, $config, $rewritten, '_ai_integration_eca_post_update_rewrite_diagram_remove_token_input');
    $config->save();
  }

  if ($removed === []) {
    return 'No Chat or Text to Image action carried a token input.';
  }

  return sprintf(
    'Removed the token input of %d AI action(s): %s.',
    count($removed),
    implode(', ', $removed)
  );
}

/**
 * Removes the token_input field of a BPMN diagram.
 *
 * @param string $xml
 *   The raw diagram.
 * @param array $rewritten
 *   The rewritten action configuration, keyed by action ID.
 *
 * @return string
 *   The rewritten diagram, or the original one when it is not a BPMN diagram,
 *   could not be parsed, or held nothing to change.
 */
function _ai_integration_eca_post_update_rewrite_diagram_remove_token_input(string $xml, array $rewritten): string {
  if (!str_contains($xml, 'camunda:field')) {
    return $xml;
  }

  $document = new \DOMDocument();
  $previous = libxml_use_internal_errors(TRUE);
  $loaded = $document->loadXML($xml);
  libxml_clear_errors();
  libxml_use_internal_errors($previous);
  if (!$loaded) {
    return $xml;
  }

  $xpath = new \DOMXPath($document);
  $changed = FALSE;
  // Every BPMN element that has extension elements. The element ID is the ID of
  // the action in the configuration entity.
  foreach ($xpath->query('//*[local-name() = "extensionElements"]/..') as $element) {
    if (!$element instanceof \DOMElement || !isset($rewritten[$element->getAttribute('id')])) {
      continue;
    }
    // The ID alone does not prove which plugin the element stands for.
    $properties = $xpath->query('.//*[local-name() = "property" and @name = "pluginid"]', $element);
    $property = $properties->item(0);
    if (!$property instanceof \DOMElement) {
      continue;
    }
    $plugin = $property->getAttribute('value');
    if ($plugin !== 'ai_integration_eca_execute_chat' && $plugin !== 'ai_integration_eca_execute_text_to_image') {
      continue;
    }
    $extensions = $xpath->query('*[local-name() = "extensionElements"]', $element);
    $extension = $extensions->item(0);
    if (!$extension instanceof \DOMElement) {
      continue;
    }

    $changed = _ai_integration_eca_post_update_remove_diagram_field($document, $xpath, $extension, 'token_input') || $changed;
  }

  return $changed ? $document->saveXML() : $xml;
}

/**
 * Removes a single camunda field from the diagram when it is present.
 *
 * @param \DOMDocument $document
 *   The diagram.
 * @param \DOMXPath $xpath
 *   The XPath query object for the diagram.
 * @param \DOMElement $extension
 *   The extension elements of the BPMN element to work on.
 * @param string $name
 *   The name of the field to remove.
 *
 * @return bool
 *   TRUE when a field was removed, FALSE otherwise.
 */
function _ai_integration_eca_post_update_remove_diagram_field(\DOMDocument $document, \DOMXPath $xpath, \DOMElement $extension, string $name): bool {
  $field = $xpath->query(sprintf('*[local-name() = "field"][@name = "%s"]', $name), $extension)->item(0);
  if (!$field instanceof \DOMElement) {
    return FALSE;
  }
  $extension->removeChild($field);

  return TRUE;
}
