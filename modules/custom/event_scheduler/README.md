Event Scheduler
===============

INTRODUCTION
------------
This module allows specific events to be delayed until the end of page
execution, or even until a scheduled time in the future.

THIS CODE DOES NOTHING ON ITS OWN.

REQUIREMENTS
------------
This module has no special requirements.

INSTALLATION
------------

 * Install as you would normally install a contributed Drupal module. Visit
   https://www.drupal.org/node/1897420 for further information.

CONFIGURATION
-------------
No configuration is required.

ABOUT THIS MODULE
-----------------
The following is a description of what this module does, why, and how.

The Problem with Events
-----------------------
System events are executed at the time they are issued. This means that
non-critical actions might be executed during the page build and therefore
delay it.

**For example**, (using unreal times just for illustration), the action of a
form might be to save an entity but also send a dozen emails to interested
users.

Let's say it takes 0.05 seconds to save the entity, and it takes 0.10 seconds
to send a single email. The site should be building the next page after 0.05
seconds, but it has to send 10 emails, so it adds a whole second to the time.

But the event is working exactly the same as an old Drupal hook, it has to be
acted on right at that moment.

Delayed events
--------------
How about if you could delay all that non-critical processing until after the
main page processing is complete?

**_You can._**

All you have to do is queue non-critical events until the Symfony Kernel sends
its Terminate event (meaning the page has been sent), then execute all those
queued events.

In this example it means the page is delivered after 0.05 seconds, while the
time-consuming emails are sent afterwards.

This module provides that choice when dispatching events, and another (even
better) one:

* **Delayed events**: Execute after the page has been sent to the user, which
means it will not add time to the user experience of the page.

* **Scheduled events**: Allow an event to be held until some time in the future
when it is launched and can be executed.

The module also permits scheduled events to be purged based on a variety of
criteria such as the specific entity or entity type it relates to.

Scheduled events
----------------
As mentioned these have additional data stored with them, obviously they
require a launch time. However another important value is the "tag".

Let's say that a piece of content is set up to be published at a given time
using scheduled events, and that a set of emails should sent as well.

But then the content is deleted (for some reason). If the scheduled event is
left in the DB it will eventually be launched. Now if it tries to publish the
content, it will no longer be there which might not cause an error, but
sending the emails would definitely be a mistake.

### Tagging

The tag allows you to identify scheduled events related to the same or similar
items so that they can be deleted (the main use, modifying scheduled events is
not particularly workable).

For content entity-related events (node, comment, user, term and so on) it is
assumed the tag will be `entity_type:id`, and there is default code to remove
scheduled events tagged this way if a content entity is deleted.

For other scheduled events you can use whatever scheme you wish.

Creating delayed and scheduled events
-------------------------------------
This can only be done at the code level, and requires you (the developer) to
create new events and use one or other of the interfaces to specify what sort
of event it is.

Plus you must dispatch these events using the new event dispatcher.

Delayed events only require the `EventDelayInterface`, and an event name. It's
recommended you have a `const NAME = "my_module.my_event` as the name.

Scheduled events require the event name, a launch timestamp, and a tag, as
described above.

There is a trait you can use `EventSchedulerTrait` which provides all the
methods for setting and accessing scheduled event values, but also an
initialisation method that can make the set-up easier.

It would look something like this. The event class could be as simple as:
```php
class MyEvent extends Event implements EventSchedulerInterface {
  use EventSchedulerTrait;

  static NAME = 'my_module.my_event';
}
```
And then the calling code:
```php
$event = new MyEvent();
$event->initialise(MyEvent::NAME, $time + 3600, 'my-tag');
\Drupal::service('')->dispatch(MyEvent::NAME, $event);
```
And that's all that's needed (cron will need to be active).

If the event is already overdue it's simply added to the queue of events to be
executed at the end of the current page.


Launching scheduled events
--------------------------

Scheduled events are launched in two ways.

* **Page-execution** Every time a page is executed a check is done to see if
any scheduled events need to be launched. If they are, they added to the local
delayed event queue and deleted from the database. There is a locking system to
ensure that events aren't grabbed twice or more if multiple pages are being
requested simultaneously.

* **Cron** The check for scheduled events is also done in `hook_cron()` where
they are added to a cron queue and a queue worker performs the actual
launching of the scheduled events.

The reason for having both methods is that the first allows events to be
launched close to their scheduled time - but that doesn't work so well on a
site that's not very busy. Using cron means that events will always get
executed eventually even if no one is using the site.

### Forcing queue processing

There is also an admin form that forces the scheduled events held in the DB to
be checked, and any that need to be launched will be. The same effect could be
achieved by manually running cron but you might only want events to be checked
and nothing else.

### When scheduled events happen

As in all situations like this, there is no way to guarantee that an event will
be executed at an exact time. The best you can say is that execution will
happen within a given period after the launch time. What that period is depends
on factors like time between cron.

### Locking and clean up

Although scheduled events processed through cron and the queue should get
deleted, something might happen that prevents it from happening. So you end up
with old scheduled events in the DB that have been launched but not removed.
They are locked so they won't be launched again.

The cron process checks for them and deletes any that have been processed but
not removed from the DB, if they are older than 24 hours.

There is a further check, although it is extremely unlikely to happen, for
unlocked scheduled events that are older than a week. They are also deleted.

Dispatcher and events
---------------------
A new dispatcher service has been created to handle the new events. This
behaves normally with ordinary events but has different behaviour for events
that have additional interfaces:

* **EventDelayInterface** adding this interface to a custom Event class means
that it will be delayed until the end of the page processing before being
executed. You don't have to do anything else.

* **EventScheduleInterface** adding this interface to a custom Event class
forces it to be included in the scheduled events table. There is additional
data to be added to the event object (when created), such as when it should be
launched (you can use `EventSchedulerTrait` to assist).

There is one trick here. If the service is called for a scheduled event, but
the launch time for that event has already passed, it is added to the Delayed
queue instead, and executed at the end of the current page.

This allows us to use the same code to launch scheduled events that have been
waiting in the DB.

Acknowledgements
----------------
The starting point for this code was the following article:

https://thomas.jarrand.fr/blog/events-part-3/
