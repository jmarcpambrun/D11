<?php

namespace Drupal\quiz\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Url;
use Drupal\quiz\Entity\Quiz;
use Drupal\quiz\Entity\QuizQuestionRelationship;
use Drupal\quiz\Util\QuizUtil;
use Symfony\Component\DependencyInjection\ContainerInterface;
use function count;
use function quiz_get_question_types;

/**
 * Form to manage questions in a quiz.
 */
class QuizQuestionsForm extends FormBase {

  use MessengerTrait;

  /**
   * QuizQuestionAnsweringForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $destination
   *   The redirect destination helper.
   * @param \Drupal\Core\Database\Connection $connection
   *   The current active database's master connection.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer to use.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TimeInterface $time,
    protected RedirectDestinationInterface $destination,
    protected Connection $connection,
    protected RendererInterface $renderer,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('datetime.time'),
      $container->get('redirect.destination'),
      $container->get('database'),
      $container->get('renderer'),
    );
  }

  /**
   * Fields for creating new questions are added to the quiz_questions_form.
   *
   * @param array $form
   *   FAPI form(array).
   * @param array $types
   *   All the question types(array).
   * @param \Drupal\quiz\Entity\Quiz $quiz
   *   The quiz node.
   */
  public function quizAddFieldsCreatingQuestions(array &$form, array &$types, Quiz $quiz): void {
    // Display links to create other questions.
    $form['additional_questions'] = [
      '#type' => 'details',
      '#title' => $this->t('Create new question'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];

    $create_question = FALSE;

    $entity_manager = $this->entityTypeManager;
    $access_handler = $entity_manager->getAccessControlHandler('quiz_question');

    foreach ($types as $type => $info) {

      $options = [
        'query' => [
          'qid' => $quiz->id(),
          'vid' => $quiz->getRevisionId(),
        ],
        'attributes' => [
          'class' => 'use-ajax',
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode(['width' => 800]),
        ],
      ];

      $access = $access_handler->createAccess($type);
      if ($access) {
        $create_question = TRUE;
      }
      $url = Url::fromRoute('entity.quiz_question.add_form', ['quiz_question_type' => $type], $options);
      $form['additional_questions'][$type] = [
        '#markup' => '<div class="add-questions">' .
        Link::fromTextAndUrl($info['label'], $url)->toString() . '</div>',
        '#access' => $access,
      ];
    }
    if (!$create_question) {
      $form['additional_questions']['create'] = [
        '#type' => 'markup',
        '#markup' => $this->t('You have not enabled any question type module or no has permission been given to create any question.'),
        // @todo revisit UI text
      ];
    }
  }

  /**
   * Handles "manage questions" tab.
   *
   * Displays form which allows questions to be assigned to the given quiz.
   *
   * This function is not used if the question assignment type "categorized
   * random questions" is chosen.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $types = quiz_get_question_types();
    $quiz = $form_state->getBuildInfo()['args'][0];
    $this->quizAddFieldsCreatingQuestions($form, $types, $quiz);

    $header = ['Question', 'Type', 'Max score', 'Auto max score'];
    if ($quiz->get('randomization')->getString() == 2) {
      $header[] = 'Required';
    }
    $header = array_merge($header, [
      'Revision',
      'Operations',
      'Weight',
      'Parent',
    ]);

    // Display questions in this quiz.
    $form['question_list'] = [
      '#type' => 'table',
      '#title' => $this->t('Questions in this @quiz', ['@quiz' => QuizUtil::getQuizName()]),
      '#header' => $header,
      '#empty' => $this->t('There are currently no questions in this @quiz. Assign existing questions by using the question browser below. You can also use the links above to create new questions.', ['@quiz' => QuizUtil::getQuizName()]),
      '#tabledrag' => [
        [
          'action' => 'match',
          'relationship' => 'parent',
          'group' => 'qqr-pid',
          'source' => 'qqr-id',
          'hidden' => TRUE,
          'limit' => 1,
        ],
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'table-sort-weight',
        ],
      ],
    ];

    // @todo deal with $include_random.
    $all_questions = $quiz->getQuestions();

    uasort($all_questions, [static::class, 'sortQuestions']);
    $questions = [];
    foreach ($all_questions as $qqr_id => $question) {
      if (!$question->get('qqr_pid')->getString()) {
        // This is a parent question.
        $questions[$qqr_id] = $question;
        $questions += $this->getSubQuestions($question, $all_questions);
      }
    }

    // We add the questions to the form array.
    $this->quizAddQuestionToForm($form, $questions, $quiz, $types);

    // @todo Show the number of questions in the table header.
    $always_count = isset($form['question_list']['titles']) ?
      count($form['question_list']['titles']) : 0;
    // $form['question_list']['#title'] .= ' (' . $always_count . ')';
    // Timestamp is needed to avoid multiple users editing the same quiz at the
    // same time.
    $form['timestamp'] = [
      '#type' => 'hidden',
      '#default_value' => $this->time->getRequestTime(),
    ];

    $form['actions']['#type'] = 'actions';

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    // Give the user the option to create a new revision of the quiz.
    $this->quizAddRevisionCheckbox($form, $quiz);

    return $form;
  }

  /**
   * Set the sub-questions.
   *
   * @return array
   *   of QuizQuestion
   */
  public function getSubQuestions($root_question, $all_questions): array {
    $append = [];
    foreach ($all_questions as $sub_question) {
      if ($root_question->id() == $sub_question->get('qqr_pid')->getString()) {
        // Question is a leaf of this parent.
        $append[$sub_question->id()] = $sub_question;
      }
    }
    return $append;
  }

  /**
   * Entity type sorter for quiz questions.
   */
  public function sortQuestions($a, $b): int {
    $aw = $a->get('weight')->getString();
    $bw = $b->get('weight')->getString();
    if ($aw == $bw) {
      return 0;
    }
    return ($aw < $bw) ? -1 : 1;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'quiz_questions_form';
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $question_list = $form_state->getValue('question_list');
    // Return if question list is empty.
    if (!$question_list) {
      return;
    }
    foreach ($question_list as $index => $row) {
      foreach ($row as $name => $value) {
        if ($name == 'max_score' && (!isset($value) || $value === '')) {
          $form_state->setError($form['question_list'][$index]['max_score'], $this->t('Max score cannot be empty.'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $question_list = $form_state->getValue('question_list');
    // Return if question list is empty.
    if (!$question_list) {
      return;
    }
    foreach ($question_list as $qqr_id => $row) {
      $qqr = QuizQuestionRelationship::load($qqr_id);
      foreach ($row as $name => $value) {
        if ($name == 'qqr_pid' && empty($value)) {
          $value = NULL;
        }

        $qqr->set($name, $value);
      }
      $qqr->save();
    }

    $this->messenger()->addMessage($this->t('Questions updated successfully.'));
  }

  /**
   * Adds checkbox for creating new revision.
   *
   * Checks it by default if answers exists.
   *
   * @param array $form
   *   FAPI form(array).
   * @param \Drupal\quiz\Entity\Quiz $quiz
   *   Quiz node(object).
   */
  public function quizAddRevisionCheckbox(array &$form, Quiz $quiz): void {
    if ($quiz->hasAttempts()) {
      $results_url = Url::fromRoute('view.quiz_results.list', ['quiz' => $quiz->id()])
        ->toString();
      $quiz_url = Url::fromRoute('entity.quiz.edit_form', ['quiz' => $quiz->id()], [
        'query' => $this->destination->getAsArray(),
      ])->toString();
      $form['revision_help'] = [
        '#markup' => $this->t('This quiz has been answered. To make changes to the quiz you must either <a href="@results_url">delete all results</a> or <a href="@quiz_url">create a new revision</a>. This includes deleting any questions.', [
          '@results_url' => $results_url,
          '@quiz_url' => $quiz_url,
        ]),
      ];
      $form['actions']['submit']['#access'] = FALSE;
    }
  }

  /**
   * Adds the questions in the $questions array to the form.
   *
   * @param array $form
   *   FAPI form(array).
   * @param \Drupal\Quiz\Entity\QuizQuestionRelationship[] $questions
   *   The questions to be added to the question list(array).
   * @param \Drupal\quiz\Entity\Quiz $quiz
   *   The quiz.
   * @param array $question_types
   *   Array of all available question types.
   *
   * @todo Not bringing in revision data yet.
   */
  public function quizAddQuestionToForm(array &$form, array &$questions, Quiz &$quiz, array &$question_types): void {
    foreach ($questions as $id => $question_relationship) {
      $question_vid = $question_relationship->get('question_vid')->getString();

      /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
      $storage = $this->entityTypeManager->getStorage('quiz_question');
      /** @var \Drupal\quiz\Entity\QuizQuestion $quiz_question */
      $quiz_question = $storage->loadRevision($question_vid);

      if (!$quiz_question) {
        // Question no longer exists.
        // @see https://www.drupal.org/project/quiz/issues/2923829
        continue;
      }

      $table = &$form['question_list'];

      $view_url = Url::fromRoute('entity.quiz_question.canonical', ['quiz_question' => $quiz_question->id()], [
        'attributes' => [
          'class' => 'use-ajax',
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode(['width' => 800]),
        ],
        'query' => $this->destination->getAsArray(),
      ]);

      $edit_url = Url::fromRoute('entity.quiz_question.edit_form', ['quiz_question' => $quiz_question->id()], [
        'attributes' => [
          'class' => 'use-ajax',
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode(['width' => 800]),
        ],
        'query' => $this->destination->getAsArray(),
      ]);

      $remove_url = Url::fromRoute('entity.quiz_question_relationship.delete_form', ['quiz_question_relationship' => $question_relationship->id()], [
        'attributes' => [
          'class' => 'use-ajax',
          'data-dialog-type' => 'modal',
        ],
        'query' => $this->destination->getAsArray(),
      ]);

      if ($quiz_question->access('view')) {
        $question_titles = [
          '#markup' => Link::fromTextAndUrl($quiz_question->get('title')
            ->getString(), $view_url)->toString(),
        ];
      }
      else {
        $question_titles = [
          '#plain_text' => $quiz_question->get('title')
            ->getString(),
        ];
      }

      $table[$id]['#attributes']['class'][] = 'draggable';

      if ($quiz_question->bundle() != 'page') {
        $table[$id]['#attributes']['class'][] = 'tabledrag-leaf';
      }

      $table[$id]['title'] = $question_titles;

      if ($question_relationship->get('qqr_pid')->getString()) {
        $indentation = [
          '#theme' => 'indentation',
          '#size' => 1,
        ];
        $table[$id]['title']['#prefix'] = $this->renderer->render($indentation);
      }

      $table[$id]['type'] = [
        '#markup' => $quiz_question->bundle(),
      ];

      // Toggle the max score input based on the auto max score checkbox
      // Hide for ungraded questions (directions, pages, etc.)
      $table[$id]['max_score'] = [
        '#type' => $quiz_question->isGraded() ? 'textfield' : 'hidden',
        '#size' => 2,
        '#disabled' => (bool) $question_relationship->get('auto_update_max_score')->getString(),
        '#default_value' => $question_relationship->get('max_score')->getString(),
        '#states' => [
          'disabled' => [
            "#edit-question-list-$id-auto-update-max-score" => ['checked' => TRUE],
          ],
        ],
      ];

      $table[$id]['auto_update_max_score'] = [
        '#type' => $quiz_question->isGraded() ? 'checkbox' : 'hidden',
        '#default_value' => $question_relationship->get('auto_update_max_score')
          ->getString() ?
        $question_relationship->get('auto_update_max_score')->getString() : 0,
      ];

      // Add checkboxes to mark compulsory questions for randomized quizzes.
      if ($quiz->get('randomization')->getString() == 2) {
        $table[$id]['question_status'] = [
          '#type' => 'checkbox',
          '#default_value' => $question_relationship->get('question_status')
            ->getString(),
        ];
      }

      $entity_manager = $this->entityTypeManager;
      $access_handler = $entity_manager->getAccessControlHandler('quiz_question');

      // Add a checkbox to update to the latest revision of the question.
      $latest_quiz_question = $this->entityTypeManager
        ->getStorage('quiz_question')
        ->load($quiz_question->id());
      if ($question_relationship->get('question_vid')->value ==
        $latest_quiz_question->getRevisionId()) {
        $update_cell = [
          '#markup' => $this->t('<em>Up to date</em>'),
        ];
      }
      else {
        $update_cell = [
          '#type' => 'checkbox',
          '#return_value' => $latest_quiz_question->getRevisionId(),
          '#title' => $this->t('Update to latest'),
        ];
      }
      $table[$id]['question_vid'] = $update_cell;

      $table[$id]['operations'] = [
        '#type' => 'operations',
        '#links' => [
          [
            'title' => $this->t('Edit'),
            'url' => $edit_url,
          ],
        ],
      ];

      if (!$quiz->hasAttempts()) {
        $table[$id]['operations']['#links'][] = [
          'title' => $this->t('Remove'),
          'url' => $remove_url,
        ];
      }

      $table[$id]['#weight'] = (int) $question_relationship->get('weight')
        ->getString();
      $table[$id]['weight'] = [
        '#title_display' => 'invisible',
        '#title' => $this
          ->t('Weight for ID @id', [
            '@id' => $id,
          ]),
        '#type' => 'number',
        '#default_value' => (int) $question_relationship->get('weight')
          ->getString(),
        '#attributes' => [
          'class' => [
            'table-sort-weight',
          ],
        ],
      ];

      $table[$id]['parent']['qqr_id'] = [
        '#title' => $this->t('Relationship ID'),
        '#type' => 'hidden',
        '#default_value' => $question_relationship->get('qqr_id')->getString(),
        '#attributes' => [
          'class' => [
            'qqr-id',
          ],
        ],
        '#parents' => [
          'question_list',
          $id,
          'qqr_id',
        ],
      ];

      $table[$id]['parent']['qqr_pid'] = [
        '#title' => $this->t('Parent ID'),
        '#title_display' => 'invisible',
        '#type' => 'number',
        '#size' => 3,
        '#min' => 0,
        '#default_value' => $question_relationship->get('qqr_pid')->getString(),
        '#attributes' => [
          'class' => [
            'qqr-pid',
          ],
        ],
        '#parents' => [
          'question_list',
          $id,
          'qqr_pid',
        ],
      ];
    }
  }

}
