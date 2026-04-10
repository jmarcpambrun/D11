# Schema Based Config Forms

This module provides a toolset for developers when working with config. An
extended and opinionated version of Core's ConfigFormBase builds configuration
forms automatically from config schema, allowing you to write less PHP in
exchange for writing little to no additional YAML.

Note that this module does not provide routing, menu links, local tasks, etc.
Those must still be configured by the developer.


## Requirements

This module has no requirements other than Drupal 10 itself.


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

1. Enable the module at Administration > Extend.
1. Optionally enable submodules to provide additional config schema types not in
Drupal Core, and/or to provide integration with certain other contrib modules.


## Troubleshooting & FAQ


### How do I use this module?

When defining a configuration from, instead of extending core's ConfigFormBase,
extend the one provided by this module. You must provide the methods
`getFormId()` and `getEditableConfigNames()`. The base class will do the rest
based on the schema of the configuraton objects specified in
`getEditableConfigNames()`. Any other method overrides are optional.

Here is a basic working example.

```
<?php

namespace Drupal\my_module\Form;

use Drupal\schema_based_config_forms\Form\ConfigFormBase;

/**
 * Settings form for my_module.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'my_module_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'my_module.settings',
    ];
  }

}

```


### How do I add/customize form element properties through YAML schema?

Some form attributes will be set automatically based on standard schema
properties. Config schema `label` properties will be used as the form element
`#title`. Elements will be set as `#required` unless their `nullable` property
is `true`.

Certain properties like descriptions are supported by non-standard config schema
properties. (Note that any property key beginning with #, as these non-standard
properties generally do, must be enclosed in single quotes `''` to escape the #
character.) Here is a list of non-standard properties that work across different
config schema types:

- `#description`: Sets `#description` (translatable).
- `#field_prefix`: Sets `#field_prefix` (translatable).
- `#field_suffix`: Sets `#field_suffix` (translatable).
- `#schema_form_label_hide`: Set to boolean true to prevent the `label` being
  used as the form element's `#title`.
- `#schema_form_hide`: Set to boolean true to prevent automatic building of the
  schema key as a form element. This can be useful if you want to provide
  key-specific custom element building in the config-specific form class, or if
  the value is computed from other factors during form submission. If the key is
  a parent (mapping or sequence), children will also be ignored.
- `#schema_form_plugin`: Sets the string plugin ID of the Schema Config Form
  Element plugin to use for a specific config key. When set, normal
  auto-detection based on the config schema type is ignored, even if the
  specified plugin cannot be found. If set on a parent element (mapping or
  sequence), the plugin specified will also be responsible for building the
  elements for children.

Properties noted as translatable will be passed through `t()` before being used
in the form element's render array.

Many specific types will support property names from the corresponding form
element class. For instance, many types using text or text-like inputs will
respect optional `#size` and/or `#pattern` schema properties when building form
elements. `text_format` supports the `#allowed_formats` property.

Certain element types may **require** a non-standard property to be set. For
example, the entity reference schema types provided by the corresponding
submodule require that the `target_type` property be set to an entity type ID,
because the entity_autocomplete form element requires that property.


### Can I customize the form element types of parent form elements?

Yes. There are several ways to do this:

1. Override the `getContainerTypeForDepth()` method. This is the easier and
  succinct way to change the generalized, one-size-fits-all structure of the
  form from its default.
1. Provide a `#type` property key. (Note the # making this a distinct,
  non-standard property targeting the form element as opposed to the standard
  config schema property `type`.) This is the easiest way to customize the form
  element type of a single parent key.
1. Alter the form after it is built. This can be done by providing your own
  `buildForm()` method on your config form class that calls
  `parent::buildForm()`, then alters the form array returned from the parent
  method before returning it. Form alter hooks would work as well.

Here is an example of the generalized method override approach. This example
makes the top level container for each overall config object into a set of
vertical tabs, followed by details at the next level down, followed by
fieldsets. (Depth 0 is set to NULL, meaning if there are multiple configuration
objects, there will not be an HTML element containing them.)

```
protected function getContainerTypeForDepth(int $depth, ?string $key = NULL): ?string {
  return match($depth) {
    0 => NULL,
    1 => 'vertical_tabs',
    2, 3 => 'details',
    default => 'fieldset',
  };
}
```

Note that this particular layout assumes that the first level of keys on the
config object will all be parents (mappings or sequences), since only those
types of keys would use a wrapping element type that vertical tabs expects from
its children.


### A config schema type isn't supported, or I need custom PHP. What do I do?

You have three options:

1. If an entire config schema type is not supported, you could create a plugin
  that builds form elements for all config keys of that schema type. See
  existing plugins in this module under `src/Plugin/SchemaConfigFormElement` for
  examples. You can read
  [documentation for Drupal's Plugin API](https://www.drupal.org/docs/drupal-apis/plugin-api)
  on Drupal.org. If you are addressing a schema type from Core or another
  contrib, please also consider contributing the plugin back to this module!
1. If you need to customize the form element for a specific key, override the
  `::buildForm()` method on your config form and add code to alter the form
  element in between calling the parent `buildForm()` method and returning the
  form array. If you are replacing the form element entirely, consider adding
  `#schema_form_hide` on the YML element so that the base class will not spend
  time building the form element you erase.
1. Override the buildFormElement() method. Based on $key and/or field schema
  data, conditionally perform and return completely custom element building
  logic. Remember to call the parent method for standard logic if your
  condition(s) are not met.


### Can I use this module with my contrib?

Schema Based Config Forms could be used by any module, including contrib.

That said, Schema Based Config Forms is intended primarily for use in site- or
distribution-specific modules. Any module using schema_based_config_form should
declare a dependency on it, as well as any submodule used - seeing as your
configuration form(s) will not work without them. In the context of a contrib,
consider carefully whether it's worth adding dependencies.


## Maintainers

- Ben Voynick - [bvoynick](https://www.drupal.org/u/bvoynick)
