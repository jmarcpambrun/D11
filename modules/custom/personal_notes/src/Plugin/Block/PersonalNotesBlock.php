<?php

namespace Drupal\personal_notes\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\personal_notes\Entity\PersonalNote;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates a 'personal_notes' Block.
 *
 * @Block(
 *   id = "block_personal_notesblk",
 *   admin_label = @Translation("Personal Notes Block"),
 * )
 */
class PersonalNotesBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  private AccountProxyInterface $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  private RouteMatchInterface $routeMatch;

  /**
   * The constructor for Personal Notes Block object.
   *
   * @param array $configuration
   *   The array configuration.
   * @param string $plugin_id
   *   The id for plugin.
   * @param mixed $plugin_definition
   *   The definition of plugin.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user in site.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountProxyInterface $currentUser, EntityTypeManagerInterface $entityTypeManager, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUser = $currentUser;
    $this->entityTypeManager = $entityTypeManager;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    // User must be logged on to have personal notes.
    if (!$this->currentUser->isAnonymous() && $this->routeMatch->getParameter('user') instanceof UserInterface) {

      $user = $this->routeMatch->getParameter('user');

      $query = $this->entityTypeManager->getStorage('personal_note')
        ->getQuery();

      $query->condition('user', $user->id());
      $query->sort('id', 'desc');

      $ids = $query->execute();
      $entities = PersonalNote::loadMultiple($ids);

      $notes = [];
      foreach ($entities as $note) {
        $notes[] = [
          'title' => $note->getTitle(),
          'note' => $note->getNote(),
          'notenum' => $note->id(),
          'author' => $note->getOwner()->getAccountName(),
          'created' => date("F d, Y", $note->getCreatedTime()),
        ];
      }

      return [
        '#theme' => 'block--personal_notes',
        '#notes' => $notes,
        '#attached' => [
          'library' => [
            'personal_notes/personal_notes',
          ],
        ],
      ];
    }
    return [];
  }

}
