<?php

namespace Drupal\book\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\book\BookManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays the book outline form.
 *
 * @internal
 */
class BookOutlineForm extends ContentEntityForm {

  /**
   * The book being displayed.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $entity;

  /**
   * Constructs a BookOutlineForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\book\BookManagerInterface $bookManager
   *   The BookManager service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    EntityRepositoryInterface $entity_repository,
    protected BookManagerInterface $bookManager,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    TimeInterface $time,
  ) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity.repository'),
      $container->get('book.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseFormId(): ?string {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form['#title'] = $this->entity->label();

    if (empty($this->entity->getBook())) {
      // The node is not part of any book yet - set default options.
      $this->entity->setBook($this->bookManager->getLinkDefaults($this->entity->id()));
    }
    else {
      $this->entity->setBookKey('original_bid', $this->entity->getBook()['bid']);
    }

    // Find the depth limit for the parent select.
    if (!isset($book['parent_depth_limit'])) {
      $this->entity->setBookKey('parent_depth_limit', $this->bookManager->getParentDepthLimit($this->entity->getBook()));
    }

    return $this->bookManager->addFormElements($form, $form_state, $this->entity, $this->currentUser(), FALSE);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditedFieldNames(FormStateInterface $form_state) {
    // None of the regular node fields will be directly edited by this form, so
    // allow field violations to be filtered out.
    // Please note that other entity level violations and violations flagging
    // pseudo-fields such as BookOutlineConstraint aren't affected by this and
    // will still be flagged as normal since "book" isn't a real Drupal field.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state): array {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->entity->getBook()['original_bid'] ? $this->t('Update book outline') : $this->t('Add to book outline');
    $actions['delete']['#title'] = $this->t('Remove from book outline');
    $actions['delete']['#url'] = new Url('entity.node.book_remove_form', ['node' => $this->entity->getBook()['nid']]);
    $actions['delete']['#access'] = $this->bookManager->checkNodeIsRemovable($this->entity);
    return $actions;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $form_state->setRedirect(
      'entity.node.canonical',
      ['node' => $this->entity->id()]
    );
    $book_link = $form_state->getValue('book');
    if (!$book_link['bid']) {
      $this->messenger()->addStatus($this->t('No changes were made'));
      return 1;
    }

    $this->entity->setBook($book_link);
    if ($this->bookManager->updateOutline($this->entity)) {
      if (isset($book_link['parent_mismatch']) && $book_link['parent_mismatch']) {
        // This will usually only happen when JS is disabled.
        $this->messenger()->addStatus($this->t('The post has been added to the selected book. You may now position it relative to other pages.'));
        $form_state->setRedirectUrl($this->entity->toUrl('book-outline-form'));
      }
      else {
        $this->messenger()->addStatus($this->t('The book outline has been updated.'));
      }
    }
    else {
      $this->messenger()->addError($this->t('There was an error adding the post to the book.'));
    }
    return 1;
  }

}
