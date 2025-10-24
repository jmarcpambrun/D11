<?php

namespace Drupal\drd\Plugin\Update\Build;

use Drupal\Core\Form\FormStateInterface;
use Drupal\drd\Plugin\Update\UpdateBase;
use Drupal\drd\Update\PluginBuildInterface;
use Drupal\drd\Update\PluginStorageInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Abstract DRD Update plugin to implement general build functionality.
 */
abstract class Base extends UpdateBase implements PluginBuildInterface {

  /**
   * Indicates if the build process succeeded.
   *
   * @var bool
   */
  protected bool $changed = FALSE;

  /**
   * Determine if plugin includes a patching component itself.
   *
   * @return bool
   *   TRUE if plugin handles patching automatically.
   */
  protected function implicitPatching(): bool {
    return FALSE;
  }

  /**
   * Convert patch list from configuration into editable string.
   *
   * @return string
   *   Properly formatted string for editing.
   */
  private function editablePatches(): string {
    $items = [];
    foreach ($this->configuration['patches'] as $patch) {
      $items[] = implode('|', $patch);
    }
    return implode(PHP_EOL, $items);
  }

  /**
   * Converted edited patch string into structured array for configuration.
   *
   * @param string $value
   *   The edited patch configuration.
   *
   * @return array
   *   The config array containing patching information.
   */
  private function unpackPatches(string $value): array {
    $patches = [];
    foreach (explode(PHP_EOL, $value) as $item) {
      $parts = explode('|', trim($item));
      if (count($parts) > 1) {
        $patches[] = [
          'path' => $parts[0],
          'patch' => $parts[1],
        ];
      }
    }
    return $patches;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $element = parent::buildConfigurationForm($form, $form_state);

    $element['patches'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Patches'),
      '#default_value' => $this->editablePatches(),
      '#description' => $this->t('One patch per line in the format <em>path|patchfile</em> where path is relative to the Drupal root and patchfile is a URL.'),
      '#access' => !$this->implicitPatching(),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['patches'] = $this->unpackPatches($this->getFormValue($form_state, 'patches'));
  }

  /**
   * {@inheritdoc}
   */
  final public function hasChanged(): bool {
    return $this->changed;
  }

  /**
   * {@inheritdoc}
   */
  final public function patch(PluginStorageInterface $storage): PluginBuildInterface {
    if (!$this->hasChanged() || $this->implicitPatching()) {
      return $this;
    }
    foreach ($this->configuration['patches'] as $item) {
      $path = $storage->getWorkingDirectory() . DIRECTORY_SEPARATOR . $item['path'];
      if (!file_exists($path)) {
        throw new \RuntimeException('Can not patch ' . $path . ', directory doesn\'t exist.');
      }

      $options = [
        'sink' => $this->fileSystem->tempnam('temporary://', 'patch'),
      ];
      $client = $this->httpClientFactory->fromOptions(['base_uri' => $item['patch']]);
      try {
        $response = $client->request('get', $item['patch'], $options);
      }
      catch (GuzzleException) {
        throw new \RuntimeException('Can not connect top remote site.');
      }
      if ($response->getStatusCode() !== 200) {
        throw new \RuntimeException('Can\'t download patch ' . $item['patch']);
      }

      if ($this->shell($storage, 'patch -p1 <' . $options['sink'], $path)) {
        throw new \RuntimeException('Patch ' . $item['patch'] . ' failed.');
      }
    }
    return $this;
  }

}
