<?php

namespace Drupal\webform_quiz\Model;

/**
 * Represents a data model for a webform submission's quiz results.
 */
class WebformQuizResults {

  /**
   * {@inheritdoc}
   */
  protected $numberOfPointsReceived;

  /**
   * {@inheritdoc}
   */
  protected $totalNumberOfPoints;

  /**
   * {@inheritdoc}
   */
  protected $score;

  /**
   * {@inheritdoc}
   */
  public function __construct($numberOfPointsReceived, $totalNumberOfPoints, $score) {
    $this->numberOfPointsReceived = $numberOfPointsReceived;
    $this->totalNumberOfPoints = $totalNumberOfPoints;
    $this->score = $score;
  }

  /**
   * {@inheritdoc}
   */
  public static function create($data) {
    return new static(
      $data['webform_quiz_number_of_points_received'],
      $data['webform_quiz_total_number_of_points'],
      $data['webform_quiz_score']
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getNumberOfPointsReceived() {
    return $this->numberOfPointsReceived;
  }

  /**
   * {@inheritdoc}
   */
  public function getTotalNumberOfPoints() {
    return $this->totalNumberOfPoints;
  }

  /**
   * {@inheritdoc}
   */
  public function getScore() {
    return $this->score;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray() {
    return [
      'webform_quiz_number_of_points_received' => $this->getNumberOfPointsReceived(),
      'webform_quiz_total_number_of_points' => $this->getTotalNumberOfPoints(),
      'webform_quiz_score' => $this->getScore(),
    ];
  }

}
