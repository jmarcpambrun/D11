<?php

declare(strict_types=1);

namespace Drupal\group\Hook;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Utility\Token;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;

/**
 * Token hook implementations for Group.
 */
final class TokenHooks {

  use StringTranslationTrait;

  public function __construct(
    protected Token $token,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LanguageManagerInterface $languageManager,
    protected DateFormatterInterface $dateFormatter,
    TranslationInterface $translation,
  ) {
    $this->stringTranslation = $translation;
  }

  /**
   * Implements hook_token_info().
   */
  #[Hook('token_info')]
  public function tokenInfo(): array {
    $types['group'] = [
      'name' => $this->t('Group'),
      'description' => $this->t('Tokens related to individual groups.'),
      'needs-data' => 'group',
    ];

    $tokens['group']['id'] = [
      'name' => $this->t('Group ID'),
      'description' => $this->t('The unique ID of the group.'),
    ];

    $tokens['group']['uuid'] = [
      'name' => $this->t('Group UUID'),
      'description' => $this->t('The universally unique ID of the group.'),
    ];

    $tokens['group']['type'] = [
      'name' => $this->t('Group type'),
      'description' => $this->t('The machine name of the group type.'),
    ];

    $tokens['group']['type-name'] = [
      'name' => $this->t('Group type name'),
      'description' => $this->t('The human-readable name of the group type.'),
    ];

    $tokens['group']['title'] = [
      'name' => $this->t('Title'),
    ];

    $tokens['group']['langcode'] = [
      'name' => $this->t('Language code'),
      'description' => $this->t('The language code of the language the group is in.'),
    ];

    $tokens['group']['url'] = [
      'name' => $this->t('URL'),
      'description' => $this->t('The URL of the group.'),
    ];

    $tokens['group']['edit-url'] = [
      'name' => $this->t('Edit URL'),
      'description' => $this->t("The URL of the group's edit page."),
    ];

    $tokens['group']['created'] = [
      'name' => $this->t('Date created'),
      'type' => 'date',
    ];

    $tokens['group']['changed'] = [
      'name' => $this->t('Date changed'),
      'description' => $this->t('The date the group was most recently updated.'),
      'type' => 'date',
    ];

    $tokens['group']['author'] = [
      'name' => $this->t('Author'),
      'type' => 'user',
    ];

    $types['group_relationship'] = [
      'name' => $this->t('Group relationship'),
      'description' => $this->t('Tokens related to the relationships between a group and its content.'),
      'needs-data' => 'group_relationship',
    ];

    $tokens['group_relationship']['id'] = [
      'name' => $this->t('Group relationship ID'),
      'description' => $this->t('The unique ID of the group relationship.'),
    ];

    $tokens['group_relationship']['langcode'] = [
      'name' => $this->t('Language code'),
      'description' => $this->t('The language code of the language the group relationship is in.'),
    ];

    $tokens['group_relationship']['url'] = [
      'name' => $this->t('URL'),
      'description' => $this->t('The URL of the group relationship.'),
    ];

    $tokens['group_relationship']['edit-url'] = [
      'name' => $this->t('Edit URL'),
      'description' => $this->t("The URL of the group's edit page."),
    ];

    $tokens['group_relationship']['created'] = [
      'name' => $this->t('Date created'),
      'type' => 'date',
    ];

    $tokens['group_relationship']['changed'] = [
      'name' => $this->t('Date changed'),
      'description' => $this->t('The date the group relationship was most recently updated.'),
      'type' => 'date',
    ];

    $tokens['group_relationship']['group'] = [
      'name' => $this->t('Group'),
      'type' => 'group',
    ];

    $tokens['group_relationship']['pretty-path-key'] = [
      'name' => $this->t('Pretty path key'),
      'description' => $this->t('A prettier way of labeling group relationship of the same relation type.'),
    ];

    return ['types' => $types, 'tokens' => $tokens];
  }

  /**
   * Implements hook_tokens().
   */
  #[Hook('tokens')]
  public function tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
    $replacements = [];
    $url_options = ['absolute' => TRUE];

    if (isset($options['langcode'])) {
      $url_options['language'] = $this->languageManager->getLanguage($options['langcode']);
      $langcode = $options['langcode'];
    }
    else {
      $langcode = LanguageInterface::LANGCODE_DEFAULT;
    }

    $date_format_storage = $this->entityTypeManager->getStorage('date_format');
    if ($type == 'group' && !empty($data[$type])) {
      $group = $data['group'];
      assert($group instanceof GroupInterface);

      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'id':
            $replacements[$original] = $group->id();
            break;

          case 'uuid':
            $replacements[$original] = $group->uuid();
            break;

          case 'type':
            $replacements[$original] = $group->bundle();
            break;

          case 'type-name':
            $replacements[$original] = $group->getGroupType()->label();
            break;

          case 'title':
            $replacements[$original] = $group->label();
            break;

          case 'langcode':
            $replacements[$original] = $group->language()->getId();
            break;

          case 'url':
            $replacements[$original] = $group->toUrl('canonical', $url_options)->toString();
            break;

          case 'edit-url':
            $replacements[$original] = $group->toUrl('edit-form', $url_options)->toString();
            break;

          // Default values for the chained tokens handled below.
          case 'author':
            $account = $group->getOwner();
            $bubbleable_metadata->addCacheableDependency($account);
            $replacements[$original] = $account->label();
            break;

          case 'created':
            $date_format = $date_format_storage->load('medium');
            $bubbleable_metadata->addCacheableDependency($date_format);
            $replacements[$original] = $this->dateFormatter->format($group->getCreatedTime(), 'medium', '', NULL, $langcode);
            break;

          case 'changed':
            $date_format = $date_format_storage->load('medium');
            $bubbleable_metadata->addCacheableDependency($date_format);
            $replacements[$original] = $this->dateFormatter->format($group->getChangedTime(), 'medium', '', NULL, $langcode);
            break;
        }
      }

      // Actual chaining of tokens handled below.
      if ($author_tokens = $this->token->findWithPrefix($tokens, 'author')) {
        $replacements += $this->token->generate('user', $author_tokens, ['user' => $group->getOwner()], $options, $bubbleable_metadata);
      }

      if ($created_tokens = $this->token->findWithPrefix($tokens, 'created')) {
        $replacements += $this->token->generate('date', $created_tokens, ['date' => $group->getCreatedTime()], $options, $bubbleable_metadata);
      }

      if ($changed_tokens = $this->token->findWithPrefix($tokens, 'changed')) {
        $replacements += $this->token->generate('date', $changed_tokens, ['date' => $group->getChangedTime()], $options, $bubbleable_metadata);
      }
    }
    elseif ($type == 'group_relationship' && !empty($data[$type])) {
      $group_relationship = $data['group_relationship'];
      assert($group_relationship instanceof GroupRelationshipInterface);

      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'id':
            $replacements[$original] = $group_relationship->id();
            break;

          case 'langcode':
            $replacements[$original] = $group_relationship->language()->getId();
            break;

          case 'url':
            $replacements[$original] = $group_relationship->toUrl('canonical', $url_options)->toString();
            break;

          case 'edit-url':
            $replacements[$original] = $group_relationship->toUrl('edit-form', $url_options)->toString();
            break;

          case 'pretty-path-key':
            $replacements[$original] = $group_relationship->getPlugin()->getRelationType()->getPrettyPathKey();
            break;

          // Default values for the chained tokens handled below.
          case 'group':
            $group = $group_relationship->getGroup();
            $bubbleable_metadata->addCacheableDependency($group);
            $replacements[$original] = $group->label();
            break;

          case 'created':
            $date_format = $date_format_storage->load('medium');
            $bubbleable_metadata->addCacheableDependency($date_format);
            $replacements[$original] = $this->dateFormatter->format($group_relationship->getCreatedTime(), 'medium', '', NULL, $langcode);
            break;

          case 'changed':
            $date_format = $date_format_storage->load('medium');
            $bubbleable_metadata->addCacheableDependency($date_format);
            $replacements[$original] = $this->dateFormatter->format($group_relationship->getChangedTime(), 'medium', '', NULL, $langcode);
            break;
        }

        // Actual chaining of tokens handled below.
        if ($group_tokens = $this->token->findWithPrefix($tokens, 'group')) {
          $replacements += $this->token->generate('group', $group_tokens, ['group' => $group_relationship->getGroup()], $options, $bubbleable_metadata);
        }

        if ($created_tokens = $this->token->findWithPrefix($tokens, 'created')) {
          $replacements += $this->token->generate('date', $created_tokens, ['date' => $group_relationship->getCreatedTime()], $options, $bubbleable_metadata);
        }

        if ($changed_tokens = $this->token->findWithPrefix($tokens, 'changed')) {
          $replacements += $this->token->generate('date', $changed_tokens, ['date' => $group_relationship->getChangedTime()], $options, $bubbleable_metadata);
        }
      }
    }

    return $replacements;
  }

}
