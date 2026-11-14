<?php

namespace Drupal\ai_integration_eca\Plugin\Action;

use Drupal\Component\Plugin\Exception\PluginException;

/**
 * Describes the ai_integration_eca_execute_tts action.
 *
 * @Action(
 *   id = "ai_integration_eca_execute_tts",
 *   label = @Translation("Text to Speech"),
 *   description = @Translation("Run text through the AI text-to-speech model.")
 * )
 */
class TextToSpeech extends AiConfigActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL): void {
    $modelData = $this->getModelData();
    try {
      $provider = $this->loadModelProvider();
    }
    catch (PluginException $e) {
      return;
    }

    $provider->setConfiguration($this->getModelConfig());

    $response = $provider->textToSpeech($this->resolveTokenString($this->configuration['token_input']), $modelData['model_id'], ['ai_integration_eca']);

    $normalized = $response->getNormalized();
    $file = $normalized[0]->getAsFileEntity('public://', 'audio.mp3');
    $path = $file->createFileUrl(FALSE);

    $this->tokenService->addTokenData($this->configuration['token_result'], $path);
  }

  /**
   * {@inheritdoc}
   */
  protected function getOperationType(): string {
    return 'text_to_speech';
  }

}
