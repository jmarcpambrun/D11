<?php

namespace Drupal\maestro\Plugin\views\filter;

use Drupal\Core\Session\AccountInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Filters Maestro Production Assignments based on the current user's role or assignment.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("maestro_assignment_filter")
 */
class MaestroAssignmentFilter extends FilterPluginBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * Constructs a MaestroAssignmentFilter object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function canExpose() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    return $this->t('Filter by the current user\'s role or assignment.');
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Get the current username.
    $user_id = $this->currentUser->getAccountName();
    // Get the current user's roles.
    $roles = $this->currentUser->getRoles();

    // Get the table alias defined by Views.
    $table = $this->ensureMyTable();

    // Build placeholders and argument values for roles.
    $args = [];
    $placeholders = [];
    foreach ($roles as $index => $role) {
      $placeholder = ':role_' . $index;
      $placeholders[] = $placeholder;
      $args[$placeholder] = $role; // Use in the addWhereExpression
    }
    $role_placeholders = implode(',', $placeholders);

    // Build the SQL expression:
    // (assign_type = 'user' AND assign_id = :user_id) OR (assign_type = 'role' AND assign_id IN (<role placeholders>))
    $expression = "(" . $table . ".assign_type = 'user' AND " . $table . ".assign_id = :user_id) OR (" .
      $table . ".assign_type = 'role' AND " . $table . ".assign_id IN ($role_placeholders))";

    // Set the username argument.
    $args[':user_id'] = $user_id;
    $new_where_group_id = $this->query->setWhereGroup();
    $this->query->addWhereExpression($new_where_group_id, $expression, $args);
  }
}
