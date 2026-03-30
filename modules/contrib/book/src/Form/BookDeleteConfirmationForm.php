<?php

namespace Drupal\book\Form;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Statement\FetchAs;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Book deletion confirmation form.
 */
class BookDeleteConfirmationForm extends ConfirmFormBase {
  /**
   * The book title.
   *
   * @var string
   */
  protected string $bookTitle;

  /**
   * The book id.
   *
   * @var int
   */
  protected int $bookId;

  /**
   * Constructs a new BookAdminEditForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Connection $connection,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'book_delete_confirmation_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to delete the entire %bookTitle?', ['%bookTitle' => $this->bookTitle]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('book.admin');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): TranslatableMarkup {
    return $this->t("This action cannot be undone. The Book and it's children will be removed, are you sure?");
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $book_id = NULL): array {
    $this->bookId = $book_id;
    $node = Node::load($book_id);
    $this->bookTitle = $node->getTitle();
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try {
      $bookChildrenNids = $this->getBookChildrenNode($this->bookId);
      $storage = $this->entityTypeManager->getStorage('node');
      foreach (array_chunk($bookChildrenNids, 50) as $chunk) {
        $nodes = $storage->loadMultiple($chunk);
        $storage->delete($nodes);
      }
      $this->messenger()->addMessage($this->t('The  %bookTitle has been deleted.', ['%bookTitle' => $this->bookTitle]));
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException | EntityStorageException) {
      // @todo log error.
    }
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

  /**
   * Get book child Node IDs.
   *
   * @param int $bookId
   *   The book ID.
   *
   * @return array
   *   Node IDs of book.
   */
  private function getBookChildrenNode(int $bookId): array {
    $query = $this->connection->select('book', "b");
    $query->addField("b", "nid");
    $query->condition('bid', $bookId);
    $query->orderBy("nid", "DESC");

    return $query->execute()->fetchAll(FetchAs::Column, 0);
  }

}
