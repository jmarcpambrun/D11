<?php

namespace Drupal\writer_ai;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Drupal\ai\Exception\AiResponseErrorException;
use Drupal\ai\OperationType\Chat\ChatInput;
use Psr\Log\LoggerInterface;

/**
 * Writer AI client that communicates with the Writer.com API.
 */
class WriterAiClient {

  /**
   * HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * The API key.
   *
   * @var string
   */
  protected string $apiKey;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  public function __construct(ClientInterface $httpClient, LoggerInterface $logger) {
    $this->httpClient = $httpClient;
    $this->logger = $logger;
  }

  /**
   * Sets the API key.
   *
   * @param string $api_key
   *   The API key to set.
   */
  public function setApiKey(string $api_key): void {
    $this->apiKey = $api_key;
  }

  /**
   * The current model to use for requests.
   *
   * @var string
   */
  protected string $currentModel = 'palmyra-x5';

  /**
   * Sets the model to use for the next request.
   *
   * @param string $model_id
   *   The model ID.
   *
   * @return $this
   */
  public function model(string $model_id): self {
    $this->currentModel = $model_id;
    return $this;
  }

  /**
   * Fetches the list of models from Writer.com.
   *
   * @return array
   *   List of models formatted for AI provider module.
   *
   * @throws \Drupal\ai\Exception\AiResponseErrorException
   */
  public function loadModels(): array {
    if (empty($this->apiKey)) {
      throw new AiResponseErrorException('API key is empty or not configured.');
    }

    try {
      $response = $this->httpClient->request('GET', 'https://api.writer.com/v1/models', [
        'headers' => [
          'Authorization' => 'Bearer ' . $this->apiKey,
          'Content-Type' => 'application/json',
        ],
        'http_errors' => FALSE,
      ]);

      $status_code = $response->getStatusCode();
      $body = $response->getBody()->getContents();

      if ($status_code !== 200) {
        $this->logger->error('Writer Models API error: @status - @body', [
          '@status' => $status_code,
          '@body' => $body,
        ]);
        throw new AiResponseErrorException('Writer API returned status ' . $status_code . ': ' . $body);
      }

      $data = json_decode($body, TRUE);

      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new AiResponseErrorException('Invalid JSON response from Writer API: ' . json_last_error_msg());
      }

      if (empty($data['models'])) {
        throw new AiResponseErrorException('No models returned from Writer API. Response: ' . $body);
      }

      $models = [];
      foreach ($data['models'] as $model) {
        $models[$model['id']] = [
          'id' => $model['id'],
          'name' => $model['name'] ?? $model['id'],
          'capabilities' => ['chat'],
        ];
      }

      return ['models' => $models];
    }
    catch (GuzzleException $e) {
      throw new AiResponseErrorException('Failed to fetch models from Writer: ' . $e->getMessage());
    }
  }

  /**
   * Uploads a file to Writer.com for use in vision requests.
   *
   * @param string $file_data
   *   The binary file data.
   * @param string $filename
   *   The filename.
   * @param string $mime_type
   *   The MIME type of the file.
   *
   * @return string
   *   The file ID returned by Writer API.
   *
   * @throws \Drupal\ai\Exception\AiResponseErrorException
   */
  public function uploadFile(string $file_data, string $filename, string $mime_type): string {
    if (empty($this->apiKey)) {
      throw new AiResponseErrorException('API key is empty or not configured.');
    }

    try {
      $response = $this->httpClient->request('POST', 'https://api.writer.com/v1/files', [
        'headers' => [
          'Authorization' => 'Bearer ' . $this->apiKey,
          'Content-Type' => $mime_type,
          'Content-Disposition' => 'attachment; filename=' . $filename,
        ],
        'body' => $file_data,
        'http_errors' => FALSE,
      ]);

      $statusCode = $response->getStatusCode();
      $body = $response->getBody()->getContents();

      if ($statusCode !== 200) {
        $this->logger->error('Writer File Upload API error: @status - @body', [
          '@status' => $statusCode,
          '@body' => $body,
        ]);
        throw new AiResponseErrorException('Writer File Upload API returned error: ' . $statusCode . ' - ' . $body);
      }

      $data = json_decode($body, TRUE);

      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new AiResponseErrorException('Invalid JSON response from Writer File Upload API: ' . json_last_error_msg());
      }

      if (!isset($data['id'])) {
        throw new AiResponseErrorException('File upload response missing file ID');
      }

      return $data['id'];
    }
    catch (GuzzleException $e) {
      throw new AiResponseErrorException('Failed to upload file to Writer: ' . $e->getMessage());
    }
  }

  /**
   * Deletes a file from Writer.com servers.
   *
   * @param string $file_id
   *   The file ID to delete.
   *
   * @return bool
   *   TRUE if deletion was successful, FALSE otherwise.
   */
  public function deleteFile(string $file_id): bool {
    if (empty($this->apiKey) || empty($file_id)) {
      return FALSE;
    }

    try {
      $response = $this->httpClient->request('DELETE', 'https://api.writer.com/v1/files/' . $file_id, [
        'headers' => [
          'Authorization' => 'Bearer ' . $this->apiKey,
        ],
        'http_errors' => FALSE,
      ]);

      $statusCode = $response->getStatusCode();

      if ($statusCode === 200 || $statusCode === 204) {
        return TRUE;
      }

      $this->logger->warning('Failed to delete file @file_id: HTTP @status', [
        '@file_id' => $file_id,
        '@status' => $statusCode,
      ]);

      return FALSE;
    }
    catch (GuzzleException $e) {
      $this->logger->warning('Error deleting file @file_id: @error', [
        '@file_id' => $file_id,
        '@error' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Analyzes images using Writer's vision API.
   *
   * @param string $prompt
   *   The prompt for image analysis.
   * @param array $image_variables
   *   Array of image variables with 'name' and 'file_id' keys.
   *
   * @return string
   *   The analysis result.
   *
   * @throws \Drupal\ai\Exception\AiResponseErrorException
   */
  public function analyzeImages(string $prompt, array $image_variables): string {
    if (empty($this->apiKey)) {
      throw new AiResponseErrorException('API key is empty or not configured.');
    }

    try {
      $requestData = [
        'model' => 'palmyra-vision',
        'prompt' => $prompt,
        'variables' => $image_variables,
      ];

      $response = $this->httpClient->request('POST', 'https://api.writer.com/v1/vision', [
        'headers' => [
          'Authorization' => 'Bearer ' . $this->apiKey,
          'Content-Type' => 'application/json',
        ],
        'json' => $requestData,
        'http_errors' => FALSE,
      ]);

      $statusCode = $response->getStatusCode();
      $body = $response->getBody()->getContents();

      if ($statusCode !== 200) {
        $this->logger->error('Writer Vision API error: @status - @body', [
          '@status' => $statusCode,
          '@body' => $body,
        ]);
        throw new AiResponseErrorException('Writer Vision API returned error: ' . $statusCode . ' - ' . $body);
      }

      $data = json_decode($body, TRUE);

      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new AiResponseErrorException('Invalid JSON response from Writer Vision API: ' . json_last_error_msg());
      }

      if (!isset($data['data'])) {
        throw new AiResponseErrorException('Vision API response missing data field');
      }

      $result = $data['data'];

      // Clean up uploaded files after successful analysis.
      $this->cleanupUploadedFiles($image_variables);

      return $result;
    }
    catch (GuzzleException $e) {
      // Still attempt cleanup even on failure.
      $this->cleanupUploadedFiles($image_variables);
      throw new AiResponseErrorException('Failed to analyze images with Writer: ' . $e->getMessage());
    }
  }

  /**
   * Cleans up uploaded files from Writer servers.
   *
   * @param array $image_variables
   *   Array of image variables with 'file_id' keys.
   */
  protected function cleanupUploadedFiles(array $image_variables): void {
    foreach ($image_variables as $variable) {
      if (isset($variable['file_id'])) {
        $deleted = $this->deleteFile($variable['file_id']);
        if ($deleted) {
          $this->logger->info('Successfully deleted file @file_id from Writer servers', [
            '@file_id' => $variable['file_id'],
          ]);
        }
      }
    }
  }

  /**
   * Generates a response based on the provided chat input.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatInput $chatInput
   *   The chat input containing messages.
   *
   * @return string
   *   The generated response content.
   *
   * @throws \Drupal\ai\Exception\AiResponseErrorException
   */
  public function generate($chatInput): string {
    if (empty($this->apiKey)) {
      throw new AiResponseErrorException('API key is empty or not configured.');
    }

    $messages = [];
    $hasImages = FALSE;
    $imageVariables = [];
    $imageCounter = 1;

    if ($chatInput instanceof ChatInput) {
      foreach ($chatInput->getMessages() as $msg) {
        $role = $msg->getRole();
        // Map Drupal AI roles to Writer API roles.
        if ($role === 'model') {
          $role = 'assistant';
        }

        $content = $msg->getText();

        // Check if this message has images.
        if (count($msg->getImages()) > 0) {
          $hasImages = TRUE;

          // Upload each image and create variables.
          foreach ($msg->getImages() as $image) {
            $imageName = 'image_' . $imageCounter;

            try {
              // Get image data from ImageFile object.
              $imageData = $image->getBinary();
              $mimeType = $image->getMimeType();
              $filename = $image->getFilename() ?: ($imageName . '.jpg');

              if (empty($imageData)) {
                throw new AiResponseErrorException('Empty image data for ' . $imageName);
              }

              // Upload the image to Writer.com.
              $fileId = $this->uploadFile($imageData, $filename, $mimeType);

              // Add to variables array.
              $imageVariables[] = [
                'name' => $imageName,
                'file_id' => $fileId,
              ];

              // Update content to reference the image.
              if (strpos($content, '{{' . $imageName . '}}') === FALSE) {
                $content .= ' {{' . $imageName . '}}';
              }
              $imageCounter++;

            }
            catch (\Exception $e) {
              $this->logger->error('Failed to process image @name: @error', [
                '@name' => $imageName,
                '@error' => $e->getMessage(),
              ]);
              // Continue with other images.
              continue;
            }
          }
        }

        $messages[] = [
          'role' => $role,
          'content' => $content,
        ];
      }
    }
    elseif (is_string($chatInput)) {
      $messages[] = [
        'role' => 'user',
        'content' => $chatInput,
      ];
    }
    elseif (is_array($chatInput)) {
      $messages = $chatInput;
    }

    try {
      // If we have images, use the vision API directly.
      if ($hasImages && !empty($imageVariables)) {
        // Get the last user message as the prompt.
        $prompt = '';
        foreach (array_reverse($messages) as $message) {
          if ($message['role'] === 'user') {
            $prompt = $message['content'];
            break;
          }
        }

        if (empty($prompt)) {
          throw new AiResponseErrorException('No user prompt found for vision analysis');
        }

        // Validate that all image variables are referenced in the prompt.
        foreach ($imageVariables as $variable) {
          if (strpos($prompt, '{{' . $variable['name'] . '}}') === FALSE) {
            $prompt .= ' Please analyze the image {{' . $variable['name'] . '}}.';
          }
        }

        return $this->analyzeImages($prompt, $imageVariables);
      }

      // Regular chat completion for text-only messages.
      $requestData = [
        'model' => $this->currentModel,
        'messages' => $messages,
        'max_tokens' => 1000,
        'temperature' => 0.7,
      ];

      $response = $this->httpClient->request('POST', 'https://api.writer.com/v1/chat/completions', [
        'headers' => [
          'Authorization' => 'Bearer ' . $this->apiKey,
          'Content-Type' => 'application/json',
        ],
        'json' => $requestData,
        'http_errors' => FALSE,
      ]);

      $statusCode = $response->getStatusCode();
      $body = $response->getBody()->getContents();

      if ($statusCode !== 200) {
        $this->logger->error('Writer API error: @status - @body', [
          '@status' => $statusCode,
          '@body' => $body,
        ]);
        throw new AiResponseErrorException('Writer API returned error: ' . $statusCode . ' - ' . $body);
      }

      $data = json_decode($body, TRUE);

      if (json_last_error() !== JSON_ERROR_NONE) {
        $this->logger->error('Invalid JSON response: @error', ['@error' => json_last_error_msg()]);
        throw new AiResponseErrorException('Invalid JSON response from Writer API: ' . json_last_error_msg());
      }

      if (!isset($data['choices'][0]['message']['content'])) {
        $this->logger->error('Invalid response structure: @response', ['@response' => $body]);
        throw new AiResponseErrorException('Invalid response structure from Writer API');
      }

      return $data['choices'][0]['message']['content'];
    }
    catch (GuzzleException $e) {
      $this->logger->error('HTTP error: @message', ['@message' => $e->getMessage()]);
      throw new AiResponseErrorException('Failed to get response from Writer: ' . $e->getMessage());
    }
    catch (\Exception $e) {
      $this->logger->error('General error: @message', ['@message' => $e->getMessage()]);
      throw new AiResponseErrorException('Failed to get response from Writer: ' . $e->getMessage());
    }
  }

}
