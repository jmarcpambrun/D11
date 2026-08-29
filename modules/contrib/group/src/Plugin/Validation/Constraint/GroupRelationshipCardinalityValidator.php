<?php

namespace Drupal\group\Plugin\Validation\Constraint;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Checks the amount of times a single entity can be grouped.
 */
class GroupRelationshipCardinalityValidator extends ConstraintValidator implements ContainerInjectionInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Connection $database,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $group_relationship, Constraint $constraint): void {
    assert($group_relationship instanceof GroupRelationshipInterface);
    assert($constraint instanceof GroupRelationshipCardinality);

    // Both the group and entity field are required, so their presence is
    // checked by the NotNull constraint that gets added by default in the
    // TypedDataManager's ::getDefaultConstraints() method. Instead of blindly
    // relying on the entities being set, we quietly fail if they're not there
    // so NotNull can give a clear failure message.
    if (!$group = $group_relationship->getGroup()) {
      return;
    }
    if (!$entity = $group_relationship->getEntity()) {
      return;
    }

    // If the entity is new, it can't belong to any groups yet. So both the
    // group and entity cardinality can not be surpassed. Note that this should
    // only occur when on a combined form where the entity that is to be added
    // to a group has not been saved yet.
    if ($entity->isNew()) {
      return;
    }

    // Get the plugin for the relationship entity.
    $plugin = $group_relationship->getPlugin();

    // Get the cardinality settings from the plugin.
    $group_cardinality = $plugin->getGroupCardinality();
    $entity_cardinality = $plugin->getEntityCardinality();

    // Exit early if both cardinalities are set to unlimited.
    if ($group_cardinality <= 0 && $entity_cardinality <= 0) {
      return;
    }

    // Get the entity_id field label for error messages.
    $field_name = $group_relationship->getFieldDefinition('entity_id')->getLabel();

    // Get the entity ID to look for, we directly use the entity_id field
    // because it reflects what's actually stored in the DB, even if we're
    // dealing with a wrapped config entity.
    $entity_id = $group_relationship->get('entity_id')->target_id;
    $data_table = $this->entityTypeManager->getDefinition('group_relationship')->getDataTable();

    // Enforce the group cardinality if it's not set to unlimited.
    if ($group_cardinality > 0) {
      // Get the groups this content entity already belongs to, not counting
      // the current group towards the limit.
      $group_count = $this->database->select($data_table, 'gc')
        ->fields('gc', ['gid'])
        ->condition('group_type', $group_relationship->getGroupTypeId())
        ->condition('plugin_id', $group_relationship->getPluginId())
        ->condition('entity_id', $entity_id)
        // We allow new group entities on the create group form, where we
        // validate the group relationship for the member. For this single use
        // case we need to cast NULL to 0.
        ->condition('gid', $group->id() ?? 0, '!=')
        ->distinct()
        ->countQuery()
        ->execute()
        ->fetchField();

      // Raise a violation if the content has reached the cardinality limit.
      if ($group_count >= $group_cardinality) {
        $this->context->buildViolation($constraint->groupMessage)
          ->setParameter('@field', $field_name)
          ->setParameter('%content', $entity->label())
          ->setParameter('%group_type', $group_relationship->getGroupType()->label())
          // We manually flag the entity reference field as the source of the
          // violation so form API will add a visual indicator of where the
          // validation failed.
          ->atPath('entity_id.0')
          ->addViolation();
      }
    }

    // Enforce the entity cardinality if it's not set to unlimited.
    if ($entity_cardinality > 0) {
      // We need to exclude the current relationship from the count, but only if
      // it already existed in the database.
      $relationship_id = $group_relationship->id() ?? -1;
      $entity_count = $this->database->select($data_table, 'gc')
        ->fields('gc', ['gid'])
        ->condition('plugin_id', $group_relationship->getPluginId())
        ->condition('entity_id', $entity_id)
        ->condition('gid', $group->id())
        ->condition('id', $relationship_id, '!=')
        ->distinct()
        ->countQuery()
        ->execute()
        ->fetchField();

      // Raise a violation if the content has reached the cardinality limit.
      if ($entity_count >= $entity_cardinality) {
        $this->context->buildViolation($constraint->entityMessage)
          ->setParameter('@field', $field_name)
          ->setParameter('%content', $entity->label())
          ->setParameter('%group', $group->label())
          // We manually flag the entity reference field as the source of the
          // violation so form API will add a visual indicator of where the
          // validation failed.
          ->atPath('entity_id.0')
          ->addViolation();
      }
    }
  }

}
