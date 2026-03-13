<?php

namespace Drupal\drd\Plugin\Update\Build;

use Drupal\drd\Update\PluginBuildInterface;
use Drupal\drd\Update\PluginStorageInterface;

/**
 * Provides update build plugin to copy updates directly into the working dir.
 *
 * @Update(
 *  id = "direct",
 *  admin_label = @Translation("Copy directly"),
 * )
 */
class Direct extends Base {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'patches' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function build(PluginStorageInterface $storage, array $releases): PluginBuildInterface {
    foreach ($releases as $release) {
      $name = $release->getMajor()->getProject()->getName();
      if ($release->getProjectType() === 'core') {
        $destination = $storage->getWorkingDirectory();
      }
      else {
        $ext = ($release->getMajor()->getCoreVersion() >= 8) ? '.info.yml' : '.info';
        $destination = $this->find($storage->getWorkingDirectory(), $name . $ext);
        if (!$destination) {
          // We can't find the destination for the new release, let's ignore
          // for now.
          $storage->log('Destination not found for ' . $name);
          continue;
        }
      }
      $archive = $this->fileSystem->getTempDirectory() . DIRECTORY_SEPARATOR . $name . '.tar.gz';
      try {
        $client = $this->httpClientFactory->fromOptions(['base_uri' => $release->getDownloadLink()->toString()]);
        $response = $client->request('get');
      }
      catch (\Exception) {
        throw new \RuntimeException('Can not download archive for ' . $name);
      }
      if ($response->getStatusCode() !== 200) {
        throw new \RuntimeException('Update not available for ' . $name);
      }
      file_put_contents($archive, $response->getBody()->getContents());
      if ($this->shell($storage, 'tar xf ' . $archive . ' -C ' . $destination . ' --strip-components=1')) {
        // An error occured, we stop further processing.
        throw new \RuntimeException('Error while extracting ' . $archive);
      }
    }

    $this->changed = TRUE;
    return $this;
  }

  /**
   * Find a file in a directory tree recursively.
   *
   * @param string $dir
   *   The directory name.
   * @param string $filename
   *   The file name.
   *
   * @return string|bool
   *   The directory name in which the file was found or FALSE if the file
   *   couldn't be found in the tree.
   */
  private function find(string $dir, string $filename): bool|string {
    if (file_exists($dir . DIRECTORY_SEPARATOR . $filename)) {
      return $dir;
    }
    $files = iterator_to_array(new \FilesystemIterator($dir, \FilesystemIterator::CURRENT_AS_PATHNAME | \FilesystemIterator::SKIP_DOTS), FALSE);
    foreach ($files as $file) {
      if (is_dir($file)) {
        $find = $this->find($file, $filename);
        if ($find) {
          return $find;
        }
      }
    }
    return FALSE;
  }

}
