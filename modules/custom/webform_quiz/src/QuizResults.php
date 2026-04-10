<?php

namespace Drupal\webform_quiz;

use Drupal\webform\Entity\WebformSubmission;
use Drupal\Core\Render\Element;
use Mpdf\Mpdf;

/**
 * Quiz results class definition.
 */
class QuizResults {

  /**
   * {@inheritdoc}
   */
  protected $webformSubmission;

  /**
   * {@inheritdoc}
   */
  protected $numberOfPointsReceived;

  /**
   * {@inheritdoc}
   */
  protected $numberOfPointsAvailable;

  /**
   * {@inheritdoc}
   */
  protected $percentageCorrect;

  /**
   * {@inheritdoc}
   */
  protected $statPerSection = [];

  /**
   * QuizResults constructor.
   *
   * @param \Drupal\webform\Entity\WebformSubmission|string $webformSubmission
   *   WebformSubmission entity object.
   */
  public function __construct($webformSubmission) {
    if (!($webformSubmission instanceof WebformSubmission)) {
      $webformSubmission = static::loadSubmission($webformSubmission);
    }
    $this->webformSubmission = $webformSubmission;
    $this->processResults();
  }

  /**
   * Compare the user's answer to the correct answer.
   *
   * @param mixed $answer
   *   The user's answer. An array of values (prepared to be compared with correct answer key)
   * @param array $correct_answer
   *   The correct answer(s), specified by Admin in UI. 
   *   Must be an array where key is answer and value is number of points
   * @param bool $strict
   *   Whether to compare strictly. If $missed_answers or $missed_correct_answers are not 0, return 0.
   * @param array $hits
   *   The hits, array of correct answers picked by user.
   * @param array $missed_answers
   *   The answer which user marked correct, but is not correct.
   * @param array $missed_correct_answers
   *   The answer which user did not mark correct, but is correct.
   *
   * @return int|array
   *   The number of correct answers picked ($hits) or array of $hits.
   */
  public static function compareAnswer($answer, array $correct_answer, $strict = TRUE, &$hits = [], &$missed_answers = [], &$missed_correct_answers = []) {
    $answer = empty($answer) ? [] : $answer;
    $answer = is_array($answer) ? $answer : [$answer];

    $correct_answer = empty($correct_answer) ? [] : $correct_answer;
    //$correct_answer = is_array($correct_answer) ? $correct_answer : [$correct_answer];

    $ca_used = [];

    // Loop answers.
    foreach ($answer as $a) {
      if (!is_scalar($a)) {
        throw new \RuntimeException("Answer is of wrong type " . gettype($a));
      }

      $hit = FALSE;
      foreach ($correct_answer as $ca_key => $ca_data) {
        if (!is_array($ca_data)) {
          throw new \RuntimeException("Correct answer is of wrong type " . gettype($ca_data));
        }
        if ($a == $ca_key) {
          $hit = TRUE;
          $ca_used[] = $ca_key;
          break;
        }
      }

      if ($hit) {
        $hits[$a] = $correct_answer[$a]['answer_score'];
      }
      else {
        $missed_answers[] = $a;
      }
    }

    // Loop correct answers.
    foreach ($correct_answer as $ca_key => $ca_data) {
      if (array_search($ca_key, $ca_used) !== FALSE) {
        continue;
      }

      if (!is_array($ca_data)) {
        throw new \RuntimeException("Answer is of wrong type " . gettype($ca_data));
      }

      $missed_correct_answers[] = $ca_key;
    }

    $res = array_sum($hits);
    if ($strict) {
      return count($missed_correct_answers) + count($missed_answers) > 0 ? 0 : $res;
    }
    return $res < 0 ? 0 : $res;
  }

  /**
   * {@inheritdoc}
   */
  protected function processElementWithChildren($webform, $webform_submission, &$submission_data, $element_key, &$element, &$number_of_available_points, &$number_of_points_received) {
    if (isset($submission_data[$element_key])) {
      $user_choice = $submission_data[$element_key];

      /** @var \Drupal\webform\Plugin\WebformElementManagerInterface  */
      $element_manager = \Drupal::service('plugin.manager.webform.element');

      $element_plugin = $element_manager->getElementInstance($element, $webform);
      if ($element_plugin instanceof QuizWebformElementBaseInterface) {
        $element_plugin->setWebformSubmission($webform_submission);
        $c_answer = $element_plugin->getCorrectAnswersWithScores($element);
        $user_choice = $element_plugin::prepareValueForCompare($user_choice);

        $number_of_available_points += $element_plugin->getMaxScorePossible($element);

        $points_got = static::compareAnswer($user_choice, $c_answer, $element_plugin->isStrictCompare($element, $c_answer));
        
        if ($points_got > 0) {
          // This indicates that the user answered the question correctly.
          $number_of_points_received += $points_got;
        }        
      }
    }

    foreach (Element::children($element) as $subelement_key) {
      $this->processElementWithChildren($webform, $webform_submission, $submission_data, $subelement_key, $element[$subelement_key], $number_of_available_points, $number_of_points_received);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function processResults() {
    $webform_submission = $this->webformSubmission;
    $webform = $webform_submission->getWebform();
    $elements = $webform->getElementsInitialized();

    $submission_data = $webform_submission->getData();

    $number_of_points_received = 0;
    $number_of_available_points = 0;

    foreach ($elements as $element_key => $element) {
      $this->processElementWithChildren($webform, $webform_submission, $submission_data, $element_key, $element, $number_of_available_points, $number_of_points_received);
    }

    $this->numberOfPointsReceived = $number_of_points_received;
    $this->numberOfPointsAvailable = $number_of_available_points;
    $this->percentageCorrect = $number_of_available_points > 0 ? ($number_of_points_received / $number_of_available_points) * 100 : 0;

    //calculate per section stats
    foreach ($elements as $element_key => $element) {
      if($element['#type'] == 'webform_quiz_wizard_page') {
        if(!isset($this->statPerSection[$element_key])) {
          $this->statPerSection[$element_key] = [
            'number_of_points_received' => 0,
            'number_of_points_available' => 0,
            'percentage_correct' => 0,
          ];
        }
      }
      $this->processElementWithChildren($webform, $webform_submission, $submission_data, $element_key, $element, $this->statPerSection[$element_key]['number_of_points_available'], $this->statPerSection[$element_key]['number_of_points_received']);
      $this->statPerSection[$element_key]['percentage_correct'] = $this->statPerSection[$element_key]['number_of_points_available'] > 0 ? ($this->statPerSection[$element_key]['number_of_points_received'] / $this->statPerSection[$element_key]['number_of_points_available']) * 100 : 0;
    }

    foreach($this->statPerSection as $key => $section) {
      if(empty($section['number_of_points_available'])) {
        unset($this->statPerSection[$key]);
      }
    }

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
  public function getNumberOfPointsAvailable() {
    return $this->numberOfPointsAvailable;
  }

  /**
   * {@inheritdoc}
   */
  public function getPercentageCorrect() {
    return $this->percentageCorrect;
  }

  public function getStatPerSection() {
    return $this->statPerSection;
  }

  /**
   * Generates a report from the webform submission.
   *
   * @return array
   *   An array containing the generated temp PDF file full path for reading and file name for download.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   If the webform submission is not found.
   * @throws \Mpdf\MpdfException
   *   If there is an error generating the PDF.
   */
  public function generateReportFromSubmission() {
    $webform_submission = $this->webformSubmission;
    if (!$webform_submission) {
        throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    $data = $webform_submission->getData();

    $webform = $webform_submission->getWebform();
    $third_party_settings = $webform->getThirdPartySettings('webform_quiz');

    $template = $third_party_settings['settings']['report_code'] ?? t('Report template not defined');
    $base64 = $third_party_settings['settings']['report_code_base64'] ?? false;
    $skip_twig = $third_party_settings['settings']['report_code_skip_twig'] ?? false;
    $is_pdf = $third_party_settings['settings']['report_code_skip_html_to_dpf'] ?? false;

    $context = [
        'results_data' => [
            'number_of_points_received' => $this->getNumberOfPointsReceived(),
            'number_of_points_available' => $this->getNumberOfPointsAvailable(),
            'percentage_correct' => $this->getPercentageCorrect(),
            'stat_per_section' => $this->getStatPerSection(),
        ],
        'submission_data' => $data,
    ];

    $template = $base64 ? base64_decode($template) : $template;

    $rendered = $template;
    if(!$skip_twig) {
      $rendered = \Drupal::service('twig')->renderInline($template, $context);
    }

    $fname = 'submission_report_' . $webform->uuid();
    $temp_file = tempnam(sys_get_temp_dir(), $fname) . '.pdf';

    if($is_pdf) {
      file_put_contents($temp_file, $rendered);
    } else {
      $mpdf = new Mpdf();
      $mpdf->WriteHTML($rendered);
      $mpdf->Output($temp_file, \Mpdf\Output\Destination::FILE);
    }

    return [
        'file' => $temp_file,
        'name' => $fname . '.pdf',
    ];
  }

  /**
   * Loads a webform submission by its ID or UUID.
   *
   * @param string $sid
   *   The ID or UUID of the webform submission.
   *
   * @return \Drupal\webform\Entity\WebformSubmission|null
   *   The loaded webform submission entity, or NULL if not found.
   */
  public static function loadSubmission($sid) {
    $sub = null;
    if(preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $sid))
      $sub = \Drupal::service('entity.repository')->loadEntityByUuid('webform_submission', $sid);
    else
      $sub = WebformSubmission::load($sid);
    return $sub;
  }

}
