<?php

namespace Drupal\Tests\quiz\Functional;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\quiz\Util\QuizUtil;

/**
 * Test quiz taking behavior.
 *
 * @group Quiz
 */
class QuizTakingTest extends QuizTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'quiz_multichoice',
    'quiz_directions',
    'quiz_truefalse',
  ];

  /**
   * Converts session data from the database into an array of data.
   *
   * This is necessary because PHP's methods write to the $_SESSION
   * superglobal,
   * and we want to work with session data that isn't ours.
   *
   * See
   * https://stackoverflow.com/questions/530761/how-can-i-unserialize-session-data-to-an-arbitrary-variable-in-php
   *
   * @param string $session_data
   *   The serialized session data. (Note this is not the same serialization
   *   format as used by serialize().)
   *
   * @return array
   *   An array of data.
   *
   * @throws \Exception
   */
  protected function unserializePhp(string $session_data): array {
    $return_data = [];
    $offset = 0;

    while ($offset < strlen($session_data)) {
      if (!str_contains(substr($session_data, $offset), "|")) {
        throw new \Exception("Invalid data, remaining: " . substr($session_data, $offset));
      }

      // Extract variable name.
      $pos = strpos($session_data, "|", $offset);
      if ($pos === FALSE) {
        throw new \Exception("Malformed session data.");
      }

      $varname = substr($session_data, $offset, $pos - $offset);
      $offset = $pos + 1;

      // Extract the serialized value.
      $remainingData = substr($session_data, $offset);
      $data = @unserialize($remainingData);

      // Ensure unserialization was successful.
      if ($data === FALSE && $remainingData !== 'b:0;') {
        throw new \Exception("Failed to unserialize data at offset $offset.");
      }

      $return_data[$varname] = $data;

      // Move offset forward by the exact serialized data length.
      $dataLength = strlen(serialize($data));
      $offset += $dataLength;
    }

    return $return_data;
  }

  /**
   * Converts an array of data into a session data string.
   *
   * This is necessary because PHP's methods write to the $_SESSION superglobal,
   * and we want to work with session data that isn't ours.
   *
   * See https://stackoverflow.com/questions/530761/how-can-i-unserialize-session-data-to-an-arbitrary-variable-in-php
   *
   * @param array $data
   *   The session data.
   *
   * @return string
   *   The serialized data. (Note this is not the same serialization format as
   *   used by serialize().)
   */
  protected function serializePhp(array $data): string {
    $return_data = '';
    foreach ($data as $key => $key_data) {
      $return_data .= "$key|" . serialize($key_data);
    }

    return $return_data;
  }

  /**
   * Write data to the given session.
   *
   * This exists because we can't use
   * \Drupal\Core\Session\SessionHandler::write() as that assumes the current
   * session is being written, and will fail within tests as no session exists.
   *
   * @param int $uid
   *   The user ID.
   * @param string $sid
   *   The session ID.
   * @param string $value
   *   The session data. Use serializePhp() to format this.
   */
  protected function writeSession(int $uid, string $sid, string $value): void {
    $fields = [
      'uid' => $uid,
      'hostname' => 'testing',
      'session' => $value,
      'timestamp' => \Drupal::time()->getRequestTime(),
    ];
    $this->container->get('database')->merge('sessions')
      ->keys(['sid' => Crypt::hashBase64($sid)])
      ->fields($fields)
      ->execute();
  }

  /**
   * Test the quiz availability tests.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testQuizAvailability() {
    // Anonymous doesn't have 'access quiz' permissions, so login a user that
    // has that permission.
    $this->drupalLogin($this->user);
    $future = \Drupal::time()->getRequestTime() + 86400;
    $past = \Drupal::time()->getRequestTime() - 86400;

    // Within range.
    $quiz_node_open = $this->createQuiz([
      'quiz_date' => [
        'value' => date('Y-m-d\TH:i:s', $past),
        'end_value' => date('Y-m-d\TH:i:s', $future),
      ],
    ]);
    $this->drupalGet("quiz/{$quiz_node_open->id()}");
    $this->assertSession()->pageTextNotContains($this->t('This @quiz is closed.', ['@quiz' => QuizUtil::getQuizName()]));
    $this->assertSession()->pageTextNotContains($this->t('You are not allowed to take this @quiz.', ['@quiz' => QuizUtil::getQuizName()]));

    // Starts in the future.
    $quiz_node_future = $this->createQuiz([
      'quiz_date' => [
        'value' => date('Y-m-d\TH:i:s', $future),
        'end_value' => date('Y-m-d\TH:i:s', $future + 1),
      ],
    ]);
    $this->drupalGet("quiz/{$quiz_node_future->id()}");
    $this->assertSession()->pageTextContains($this->t('This @quiz is not yet open.', ['@quiz' => QuizUtil::getQuizName()]));
    $this->assertSession()->pageTextNotContains($this->t('You are not allowed to take this @quiz.', ['@quiz' => QuizUtil::getQuizName()]));
    $this->drupalGet("quiz/{$quiz_node_future->id()}/take");
    $this->assertSession()->pageTextContains($this->t('This @quiz is not yet open.', ['@quiz' => QuizUtil::getQuizName()]));

    // Ends in the past.
    $quiz_node_past = $this->createQuiz([
      'quiz_date' => [
        'value' => date('Y-m-d\TH:i:s', $past),
        'end_value' => date('Y-m-d\TH:i:s', $past + 1),
      ],
    ]);
    $this->drupalGet("quiz/{$quiz_node_past->id()}");
    $this->assertSession()->pageTextContains($this->t('This @quiz is closed.', ['@quiz' => QuizUtil::getQuizName()]));
    $this->assertSession()->pageTextNotContains($this->t('You are not allowed to take this @quiz.', ['@quiz' => QuizUtil::getQuizName()]));
    $this->drupalGet("quiz/{$quiz_node_past->id()}/take");
    $this->assertSession()->pageTextContains($this->t('This @quiz is closed.', ['@quiz' => QuizUtil::getQuizName()]));

    // Always available.
    $quiz = $this->createQuiz();
    $this->drupalGet("quiz/{$quiz->id()}");
    $this->assertSession()->pageTextNotContains($this->t('This @quiz is closed.', ['@quiz' => QuizUtil::getQuizName()]));
    $this->assertSession()->pageTextNotContains($this->t('You are not allowed to take this @quiz.', ['@quiz' => QuizUtil::getQuizName()]));
  }

  /**
   * Make sure questions cannot be viewed outside of quizzes.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testViewQuestionsOutsideQuiz() {
    $this->drupalLogin($this->admin);
    $quiz_node = $this->createQuiz();

    $question1 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question1, $quiz_node);

    $this->drupalLogin($this->user);
    $this->drupalGet("quiz-question/{$question1->id()}");
    $this->assertSession()->statusCodeEquals(403);

    $user_with_privs = $this->drupalCreateUser([
      'view any quiz_question',
      'access quiz',
    ]);
    $this->drupalLogin($user_with_privs);
    $this->drupalGet("quiz-question/{$question1->id()}");
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Test allow/restrict changing of answers.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testChangeAnswer() {
    $quiz_node = $this->createQuiz([
      'review_options' => ['question' => ['score' => 'score']],
    ]);

    $question1 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question1, $quiz_node);
    $question2 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question2, $quiz_node);
    $question3 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question3, $quiz_node);

    // Answer incorrectly.
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->drupalGet("quiz/{$quiz_node->id()}/take/1");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => '0',
    ], (string) $this->t('Next'));
    $this->assertSession()->pageTextContains('Score: 0 of 1');

    // Go back and correct the answer.
    $this->drupalGet("quiz/{$quiz_node->id()}/take/1");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => '1',
    ], (string) $this->t('Next'));
    $this->assertSession()->pageTextContains('Score: 1 of 1');

    // Go back and incorrect the answer.
    $this->drupalGet("quiz/{$quiz_node->id()}/take/1");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => '0',
    ], (string) $this->t('Next'));
    $this->assertSession()->pageTextContains('Score: 0 of 1');

    $quiz_node->set('allow_change', 0);
    $quiz_node->save();

    // Check that the answer cannot be changed.
    $this->drupalGet("quiz/{$quiz_node->id()}/take/1");
    $this->assertSession()->fieldDisabled('edit-question-1-answer-1');
    $this->submitForm([], (string) $this->t('Next'));
    $this->assertSession()->pageTextContains('Score: 0 of 1');

    // Check allow change/blank behavior.
    $this->drupalGet("quiz/{$quiz_node->id()}/take/2");
    $this->submitForm([], (string) $this->t('Skip'));
    $this->drupalGet("quiz/{$quiz_node->id()}/take/2");
    $this->assertSession()->fieldDisabled('edit-question-2-answer-1');
    $quiz_node->set('allow_change_blank', 1);
    $quiz_node->save();
    $this->drupalGet("quiz/{$quiz_node->id()}/take/2");
    $this->assertSession()->fieldEnabled('edit-question-2-answer-1');
  }

  /**
   * Test the max attempt message configuration.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testQuizMaxAttemptsMessage() {
    $quiz_node = $this->createQuiz([
      'takes' => 2,
    ]);

    $question1 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question1, $quiz_node);

    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->drupalGet("quiz/{$quiz_node->id()}/take/1");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => '0',
    ], (string) $this->t('Finish'));

    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz_node->id()}");
    $this->assertSession()->pageTextContains('You can only take this Quiz 2 times. You have taken it 1 time.');

    $quiz_node->set('show_attempt_stats', 0)->save();

    $this->drupalGet("quiz/{$quiz_node->id()}");
    $this->assertSession()->pageTextNotContains('You can only take this Quiz 2 times. You have taken it 1 time.');
  }

  /**
   * Test Quiz Max Attempts.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testQuizMaxAttempts() {
    $quiz_node = $this->createQuiz([
      'takes' => 2,
    ]);

    $question1 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question1, $quiz_node);
    $question2 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question2, $quiz_node);

    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->drupalGet("quiz/{$quiz_node->id()}/take/1");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => '0',
    ], (string) $this->t('Next'));
    $this->submitForm([
      "question[{$question2->id()}][answer]" => '0',
    ], (string) $this->t('Finish'));

    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz_node->id()}");
    $this->assertSession()->pageTextContains('You can only take this Quiz 2 times. You have taken it 1 time.');
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => '0',
    ], (string) $this->t('Next'));

    // Make sure we can get back.
    $this->drupalGet("quiz/{$quiz_node->id()}");
    $this->assertSession()->pageTextNotContains('You can only take this Quiz 2 times. You have taken it 1 time.');
    $this->drupalGet("quiz/{$quiz_node->id()}/take/2");
    $this->submitForm([
      "question[{$question2->id()}][answer]" => '0',
    ], (string) $this->t('Finish'));

    // No more attempts.
    $this->drupalGet("quiz/{$quiz_node->id()}");
    $this->assertSession()->pageTextContains('You have already taken this Quiz 2 times. You may not take it again.');
  }

  /**
   * Test that a user can answer a skipped question.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testAnswerSkipped() {
    $this->drupalLogin($this->admin);
    $quiz_node = $this->createQuiz([
      'allow_skipping' => 1,
      'allow_jumping' => 1,
    ]);

    // 2 questions.
    $question1 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question1, $quiz_node);
    $question2 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question2, $quiz_node);

    $this->drupalLogin($this->user);

    // Leave a question blank.
    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->drupalGet("quiz/{$quiz_node->id()}/take/1");
    $this->submitForm([], (string) $this->t('Skip'));

    // Fill out the blank question.
    $this->drupalGet("quiz/{$quiz_node->id()}/take/1");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => '1',
    ], (string) $this->t('Next'));
    $this->submitForm([
      "question[{$question2->id()}][answer]" => '1',
    ], (string) $this->t('Finish'));

    $this->assertSession()->pageTextContains("Your score: 100%");
  }

  /**
   * Make sure a user can answer or skip an old question's revision.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testAnswerOnOldQuizRevisioning() {
    $this->drupalLogin($this->admin);

    $question1 = $this->createQuestion([
      'title' => 'Q 1',
      'body' => 'Q 1',
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $quiz_node = $this->linkQuestionToQuiz($question1);

    $question2 = $this->createQuestion([
      'title' => 'Q 2',
      'body' => 'Q 2',
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question2, $quiz_node);

    $question1->revision = TRUE;
    $question1->save();

    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz_node->id()}/take");

    // Leave a question blank.
    $this->drupalGet("quiz/{$quiz_node->id()}/take/1");
    $this->submitForm([], (string) $this->t('Skip'));

    // Submit the question.
    $this->drupalGet("quiz/{$quiz_node->id()}/take/1");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => '1',
    ], (string) $this->t('Next'));
  }

  /**
   * Verify non gradable questions are excluded from counts.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testQuestionCount() {
    $quiz_node = $this->createQuiz([
      'review_options' => ['question' => ['score' => 'score']],
    ]);

    $question1 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question1, $quiz_node);
    $question2 = $this->createQuestion([
      'type' => 'directions',
    ]);
    $this->linkQuestionToQuiz($question2, $quiz_node);
    $question3 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question3, $quiz_node);
    $question4 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question4, $quiz_node);
    $question5 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question5, $quiz_node);

    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz_node->id()}");

    // @todo check the pager, this isn't reliable
    $this->assertSession()->pageTextContains("4");
  }

  /**
   * Test the mark doubtful functionality.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testMarkDoubtful() {
    $this->drupalLogin($this->admin);
    $quiz_node = $this->createQuiz([
      'allow_skipping' => 1,
      'allow_jumping' => 1,
      'mark_doubtful' => 1,
    ]);

    // 2 questions.
    $question1 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question1, $quiz_node);
    $question2 = $this->createQuestion([
      'type' => 'directions',
    ]);
    $this->linkQuestionToQuiz($question2, $quiz_node);

    // Take the quiz.
    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz_node->id()}/take");

    // Ensure it is on truefalse.
    $this->drupalGet("quiz/{$quiz_node->id()}/take/1");
    $this->assertSession()->fieldExists("edit-question-{$question1->id()}-is-doubtful");

    $this->submitForm([
      "question[{$question1->id()}][answer]" => '1',
      "question[{$question1->id()}][is_doubtful]" => '1',
    ], (string) $this->t('Next'));
    // Go back and verify it was saved.
    $this->drupalGet("quiz/{$quiz_node->id()}/take/1");
    $this->assertSession()->checkboxChecked("edit-question-{$question1->id()}-is-doubtful");

    // Ensure it is not on quiz directions.
    $this->drupalGet("quiz/{$quiz_node->id()}/take/2");
    $this->assertSession()->fieldNotExists("edit-question-{$question2->id()}-is-doubtful");
  }

  /**
   * Test if necessary session data disappears during a quiz.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Exception
   */
  public function testQuestionSessionLossRedirect(): void {
    $this->drupalLogin($this->admin);
    $quiz_node = $this->createQuiz();

    // 2 questions.
    $question1 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question1, $quiz_node);
    $question2 = $this->createQuestion([
      'type' => 'truefalse',
      'truefalse_correct' => 1,
    ]);
    $this->linkQuestionToQuiz($question2, $quiz_node);

    $this->drupalLogin($this->user);

    $this->drupalGet("quiz/{$quiz_node->id()}/take");
    $this->assertSession()->addressEquals("quiz/{$quiz_node->id()}/take/1");
    $this->submitForm([
      "question[{$question1->id()}][answer]" => '1',
    ], (string) $this->t('Next'));
    // Go back to the previous question.
    $this->drupalGet("quiz/{$quiz_node->id()}/take/1");
    $this->assertSession()->addressEquals("quiz/{$quiz_node->id()}/take/1");
    // Remove the session data.
    $sid = $this->getSession()->getCookie($this->getSessionName());
    $session_data = $this->container->get('session_handler.storage')->read($sid);
    $session_data = $this->unserializePhp($session_data);
    // Unsetting $session_data['_sf2_attributes']['quiz'][1]['current_question']
    // has no effect. Whereas this should trigger 'access denied' or a redirect.
    unset($session_data['_sf2_attributes']['quiz'][1]['result_id']);
    // Write the removed session data back to the database.
    $this->writeSession($this->user->id(), $sid, $this->serializePhp($session_data));
    // Try to go to the next question.
    $this->submitForm([
      "question[{$question1->id()}][answer]" => '1',
    ], (string) $this->t('Next'));
    // Find if the user is redirected to quiz/quiz id.
    $this->assertSession()->addressEquals("quiz/{$quiz_node->id()}");
    // Confirm that this is not a 403.
    $this->assertSession()->pageTextNotContains("Access denied");

  }

}
