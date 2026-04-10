# Install hardening

## Remove unnecessary files
Unnecessary files may be removed in order to reduce the amount of information
available to attackers regarding the version of TFA deployed.

The following paths are safe to remove:
<!-- cSpell:disable -->
```
docs/**
tests/**
.gitignore
.gitlab-ci.yml
.travis.yml
composer.json
mkdocs.yml
phpstan*
README.md
```
<!-- cSpell:enable -->


Using the
[Drupal Vendor Hardening Composer Plugin](https://www.drupal.org/docs/develop/using-composer/using-drupals-vendor-hardening-composer-pluging)
the following config in your root composer.json will remove the listed
directories during install.

```json
"extra": {
  "drupal-core-vendor-hardening": {
  "drupal/tfa": ["docs", "tests"]
  }
}
```
Note: The Drupal Vendor Hardening plugin only removes directories, it will not
remove individual files.
