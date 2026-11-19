<?php

namespace Drupal\upgrade_status;

use Drupal\Core\Extension\Extension;
use Drupal\Core\Template\TwigEnvironment;
use Twig\Error\SyntaxError;
use Twig\Source;
use Twig\Util\TemplateDirIterator;

/**
 * Twig Deprecation Analyzer.
 */
class TwigDeprecationAnalyzer implements AnalyzerInterface {

  use ExtensionAnalyzerTrait;

  /**
   * The Twig environment.
   *
   * @var \Drupal\Core\Template\TwigEnvironment
   */
  protected $twigEnvironment;

  /**
   * The file finder.
   *
   * @var \Drupal\upgrade_status\FileFinder
   */
  protected $fileFinder;

  /**
   * Constructs a new Twig deprecation analyzer.
   *
   * @param \Drupal\Core\Template\TwigEnvironment $twig_environment
   *   The Twig environment.
   * @param \Drupal\upgrade_status\FileFinder $file_finder
   *   The file finder.
   */
  public function __construct(TwigEnvironment $twig_environment, FileFinder $file_finder) {
    $this->twigEnvironment = $twig_environment;
    $this->fileFinder = $file_finder;
  }

  /**
   * Analyzes theme functions in an extension.
   *
   * This is based on Twig\Util\DeprecationCollector which is a final class
   * and thus cannot be extended. While it did find non-twig runtime deprecated
   * errors, it did not gave us the file/line information, so we needed to copy
   * and modify that behavior. We folded in our twig file/line parsing inline
   * then to make it simpler.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   The extension to be analyzed.
   *
   * @return \Drupal\upgrade_status\DeprecationMessage[]
   *   The deprecation message.
   */
  public function analyze(Extension $extension): array {
    $deprecations = [];

    set_error_handler(function ($type, $msg, $file, $line) use (&$deprecations) {
      if (\E_USER_DEPRECATED === $type) {
        if (preg_match('!([a-zA-Z0-9\_\-\/]+.html\.twig)!', $msg, $file_matches)) {
          // Caught a Twig syntax based deprecation, record file name and line
          // number from the message we caught.
          preg_match('/(\d+).?$/', $msg, $line_matches);
          $msg = preg_replace('! in (.+)\.twig at line \d+\.!', '.', $msg);
          $msg .= ' See https://drupal.org/node/3071078.';
          $deprecations[] = new DeprecationMessage(
            $msg,
            $file_matches[1],
            $line_matches[1] ?? 0,
            'TwigDeprecationAnalyzer'
          );
        }
        else {
          // Skip deprecations from vendor paths (e.g. Symfony's
          // DebugClassLoader reporting on Drupal core Twig classes).
          if (str_contains($file, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) {
            return;
          }
          // Otherwise record the deprecation from the original caught error.
          $deprecations[] = new DeprecationMessage(
            $msg,
            $file,
            $line,
            'TwigDeprecationAnalyzer'
          );
        }
      }
    });

    $iterator = new TemplateDirIterator(
      new \ArrayIterator($this->fileFinder->findTemplates($extension->getPath()))
    );
    foreach ($iterator as $name => $contents) {
      try {
        $this->twigEnvironment->parse($this->twigEnvironment->tokenize(new Source($contents, $name)));
      }
      catch (SyntaxError $e) {
        // Report twig syntax error which stops us from parsing it.
        $deprecations[] = new DeprecationMessage(
          'Twig template ' . $name . ' contains a syntax error and cannot be parsed: ' . $e->getRawMessage(),
          $name,
          $e->getTemplateLine(),
          'TwigDeprecationAnalyzer'
        );
      }
      catch (\Throwable $e) {
        // Other errors, like from a Twig extension of the site, stop the
        // parsing of this template, but not the scan of the other templates.
        $deprecations[] = new DeprecationMessage(
          'Twig template ' . $name . ' cannot be parsed: ' . get_class($e) . ': ' . $e->getMessage(),
          $name,
          0,
          'TwigDeprecationAnalyzer'
        );
      }
    }
    restore_error_handler();

    // Ensure files are sorted properly.
    usort($deprecations, static function (DeprecationMessage $a, DeprecationMessage $b) {
      return strcmp($a->getFile(), $b->getFile());
    });
    return $deprecations;
  }

}
