<?php

namespace Drupal\ai_media_image\Form;

use Drupal\ai\OperationType\TextToImage\TextToImageInput;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\media\Entity\Media;

/**
 * Provides friendly methods building forms to generate images.
 */
trait AiMediaImageFormTrait {

  /**
   * Helper function to build out the form structure.
   *
   * @param array $form
   *   The form array.
   * @param Drupal\Core\Form\FormStateInterface $form_state
   *   The form state array.
   *
   * @return array
   *   The modified form array.
   */
  protected function buildAiForm(array $form, FormStateInterface $form_state) {

    $isMediaLibraryForm = NULL !== $form_state->get('media_library_state');
    $form['ai_media_image'] = [
      '#type' => 'details',
      '#title' => t('Generate Image with AI'),
      '#weight' => 2,
      '#open' => TRUE,
      '#states' => [
        'invisible' => [
          'select[name="image_source"]' => [
            'value' => 'upload',
          ],
        ],
      ],
    ];

    // Main container for the options.
    $form['ai_media_image']['generator_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['generator_container'],
      ],
    ];

    // Prompt used for the ai to generate and image.
    $form['ai_media_image']['generator_container']['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt'),
      '#required' => TRUE,
    ];

    // Group the provider config form.
    $form['ai_media_image']['generator_container']['options_group'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['options_group'],
      ],
    ];

    \Drupal::service('ai.form_helper')->generateAiProvidersForm($form['ai_media_image']['generator_container']['options_group'], $form_state, 'text_to_image', 'image_generator', AiProviderFormHelper::FORM_CONFIGURATION_FULL);

    // Decide whether to open the "Provider configuration" fieldset by default
    // or not.
    $provider_configuration_default_state = (bool) \Drupal::config('ai_media_image.settings')->get('provider_configuration_open');
    $form['ai_media_image']['generator_container']['options_group']['image_generator_ajax_prefix']['#open'] = $provider_configuration_default_state;

    // Only generate 1 image at a time.
    if (isset($form['ai_media_image']['generator_container']['options_group']['image_generator_ajax_prefix']['image_generator_ajax_prefix_configuration_n'])) {
      $form['ai_media_image']['generator_container']['options_group']['image_generator_ajax_prefix']['image_generator_ajax_prefix_configuration_n']['#default_value'] = 1;
      $form['ai_media_image']['generator_container']['options_group']['image_generator_ajax_prefix']['image_generator_ajax_prefix_configuration_n']['#attributes']['disabled'] = 'disabled';
    }

    // Generate image button to send prompt to ai and get the result.
    $form['ai_media_image']['generator_container']['options_group']['generate_image'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate Image'),
      '#name' => 'addAiImage',
      '#submit' => [[$this, 'addAiImage']],
      '#ajax' => [
        'callback' => [$this, 'generateImageAjaxCallback'],
        'event' => 'click',
        'wrapper' => 'image-preview',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Generating image...'),
        ],
      ],
    ];

    // Extra paramters for Media Library AJAX form.
    if ($isMediaLibraryForm) {
      $form['ai_media_image']['generator_container']['options_group']['generate_image']['#ajax'] += [
        // https://www.drupal.org/project/drupal/issues/2934463#comment-13180158
        'url' => Url::fromRoute('media_library.ui', $form_state->get('media_library_state')->all()),
        'options' => [
          'query' => [
            FormBuilderInterface::AJAX_FORM_REQUEST => TRUE,
          ],
        ],
      ];
    }

    // Wrapper to preview the image returned by the ai.
    $form['ai_media_image']['generator_container']['image_preview'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'image-preview',
        'class' => ['image_preview_zone'],
      ],
      '#prefix' => '<div id="image-preview-wrapper">',
      '#suffix' => '</div>',
    ];

    if ($form_state->get('image_data')) {
      $form['ai_media_image']['generator_container']['image_preview']['image_element'] = [
        '#type' => 'html_tag',
        '#tag' => 'img',
        '#attributes' => [
          'src' => 'data:image/jpeg;base64,' . $form_state->get('image_data'),
        ],
      ];

      $form['ai_media_image']['generator_container']['image_preview']['alt_text'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Alternative text'),
        '#default_value' => substr($form_state->getValues()['prompt'], 0, 255),
        '#size' => 60,
        '#maxlength' => 512,
        '#description' => $this->t('Short description of the image used by screen readers and displayed when the image is not loaded. This is important for accessibility.'),
        '#required' => TRUE,
      ];

      if ($isMediaLibraryForm) {
        $form['ai_media_image']['generator_container']['image_preview']['save_image'] = [
          '#type' => 'submit',
          '#value' => $this->t('Save to Media Library'),
          '#name' => 'saveMl',
          '#submit' => [[$this, 'processGeneratedFile']],
          '#attributes' => [
            'class' => ['button--primary'],
          ],
          '#ajax' => [
            'callback' => [$this, 'saveImageAjaxCallback'],
            'event' => 'click',
            'wrapper' => 'media-library-wrapper',
            'progress' => [
              'type' => 'throbber',
              'message' => $this->t('Saving image...'),
            ],
            'url' => Url::fromRoute('media_library.ui', $form_state->get('media_library_state')->all()),
            'options' => [
              'query' => [
                FormBuilderInterface::AJAX_FORM_REQUEST => TRUE,
              ],
            ],
          ],
        ];
      }
    }

    return $form;
  }

  /**
   * Save image AJAX callback.
   */
  public function saveImageAjaxCallback(array &$form, FormStateInterface $form_state) {
    if (empty($form_state->getErrors())) {
      $response = $this->updateLibrary($form, $form_state);
      $response->addCommand(new RemoveCommand("#media-library-view"), TRUE);
      return $response;
    }
    return $form;
  }

  /**
   * Generate image callback.
   */
  public function addAiImage(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $config = [];
    foreach ($values as $key => $value) {
      if (substr($key, 0, 42) == 'image_generator_ajax_prefix_configuration_') {
        $config[substr($key, 42)] = $value;
      }
    }
    $prompt = $values['prompt'];
    $provider = $values['image_generator_ai_provider'];
    $model = $values['image_generator_ai_model'];

    $generated_image = $this->generateImageInAiModule($prompt, $provider, $model, $config);

    $form_state->set('image_data', $generated_image);
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function generateImageAjaxCallback(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#image-preview-wrapper', $form['ai_media_image']['generator_container']['image_preview']));

    $messages = \Drupal::messenger()->all();

    if (isset($messages['error'])) {
      foreach ($messages['error'] as $error) {
        $errorString = $this->t('Oops, there was an error. Please check the database log for more details.');
        if (gettype($error) == 'string') {
          $errorString = $error;
        }
        else {
          $errorString = $error->__toString();
        }
        $response->addCommand(new MessageCommand($errorString, '#image-preview-wrapper', ['type' => 'error']));
      }
      \Drupal::messenger()->deleteByType('error');
    }

    return $response;
  }

  /**
   * Generate the image in the AI provider.
   *
   * @param string $prompt
   *   The prompt.
   * @param string $provider
   *   The provider ID.
   * @param string $model
   *   The model ID.
   * @param array $config
   *   The provider API query parameters.
   *
   * @return string
   *   Base64 encoded image.
   */
  protected function generateImageInAiModule($prompt, $provider, $model, $config) {
    $service = \Drupal::service('ai.provider');
    $ai_provider = $service->createInstance($provider);
    $ai_provider->setConfiguration($config);
    $input = new TextToImageInput($prompt);
    $response = $ai_provider->textToImage($input, $model);
    if (isset($response->getNormalized()[0])) {
      return base64_encode($response->getNormalized()[0]->getBinary());
    }
  }

  /**
   * Get the generated image from form state and save as a media entity.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function processGeneratedFile(array &$form, FormStateInterface $form_state) {
    // Retrieve the stored image data.
    $binaryData = base64_decode($form_state->get('image_data'));

    // Generate a unique filename.
    $filename = 'ai_generated_image_' . uniqid() . '.jpg';

    // Save the file entity.
    $file_path = 'public://' . $filename;
    $file = \Drupal::service('file.repository')->writeData($binaryData, $file_path, FileSystemInterface::EXISTS_REPLACE);

    if ($file) {
      // Retrieve the image field name for the media entity.
      $image_field_name = _ai_media_image_get_image_field_name('image');

      // Create a media entity.
      $media = Media::create([
        'bundle' => 'image',
        'name' => $form_state->getValue('name')[0]['value'] ?? $filename,
        $image_field_name => [
          'target_id' => $file->id(),
          'alt' => $form_state->getValue('alt_text'),
        ],
      ]);

      // Save the media entity.
      $media->save();

      // Display a success message.
      $this->messenger()->addMessage($this->t('The AI generated image was saved as a media entity.'));

      $form_state->setValue('upload', [
        'fids' => [],
      ]);
      $form_state->set('media', [$media]);

      // Redirect to the media library.
      $form_state->setRedirect('entity.media.collection');
    }
    else {
      // Display an error message.
      $this->messenger()->addError($this->t('There was an error saving the AI generated image.'));
    }
  }

}
