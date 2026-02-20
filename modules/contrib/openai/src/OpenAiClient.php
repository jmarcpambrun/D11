<?php

declare(strict_types=1);

namespace Drupal\openai;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\openai\Exceptions\ApiKeyNotConfiguredException;
use OpenAI\Contracts\ClientContract;
use OpenAI\Contracts\Resources\AssistantsContract;
use OpenAI\Contracts\Resources\AudioContract;
use OpenAI\Contracts\Resources\ChatContract;
use OpenAI\Contracts\Resources\CompletionsContract;
use OpenAI\Contracts\Resources\EditsContract;
use OpenAI\Contracts\Resources\EmbeddingsContract;
use OpenAI\Contracts\Resources\FilesContract;
use OpenAI\Contracts\Resources\FineTunesContract;
use OpenAI\Contracts\Resources\FineTuningContract;
use OpenAI\Contracts\Resources\ImagesContract;
use OpenAI\Contracts\Resources\ModelsContract;
use OpenAI\Contracts\Resources\ModerationsContract;
use OpenAI\Contracts\Resources\ThreadsContract;
use OpenAI\Exceptions\TransporterException;

final class OpenAiClient implements ClientContract
{

  /**
   * Creates a Client instance with the given API token.
   */
  public function __construct(
    private readonly ImmutableConfig $config,
    private readonly ClientContract $clientContract) {
  }

  /**
   * Given a prompt, the model will return one or more predicted completions, and can also return the probabilities
   * of alternative tokens at each position.
   *
   * @see https://platform.openai.com/docs/api-reference/completions
   */
  public function completions(): CompletionsContract
  {
    return $this->doContract($this->clientContract->completions());
  }

  /**
   * Given a chat conversation, the model will return a chat completion response.
   *
   * @see https://platform.openai.com/docs/api-reference/chat
   */
  public function chat(): ChatContract
  {
    return $this->doContract($this->clientContract->chat());
  }

  /**
   * Get a vector representation of a given input that can be easily consumed by machine learning models and algorithms.
   *
   * @see https://platform.openai.com/docs/api-reference/embeddings
   */
  public function embeddings(): EmbeddingsContract
  {
    return $this->doContract($this->clientContract->embeddings());
  }

  /**
   * Learn how to turn audio into text.
   *
   * @see https://platform.openai.com/docs/api-reference/audio
   */
  public function audio(): AudioContract
  {
    return $this->doContract($this->clientContract->audio());
  }

  /**
   * Given a prompt and an instruction, the model will return an edited version of the prompt.
   *
   * @see https://platform.openai.com/docs/api-reference/edits
   */
  public function edits(): EditsContract
  {
    return $this->doContract($this->clientContract->edits());
  }

  /**
   * Files are used to upload documents that can be used with features like Fine-tuning.
   *
   * @see https://platform.openai.com/docs/api-reference/files
   */
  public function files(): FilesContract
  {
    return $this->doContract($this->clientContract->files());
  }

  /**
   * List and describe the various models available in the API.
   *
   * @see https://platform.openai.com/docs/api-reference/models
   */
  public function models(): ModelsContract
  {
    return $this->doContract($this->clientContract->models());
  }

  /**
   * Manage fine-tuning jobs to tailor a model to your specific training data.
   *
   * @see https://platform.openai.com/docs/api-reference/fine-tuning
   */
  public function fineTuning(): FineTuningContract
  {
    return $this->doContract($this->clientContract->fineTuning());
  }

  /**
   * Manage fine-tuning jobs to tailor a model to your specific training data.
   *
   * @see https://platform.openai.com/docs/api-reference/fine-tunes
   * @deprecated OpenAI has deprecated this endpoint and will stop working by January 4, 2024.
   * https://openai.com/blog/gpt-3-5-turbo-fine-tuning-and-api-updates#updated-gpt-3-models
   */
  public function fineTunes(): FineTunesContract
  {
    return $this->doContract($this->clientContract->fineTunes());
  }

  /**
   * Given a input text, outputs if the model classifies it as violating OpenAI's content policy.
   *
   * @see https://platform.openai.com/docs/api-reference/moderations
   */
  public function moderations(): ModerationsContract
  {
    return $this->doContract($this->clientContract->moderations());
  }

  /**
   * Given a prompt and/or an input image, the model will generate a new image.
   *
   * @see https://platform.openai.com/docs/api-reference/images
   */
  public function images(): ImagesContract
  {
    return $this->doContract($this->clientContract->images());
  }

  /**
   * Build assistants that can call models and use tools to perform tasks.
   *
   * @see https://platform.openai.com/docs/api-reference/assistants
   */
  public function assistants(): AssistantsContract
  {
    return $this->doContract($this->clientContract->assistants());
  }

  /**
   * Create threads that assistants can interact with.
   *
   * @see https://platform.openai.com/docs/api-reference/threads
   */
  public function threads(): ThreadsContract
  {
    return $this->doContract($this->clientContract->threads());
  }


  protected function doContract(mixed $result): mixed {
    $api_key_set = !empty($this->config->get('api_key'));

    if (!$api_key_set) {
      throw new ApiKeyNotConfiguredException();
    }

    return $result;
  }
}