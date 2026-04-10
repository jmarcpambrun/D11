<?php

namespace Drupal\webform_quiz;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\WebformSubmissionInterface;

use Doctrine\Common\Annotations\AnnotationReader;
use Drupal\webform\Plugin\WebformElement\OptionsBase;
use Drupal\webform\Utility\WebformOptionsHelper;

/**
 * Quiz Webform Element Base trait class.
 */
trait QuizWebformElementBase {

  /**
   * {@inheritdoc}
   */
  protected static function getParentClassPluginId() {
    $element_manager = \Drupal::service('plugin.manager.webform.element');
    $definitions = $element_manager->getDefinitions();
    
    foreach ($definitions as $plugin_id => $definition) {
      if ($definition['class'] == get_parent_class(self::class)) {
        return $plugin_id;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isStrictCompare(array $element, ?array $caws = null) {
    switch (@$element['#webform_quiz_element_mode']) {
      case QuizWebformElementBaseInterface::WEBFORM_ELEMENT_MODE_QUIZ:
        //all correct answers must be picked and no mistaken answers
        //if more than one correct answer, element #webform_multiple must be true
        $caws = $caws ?? $this->getCorrectAnswersWithScores($element);
        if(count($caws) > 1 && !$this->supportsMultiple($element))
          throw new \RuntimeException('Element with multiple correct answers must allow multiple answers.');

        return TRUE;
        break;
      case QuizWebformElementBaseInterface::WEBFORM_ELEMENT_MODE_SURVEY:
        return FALSE;
        break;
      
      default:
        return TRUE;
        break;
    }
    return TRUE;
  }

  /**
   * if to render the correct answer field in the form.
   * For select, checkboxes, radios, etc ... it is not rendered,
   * as its managed from options form, but field correct_answer
   * still kept and populated in background for polymorphism
   *
   * @return void
   */
  public function correctAnswerFieldVisible() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineDefaultProperties() {
    $properties = [
      'correct_answer' => [],
      'correct_answer_description' => '',
    ] + parent::defineDefaultProperties() + parent::defineDefaultMultipleProperties();
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultProperties() {
    return [
          // Form display.
      'correct_answer' => [],
      'correct_answer_description' => '',
    ] + parent::getDefaultProperties();
  }

  /**
   * {@inheritdoc}
   */
  public function traitBuildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $storage = $form_state->getStorage();
    $element_properties = $storage['element_properties'];

    // Modify the existing element description to distinguish it from the
    // correct answer description.
    $form['element_description']['description']['#title'] = $this->t('Element Description');

    // Add a WYSIWYG for the correct answer description.
    $form['element_description']['correct_answer_description'] = [
      '#type' => 'webform_html_editor',
      '#title' => $this->t('Correct Answer Description'),
      '#description' => $this->t('A description of why the correct answer is correct.'),
      '#default_value' => $element_properties['correct_answer_description'] ?? '',
      '#weight' => 0,
    ];

    $form['validation']['webform_quiz_number_of_points'] = [
      '#type' => 'number',
      '#title' => t('Number of points'),
      '#description' => t('Enter the number of points for correct answer. Quiz mode only.'),
      '#default_value' => $element_properties['webform_quiz_number_of_points'] ?? 1,
      '#states' => [
        'visible' => [
          ':input[name="properties[options][webform_quiz_element_mode]"]' => ['value' => 'quiz'],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function prepare(array &$element, WebformSubmissionInterface $webform_submission = NULL, $type = NULL, $call_parent = TRUE) {
    $type = $type ?? static::getParentClassPluginId();
    if (!empty($type)) {
      $element['#type'] = $type;
    }

    if(!empty($element['#options'])) {
      $element['#options_raw'] = $element['#options'];
      foreach($element['#options'] as $key => &$value)  {
        $decoded = json_decode($value, true);
        if($decoded) {
          $value = $decoded['text'] . ($decoded['description'] ? WebformOptionsHelper::DESCRIPTION_DELIMITER . $decoded['description'] : '');
        }
      }
    }

    $element['#attributes']['quiz-element'] = '';

    $correct_answer_description_wrapper = [
      '#type' => 'container',
      '#attributes' => ['id' => 'cad-wrapper-' . $element['#webform_id']],
    ];
    $element['#suffix'] = \Drupal::service('renderer')->render($correct_answer_description_wrapper);

    if (!empty($element['#correct_answer_description'])) {
      $element['#attributes']['quiz-has-cad'] = '';

      $element['#ajax'] = [
        'callback' => 'Drupal\webform_quiz\Plugin\WebformElement\WebformQuizRadios::ajaxShowCorrectAnswerDescription',
        'event' => 'show_answer',
        'progress' => [
          'type' => 'throbber',
          'message' => NULL,
        ],
      ];
    }

    if ($call_parent) {
      parent::prepare($element, $webform_submission);
    }

  }

  /**
   * Ajax handler to show the correct description when user clicks an option.
   *
   * @param array $form
   *   Submited form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   FormState object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AjaxResponse.
   */
  public static function ajaxShowCorrectAnswerDescription(array &$form, FormStateInterface $form_state) {
    $ajax_response = new AjaxResponse();

    $triggering_element = $form_state->getTriggeringElement();
    $element_key = $triggering_element['#name'];

    /** @var \Drupal\webform\WebformSubmissionForm $form_obj */
    $form_obj = $form_state->getFormObject();
    $webform = $form_obj->getWebform();
    $element = $webform->getElement($element_key);
    $count = 0;
    while (empty($element) && $count < count($triggering_element['#parents'])) {
      $element = $webform->getElement($triggering_element['#parents'][$count]);
      $count++;
    }
    $description = $element['#correct_answer_description'] ?? '';

    $answer = $form_state->getValue($element['#webform_key']);
    $answer = is_array($answer) ? $answer : [$answer];
    $c_answer = $element['#correct_answer'];
    $temp = [];
    foreach ($answer as $value) {
      if (!empty($value)) {
        $_key = (string) $value;
        $temp[$_key] = $_key;
      }
    }
    $answer = $temp;

    $element_manager = \Drupal::service('plugin.manager.webform.element');
    $element_plugin = $element_manager->getElementInstance($element, $webform);
    // $element_plugin->setWebformSubmission($webform_submission);
    if (method_exists($element_plugin, 'prepareValueForCompare')) {
      $answer = $element_plugin->prepareValueForCompare($answer);
      $c_answer = $element_plugin->prepareValueForCompare($c_answer);
    }

    $is_user_correct = FALSE;
    if (QuizResults::compareAnswer($answer, $c_answer, $element_plugin->isStrictCompare()) > 0) {
      $is_user_correct = TRUE;
    }

    $build['#type'] = 'container';
    $build['#attributes']['id'] = 'cad-wrapper-inner' . $element['#webform_id'];
    $build['description'] = [
      '#type' => 'webform_quiz_correct_answer_description',
      '#correct_answer' => $element['#correct_answer'],
      '#correct_answer_description' => $description,
      '#is_user_correct' => $is_user_correct,
      '#triggering_element' => $triggering_element,
    ];

    $ajax_response->addCommand(new HtmlCommand('#cad-wrapper-' . $element['#webform_id'], $build));

    // Allow other modules to add ajax commands.
    \Drupal::moduleHandler()->invokeAll(
      'webform_quiz_correct_answer_shown',
      [$ajax_response, $element, $form_state]
    );

    return $ajax_response;
  }

  /**
   * {@inheritdoc}
   */
  public static function prepareValueForCompare($value = NULL) {
    return $value;
  }

  public function isOptions() {
    return $this instanceof OptionsBase;
  }

  public static function supportsMultiple($element) {
    return !empty($element['#webform_multiple']);
  }

  /**
   * {@inheritDoc}
   */
  public function getCorrectAnswersWithScores($element, $prepare_for_compare = null) {
    $correct_answers = [];
    if($this->isOptions()) {
      $items = $element['#options'] ?? [];
      foreach($items as $key => $item) {
        $items[$key] = json_decode($item, true);
      }
      switch ($element['#webform_quiz_element_mode']) {
        case QuizWebformElementBaseInterface::WEBFORM_ELEMENT_MODE_QUIZ:
          foreach ($items as $key => $item) {
            if (!empty($item['is_correct_answer'])) {
              $correct_answers[$key] = $item;
              $correct_answers[$key]['answer_score'] = $element['#webform_quiz_number_of_points'];
              unset($correct_answers[$key]['is_correct_answer']);
            }
          }
          break;
        case QuizWebformElementBaseInterface::WEBFORM_ELEMENT_MODE_SURVEY:
          foreach ($items as $key => $item) {
            $correct_answers[$key] = $item;
            unset($correct_answers[$key]['is_correct_answer']);
          }
          break;
        
        default:
          break;
      }
    } else {
      $correct_answers[$element['#correct_answer']] = [
        'text' => $element['#correct_answer'],
        'description' => $element['#correct_answer_description'],
        'answer_score' => $element['#webform_quiz_number_of_points'],
      ];
    }

    if(is_null($prepare_for_compare)) {
      $prepare_for_compare = !$this->isOptions();
    }

    if($prepare_for_compare) {
      foreach($correct_answers as $key => $item) {
        $new_key = $this->prepareValueForCompare($key);
        unset($correct_answers[$key]);
        $correct_answers[$new_key] = $item;
      }
    }

    return $correct_answers;

  }

  /**
   * {@inheritdoc}
   */
  public function getMaxScorePossible(array $element, ?array $caws = null) {
    $caws = $caws ?? $this->getCorrectAnswersWithScores($element);
    return $this->supportsMultiple($element) ? $this->getScoresSum($caws) : $this->getMaxScore($caws);
  }

  /**
   * {@inheritdoc}
   */
  public function getScoresSum(array $caws) {
    $max_score = 0;
    foreach($caws as $key => $item) {
      $max_score += intval($item['answer_score']);
    }
    return $max_score;
  }

  /**
   * {@inheritdoc}
   */
  public function getMaxScore(array $caws) {
    $max_score = 0;
    foreach($caws as $key => $item) {
      $max_score = max($max_score, intval($item['answer_score']));
    }
    return $max_score;
  }

}
