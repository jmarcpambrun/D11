Webform Entity Builder
======================
This module provides support for webform -> entity construction. That is to
say, a webform can collect data from the user, and this data can automatically
be used to build an entity.

Although adding the handler to a Webform is done in the UI, this module does
nothing on its own and requires code support in the form of custom plugins to
work. So it's only really of use to a developer.

There are plans to create a module that allows the process to be set up in the
UI itself.

INTRODUCTION
------------
In many situations letting a user loose on the, generally unfriendly, Drupal
entity creation forms is not the best idea. It's true that we can now create
custom forms and miss out fields we don't want the user to see, but making
sure they see the right form in the right way is a work in itself.

Webforms is intended to create user-friendly forms, can easily handle
multi-page forms and provides a better user experience. It's also easier to
build and modify in the user-interface.

But you can't create an entity from a webform ... until now.

This module provides the framework creating for letting the user fill in a
webform and giving the data to code that can create the entity. Much of it
is duplicated so only a small amount of support code needs to be written
usually.

It also permits a webform (probably the same one but could be different) to
be used to edit an entity.

REQUIREMENTS
------------
 * There are no special requirements but...

This module uses events and requires the `event_scheduler` module, it also
provides some helper methods, especially for files, and so needs the
`universal_file_utils` too.

INSTALLATION
------------
 * Install as you would normally install a contributed Drupal module.
   See: https://www.drupal.org/docs/8/extending-drupal-8/installing-drupal-8-modules
   for further information.

The required additional modules should be installed automatically.

CONFIGURATION
-------------

No configuration is needed.

HOW TO USE
----------

### Webform and Handler

We provide a webform handler that must be attached to the webform under the
email/handlers configuration section.

The webform must also have an element called `_build_entity` which contains the
entity and bundle IDs, e.g. `node:article`. If the entity type has no bundles
you can just omit the bundle part, e.g. `user`.

It's recommended you use a "value" element rather than a "hidden" element for
the `_build_entity` field because a hidden element could be hacked in the
browser.

_**NOTE**: Previously you would use `entity_type` to specify the type of entity is
**deprecated**, and you will receive a warning from Webforms if you use it._

If you also want to use the form (or a different one) to edit an existing
entity you also need to create another value element this time called
`_entity_id`. This should default to zero (for entity creation) and should be
set to the ID of the entity when you want to edit.

The webform should be prepopulated with the entity's field values.

#### What happens next

When the form has been completed, the data entered is collected and launched
using a delayed event (courtesy of the `event_scheduler` module).

The delayed event means that the work on constructing the entity from the
webform data takes place after the current page has been sent to the user. It
provides better UX with less delay.

### EntityBuilder

When the event activates, it triggers the framework to find a plugin that
supports the creation of the required entity (the EntityBuilder).

Most of the entity building process is the same so standard code is run, the
key is in creating the array of data used in the create entity call. Putting
this array together is the main task of the custom EntityBuilder plugin.

Once the entity has been successfully built, the webform submission entry is
deleted. (It is not deleted if the entity build fails.)

