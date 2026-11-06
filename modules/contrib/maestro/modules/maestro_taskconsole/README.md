# Maestro Task Console

Maestro Task Console provides the task list at `/taskconsole`, workflow details,
and presentation for modal and full-page task forms. It also styles Maestro's
bundled Views-based task console and administrative task reports.

## Requirements and setup

The submodule is included in the Maestro package and supports Drupal 10 and 11.
Enable **Maestro Task Console** and its Maestro dependency through Drupal's Extend
page or Drush:

```sh
drush en maestro_taskconsole
drush cr
```

Grant the `view maestro task console` permission to the appropriate roles. Users
need an executable task assignment to see tasks in the standard console. Access
to reports and attached Views is also governed by their configured permissions.

After updating the Maestro package, run database updates and rebuild caches:

```sh
drush updb
drush cr
```

For sites using configuration synchronization, export and review any configuration
changes made by update hooks and include them in the deployment configuration.

## Using the task console

The standard console shows Task, Flow, Assigned, Stage, Actions, and Details.
Multiple assignments to the same task produce one task row. Expanding Details
shows task and process identifiers, workflow milestones, assignment records,
custom information, and any Views attached to the workflow template.

Assignment details list each assignment record and its assignment method.
Multiple records may identify the same assignee. Attached Views supply content
links, completed-task history, and other workflow-specific information.

Click Task, Flow, Assigned, or Stage to sort ascending; click again to reverse the
order. Header buttons support Enter and Space and expose the sort direction to
screen readers. Sorting is local to the current page and resets on reload.
Expanded details remain with their task when sorting.

Assigned uses the queue record's creation timestamp. Milestones use the template
and process stage configuration; unavailable labels are shown as stage numbers.
“Previous stage” describes stage position, not proof that every earlier task
succeeded.

The standard console has no pager or exposed filters. Below 44rem of container
width it presents labeled task cards. The host theme supplies page navigation.

## Views and administrative reports

The bundled `maestro_views_based_task_console` View provides configurable fields,
filters, and paging. Its summary counts tasks on the current page. Details expand
into a row spanning the table, with distinct AJAX targets for repeated results.
An empty-state message is supplied only when the View has no configured empty
content. Without JavaScript, the original Views table markup remains available.

The module also styles these bundled reports:

- **Outstanding Tasks** (`maestro_outstanding_tasks`).
- **All Active Tasks** (`maestro_all_in_production_tasks`, lean and full displays).
- **All Flows** (`maestro_all_flows`), including expandable process details.

The task reports expose Task Name before Process Name. Outstanding Tasks defaults
to Queue ID descending; users click the Queue ID column heading to reverse the
order. The heading indicates the active sort direction. All Flows provides a
Process Name contains filter and a single-select Template filter. Site builders
can customize these Views through Drupal's Views UI. Wide report tables scroll
within their containers.

The active handler field supports a short action label: enable **Show as an HTML
link** and set **Text used for the link** to the desired text, such as “Open task”.
Non-interactive tasks without a destination show an em dash.

Presentation libraries are attached through module preprocessing for the bundled
Views. Cloned Views are not opted in automatically. Custom integrations should
attach the appropriate libraries and supply their component classes.

## Task form integration

The presentation wrapper recognizes `MaestroInteractiveFormBase` forms and
node/Webform forms that Maestro has associated through `maestro.queue_id`. It runs
after form alters and preserves input parents, handler-defined fields, and action
callbacks. Standalone external handlers remain external links.

The wrapper supplies task context, workflow milestones, and a return link using
the configured internal return path. Where the handler has no Cancel action, it
adds a non-submitting Cancel control. User dismissal warns after edits made in
the rendered form.

A custom form can opt out of this presentation:

```php
$form['#maestro_taskconsole_disable'] = TRUE;
```

Handlers remain responsible for validation, task status transitions, custom submit
and AJAX callbacks, and redirects. Maestro's custom-information, task-details,
execution-link, link-label, URL, and status-bar hooks remain extension points.

## Theme customization

The components inherit the active theme's fonts. Shared colour, focus, and radius
defaults are defined on `:root` in Maestro's `css/maestro-tokens.css`. The
`maestro/maestro-tokens` library loads this stylesheet as a dependency of the
presentation libraries; no CSS `@import` is needed. Customize the public CSS
properties in your theme's stylesheet:

```css
body {
  --maestro-accent: #006b66;
  --maestro-focus: #006b66;
  --maestro-selected: #e8f5f2;
  --maestro-radius: 8px;
}
```

Available properties include `--maestro-surface`, `--maestro-background`,
`--maestro-selected`, `--maestro-border`, `--maestro-text`, `--maestro-muted`,
`--maestro-accent`, `--maestro-accent-hover`, `--maestro-on-accent`,
`--maestro-focus`, `--maestro-radius`, `--maestro-font-size`, and
`--maestro-progress`. The optional `--maestro-font-size` override keeps its
component-specific fallback (14px or 15px); spacing and column widths also remain
local to their components.

The shared defaults file is named **`maestro-tokens.css`** (plural). It lives in
the **main Maestro module**, not in this Task Console submodule. In a typical
Composer installation its path is:

```text
web/modules/contrib/maestro/css/maestro-tokens.css
```

This location is intentional: the task consoles, Views reports, action buttons,
and Trace screen share these colours and other defaults. Actions and Trace can
be used without enabling the Task Console submodule, so the shared stylesheet
belongs to their common Maestro dependency.

The file declares defaults such as `--maestro-surface: #fff` on `:root`.
Component styles consume them with expressions such as
`background: var(--maestro-surface)`. Drupal loads the token library automatically
through each component's library dependencies; you do not need to attach the file
manually or change Views configuration.

For site-specific customization, override the properties in your own theme as
shown above rather than editing `maestro-tokens.css` in the contributed module.
This preserves your customization when Maestro is updated. Properties you do not
override continue to use the shared defaults. Rebuild Drupal caches after adding
or changing theme library definitions.

Set shared properties on `body` or an ancestor shared by the console and Drupal's
dialog element. Properties set only on the console will not affect a modal
mounted elsewhere in the page. Theme-specific control and dialog rules may need
scoped overrides.

The submodule provides these Twig templates:

- `maestro-taskconsole-execution.html.twig`: the form and its contextual layout.
- `maestro-taskconsole-milestones.html.twig`: workflow stages and current position.
- `taskconsole-views.html.twig`: an attached View's expandable section.

Use normal Drupal theme template overrides and rebuild caches after changes.
Preserve component classes and accessibility attributes in overridden templates.
The standard console table uses Drupal's table render element.

The `taskconsole_status_bar` theme hook is retained for compatibility. The console
renders milestones with `maestro_taskconsole_milestones`; overrides of the older
status-bar template do not apply to that component.

### Stylesheet responsibilities

Shared defaults live in the parent Maestro module's `css/maestro-tokens.css`.
The following stylesheets live in the Task Console submodule:

- `taskconsole.css`: standard console, milestones, and task execution layout.
- `task-details.css`: shared detail panels, chevrons, and nested tables.
- `sortable-table.css`: shared sort links, direction indicators, active-column styling, and keyboard focus across themes.
- `view-filters.css`: exposed-filter controls.
- `views-taskconsole.css`: Views console and All Flows table presentation.
- `outstanding-tasks.css`: administrative task report layout and scroll controls.

Render arrays and preprocessing add `maestro-detail-panel`,
`maestro-detail-table`, `maestro-report-table`, and `maestro-filters` at runtime.
Report cell rules target direct children so they do not affect nested detail
tables. Standard console and Trace columns use named classes rather than column
positions. The console toggles `maestro-ajax-row--expanded`, allowing CSS to choose
a table row or block layout according to the available width.

Task Console actions and administrative operations share the `maestro_actions`
theme hook and `maestro/maestro-actions` library. Secondary actions remain visible
without JavaScript. The enhanced disclosure supports keyboard navigation, Escape,
and dismissal on outside click or focus.

The main presentation library is
`maestro_taskconsole/maestro_taskconsole_css`; Views presentation uses
`maestro_taskconsole/views_taskconsole` and
`maestro_taskconsole/outstanding_tasks`. Exposed forms attach
`maestro_taskconsole/view_filters`. Use Drupal's library extension/override
mechanisms when needed. No frontend build step is required.

## Development and testing

Run the kernel suite from a Drupal project root with development dependencies and
SQLite enabled. Adjust paths if the project uses a different document root:

```sh
SIMPLETEST_DB=sqlite://localhost/:memory: vendor/bin/phpunit \
  -c "$PWD/web/core/phpunit.xml.dist" \
  "$PWD/web/modules/contrib/maestro/modules/maestro_taskconsole/tests/src/Kernel/TaskConsoleTest.php"
```

The suite covers assignment isolation, detail access, hooks, escaping, milestones,
form structure, reports, and Views integration. It uses isolated test data.

For presentation changes, also check the site's supported themes and custom
handlers in a browser: empty and populated consoles, AJAX details, exposed filters,
keyboard interaction, narrow layouts, and modal/full-page forms. Verify validation,
Cancel, and redirects without unintentionally advancing production workflows.

### Shared filter and assignment markup

`maestro-exposed-form.html.twig` provides a shared grid for the four report filter
forms. Labels, inputs, and help text occupy separate rows; Apply shares the input
row. On narrow screens, controls stack. Scoped spacing resets prevent theme form
margins from shifting the controls.

The main module's `maestro-assignment-panel.html.twig` renders Assignment details
with the same summary and body classes as the other detail panels. Workflow
History shows task names in the Task ID context, keeping disclosure headings
consistent even when a process has several active tasks.

The Views-based task console, Outstanding Tasks, All Active Tasks, and Workflow
History use Views AJAX for sorting, filtering, and paging. Only the report refreshes;
Maestro reattaches its detail toggles and table controls to the new results.
