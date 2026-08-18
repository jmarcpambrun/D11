# Testing

The module ships automated tests under `tests/src/`. They are all in the `ai_ckeditor` test group.

## Test suites

- **Unit** (`tests/src/Unit/`): fast, isolated tests. Covers the library/asset definitions and the no-selected-text message.
- **Kernel** (`tests/src/Kernel/`): tests with a booted kernel and a database. Covers module install, dialog access checks, entity context metadata, and the `PreGenerateResponseEvent` wiring.
- **Functional** (`tests/src/Functional/`): full-site browser tests without JavaScript. Covers the AI request controller.
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
