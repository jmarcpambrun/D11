Burndown is a Drupal native, agile project management tool. It provides support for both kanban and sprint-based projects and several forms of task-size estimation. It is designed for flexibility and extensibility, but it has sensible defaults in place: just turn it on, navigate to the Burndown dashboard from your menu, and start adding projects!

## Core Features

* Drag and drop interface.
* Kanban and Sprint-base projects.
* Estimate based on T-shirt, geometric, or dot sizing.
* Customizable board columns per project.
* Backlog and completed boards per project.
* Projects and Tasks support fieldable entity bundles, so you can easily extend them.
* Relationships between Tasks (blocked by, child of, etc).
* Task work log and commenting.
* Attach links and images to tasks.
* "Cloud" project sidebar block, to easily find projects.
* Simple sidebar navigation block to navigate within a project.
* Email notifications on changes (optionally, you may want to use [SMTP](https://drupal.org/project/smtp) if the volume of emails will be large).
* Robust permission options with per Project granularity.
* Large library of Drush commands.

## Submodule: Time Tracker

The optional submodule allows timer tracking and reporting at the Task level.
* Easy start and stop task timer.
* Report showing hours summary for the current user.
* Time managers can see hours logged for any user.
* Time managers can manage running timers.


## Requirements

Burndown is designed to leverage the new built-in functionality of modern Drupal. It has no additional external dependencies.

## Installation Steps

* composer require drupal/burndown
* Enable the Burndown module
* Depending on your theme, you may want to add the "Projects" and "Burndown Sidebar" blocks to a sidebar region. These are added by default in the Bartik theme. They are both optional, but make navigation a bit easier.
* Go to the Burndown dashboard (in your top menu) and start adding projects and tasks.

## Documentation
[Extensive documentation](https://www.drupal.org/docs/contributed-modules/burndown) on Drupal.org.


## MAINTAINERS

Sponsored and maintained by [Lichtman Consulting](http://lichtman.ca). We are looking for additional sponsors to add new functionality to this module.
