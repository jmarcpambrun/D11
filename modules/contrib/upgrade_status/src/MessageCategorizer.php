<?php

namespace Drupal\upgrade_status;

use Drupal\Core\Extension\Extension;

/**
 * Rewords messages and puts them in categories.
 *
 * The category says when a deprecation should be fixed, based on its Drupal
 * version and the project type, or that drupal-rector can fix it.
 */
final class MessageCategorizer {

  /**
   * Messages about the requirements and dependencies of a project.
   *
   * These are patterns for messages of the analyzers of this module about
   * info files, composer.json files and recipes.
   */
  protected const REQUIREMENT_MESSAGES = [
    '/^Add core_version_requirement /',
    '/^Value of core_version_requirement/',
    '/^The drupal\/core requirement /',
    '/^The PHP requirement /',
    '/^The \S+ requirement \(.*\) is not compatible with Symfony /',
    '/^This extension depends on /',
    '/^The base theme is /',
    '/^The \S+ package is required, and it provides /',
    '/^The recipe installs /',
    '/^The recipe imports configuration from /',
    '/^The configuration depends on /',
  ];

  /**
   * Messages of deprecations with a fix that works on older Drupal versions.
   *
   * These are patterns for messages of the analyzers of this module, with the
   * first Drupal version that the fix works on. The fix does not need the
   * Drupal version of the deprecation, so the category of the message comes
   * from the version of the fix. A contributed project can make the fix and
   * keep its support for the older versions that have it.
   */
  protected const FIXES_FOR_OLDER_VERSIONS = [
    // Older versions pass named options to the constraint as an array of
    // options, which sets the same properties.
    '/^The options of the \w+ constraint in this config schema are not a named array\./' => '9.5',
    // Views handlers always had the access() method.
    "/^The 'access callback' key is used in a Views handler definition\./" => '8.0',
    // Layouts could have a label since the layout API was added.
    '/^The \S+ layout has no label\./' => '8.3',
    // The fix is to add the configuration to the recipe itself, which works
    // since recipes were added.
    '/^The \S+ core recipe is applied\./' => '10.3',
  ];

  /**
   * The first Drupal version that the fix of a message works on, by pattern.
   *
   * @var array<string, string>
   */
  protected array $fixVersions = self::FIXES_FOR_OLDER_VERSIONS;

  /**
   * Annotate and categorize the error message.
   *
   * @param string $error
   *   Error message as identified by phpstan.
   * @param \Drupal\Core\Extension\Extension $extension
   *   Extension where the error was found.
   *
   * @return array
   *   Two item array. The reformatted error and the category.
   */
  public function categorize(string $error, Extension $extension): array {
    // Make the error more readable in case it has the deprecation text.
    $error = preg_replace('!\s+!', ' ', trim($error));
    $error = preg_replace('!:\s+(in|as of)!', '. Deprecated \1', $error);
    // PHPStan's message for a deprecated constant is only its @deprecated text.
    $error = preg_replace('!^(in|as of) ([Dd]rupal)!', 'Deprecated \1 \2', $error);
    $error = preg_replace('!(u|U)se \\\\Drupal!', '\1se Drupal', $error);

    // Set a default category for the messages we can't categorize.
    $category = 'uncategorized';
    $contrib = ProjectCollector::isContributed($extension);
    $next_major = ProjectCollector::getDrupalCoreMajorVersion() + 1;
    $removed_after_next_major = FALSE;

    // Drupal deprecations. The message of a @deprecated tag says "Deprecated
    // in drupal:X", and core's runtime messages say "is deprecated in
    // drupal:X". The removal version is the next Drupal version after it,
    // whatever the wording, like "is removed from", "will be removed in" or
    // "an error will be thrown from".
    if (preg_match('!deprecated (?:in|as of) drupal[ :](\d+\.\d+)(.*)$!i', $error, $found)) {
      $category = $this->getVersionCategory($found[1], $contrib);
      // A fix that also works on older Drupal versions is needed as soon as
      // the project supports no version before the first one with the fix.
      if ($category === 'later' && ($fix_version = $this->getFixVersion($error)) !== NULL) {
        $category = $this->getVersionCategory($fix_version, $contrib);
      }
      $removed_after_next_major = preg_match('!drupal[ :](\d+)\.!i', $found[2], $removed) && $removed[1] > $next_major;
    }
    // Deprecations of other projects, like "Deprecated in search_api:8.x-1.31
    // and is removed from search_api:2.0.0", are removed in a release of that
    // project. That release may or may not be the one needed for the next
    // major version of Drupal, so these have a category of their own.
    elseif (preg_match('!Deprecated in [a-z][a-z0-9_]*:!', $error)) {
      $category = 'contrib';
    }
    // Symfony removes deprecations in its next major version.
    elseif (preg_match('!since symfony(?:/[a-z0-9-]+)? (\d+)\.\d+!i', $error, $found)) {
      $category = $this->getDependencyCategory((int) $found[1] + 1, ProjectCollector::SYMFONY_MAJOR_VERSIONS[$next_major] ?? NULL, $contrib);
    }
    // Twig removes deprecations in its next major version.
    elseif (preg_match('!since twig/twig (\d+)\.\d+!i', $error, $found)) {
      $category = $this->getDependencyCategory((int) $found[1] + 1, ProjectCollector::TWIG_MAJOR_VERSIONS[$next_major] ?? NULL, $contrib);
    }
    // PHP's own functions and methods. PHPStan's message has no version, but
    // PHP only removes deprecated features in a major version, so they work
    // on PHP 8, which the next major version of Drupal requires. They need to
    // be fixed before PHP 9.
    elseif ($this->isPhpDeprecation($error)) {
      // If the next major version of Drupal requires the PHP major version
      // that runs this code, the function or method is still there, so the
      // fix is not required for it. If the PHP requirement of the next major
      // version is not known yet, this is not known either.
      $required_php = ProjectCollector::MINIMUM_PHP[$next_major] ?? NULL;
      $category = $required_php !== NULL && (int) $required_php === PHP_MAJOR_VERSION ? 'ignore' : 'later';
    }

    // If the error is covered by rector, override the result.
    if ($this->isRectorCovered($error)) {
      $category = 'rector';
    }

    // A deprecation that is removed after the next major version doesn't need
    // to be fixed yet, even if rector can fix it.
    if ($removed_after_next_major) {
      $category = 'ignore';
    }

    // Ignore the broken messages for EntityStorageInterface deprecation.
    if (strpos($error, 'of interface Drupal\Core\Entity\EntityStorageInterface. Deprecated in drupal:10.1.0 and is removed from drupal:11.0.0. Use Drupal\Core\Entity\RevisionableStorageInterface') !== FALSE) {
      $category = 'ignore';
    }

    // Check for "guzzlehttp/guzzle:8.0" and ignore those errors.
    // That major is not
    // released yet, so compatibility cannot be proven.
    // Stop ignoring this error from
    // Drupal 11 as a safeguard.
    if (strpos($error, 'guzzlehttp/guzzle:8.0') !== FALSE && ProjectCollector::getDrupalCoreMajorVersion() < 11) {
      $category = 'ignore';
    }

    // Requirements and dependencies in info files, composer.json files and
    // recipes have no Drupal version in the message, and are not code to
    // check. The requirement is to be updated.
    if ($category === 'uncategorized') {
      foreach (self::REQUIREMENT_MESSAGES as $pattern) {
        if (preg_match($pattern, $error)) {
          $category = 'requirements';
          break;
        }
      }
    }

    return [$error, $category];
  }

  /**
   * Categorizes the messages of analysis results, and adds the totals.
   *
   * @param array $result
   *   The results of a project, with the messages of each file in
   *   $result['data']['files'].
   * @param \Drupal\Core\Extension\Extension $extension
   *   The project of the results.
   *
   * @return array
   *   The results. Each message has its reworded text and its category in
   *   upgrade_status_category. The totals have the number of messages in each
   *   category, the numbers of errors and warnings, and the next step of the
   *   project: fix with rector if rector covers at least one message, fix
   *   manually if there are only other messages, or nothing to do.
   */
  public function categorizeResults(array $result, Extension $extension): array {
    // Assume next step is to relax (there were no errors found).
    $result['data']['totals']['upgrade_status_next'] = ProjectCollector::NEXT_RELAX;

    foreach ($result['data']['files'] as &$errors) {
      foreach ($errors['messages'] as &$error) {

        // Overwrite message with processed text. Save category.
        [$message, $category] = $this->categorize($error['message'], $extension);
        // Messages quote the analyzed files and the output of PHPStan. Control
        // characters in them could change what a terminal shows.
        $error['message'] = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $message) ?? $message;
        $error['upgrade_status_category'] = $category;

        // If the category was 'rector' that means at least one error was
        // identified as covered by rector, so next step should be to run
        // rector on this project.
        if ($category == 'rector') {
          $result['data']['totals']['upgrade_status_next'] = ProjectCollector::NEXT_RECTOR;
        }
        // A message in the 'ignore' category is for a later major version,
        // so there is nothing to fix for the next one.
        elseif ($category == 'ignore') {
          // The next step stays.
        }
        // With only requirements to update, there are no known problems in
        // the code.
        elseif ($category == 'requirements') {
          if ($result['data']['totals']['upgrade_status_next'] == ProjectCollector::NEXT_RELAX) {
            $result['data']['totals']['upgrade_status_next'] = ProjectCollector::NEXT_REQUIREMENTS;
          }
        }
        // Any other message is to fix or to check manually, unless rector
        // has a fix for one of the messages.
        elseif ($result['data']['totals']['upgrade_status_next'] != ProjectCollector::NEXT_RECTOR) {
          $result['data']['totals']['upgrade_status_next'] = ProjectCollector::NEXT_MANUAL;
        }

        // Sum up the error based on the category it ended up in. Split the
        // categories into two high level buckets needing attention now or
        // later for compatibility with the next major version. Issues in the
        // 'ignore' category are intentionally not counted in either.
        @$result['data']['totals']['upgrade_status_category'][$category]++;
        if (in_array($category, ['safe', 'old', 'rector'])) {
          @$result['data']['totals']['upgrade_status_split']['error']++;
        }
        elseif (in_array($category, ['later', 'uncategorized', 'requirements', 'contrib'])) {
          @$result['data']['totals']['upgrade_status_split']['warning']++;
        }
      }
    }
    return $result;
  }

  /**
   * Checks whether a message is about a deprecated function or method of PHP.
   *
   * @param string $error
   *   The message.
   *
   * @return bool
   *   TRUE if the deprecated function or the class of the deprecated method is
   *   built into PHP or one of its extensions.
   */
  protected function isPhpDeprecation(string $error): bool {
    if (preg_match('!^Call to deprecated function \\\\?([\w\\\\]+)\(\)!', $error, $found)) {
      return function_exists($found[1]) && (new \ReflectionFunction($found[1]))->isInternal();
    }
    if (preg_match('!^Call to deprecated (?:static )?method \w+\(\) of class \\\\?([\w\\\\]+)[.:]!', $error, $found)) {
      // Classes of PHP are always loaded, so others are not autoloaded here.
      return class_exists($found[1], FALSE) && (new \ReflectionClass($found[1]))->isInternal();
    }
    return FALSE;
  }

  /**
   * Gets the first Drupal version that the fix of a deprecation works on.
   *
   * @param string $error
   *   The message.
   *
   * @return string|null
   *   The version, like 10.3, if the message is one of
   *   FIXES_FOR_OLDER_VERSIONS. NULL if the fix needs the version of the
   *   deprecation.
   */
  protected function getFixVersion(string $error): ?string {
    foreach ($this->fixVersions as $pattern => $version) {
      if (preg_match($pattern, $error)) {
        return $version;
      }
    }
    return NULL;
  }

  /**
   * Gets the category of a Drupal deprecation from its version.
   *
   * @param string $version
   *   The Drupal version of the deprecation, like 11.2.
   * @param bool $contrib
   *   Whether the project is a contributed project.
   *
   * @return string
   *   The category: old, safe or later.
   */
  protected function getVersionCategory(string $version, bool $contrib): string {
    // Contributed projects support older Drupal versions too. A deprecation
    // in the oldest supported minor version or before can be fixed now.
    if ($contrib) {
      return version_compare($version, ProjectCollector::getOldestSupportedMinor()) <= 0 ? 'old' : 'later';
    }
    // A custom project only needs to work on this site.
    return version_compare($version, \Drupal::VERSION) <= 0 ? 'safe' : 'later';
  }

  /**
   * Gets the category of a deprecation in a dependency of Drupal core.
   *
   * @param int $removed
   *   The major version of the dependency that removes the deprecated API.
   * @param int|null $required
   *   The major version of the dependency that the next major version of
   *   Drupal requires, or NULL if it is not known yet.
   * @param bool $contrib
   *   Whether the project is a contributed project.
   *
   * @return string
   *   The category: ignore if the next major version of Drupal doesn't need
   *   the version that removes it, otherwise safe for custom projects and
   *   later for contributed projects. Contributed projects may need to
   *   support older versions of the dependency, which may not have the
   *   replacement.
   */
  protected function getDependencyCategory(int $removed, ?int $required, bool $contrib): string {
    if ($required === NULL) {
      return 'later';
    }
    if ($removed > $required) {
      return 'ignore';
    }
    return $contrib ? 'later' : 'safe';
  }

  /**
   * Checks whether an error message is covered by rector.
   *
   * @return bool
   *   Returns bool value.
   */
  protected function isRectorCovered($string) {
    // Hardcoded lo-fi implementation for now. This should be the same as in
    // https://git.drupalcode.org/project/deprecation_status/-/blob/script/stats.php
    $rector_covered = [
      // 0.5.2
      'Call to deprecated constant REQUEST_TIME: Deprecated in drupal:8.3.0 and is removed from drupal:11.0.0. Use Drupal::time()->getRequestTime();',

      // -- not found in contrib
      'Call to deprecated function watchdog_exception(). Deprecated in drupal:10.1.0 and is removed from drupal:11.0.0. Use Use Drupal\Core\Utility\Error::logException() instead.',
      // 0.18.1.
      'Drupal\Tests\BrowserTestBase::$defaultTheme is required in drupal:9.0.0 when using an install profile that does not set a default theme. See https://www.drupal.org/node/3083055, which includes recommendations on which theme to use.',

      // 0.18.2
      'Call to deprecated function system_time_zones(). Deprecated in drupal:10.1.0 and is removed from drupal:11.0.0. This function is no longer used in Drupal core. Use Drupal\Core\Datetime\TimeZoneFormHelper::getOptionsList() or \DateTimeZone::listIdentifiers() instead.',

      // 0.18.3
      'Missing call to parent::setUp() method.',
      'Missing call to parent::tearDown() method.',

      // 0.18.4
      'Call to deprecated function module_load_include(). Deprecated in drupal:9.4.0 and is removed from drupal:11.0.0. Use Drupal::moduleHandler()->loadInclude($module, $type, $name = NULL). Note that including code from uninstalled extensions is no longer supported.',

      // 0.19.0
      'Call to deprecated function format_size(). Deprecated in drupal:10.2.0 and is removed from drupal:11.0.0. Use Drupal\Core\StringTranslation\ByteSizeMarkup::create($size, $langcode) instead.',

      // 0.20.0
      'Call to deprecated method getResource() of class Drupal\system\Plugin\ImageToolkit\GDToolkit. Deprecated in drupal:10.2.0 and is removed from drupal:11.0.0. Use Drupal\system\Plugin\ImageToolkit\GDToolkit::getImage() instead.',
      'Call to deprecated method setResource() of class Drupal\system\Plugin\ImageToolkit\GDToolkit. Deprecated in drupal:10.2.0 and is removed from drupal:11.0.0. Use Drupal\system\Plugin\ImageToolkit\GDToolkit::setImage() instead.',
      // non-drupal deprecations.
      'Fetching deprecated class constant MASTER_REQUEST of interface Symfony\Component\HttpKernel\HttpKernelInterface: since symfony/http-kernel 5.3, use MAIN_REQUEST instead. To ease the migration, this constant won\'t be removed until Symfony 7.0.',
      'Call to deprecated method getContentType() of class Symfony\Component\HttpFoundation\Request: since Symfony 6.2, use getContentTypeFormat() instead',
      'Call to deprecated method enableAnnotationMapping() of class Symfony\Component\Validator\ValidatorBuilder: since Symfony 6.4, use "enableAttributeMapping()" instead.',
      'Call to deprecated method attachPart() of class Symfony\Component\Mime\Email: since Symfony 6.2, use addPart() instead',
      'Class [redacted] implements deprecated interface Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface: since Symfony 6.2, implement ValueResolverInterface instead',

      // 0.20.2
      'Call to deprecated function _drupal_flush_css_js(). Deprecated in drupal:10.2.0 and is removed from drupal:11.0.0. Use Use Drupal\Core\Asset\AssetQueryStringInterface::reset() instead.',
      'drupal_theme_rebuild() is deprecated in drupal:10.1.0 and is removed from drupal:11.0.0. Use theme.registry service reset() method instead. See https://www.drupal.org/node/3348853',

      // 0.20.3
      'Call to deprecated function file_icon_class(). Deprecated in drupal:10.3.0 and is removed from drupal:11.0.0. Use Drupal\file\IconMimeTypes::getIconClass() instead.',
      'Call to deprecated method setMethods() of class PHPUnit\Framework\MockObject\MockBuilder: https://github.com/sebastianbergmann/phpunit/pull/3687',

      // 1.0.0
      'Call to deprecated function _content_translation_install_field_storage_definitions(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use ContentTranslationHooks::installFieldStorageDefinitions() instead.',
      'Call to deprecated function _contextual_id_to_links(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use Drupal\contextual\ContextualLinksSerializer::idToLinks() instead.',
      'Call to deprecated function _contextual_links_to_id(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use Drupal\contextual\ContextualLinksSerializer::linksToId() instead.',
      'Call to deprecated function _dblog_get_message_types(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal::service(\Drupal\dblog\DbLogFilters::class)->getMessageTypes() instead.',
      'Call to deprecated function _filter_autop(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. The logic has been included in \Drupal\filter\Plugin\Filter\FilterAutoP::process() and no replacement is provided.',
      'Call to deprecated function _filter_html_escape(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. The logic has been included in \Drupal\filter\Plugin\Filter\FilterHtmlEscape::process() and no replacement is provided.',
      'Call to deprecated function _filter_html_image_secure_process(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. The logic is included in \Drupal\filter\Plugin\Filter\FilterHtmlImageSecure::process() and no replacement is provided.',
      'Call to deprecated function _filter_tips(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. There is no replacement.',
      'Call to deprecated function _media_library_configure_form_display(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. There is no replacement.',
      'Call to deprecated function _media_library_configure_view_display(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. There is no replacement.',
      'Call to deprecated function _media_library_media_type_form_submit(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. There is no replacement.',
      'Call to deprecated function _media_library_views_form_media_library_after_build(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. There is no replacement.',
      'Call to deprecated function _menu_ui_node_save(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use Drupal\menu_ui\MenuUiUtility::menuUiNodeSave() instead.',
      'Call to deprecated function _responsive_image_build_source_attributes(). Deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Use Drupal\responsive_image\ResponsiveImageBuilder::buildSourceAttributes() instead.',
      'Call to deprecated function _responsive_image_image_style_url(). Deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. Use Drupal\responsive_image\ResponsiveImageBuilder::getImageStyleUrl() instead.',
      'Call to deprecated function _system_default_theme_features(). Deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. Use Drupal\Core\Extension\ThemeSettingsProvider::DEFAULT_THEME_FEATURES instead.',
      'Call to deprecated function _update_ckeditor5_html_filter(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use Drupal\ckeditor5\Hook\Ckeditor5Hooks::updateCkeditor5HtmlFilter() instead.',
      'Call to deprecated function _update_manager_cache_directory(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no replacement. Use composer to manage the code for your site.',
      'Call to deprecated function _update_manager_extract_directory(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no replacement. Use composer to manage the code for your site.',
      'Call to deprecated function _update_manager_unique_identifier(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no replacement. Use composer to manage the code for your site.',
      'Call to deprecated function _views_field_get_entity_type_storage(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use Drupal::service(\'views.field_data_provider\') ->getSqlStorageForField($field_storage); instead.',
      'Call to deprecated function _views_field_get_entity_type_storage(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use views.field_data_provider service instead.',
      'Call to deprecated function automated_cron_settings_submit(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use AutomatedCronHooks::automatedCronSettingsSubmit() instead.',
      'Call to deprecated function block_content_add_body_field(). Deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. There is no replacement.',
      'Call to deprecated function block_theme_initialize(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. No replacement is provided.',
      'Call to deprecated function check_markup(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. There is no direct replacement. It\'s recommended to always return a renderable array without flattening as markup to pass the cacheability metadata.',
      'Call to deprecated function ckeditor5_filter_format_edit_form_submit(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use Drupal\ckeditor5\Hook\Ckeditor5Hooks::filterFormatEditFormSubmit() instead.',
      'Call to deprecated function contact_form_user_admin_settings_submit(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use Drupal\contact\Hook\ContactFormHooks::userAdminSettingsSubmit instead.',
      'Call to deprecated function contact_user_profile_form_submit(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use Drupal\contact\Hook\ContactFormHooks::profileFormSubmit() instead.',
      'Call to deprecated function content_translation_enable_widget(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use ContentTranslationEnableTranslationPerBundle::getWidget() instead.',
      'Call to deprecated function content_translation_language_configuration_element_process(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use ContentTranslationEnableTranslationPerBundle::configElementProcess() instead.',
      'Call to deprecated function content_translation_language_configuration_element_submit(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use ContentTranslationEnableTranslationPerBundle::configElementSubmit() instead.',
      'Call to deprecated function content_translation_language_configuration_element_validate(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use ContentTranslationEnableTranslationPerBundle::configElementValidate() instead.',
      'Call to deprecated function content_translation_translate_access(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use the access() method of the content_translation.manager service instead.',
      'Call to deprecated function datetime_type_field_views_data_helper(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use Drupal::service(\'datetime.views_helper\') ->buildViewsData($field_storage, $data, $column_name); instead.',
      'Call to deprecated function dblog_filters(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal::service(\Drupal\dblog\DbLogFilters::class)->filters() instead.',
      'Call to deprecated function drupal_common_theme(). Deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Use Drupal\Core\Theme\ThemeCommonElements::commonElements() instead,',
      'Call to deprecated function drupal_requirements_severity(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use Drupal\Core\Extension\Requirement\RequirementSeverity::getMaxSeverity() instead.',
      'Call to deprecated function editor_filter_xss(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Instead, use the method ::filterXss() from the element.editor service.',
      'Call to deprecated function editor_load(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use Drupal::entityTypeManager()->getStorage(\'editor\')->load($format_id) instead.',
      'Call to deprecated function entity_test_create_bundle(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use Drupal\entity_test\EntityTestHelper::createBundle() instead.',
      'Call to deprecated function entity_test_delete_bundle(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use Drupal\entity_test\EntityTestHelper::deleteBundle() instead.',
      'Call to deprecated function field_purge_batch(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal\Core\Field\FieldPurger::purgeBatch() instead.',
      'Call to deprecated function field_ui_form_manage_field_form_submit(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use Drupal\field_ui\Hook\FieldUiHooks::manageFieldFormSubmit() instead.',
      'Call to deprecated function file_get_content_headers(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use Drupal\file\Entity\FileInterface::getDownloadHeaders() instead.',
      'Call to deprecated function file_managed_file_submit(). Deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Use Drupal\file\Element\ManagedFile::submit() instead.',
      'Call to deprecated function file_system_settings_submit(). Deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Use Drupal\file\Hook\FileHooks::settingsSubmit() instead.',
      'Call to deprecated function filter_default_format(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use the \Drupal\filter\FilterFormatRepositoryInterface service with the ::getDefaultFormat() method instead.',
      'Call to deprecated function filter_fallback_format(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use the \Drupal\filter\FilterFormatRepositoryInterface service with the ::getFallbackFormatId() method instead.',
      'Call to deprecated function filter_formats(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use the Drupal\filter\FilterFormatRepositoryInterface service with the ::getAllFormats() method to get all formats, or with the ::getFormatsForAccount() method to get all formats that a user is able to access.',
      'Call to deprecated function filter_get_formats_by_role(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use the \Drupal\filter\FilterFormatRepositoryInterface service with the ::getFormatsByRole() method instead.',
      'Call to deprecated function filter_get_roles_by_format(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use the \Drupal\filter\FilterFormatInterface::getRoles() method instead.',
      'Call to deprecated function image_filter_keyword(). Deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Use Drupal\Component\Utility\Image::getKeywordOffset() instead.',
      'Call to deprecated function image_path_flush(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use ImageDerivativeUtilities::pathFlush() instead.',
      'Call to deprecated function image_style_options(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use ImageDerivativeUtilities::styleOptions() instead.',
      'Call to deprecated function language_configuration_element_submit(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use Drupal\language\Element\LanguageConfiguration::submit() instead.',
      'Call to deprecated function language_process_language_select(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use Drupal\language\Hook\LanguageHooks::processLanguageSelect() instead.',
      'Call to deprecated function locale_config_batch_refresh_name(). Deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Use locale_config_batch_update_config_translations() instead.',
      'Call to deprecated function locale_config_batch_set_config_langcodes(). Deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Use locale_config_batch_update_default_config_langcodes() instead.',
      'Call to deprecated function locale_translation_batch_update_build(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal::service(LocaleFetch::class) ->buildUpdateBatch($projects, $langcodes, $options) instead.',
      'Call to deprecated function media_filter_format_edit_form_validate(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use MediaHooks::formatEditFormValidate() instead.',
      'Call to deprecated function menu_ui_form_node_form_submit(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use Drupal\menu_ui\Hooks\MenuUiHooks::formNodeFormSubmit() instead.',
      'Call to deprecated function menu_ui_form_node_type_form_builder(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use Drupal\menu_ui\Hooks\MenuUiHooks::formNodeTypeFormBuilder() instead.',
      'Call to deprecated function menu_ui_form_node_type_form_validate(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use Drupal\menu_ui\Hooks\MenuUiHooks::formNodeTypeFormValidate() instead.',
      'Call to deprecated function menu_ui_get_menu_link_defaults(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal\menu_ui\MenuUiUtility::getMenuLinkDefaults() instead.',
      'Call to deprecated function menu_ui_node_builder(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal\menu_ui\Hooks\MenuUiHooks::nodeBuilder() instead.',
      'Call to deprecated function node_access_grants(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal::service(\'Drupal\node\NodeGrantsHelper\')->nodeAccessGrants() instead.',
      'Call to deprecated function node_access_needs_rebuild(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal::service(\'\Drupal\node\NodeAccessRebuild\')->setNeedsRebuild() and \Drupal::service(\'\Drupal\node\NodeAccessRebuild\')->needsRebuild() instead.',
      'Call to deprecated function node_access_rebuild(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal::service(\'\Drupal\node\NodeAccessRebuild\')->rebuild($batch_mode) instead.',
      'Call to deprecated function node_access_view_all_nodes(). Deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Use Drupal::entityTypeManager()->getAccessControlHandler(\'node\')->checkAllGrants() instead.',
      'Call to deprecated function node_add_body_field(). Deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. There is no replacement.',
      'Call to deprecated function node_get_type_label(). Deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. Use $node->getBundleEntity()->label() instead.',
      'Call to deprecated function node_type_get_description(). Deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Use $node_type->getDescription() instead.',
      'Call to deprecated function node_type_get_names(). Deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. Use Drupal::service(\'entity_type.bundle.info\')->getBundleLabels(\'node\') instead.',
      'Call to deprecated function responsive_image_get_image_dimensions(). Deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Use Drupal\responsive_image\ResponsiveImageBuilder::getImageDimensions() instead.',
      'Call to deprecated function responsive_image_get_mime_type(). Deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. Use Drupal\responsive_image\ResponsiveImageBuilder::getMimeType() instead.',
      'Call to deprecated function syslog_facility_list(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. There is no replacement.',
      'Call to deprecated function syslog_logging_settings_submit(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. There is no replacement.',
      'Call to deprecated function system_default_region(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal::service(\'theme_handler\')->getTheme()->getDefaultRegion() instead.',
      'Call to deprecated function system_region_list(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal::service(\'theme_handler\')->getTheme()->listAllRegions() or \Drupal::service(\'theme_handler\')->getTheme()->listVisibleRegions() instead.',
      'Call to deprecated function system_sort_themes(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. There is no replacement.',
      'Call to deprecated function taxonomy_build_node_index(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. There is no replacement.',
      'Call to deprecated function taxonomy_delete_node_index(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. There is no replacement.',
      'Call to deprecated function template_preprocess(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no replacement, default preprocess variables are added for all theme hooks directly in \Drupal\Core\Theme\ThemeManager.',
      'Call to deprecated function template_preprocess_container(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Initial template_preprocess functions are registered directly in hook_theme().',
      'Call to deprecated function template_preprocess_datetime_form(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Initial template_preprocess functions are registered directly in hook_theme().',
      'Call to deprecated function template_preprocess_datetime_wrapper(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Initial template_preprocess functions are registered directly in hook_theme().',
      'Call to deprecated function template_preprocess_html(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Initial template_preprocess functions are registered directly in hook_theme().',
      'Call to deprecated function template_preprocess_layout(). Deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Initial template_preprocess functions are registered directly in hook_theme().',
      'Call to deprecated function template_preprocess_links(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Initial template_preprocess functions are registered directly in hook_theme().',
      'Call to deprecated function template_preprocess_page(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Initial template_preprocess functions are registered directly in hook_theme().',
      'Call to deprecated function template_preprocess_page(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use hook_theme() to define page variables instead.',
      'Call to deprecated function template_preprocess_time(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Initial template_preprocess functions are registered directly in hook_theme().',
      'Call to deprecated function text_summary(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal::service(TextSummary::class)->generate() instead.',
      'Call to deprecated function theme_get_setting(). Deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. Use Drupal::service(\'Drupal\Core\Extension\ThemeSettingsProvider\')->getSetting() instead.',
      'Call to deprecated function twig_render_template(). Deprecated in drupal:11.3.0 and is removed from drupal:12.0.0.',
      'Call to deprecated function update_clear_update_disk_cache(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no replacement. Use composer to manage the code for your site.',
      'Call to deprecated function update_delete_file_if_stale(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no replacement. Use composer to manage the code for your site.',
      'Call to deprecated function user_form_process_password_confirm(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use UserThemeHooks::processPasswordConfirm() instead.',
      'Call to deprecated function views_add_contextual_links(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal\views\ContextualLinksHelper::addLinks() instead.',
      'Call to deprecated function views_disable_view(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use $view->disable()->save() instead.',
      'Call to deprecated function views_enable_view(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use $view->enable()->save() instead.',
      'Call to deprecated function views_entity_field_label(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use Drupal::service(\'entity_field.manager\')->getFieldLabels() instead.',
      'Call to deprecated function views_entity_field_label(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use entity_field.manager service instead.',
      'Call to deprecated function views_field_default_views_data(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use Drupal::service(\'views.field_data_provider\') ->defaultFieldImplementation($field_storage); instead.',
      'Call to deprecated function views_field_default_views_data(). Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use views.field_data_provider service instead.',
      'Call to deprecated function views_get_view_result(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Views::getViewResult($name, $display_id, ...$args) instead.',
      'Call to deprecated function views_ui_contextual_links_suppress(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. There is no replacement.',
      'Call to deprecated function views_ui_contextual_links_suppress_pop(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. There is no replacement.',
      'Call to deprecated function views_ui_contextual_links_suppress_push(). Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. There is no replacement.',
      'Call to deprecated function views_view_is_disabled(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use !$view->status() instead.',
      'Call to deprecated function views_view_is_enabled(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use $view->status() instead.',
      'Call to deprecated method addModule() of class Drupal\Core\Extension\ModuleHandler. Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no direct replacement.',
      'Call to deprecated method addModule() of interface Drupal\Core\Extension\ModuleHandlerInterface. Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no direct replacement.',
      'Call to deprecated method addProfile() of class Drupal\Core\Extension\ModuleHandler. Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no direct replacement.',
      'Call to deprecated method addProfile() of interface Drupal\Core\Extension\ModuleHandlerInterface. Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no direct replacement.',
      'Call to deprecated method basename() of class Drupal\Core\File\FileSystem. Deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. Use PHP native basename() instead.',
      'Call to deprecated method basename() of interface Drupal\Core\File\FileSystemInterface. Deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. Use PHP native basename() instead.',
      'Call to deprecated method countDefaultLanguageRevisions() of interface Drupal\node\NodeStorageInterface. Deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. There is no replacement.',
      'Call to deprecated method currentErrorHandler() of class Drupal\Core\Utility\Error. Deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. Use get_error_handler() instead.',
      'Call to deprecated method delete() of class Drupal\Core\Session\SessionManager. Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use Drupal\user\UserSessionRepositoryInterface::deleteAll() instead.',
      'Call to deprecated method delete() of interface Drupal\Core\Session\SessionManagerInterface. Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use Drupal\user\UserSessionRepositoryInterface::deleteAll() instead.',
      'Call to deprecated method fetchColumn() of class Drupal\Core\Database\StatementPrefetchIterator. Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use ::fetchField() instead.',
      'Call to deprecated method getCountNewComments() of class Drupal\comment\CommentManager. Deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Use Drupal\history\HistoryManager::getCountNewComments instead.',
      'Call to deprecated method getCountNewComments() of interface Drupal\comment\CommentManagerInterface. Deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Use Drupal\history\HistoryManager::getCountNewComments instead.',
      'Call to deprecated method getEntityTypeIdKeyType() of class Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. To determine if an entity type has an integer ID key, use Drupal\Core\Entity\EntityTypeInterface::hasIntegerId().',
      'Call to deprecated method getHookInfo() of interface Drupal\Core\Extension\ModuleHandlerInterface. Deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Not needed any more.',
      'Call to deprecated method getName() of class Drupal\Core\Extension\ModuleHandler. Deprecated in drupal:10.3.0 and is removed from drupal:12.0.0. Use Drupal::service(\'extension.list.module\')->getName($module) instead.',
      'Call to deprecated method getName() of interface Drupal\Core\Extension\ModuleHandlerInterface. Deprecated in drupal:10.3.0 and is removed from drupal:12.0.0. Use Drupal::service(\'extension.list.module\')->getName($module) instead.',
      'Call to deprecated method getOriginalClass() of interface Drupal\Core\Entity\EntityTypeInterface. Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use getDecoratedClasses() instead.',
      'Call to deprecated method getRowCacheKeys() of class Drupal\views\Plugin\views\cache\CachePluginBase. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. There is no replacement.',
      'Call to deprecated method getRowId() of class Drupal\views\Plugin\views\cache\CachePluginBase. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. There is no replacement.',
      'Call to deprecated method hasIntegerId() of class Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal\Core\Entity\EntityTypeInterface::hasIntegerId() instead.',
      'Call to deprecated method installModule() of class Drupal\Core\Recipe\RecipeRunner. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal\Core\Recipe\RecipeRunner::installModules() instead.',
      'Call to deprecated method invalidateAll() of interface Drupal\Core\Cache\CacheBackendInterface. Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use CacheBackendInterface::deleteAll() or cache tag invalidation instead.',
      'Call to deprecated method isConfigurable() of class Drupal\Component\Plugin\PluginBase. Deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Use instanceof to check if the plugin implements \Drupal\Component\Plugin\ConfigurableInterface instead.',
      'Call to deprecated method loadAllIncludes() of interface Drupal\Core\Extension\ModuleHandlerInterface. Deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. There is no replacement.',
      'Call to deprecated method movePointerTo() of class Drupal\Tests\layout_builder\FunctionalJavascript\LayoutBuilderDisableInteractionsTest. Deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Use $this->getSession()->getDriver()->mouseOver() instead.',
      'Call to deprecated method pathAliasWhitelistRebuild() of class Drupal\path_alias\AliasManager. Deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Use Drupal\path_alias\AliasManager::pathAliasPrefixListRebuild instead.',
      'Call to deprecated method pluginManager() of class Drupal\views\Views. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal::service(\'plugin.manager.views.{type}\') for specific types or \Drupal::service(\'views.plugin_managers\')->get($type) for dynamic.',
      'Call to deprecated method rebuildThemeData() of class Drupal\Core\Extension\ThemeHandler. Deprecated in drupal:10.3.0 and is removed from drupal:12.0.0. Use Drupal::service(\'extension.list.theme\')->reset()->getList() instead.',
      'Call to deprecated method rebuildThemeData() of interface Drupal\Core\Extension\ThemeHandlerInterface. Deprecated in drupal:10.3.0 and is removed from drupal:12.0.0. Use Drupal::service(\'extension.list.theme\')->reset()->getList() instead.',
      'Call to deprecated method renderPlain() of class Drupal\Core\Render\Renderer. Deprecated in drupal:10.3.0 and is removed from drupal:12.0.0. Use Drupal\Core\Render\RendererInterface::renderInIsolation() instead.',
      'Call to deprecated method renderPlain() of interface Drupal\Core\Render\RendererInterface. Deprecated in drupal:10.3.0 and is removed from drupal:12.0.0. Use Drupal\Core\Render\RendererInterface::renderInIsolation() instead.',
      'Call to deprecated method revisionIds() of interface Drupal\node\NodeStorageInterface. Deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. Use an entity query instead.',
      'Call to deprecated method setUriCallback() of interface Drupal\Core\Entity\EntityTypeInterface. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use link templates or a route provider to specify entity URIs.',
      'Call to deprecated method trustData() of class Drupal\Core\Config\Entity\ConfigEntityBase. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. There is no replacement.',
      'Call to deprecated method trustData() of interface Drupal\Core\Config\Entity\ConfigEntityInterface. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. There is no replacement.',
      'Call to deprecated method userRevisionIds() of interface Drupal\node\NodeStorageInterface. Deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. Use an entity query instead.',
      'Call to deprecated method validateTitleElement() of class Drupal\link\Plugin\Field\FieldWidget\LinkWidget. Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Instead, validation is performed by the LinkTitleRequiredConstraint on the LinkItem field type.',
      'Call to deprecated method writeCache() of interface Drupal\Core\Extension\ModuleHandlerInterface. Deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Not needed any more.',
      'Calling Drupal\Core\Config\Config::save() with the $has_trusted_data argument is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. There is no replacement. See https://www.drupal.org/node/3348180',
      'Fetching deprecated class constant ANONYMOUS_MAYNOT_CONTACT of interface Drupal\comment\CommentInterface. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal\comment\AnonymousContact::Forbidden instead.',
      'Fetching deprecated class constant ANONYMOUS_MAY_CONTACT of interface Drupal\comment\CommentInterface. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal\comment\AnonymousContact::Allowed instead.',
      'Fetching deprecated class constant ANONYMOUS_MUST_CONTACT of interface Drupal\comment\CommentInterface. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal\comment\AnonymousContact::Required instead.',
      'Fetching deprecated class constant CLOSED of interface Drupal\comment\Plugin\Field\FieldType\CommentItemInterface. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal\comment\CommentingStatus::Closed instead.',
      'Fetching deprecated class constant EXISTS_ERROR of interface Drupal\Core\File\FileSystemInterface. Deprecated in drupal:10.3.0 and is removed from drupal:12.0.0. Use Drupal\Core\File\FileExists::Error instead.',
      'Fetching deprecated class constant EXISTS_RENAME of interface Drupal\Core\File\FileSystemInterface. Deprecated in drupal:10.3.0 and is removed from drupal:12.0.0. Use Drupal\Core\File\FileExists::Rename instead.',
      'Fetching deprecated class constant EXISTS_REPLACE of interface Drupal\Core\File\FileSystemInterface. Deprecated in drupal:10.3.0 and is removed from drupal:12.0.0. Use Drupal\Core\File\FileExists::Replace instead.',
      'Fetching deprecated class constant FORM_BELOW of interface Drupal\comment\Plugin\Field\FieldType\CommentItemInterface. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal\comment\FormLocation::Below instead.',
      'Fetching deprecated class constant FORM_SEPARATE_PAGE of interface Drupal\comment\Plugin\Field\FieldType\CommentItemInterface. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal\comment\FormLocation::SeparatePage instead.',
      'Fetching deprecated class constant HIDDEN of interface Drupal\comment\Plugin\Field\FieldType\CommentItemInterface. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal\comment\CommentingStatus::Hidden instead.',
      'Fetching deprecated class constant OPEN of interface Drupal\comment\Plugin\Field\FieldType\CommentItemInterface. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal\comment\CommentingStatus::Open instead.',
      'Fetching deprecated class constant RECURSIVE_RENDER_LIMIT of class Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceEntityFormatter. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. EntityViewBuilder #pre_render and #post_render callbacks prevent recursion.',
      'Fetching deprecated class constant REQUIREMENT_ERROR of class Drupal\system\SystemManager. Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use Drupal\Core\Extension\Requirement\RequirementSeverity::Error instead.',
      'Fetching deprecated class constant REQUIREMENT_OK of class Drupal\system\SystemManager. Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use Drupal\Core\Extension\Requirement\RequirementSeverity::OK instead.',
      'Fetching deprecated class constant REQUIREMENT_WARNING of class Drupal\system\SystemManager. Deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use Drupal\Core\Extension\Requirement\RequirementSeverity::Warning instead.',
      'Passing a string $root value to Drupal\Core\Database\Database::convertDbUrlToConnectionInfo() is deprecated in drupal:11.3.0 and will be removed in drupal:12.0.0. There is no replacement.',
      'Use Drupal::TRANSLATION_DEFAULT_SERVER_PATTERN instead. in drupal:11.2.0 and is removed from drupal:12.0.0.',
      'Use of deprecated global constant JSONAPI_FILTER_AMONG_ENABLED',
      'Use of deprecated global constant JSONAPI_FILTER_AMONG_OWN',
      'Use of deprecated global constant JSONAPI_FILTER_AMONG_PUBLISHED',
      'Using the variable $values as string is deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Provide an array as parameter. See https://www.drupal.org/node/3473739',
      'editor_image_upload_settings_form() deprecated in drupal:11.4.0, removed in drupal:13.0.0. Replaced by \Drupal::service(EditorImageUploadSettings::class)->getForm().',
      'locale_translation_batch_fetch_build() is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal::service(LocaleFetch::class)->buildFetchBatch($projects, $langcodes, $options) instead. See https://www.drupal.org/node/3572345',
      'locale_translation_build_sources() is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal::service(LocaleSource::class)->buildSources($projects, $langcodes) instead. See https://www.drupal.org/node/3569330',
      'locale_translation_clear_cache_projects() is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal::service("cache.memory")->delete("locale_get_projects") instead. See https://www.drupal.org/node/3569330',
      'locale_translation_get_projects() is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal::service(LocaleProjectRepository::class)->getAll() or \Drupal::service(LocaleProjectRepository::class)->getMultiple($project_names) instead. See https://www.drupal.org/node/3569330',
      'locale_translation_load_sources() is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use Drupal::service(LocaleSource::class)->loadSources($projects, $langcodes) instead. See https://www.drupal.org/node/3569330',
      'postInstall() is deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. There is no replacement. See https://www.drupal.org/node/3461934',
      'Call to deprecated method getDrupalRoot() of class Drupal\KernelTests\KernelTestBase. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Access $this->root directly.',
      'Call to deprecated method getDrupalRoot() of class Drupal\Tests\BrowserTestBase. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Access $this->root directly.',
      'Call to deprecated method getDrupalRoot() of class Drupal\Tests\UnitTestCase. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Access $this->root directly.',
      'Call to deprecated method setCacheKey() of class Drupal\path_alias\AliasManager. Deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. There is no replacement.',
      'Call to deprecated method writeCache() of class Drupal\path_alias\AliasManager. Deprecated in drupal:11.3.0 and is removed from drupal:13.0.0. There is no replacement.',
      'Usage of deprecated trait Drupal\Component\Utility\ToStringTrait. Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Implement the __toString() method directly, exception handling is no longer required.',
      'Calling Drupal\Core\Render\Renderer::addCacheableDependency() with an object that doesn\'t implement Drupal\Core\Cache\CacheableDependencyInterface is deprecated in drupal:11.3.0 and will throw an error in drupal:13.0.0. See https://www.drupal.org/node/3525389',
      'Call to deprecated method expectDeprecation() of class Drupal\KernelTests\KernelTestBase. Deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. Use $this->expectUserDeprecationMessage() or $this->expectUserDeprecationMessageMatches() instead.',
      'Call to deprecated function hide(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. To hide form elements, use [\'#access\'] = FALSE. For render elements, use [\'#printed\'] = TRUE.',
      'Call to deprecated function show(). Deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. To show form elements, use [\'#access\'] = TRUE. For render elements, use [\'#printed\'] = FALSE.',
    ];
    return in_array($string, $rector_covered) ||
      strpos($string, 'Call to deprecated method l() of class Drupal') === 0;
  }

}
