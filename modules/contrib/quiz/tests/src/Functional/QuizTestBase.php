<?php

namespace Drupal\Tests\quiz\Functional;

use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\Tests\BrowserTestBase;
use Drupal\quiz\Entity\Quiz;
use Drupal\quiz\Entity\QuizQuestion;
use Drupal\user\UserInterface;
use function quiz_get_feedback_options;

/**
 * Base test class for Quiz questions.
 */
abstract class QuizTestBase extends BrowserTestBase {

  /**
   * Set to TRUE to strict check all configuration saved.
   *
   * @var bool
   * @see ConfigSchemaChecker
   */
  protected $strictConfigSchema = FALSE;

  /**
   * The theme to install as the default for testing.
   *
   * Defaults to the profile's default theme, if it specifies any.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'editor',
    'ckeditor5',
    'filter',
    'quiz',
    'quiz_test',
  ];

  /**
   * Normal User account.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $user;

  /**
   * Administration user account.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $admin;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setUp($admin_permissions = [], $user_permissions = []): void {
    parent::setUp();

    FilterFormat::create([
      'format' => 'basic_html',
      'name' => 'Basic HTML',
    ])->save();
    Editor::create([
      'format' => 'basic_html',
      'editor' => 'ckeditor5',
    ])->save();
    Editor::create([
      'format' => 'restricted_html',
      'editor' => 'ckeditor5',
    ])->save();
    FilterFormat::create([
      'format' => 'full_html',
      'name' => 'Full HTML',
    ])->save();
    Editor::create([
      'format' => 'full_html',
      'editor' => 'ckeditor5',
    ])->save();

    $admin_permissions[] = 'administer quiz configuration';
    $admin_permissions[] = 'administer quiz_question';
    $admin_permissions[] = 'administer quiz_result_answer';
    $admin_permissions[] = 'administer quiz_result';
    $admin_permissions[] = 'administer quiz';
    // Unevaluated results view is tied to this.
    $admin_permissions[] = 'update any quiz_result';
    $admin_permissions[] = 'use text format basic_html';
    $admin_permissions[] = 'use text format restricted_html';
    $admin_permissions[] = 'use text format full_html';

    $user_permissions[] = 'use text format basic_html';
    $user_permissions[] = 'use text format restricted_html';
    $user_permissions[] = 'access quiz';
    $user_permissions[] = 'view any quiz';
    $user_permissions[] = 'view own quiz_result';

    $this->admin = $this->drupalCreateUser(array_unique($admin_permissions));
    $this->user = $this->drupalCreateUser(array_unique($user_permissions));
  }

  /**
   * Link a question to a new or provided quiz.
   *
   * @param \Drupal\quiz\Entity\QuizQuestion $quiz_question
   *   A quiz question.
   * @param \Drupal\quiz\Entity\Quiz|null $quiz
   *   A Quiz, or NULL to create one.
   *
   * @return \Drupal\quiz\Entity\Quiz|null
   *   The quiz.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function linkQuestionToQuiz(QuizQuestion $quiz_question, ?Quiz $quiz = NULL): ?Quiz {
    static $weight = 0;

    if (!$quiz) {
      // Create a new quiz with defaults.
      $quiz = $this->createQuiz();
    }

    // Test helper - weight questions one after another.
    $quiz->addQuestion($quiz_question)->set('weight', $weight)->save();
    $weight++;

    return $quiz;
  }

  /**
   * Create a quiz with all end feedback settings enabled by default.
   *
   * @return \Drupal\quiz\Entity\Quiz
   *   The newly created Quiz object.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createQuiz($settings = []): Quiz {
    $settings += [
      'title' => 'Quiz',
      'body' => 'Quiz description',
      'type' => 'quiz',
      'result_type' => 'quiz_result',
      'review_options' => ['end' => array_combine(array_keys(quiz_get_feedback_options()), array_keys(quiz_get_feedback_options()))],
    ];
    $quiz = Quiz::create($settings);
    $quiz->save();
    return $quiz;
  }

  /**
   * Create a Quiz Question.
   *
   * @param array $settings
   *   The question settings.
   *
   * @return \Drupal\quiz\Entity\QuizQuestion
   *   The newly created Quiz Question object.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createQuestion(array $settings = []): QuizQuestion {
    $question = QuizQuestion::create($settings);
    $question->save();
    return $question;
  }

}
