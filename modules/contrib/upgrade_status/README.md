# Upgrade Status

Review Drupal major upgrade readiness of the environment
and components of the site.

The module provides the following key features:

- Checks if you are using a version of Drupal that supports an upgrade.
- Checks if your system meets the next major version's system requirements.
- Integrates with the Update Status core module to list the available updates
 of your contributed projects. Projects can be compatible with multiple major
 Drupal versions, so most projects can be updated on your existing site
 before doing the core major update.
- Runs PHPStan and a set of other checks to find the compatibility issues with
 the next Drupal major version that remain: deprecated PHP APIs, hooks,
 templates, libraries, routes, services, plugin definitions, configuration,
 JavaScript and recipes, and the requirements in info and composer.json files.
- Analyzes modules, themes, profiles and recipes, also the ones that are not
 installed, and groups the projects by next step: remove, update, analyze,
 collaborate with maintainers, fix, update requirements, or nothing to do.
- Puts each problem in a category by what to do with it, like fix now with
 drupal-rector, fix now manually, or only needed after the next major version.
- Exports the report and the results as HTML, Markdown, CSV, Code Climate JSON
 and Checkstyle XML.
- Integrates with Drush 13 and later, and with `vendor/bin/dr` on Drupal 11.4
 and later, for command line usage and to plug into CI systems.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/upgrade_status).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/upgrade_status).

## Requirements

This module requires no modules outside of Drupal core.

## Installation

You must use Composer to install all the required third party dependencies,
for example `composer require drupal/upgrade_status` then install as you would
normally install a contributed Drupal module. For further information, see:
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

Install [Composer Deploy](https://www.drupal.org/project/composer_deploy) or
[Git Deploy](https://www.drupal.org/project/git_deploy), as appropriate to your
Drupal setup, for a more accurate split of custom and contributed projects, and
for the version information that the available updates are based on.

Test classes are only analyzed when PHPUnit is installed. To analyze test
classes too, install the development dependencies of Drupal, for example with
`composer require --dev drupal/core-dev`.

## Configuration

There are no configuration options. Go to Administration » Reports » Upgrade
status to use the module.

Recipes are found where Composer installed them, and in the `recipes`
directory inside and next to the webroot. To list recipes from other
directories, add the paths of those directories to settings.php:

```php
$settings['upgrade_status_recipe_directories'] = ['/path/to/recipes'];
```

PHPStan is run with the PHP command line binary of the same PHP version as the
site. If the binary is not found, for example with `open_basedir` or PHP-FPM,
add the path of the binary to settings.php:

```php
$settings['upgrade_status_php_binary'] = '/usr/bin/php8.3';
```

Some deprecations are not reported with older versions of Upgrade Status,
PHPStan, phpstan-drupal and phpstan-deprecation-rules. The report page and the
commands show a warning when newer versions are available in the same major
version. Releases of Upgrade Status come from the Update Status module. The
releases of the PHPStan packages are fetched from Packagist at the same time:
on cron, at the interval of the Update Status module, and when available
updates are checked manually. To turn this off, add to settings.php:

```php
$settings['upgrade_status_check_tool_updates'] = FALSE;
```

Directories that Drupal's `file_scan_ignore_directories` setting names, like
`node_modules` and `bower_components`, are not analyzed.

## Command line

Two commands are available in Drush 13 and later, and with `vendor/bin/dr` on
Drupal 11.4 and later.

`upgrade-status:analyze` (alias `us-a`) analyzes projects and prints the
results:

```
drush upgrade-status:analyze my_module my_theme
drush upgrade-status:analyze --all --ignore-contrib --format=codeclimate
```

| Option | Effect |
|---|---|
| `--all` | Analyze all projects. Without it, name the projects. |
| `--skip-existing` | Use the stored results of a project, if there are any. |
| `--ignore-uninstalled` | Leave out projects that are not installed. |
| `--ignore-contrib` | Leave out contributed projects. |
| `--ignore-custom` | Leave out custom projects. |
| `--ignore-list=a,b` | Leave out the named projects. |
| `--phpstan-memory-limit=2G` | The memory limit of PHPStan. The default is 1500M. |
| `--format=plain` | `plain` for Markdown, which is the default, `html`, `csv` with one row for each message, `checkstyle` or `codeclimate`. |

The exit code is 3 when a project has messages for the next major Drupal
version or the analysis of a project failed, and 0 otherwise. Messages that are
only for a later major version give 0. So you can fail a CI job with the
command. In Drush, the older name `upgrade_status:analyze` still works, and is
deprecated.

`upgrade-status:report` (alias `us-r`) prints the report of the whole site:
the environment checks, and the projects grouped by next step.

```
drush upgrade-status:report
drush upgrade-status:report --groups=environment,update --format=html
```

| Option | Effect |
|---|---|
| `--groups=a,b` | Show only these sections: `environment`, `remove`, `update`, `scan`, `collaborate`, `fix-with-rector`, `fix-manually`, `update-requirements`, `compatible`. |
| `--ignore-uninstalled`, `--ignore-contrib`, `--ignore-custom`, `--ignore-list=a,b` | Leave out projects, like for the analyze command. |
| `--format=markdown` | `markdown`, which is the default, `html`, or `csv` for the projects only, with one row for each project. |

## Categories of problems

Each problem is put in a category by what to do with it. The project pages, the
exports and the commands use the same names. The names have the next major
version of the site. The examples here are for a Drupal 11 site.

| Category | Meaning |
|---|---|
| Fix now with rector | Removed in Drupal 12. [drupal-rector](https://www.drupal.org/project/rector) can fix it automatically. |
| Fix now manually | Removed in Drupal 12. drupal-rector has no fix for it yet. |
| Update requirements | A requirement or dependency in an info file, a composer.json file or a recipe that does not work with Drupal 12. Update these once the code is compatible. |
| Consider contrib deprecations | A deprecated API of a contributed module or theme. It is removed in a release of that module or theme, which may or may not be required for Drupal 12. |
| Check manually | A message without a Drupal version, like a parse error or code that could not be checked. It may not be about Drupal 12 at all. |
| Raise core requirement and fix | In a contributed project: removed in Drupal 12, and the fix only works from a newer Drupal core version than the oldest supported one. |
| Update core and fix | In a custom project: removed in Drupal 12, and the fix does not work on the Drupal core version of the site yet. |
| After Drupal 12 | Removed in a later major version. A fix is not required for Drupal 12. |

## Temporary files

The PHPStan configuration and caches of the analysis are kept in a directory
named `upgrade_status-` with a hash and the ID of the system user, in Drupal's
temporary directory. For each analyzed project the directory has a compressed
copy of the PHPStan result cache, about 1 MB for a large project. With that,
only the changed files and the files that depend on those are analyzed when you
analyze a project again. You can delete the directory at any time. The next
analysis of each project is slower then.

## Code that an analysis loads

An analysis runs PHPStan with phpstan-drupal in a separate process. When that
process starts, phpstan-drupal loads the `.module`, `.install`,
`.post_update.php` and some `.inc` files of all modules, themes and profiles in
the codebase. That includes the projects that are not installed, while Drupal
only loads code of installed projects. So only analyze a codebase with code
that is safe to run.

## Adding an analyzer

You can add checks to the analysis from another module. Register a service
with the `upgrade_status.analyzer` tag. The class of the service implements
`\Drupal\upgrade_status\AnalyzerInterface`:

```yaml
services:
  my_module.my_analyzer:
    class: Drupal\my_module\MyAnalyzer
    tags:
      - { name: upgrade_status.analyzer }
```

`applies()` returns whether the analyzer is used for a project, and `analyze()`
returns `DeprecationMessage` objects. A message with text like "Deprecated in
drupal:11.3.0 and is removed from drupal:12.0.0" is put in a category based on
those versions, see the categories of problems above. Analyzers run after
PHPStan, from the highest tag priority to the lowest.

## Maintainers

- Gábor Hojtsy - [Gábor Hojtsy](https://www.drupal.org/u/g%C3%A1bor-hojtsy)
