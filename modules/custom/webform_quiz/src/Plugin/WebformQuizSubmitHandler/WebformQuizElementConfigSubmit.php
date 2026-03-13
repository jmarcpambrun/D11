<?php

namespace Drupal\webform_quiz\Plugin\WebformQuizSubmitHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\PluginBase;
use Drupal\webform_quiz\Plugin\WebformQuizSubmitHandlerInterface;
use Drupal\webform_quiz\QuizWebformElementBaseInterface;
use Drupal\webform_ui\Form\WebformUiElementFormBase;

/**
 * WebformQuizSubmitHandler plugin.
 *
 * @WebformQuizSubmitHandler(
 *  id = "webform_quiz_element_config_submit",
 *  label = @Translation("Webform Quiz Element Config Submit."),
 * )
 */
class WebformQuizElementConfigSubmit extends PluginBase implements WebformQuizSubmitHandlerInterface {

  /**
   * {@inheritdoc}
   */
  public function handleSubmit(&$form, FormStateInterface $form_state) {

    $parent_key = $form_state->getValue('parent_key');
    $key = $form_state->getValue('key');

    $form_obj = $form_state->getFormObject();

    if ($form_obj instanceof WebformUiElementFormBase) {
      $element_plugin = $form_obj->getWebformElementPlugin();
      $webform = $form_obj->getWebform();

      // Submit element configuration.
      // Generally, elements will not be processing any submitted properties.
      // It is possible that a custom element might need to call a third-party
      // API to 'register' the element.
      $subform_state = SubformState::createForSubform($form['properties'], $form, $form_state);
      $element_plugin->submitConfigurationForm($form, $subform_state);

      // Add/update the element to the webform.
      $properties = $element_plugin->getConfigurationFormProperties($form, $subform_state);

      // Store the correct answer based on what the user checked.
      // This will be an array.
      $user_input = $form_state->getUserInput();

      if(!empty($user_input['properties']['options']) && isset($user_input['properties']['options']['webform_quiz_element_mode'])) {
        $properties['#webform_quiz_element_mode'] = $user_input['properties']['options']['webform_quiz_element_mode'];
      }


      //$user_input['properties']['options']['webform_quiz_element_mode']
      if (isset($user_input['properties']['correct_answer'])) {
        // Must be plain field like date.
        $correct_answers = $user_input['properties']['correct_answer'];
      }

      if (isset($user_input['properties']['options'])) {
        // Must be select or checkboxes or radios.
        $items = $user_input['properties']['options']['custom']['options']['items'];
        $correct_answers = [];
        switch ($properties['#webform_quiz_element_mode']) {
          case QuizWebformElementBaseInterface::WEBFORM_ELEMENT_MODE_QUIZ:
            foreach ($items as $item) {
              if (!empty($item['is_correct_answer'])) {
                $correct_answers[$item['value']] = $user_input['webform_quiz_number_of_points'];
              }
            }
            break;
          case QuizWebformElementBaseInterface::WEBFORM_ELEMENT_MODE_SURVEY:
            foreach ($items as $item) {
              $correct_answers[$item['value']] = $item['answer_score'];
            }
            break;
          
          default:
            $form_state->setError($form['properties']['options']['options']['webform_quiz_element_mode'], 
              $this->t('Mode "@mode" not supported', ['@mode'=>$properties['#webform_quiz_element_mode']]));
            break;
        }
      }

      if (empty($key)) {
        // @todo create a more specific exception.
        $message = 'The configuration cannot be saved because the webform element key cannot be empty.';
        throw new \Exception($message);
      }

      // Save the correct answer description.
      $values = $form_state->getValues();
      $correct_answer_description = $values['correct_answer_description'];

      $properties['#correct_answer'] = $correct_answers;
      // $properties['#correct_answer_description'] = $user_input;
      $properties['#correct_answer_description'] = $correct_answer_description;
      $properties['#webform_quiz_number_of_points'] = @$values['webform_quiz_number_of_points'];

      $webform->setElementProperties($key, $properties, $parent_key);

      if(!$form_state->hasAnyErrors())
        $webform->save();
    }
  }

}
