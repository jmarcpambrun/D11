<?php

namespace Drupal\upgrade_status;

use Drupal\Core\Site\Settings;

/**
 * Reads the directories of projects for the analyzers.
 *
 * Each analyzer walks the directories of a project with its own rules, and
 * some analyzers do that several times. While listings are kept, each
 * directory is read once for all analyzers.
 */
class FileFinder {

  /**
   * Whether the listings of directories are kept.
   */
  protected bool $keepListings = FALSE;

  /**
   * The names in the directories that were read, keyed by directory path.
   *
   * @var array<string, list<string>>
   */
  protected array $names = [];

  /**
   * The same names in alphabetical order, keyed by directory path.
   *
   * @var array<string, list<string>>
   */
  protected array $sortedNames = [];

  /**
   * Whether the paths that were checked are directories, keyed by path.
   *
   * @var array<string, bool>
   */
  protected array $directories = [];

  /**
   * Starts or stops keeping the listings of directories.
   *
   * Listings are only kept while one project is analyzed, so files that change
   * between two analyses are found, and the memory use stays low.
   *
   * @param bool $keep
   *   TRUE to keep the listings from now on. FALSE to stop. Both forget the
   *   listings kept so far.
   */
  public function keepListings(bool $keep): void {
    $this->keepListings = $keep;
    $this->names = $this->sortedNames = $this->directories = [];
  }

  /**
   * Gets the names of the files and directories in a directory.
   *
   * @param string $path
   *   The path of the directory.
   * @param bool $sorted
   *   TRUE for alphabetical order, FALSE for the order of the file system.
   *
   * @return list<string>
   *   The names, without "." and "..". Empty if the directory cannot be read.
   */
  public function getNames(string $path, bool $sorted = TRUE): array {
    if ($sorted && isset($this->sortedNames[$path])) {
      return $this->sortedNames[$path];
    }
    $names = $this->names[$path] ?? NULL;
    if ($names === NULL) {
      // A directory that cannot be read has no files to analyze.
      $names = array_values(array_diff(@scandir($path, SCANDIR_SORT_NONE) ?: [], ['.', '..']));
      if ($this->keepListings) {
        $this->names[$path] = $names;
      }
    }
    if ($sorted) {
      sort($names, SORT_STRING);
      if ($this->keepListings) {
        $this->sortedNames[$path] = $names;
      }
    }
    return $names;
  }

  /**
   * Checks whether a path is a directory.
   *
   * @param string $path
   *   The path to check.
   *
   * @return bool
   *   TRUE for a directory or a link to one.
   */
  public function isDirectory(string $path): bool {
    if (!$this->keepListings) {
      return is_dir($path);
    }
    return $this->directories[$path] ??= is_dir($path);
  }

  /**
   * Gets the paths in a directory, like glob() does for "*".
   *
   * @param string $path
   *   The path of the directory.
   *
   * @return list<string>
   *   The paths of the files and directories in alphabetical order. Names
   *   that start with a dot are left out.
   */
  public function getPaths(string $path): array {
    $paths = [];
    foreach ($this->getNames($path) as $name) {
      if ($name[0] !== '.') {
        $paths[] = "$path/$name";
      }
    }
    return $paths;
  }

  /**
   * Gets the paths of the files in a directory.
   *
   * @param string $path
   *   The path of the directory.
   * @param string $ending
   *   The ending of the file names, like ".info.yml". Empty for all files.
   *
   * @return list<string>
   *   The paths of the files in alphabetical order. Names that start with a
   *   dot are left out.
   */
  public function getFiles(string $path, string $ending = ''): array {
    $files = [];
    foreach ($this->getNames($path) as $name) {
      if ($name[0] !== '.' && ($ending === '' || str_ends_with($name, $ending)) && !$this->isDirectory("$path/$name")) {
        $files[] = "$path/$name";
      }
    }
    return $files;
  }

  /**
   * Gets the paths of the directories in a directory.
   *
   * @param string $path
   *   The path of the directory.
   * @param bool $sorted
   *   TRUE for alphabetical order, FALSE for the order of the file system.
   *
   * @return list<string>
   *   The paths of the directories. Names that start with a dot are left out.
   */
  public function getDirectories(string $path, bool $sorted = FALSE): array {
    $directories = [];
    foreach ($this->getNames($path, $sorted) as $name) {
      if ($name[0] !== '.' && $this->isDirectory("$path/$name")) {
        $directories[] = "$path/$name";
      }
    }
    return $directories;
  }

  /**
   * Finds files in a directory and in the directories below it.
   *
   * @param string $path
   *   The path of the directory.
   * @param callable(string): bool $is_wanted
   *   Gets the name of a file, and returns whether to include the file.
   * @param list<string> $skip_directories
   *   The names of directories to leave out.
   * @param bool $hidden_directories
   *   FALSE to leave out the directories with names that start with a dot.
   *
   * @return list<string>
   *   The paths of the files, in the order of the file system. Links to
   *   directories are not followed.
   */
  public function findFiles(string $path, callable $is_wanted, array $skip_directories = [], bool $hidden_directories = TRUE): array {
    $files = [];
    foreach ($this->getNames($path, FALSE) as $name) {
      $file = "$path/$name";
      if (!$this->isDirectory($file)) {
        if ($is_wanted($name)) {
          $files[] = $file;
        }
      }
      elseif (($hidden_directories || $name[0] !== '.') && !in_array($name, $skip_directories, TRUE) && !is_link($file)) {
        $files = array_merge($files, $this->findFiles($file, $is_wanted, $skip_directories, $hidden_directories));
      }
    }
    return $files;
  }

  /**
   * Finds the Twig templates of an extension.
   *
   * @param string $path
   *   The path of the extension from the Drupal root.
   *
   * @return array<string, string>
   *   The paths of the templates, keyed by the paths from the Drupal root,
   *   which are the names of the templates in messages. Templates in hidden
   *   directories and in the directories of the file_scan_ignore_directories
   *   setting are left out.
   */
  public function findTemplates(string $path): array {
    $templates = [];
    $files = $this->findFiles(
      DRUPAL_ROOT . '/' . $path,
      static fn (string $name): bool => $name[0] !== '.' && str_ends_with($name, '.html.twig'),
      Settings::get('file_scan_ignore_directories', []),
      FALSE,
    );
    foreach ($files as $file) {
      $templates[substr($file, strlen(DRUPAL_ROOT) + 1)] = $file;
    }
    return $templates;
  }

}
