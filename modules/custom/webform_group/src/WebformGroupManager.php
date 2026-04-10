<?php

namespace Drupal\webform_group;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\webform\EntityStorage\WebformEntityStorageTrait;
use Drupal\webform\WebformAccessRulesManagerInterface;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformRequestInterface;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Webform group manager manager.
 */
class WebformGroupManager implements WebformGroupManagerInterface {

  use WebformEntityStorageTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The configuration object factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The webform request handler.
   *
   * @var \Drupal\webform\WebformRequestInterface
   */
  protected $requestHandler;

  /**
   * The webform access rules manager.
   *
   * @var \Drupal\webform\WebformAccessRulesManagerInterface
   */
  protected $accessRulesManager;

  /**
   * The current user's group roles.
   *
   * @var array
   */
  protected $currentGroupRoles;

  /**
   * The current request's group content.
   *
   * @var \Drupal\group\Entity\GroupRelationshipInterface
   */
  protected $currentGroupRelationship;

  /**
   * Cache webform access rules.
   *
   * @var array
   */
  protected $accessRules = [];

  /**
   * Cache webform group allowed tokens.
   *
   * @var array
   */
  protected $allowedGroupRoleTokens;

  /**
   * Constructs a WebformGroupManager object.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration object factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\webform\WebformRequestInterface $request_handler
   *   The webform request handler.
   * @param \Drupal\webform\WebformAccessRulesManagerInterface $access_rules_manager
   *   The webform access rules manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(AccountInterface $current_user, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, WebformRequestInterface $request_handler, WebformAccessRulesManagerInterface $access_rules_manager) {
    $this->currentUser = $current_user;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->requestHandler = $request_handler;
    $this->accessRulesManager = $access_rules_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function isGroupOwnerTokenEnable() {
    return $this->configFactory->get('webform_group.settings')->get('mail.group_owner') ?: FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isGroupRoleTokenEnabled($group_role_id) {
    $allowed_group_role_tokens = $this->getAllowedGroupRoleTokens();
    return isset($allowed_group_role_tokens[$group_role_id]);
  }

  /**
   * Get allowed token group roles.
   *
   * @return array
   *   An associative array containing allowed token group roles.
   */
  protected function getAllowedGroupRoleTokens() {
    if (!isset($this->allowedGroupRoleTokens)) {
      $allowed_group_roles = $this->configFactory->get('webform_group.settings')->get('mail.group_roles');
      $this->allowedGroupRoleTokens = ($allowed_group_roles) ? array_combine($allowed_group_roles, $allowed_group_roles) : [];
    }
    return $this->allowedGroupRoleTokens;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentUserGroupRoles() {
    if (isset($this->currentGroupRoles)) {
      return $this->currentGroupRoles;
    }

    $group_relationship = $this->getCurrentGroupRelationship();
    $this->currentGroupRoles = ($group_relationship) ? $this->getUserGroupRoles($group_relationship, $this->currentUser) : [];
    return $this->currentGroupRoles;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentGroupRelationship() {
    if (isset($this->currentGroupRelationship)) {
      return $this->currentGroupRelationship;
    }

    $this->currentGroupRelationship = FALSE;

    $source_entity = $this->requestHandler->getCurrentSourceEntity(['webform_submission']);
    if (!$source_entity) {
      return $this->currentGroupRelationship;
    }

    /** @var \Drupal\group\Entity\Storage\GroupRelationshipStorageInterface $group_relationship_storage */
    $group_relationship_storage = $this->getEntityStorage('group_relationship');

    // Get group content id for the source entity.
    $group_relationship_ids = $group_relationship_storage->getQuery()
      ->accessCheck()
      ->condition('entity_id', $source_entity->id())
      ->execute();
    /** @var \Drupal\group\Entity\GroupRelationshipInterface[] $group_relationships */
    $group_relationships = $group_relationship_storage->loadMultiple($group_relationship_ids);
    foreach ($group_relationships as $group_relationship) {
      $group_relationship_entity = $group_relationship->getEntity();
      if ($group_relationship_entity->getEntityTypeId() === $source_entity->getEntityTypeId()
        && $group_relationship_entity->id() === $source_entity->id()
      ) {
        $this->currentGroupRelationship = $group_relationship;
        break;
      }
    }

    return $this->currentGroupRelationship;
  }

  /**
   * {@inheritdoc}
   */
  public function getWebformSubmissionUserGroupRoles(WebformSubmissionInterface $webform_submission, AccountInterface $account) {
    $group_relationship = $this->getWebformSubmissionGroupRelationship($webform_submission);
    return ($group_relationship) ? $this->getUserGroupRoles($group_relationship, $account) : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getWebformSubmissionGroupRelationship(WebformSubmissionInterface $webform_submission) {
    $source_entity = $webform_submission->getSourceEntity();
    if (!$source_entity) {
      return NULL;
    }

    /** @var \Drupal\group\Entity\Storage\GroupRelationshipStorageInterface $group_relationship_storage */
    $group_relationship_storage = $this->getEntityStorage('group_relationship');

    // Get group content id for the source entity.
    $group_relationship_ids = $group_relationship_storage->getQuery()
      ->accessCheck()
      ->condition('entity_id', $source_entity->id())
      ->execute();

    /** @var \Drupal\group\Entity\GroupRelationshipInterface[] $group_relationships */
    $group_relationships = $group_relationship_storage->loadMultiple($group_relationship_ids);
    foreach ($group_relationships as $group_relationship) {
      $group_relationship_entity = $group_relationship->getEntity();
      if ($group_relationship_entity->getEntityTypeId() === $source_entity->getEntityTypeId()
        && $group_relationship_entity->id() === $source_entity->id()
      ) {
        return $group_relationship;
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentGroupWebform() {
    return ($this->getCurrentGroupRelationship()) ? $this->requestHandler->getCurrentWebform() : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessRules(WebformInterface $webform) {
    $webform_id = $webform->id();
    if (isset($this->accessRules[$webform_id])) {
      return $this->accessRules[$webform_id];
    }

    $access_rules = $webform->getAccessRules()
      + $this->accessRulesManager->getDefaultAccessRules();

    // Remove configuration access rules which is never applicable to the
    // webform group integration.
    unset($access_rules['configuration']);

    // Set default group roles for each permission.
    foreach ($access_rules as &$access_rule) {
      $access_rule += ['group_roles' => []];
    }

    $this->accessRules[$webform_id] = $access_rules;
    return $access_rules;
  }

  /* ************************************************************************ */
  // Helper methods.
  /* ************************************************************************ */

  /**
   * Get current user group roles for group content.
   *
   * @param \Drupal\group\Entity\GroupRelationshipInterface $group_relationship
   *   Group content.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   A user account.
   *
   * @return array
   *   An array of group roles for the group content.
   */
  protected function getUserGroupRoles(GroupRelationshipInterface $group_relationship, AccountInterface $account) {
    $group = $group_relationship->getGroup();

    // Must get implied groups, which includes outsider, by calling
    // \Drupal\group\Entity\Storage\GroupRoleStorage::loadByUserAndGroup.
    // @see \Drupal\group\Entity\Storage\GroupRoleStorageInterface::loadByUserAndGroup
    /** @var \Drupal\group\Entity\Storage\GroupRoleStorageInterface $group_role_storage */
    $group_role_storage = $this->getEntityStorage('group_role');
    $group_roles = $group_role_storage->loadByUserAndGroup($account, $group, TRUE);
    if (!$group_roles) {
      return [];
    }

    $group_roles = array_keys($group_roles);
    return array_combine($group_roles, $group_roles);
  }

}
