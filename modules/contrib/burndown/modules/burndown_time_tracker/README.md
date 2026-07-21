# Burndown Time Tracker

Burndown Time Tracker adds time-reporting tools to the Burndown task system. It is intended for teams that want to track work log entries in hours, report on them in a dedicated view, and edit entries in place.

## Features

- Adds a dedicated Hours report at `/burndown/hours`.
- Shows total time worked for the selected filters.
- Filters the report by project, sprint, user, and date range.
- Displays the task, time spent, comment, and edit action for each work log entry.
- Opens time-entry edits in a modal dialog.
- Restricts time entry editing to the entry owner or users with the `administer burndown` permission.
- Enforces hours-only tracking for work log entries.
- Warns when the report includes legacy entries stored in days or weeks.

## Installation

Enable the `Burndown Time Tracker` module as you would any Drupal module. It depends on the main `Burndown` module and Views.

## Requirements

- Drupal 9, 10, or 11
- Burndown
- Views

## Usage

Once enabled, open the Hours report from the Burndown menu to review time logged against tasks. Use the filters above the report to narrow results, and use the edit action to update an existing work log entry.
