<?php

declare(strict_types=1);

namespace Drupal\mailer_policy\Plugin\EmailAdjuster;

use Drupal\Core\Asset\AssetOptimizerInterface;
use Drupal\Core\Asset\AssetResolverInterface;
use Drupal\Core\Asset\AttachedAssets;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mailer_policy\Attribute\EmailAdjuster;
use Drupal\mailer_policy\EmailAdjusterBase;
use Drupal\mailer_transport\AutowireTrait;
use Drupal\symfony_mailer\EmailInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

/**
 * Defines the Inline CSS Email Adjuster.
 */
#[EmailAdjuster(
  id: "mailer_inline_css",
  label: new TranslatableMarkup("Inline CSS"),
  description: new TranslatableMarkup("Add inline CSS."),
  weight: 900,
)]
class InlineCssEmailAdjuster extends EmailAdjusterBase implements ContainerFactoryPluginInterface {

  use AutowireTrait;

  /**
   * The CSS inliner.
   */
  protected CssToInlineStyles $cssInliner;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Asset\AssetResolverInterface $assetResolver
   *   The asset resolver.
   * @param \Drupal\Core\Asset\AssetOptimizerInterface $cssOptimizer
   *   The Drupal CSS optimizer.
   * @param mixed ...$args
   *   Parent constructor arguments.
   *
   * @internal
   */
  public function __construct(
    protected readonly AssetResolverInterface $assetResolver,
    #[Autowire(service: 'asset.css.optimizer')]
    protected readonly AssetOptimizerInterface $cssOptimizer,
    ...$args,
  ) {
    parent::__construct(...$args);
    $this->cssInliner = new CssToInlineStyles();
  }

  /**
   * {@inheritdoc}
   */
  public function postRender(EmailInterface $email): void {
    // Only inline CSS if we have an html body.
    if ($html_body = $email->getHtmlBody()) {
      $assets = (new AttachedAssets())->setLibraries($email->getLibraries());
      $css = '';
      foreach ($this->assetResolver->getCssAssets($assets, FALSE) as $asset) {
        if (($asset['type'] == 'file') && $asset['preprocess']) {
          // Optimize to process @import.
          $css .= $this->cssOptimizer->optimize($asset);
        }
        else {
          $css .= file_get_contents($asset['data']);
        }
      }

      if ($css) {
        $email->setHtmlBody($this->cssInliner->convert($html_body, $css));
      }
    }
  }

}
