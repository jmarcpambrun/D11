<?php

namespace Drupal\ai_integration_eca\Plugin\Action;

use Drupal\ai\OperationType\Moderation\ModerationInput;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\eca\Plugin\DataType\DataTransferObject;

/**
 * Describes the ai_integration_eca_execute_moderation action.
 *
 * @Action(
 *   id = "ai_integration_eca_execute_moderation",
 *   label = @Translation("Moderation"),
 *   description = @Translation("Determine if a piece of text violates any usage policies.")
 * )
 */
class Moderation extends AiActionBase {

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

    $input = new ModerationInput($this->resolveTokenString($this->configuration['token_input']));
    $response = $provider->moderation($input, $modelData['model_id'])->getNormalized();

    $dto = DataTransferObject::create([
      'flagged' => $response->isFlagged(),
      'information' => $response->getInformation(),
    ]);
    $this->tokenService->addTokenData($this->configuration['token_result'], $dto);
  }

  /**
   * {@inheritdoc}
   */
  protected function getOperationType(): string {
    return 'moderation';
  }

}
