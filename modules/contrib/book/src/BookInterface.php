<?php

namespace Drupal\book;

/**
 * Interface for entities that can be part of a book outline.
 */
interface BookInterface {

  /**
   * Get the book data.
   *
   * @return array
   *   The book data array.
   */
  public function getBook(): array;

  /**
   * Set the book.
   *
   * @param array $value
   *   The book data array.
   *
   * @return $this
   *   The current instance.
   */
  public function setBook(array $value): static;

  /**
   * Set a specific book key.
   *
   * @param string $key
   *   Book key.
   * @param mixed $value
   *   Value to set the book key to.
   *
   * @return $this
   *   The current instance.
   */
  public function setBookKey(string $key, mixed $value): static;

}
