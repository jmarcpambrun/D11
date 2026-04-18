<?php

namespace Drupal\book\Entity\Node;

use Drupal\book\BookInterface;
use Drupal\node\Entity\Node;

/**
 * Defines a custom Node Type class with an additional custom property.
 */
class Book extends Node implements BookInterface {

  /**
   * Book object.
   *
   * @var array
   */
  protected array $book;

  /**
   * Get the book.
   *
   * @return array
   *   Retrieve the book
   */
  public function getBook(): array {
    if (!isset($this->book)) {
      $this->book = [];
    }
    return $this->book;
  }

  /**
   * Set the book property.
   *
   * @param array $value
   *   Set the book property.
   *
   * @return $this
   *   The current instance for method chaining.
   */
  public function setBook(array $value): static {
    $this->book = $value;
    return $this;
  }

  /**
   * Set a specific book key.
   *
   * @param string $key
   *   Book key.
   * @param mixed $value
   *   Value to set the book key to.
   *
   * @return $this
   *   The current instance for method chaining.
   */
  public function setBookKey(string $key, mixed $value): static {
    $this->book[$key] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function __set($name, $value) {
    if ($name === 'book') {
      $this->setBook($value);
    }
    parent::__set($name, $value);
  }

}
