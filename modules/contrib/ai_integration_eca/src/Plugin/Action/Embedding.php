<?php

namespace Drupal\ai_integration_eca\Plugin\Action;

use Drupal\ai\OperationType\Embeddings\EmbeddingsInput;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\eca\Plugin\DataType\DataTransferObject;

/**
 * Describes the ai_integration_eca_execute_embedding action.
 *
 * @Action(
 *   id = "ai_integration_eca_execute_embedding",
 *   label = @Translation("Embedding"),
 *   description = @Translation("Generate a text embedding from an input.")
 * )
 */
class Embedding extends AiConfigActionBase {

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
    $input = new EmbeddingsInput($this->resolveTokenString($this->configuration['token_input']));
    $response = $provider->embeddings($input, $modelData['model_id'])->getNormalized();

    $dto = DataTransferObject::create((array) $response);
    $this->tokenService->addTokenData($this->configuration['token_result'], $dto);
  }

  /**
   * {@inheritdoc}
   */
  protected function getOperationType(): string {
    return 'embeddings';
  }

}
