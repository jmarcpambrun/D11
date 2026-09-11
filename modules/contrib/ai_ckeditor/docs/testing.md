# Testing

The module ships automated tests under `tests/src/`. They are all in the `ai_ckeditor` test group.

## Test suites

- **Unit** (`tests/src/Unit/`): fast, isolated tests. Covers the library/asset definitions and the no-selected-text message.
- **Kernel** (`tests/src/Kernel/`): tests with a booted kernel and a database. Covers module install, dialog access checks, entity context metadata, the `PreGenerateResponseEvent` wiring, and the Context Control Center consumer type (including the AI Context 1.0.0-beta5 version gate, hiding tools with no enabled format, ignoring disabled formats, per-tool text-format label links, and descriptions that name enabled formats).
- **Functional** (`tests/src/Functional/`): full-site browser tests without JavaScript. Covers the AI request controller and the CKEditor consumer type listing, including hiding unused tools, per-tool text-format label links, enabled-format descriptions, and hiding those links without `administer filters`.
- **FunctionalJavascript** (`tests/src/FunctionalJavascript/`): browser tests that drive the CKEditor UI. Covers the admin configuration, the editor actions, and entity context. These require a running WebDriver (Chromedriver or Selenium).

## Running the tests

Run them with PHPUnit from the Drupal project root, using core's PHPUnit configuration.

Whole group:

```bash
vendor/bin/phpunit -c web/core --group ai_ckeditor
```

A single suite:

```bash
vendor/bin/phpunit -c web/core web/modules/contrib/ai_ckeditor/tests/src/Kernel
```

A single test:

```bash
vendor/bin/phpunit -c web/core web/modules/contrib/ai_ckeditor/tests/src/Unit/AiCKEditorAssetsTest.php
```

### With DDEV

If you develop with DDEV, run the same commands inside the web container:

```bash
ddev exec vendor/bin/phpunit -c web/core --group ai_ckeditor
```

### FunctionalJavascript prerequisites

The FunctionalJavascript tests need a WebDriver endpoint and the `MINK_DRIVER_ARGS_WEBDRIVER` and `SIMPLETEST_BASE_URL` environment variables set, the same as any Drupal JavaScript test. Set `SIMPLETEST_DB` so the test runner can reach the database. Without a WebDriver these tests are skipped.

## Continuous integration

The `.gitlab-ci.yml` in this repo includes the drupal.org `gitlab_templates` pipeline, which runs the full test and static analysis suite on every push (PHPUnit, PHPCS, PHPStan, ESLint, Stylelint, Cspell, Composer lint, and Nightwatch), alongside the `pages` job that builds this documentation. To run only the docs and skip the checks, set the `SKIP_*` variables described in the drupal.org CI documentation.

The `eslint` job is required (`allow_failure: false`). A JavaScript regression fails the pipeline.

## ESLint (local)

CI and a typical Drupal tree both resolve core from three levels above this module (`web/modules/contrib/ai_ckeditor` or `web/modules/custom/ai_ckeditor`). Do not use `git rev-parse --show-toplevel` to find core: this project is often its own git root.

From the module directory:

```bash
CORE="$(cd ../../../core && pwd)"

"$CORE/node_modules/.bin/eslint" \
  --no-error-on-unmatched-pattern \
  --ignore-pattern="*.es6.js" \
  --resolve-plugins-relative-to="$CORE" \
  --ext=.js,.yml \
  .
```

Exit 0 with no error output means it passed. Documentation-comment warnings are allowed.

The project `.eslintrc.json` extends core's `.eslintrc.passing.json` and ignores build-time imports (`ckeditor5/*`, `@ckeditor/*`, `webpack`, `terser-webpack-plugin`). PHP/JSON payload keys stay snake_case; local JavaScript identifiers are camelCase.

Stylelint uses core's config. From the same directory:

```bash
"$CORE/node_modules/.bin/stylelint" \
  --ignore-path ./.stylelintignore \
  --config "$CORE/.stylelintrc.json" \
  ./**/*.css
```

## Note on dependencies

Some `drupal/ai` 1.5 releases still ship a nested `ai_ckeditor` module. The PHPUnit bootstrap (`drupal_phpunit_find_extension_directories()` in `core/tests/bootstrap.php`) registers the last `ai_ckeditor.info.yml` it scans for the `Drupal\ai_ckeditor` namespace, so in filesystem-order-dependent cases that copy is autoloaded instead of this project's `AiRequest` (no `validateEntityContext()`). The PHPUnit job deletes `web/modules/contrib/ai/modules/ai_ckeditor` before tests run. If you test locally against one of those releases, remove that nested module first or the kernel entity-context tests will fail.
