<?php

namespace Drupal\webform_quiz\EntitySettings;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\EntitySettings\WebformEntitySettingsBaseForm;

/**
 * Webform quiz settings form class.
 */
class WebformQuizWebformSettingsForm extends WebformEntitySettingsBaseForm {

  public const DEFAULT_PASSING_SCORE = 70;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = $this->getEntity();
    $third_party_settings = $webform->getThirdPartySettings('webform_quiz');

    $form['is_this_a_quiz'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Is this a quiz or survey?'),
      '#description' => $this->t('Check this box if this webform should be a quiz or survey. If not it will behaive like a normal webform.'),
      '#default_value' => $third_party_settings['settings']['is_this_a_quiz'] ?? FALSE,
    ];

    $form['quiz_settings'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          'input[name="is_this_a_quiz"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    $form['quiz_settings']['allow_retakes'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow retakes?'),
      '#default_value' => $third_party_settings['settings']['allow_retakes'] ?? TRUE,
    ];
    $form['quiz_settings']['retake_settings'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          'input[name="allow_retakes"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];
    $form['quiz_settings']['retake_settings']['number_of_retakes_allowed'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of retakes allowed'),
      '#description' => $this->t('Enter the number of retakes a user can do. Enter 0 for an unlimited number of retakes.'),
      '#default_value' => $third_party_settings['settings']['number_of_retakes_allowed'] ?? 0,
    ];
    $form['quiz_settings']['show_statistics'] = [
      '#type' => 'checkbox',
      '#title' => 'Show statistics?',
      '#description' => $this->t('Check if you would like a message displayed on how the user did compared to other users taking the same quiz.'),
      '#default_value' => $third_party_settings['settings']['show_statistics'] ?? 1,
    ];
    $form['quiz_settings']['passing_score'] = [
      '#type' => 'number',
      '#title' => $this->t('Passing score percentage'),
      '#default_value' => $third_party_settings['settings']['passing_score'] ?? static::DEFAULT_PASSING_SCORE,
      '#min' => 0,
      '#max' => 100,
    ];

    $text_formats_to_add = [
      'passing_score_message' => 'Passing',
      'failing_score_message' => 'Failing',
    ];
    foreach ($text_formats_to_add as $type => $label_prefix) {
      $form['quiz_settings'][$type] = [
        '#type' => 'text_format',
        '#title' => $this->t('@label_prefix Score Message', ['@label_prefix' => $label_prefix]),
        '#format' => 'full_html',
        '#default_value' => $third_party_settings['settings'][$type] ?? '',
      ];
    }

    $form['quiz_settings']['report_code'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Twig/HTML code for PDF report'),
      '#default_value' => $third_party_settings['settings']['report_code'] ?? '',
    ];

    $form['quiz_settings']['report_code_base64'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Report code is base64 encoded?'),
      '#description' => $this->t('Could be you placing ready binary pdf file in base64 format.'),
      '#default_value' => $third_party_settings['settings']['report_code_base64'] ?? FALSE,
    ];

    $form['quiz_settings']['report_code_skip_twig'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Skip rendering the code with twig?'),
      '#description' => $this->t('Yes, if you dont need to customize anything in the code or if have troubles with render.'),
      '#default_value' => $third_party_settings['settings']['report_code_skip_twig'] ?? FALSE,
    ];

    $form['quiz_settings']['report_code_skip_html_to_dpf'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Skip converting generated HTML to PDF?'),
      '#description' => $this->t('Yes, if your file is already PDF or you have troubles with convertor.'),
      '#default_value' => $third_party_settings['settings']['report_code_skip_html_to_dpf'] ?? FALSE,
    ];


    $form += parent::form($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    // Save webform_quiz settings to the webform entity.
    $values = $form_state->getValues();

    $webform_quiz_settings['is_this_a_quiz'] = $values['is_this_a_quiz'];
    $webform_quiz_settings['allow_retakes'] = $values['allow_retakes'];
    $webform_quiz_settings['number_of_retakes_allowed'] = $values['number_of_retakes_allowed'];
    $webform_quiz_settings['show_statistics'] = $values['show_statistics'];
    $webform_quiz_settings['passing_score'] = $values['passing_score'];
    $webform_quiz_settings['passing_score_message'] = $values['passing_score_message']['value'];
    $webform_quiz_settings['failing_score_message'] = $values['failing_score_message']['value'];
    $webform_quiz_settings['report_code'] = $values['report_code'];
    $webform_quiz_settings['report_code_base64'] = $values['report_code_base64'];
    $webform_quiz_settings['report_code_skip_twig'] = $values['report_code_skip_twig'];
    $webform_quiz_settings['report_code_skip_html_to_dpf'] = $values['report_code_skip_html_to_dpf'];

    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = $this->getEntity();
    $webform->setThirdPartySetting('webform_quiz', 'settings', $webform_quiz_settings);

    // Set settings.
    $webform->setSettings($values);


    parent::save($form, $form_state);
  }

}
