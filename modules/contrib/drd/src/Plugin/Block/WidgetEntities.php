<?php

namespace Drupal\drd\Plugin\Block;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Abstract class for DRD entity widget blocks.
 */
abstract class WidgetEntities extends WidgetBase {

  /**
   * Indicates whether the current user has the access permission.
   *
   * @var bool
   */
  protected bool $accessView = FALSE;
  /**
   * Indicates whether the current user has the create permission.
   *
   * @var bool
   */
  protected bool $accessCreate = FALSE;

  /**
   * Get the DRD entity type.
   *
   * @return string
   *   The DRD entity type.
   */
  abstract protected function type(): string;

  /**
   * {@inheritdoc}
   */
  protected function init(): void {
    $this->accessView = $this->currentUser->hasPermission('drd.view published ' . $this->type() . ' entities');
    $this->accessCreate = $this->currentUser->hasPermission('drd.add ' . $this->type() . ' entities');
  }

  /**
   * {@inheritdoc}
   */
  protected function content(): array|string|TranslatableMarkup {
    $query = $this->database->select('drd_' . $this->type(), 'h')
      ->condition('h.status', '1');
    $active = (int) $query
      ->countQuery()
      ->execute()
      ->fetchField();
    $query = $this->database->select('drd_' . $this->type(), 'h')
      ->condition('h.status', '0');
    $inactive = $query
      ->countQuery()
      ->execute()
      ->fetchField();

    $args = ['@type' => $this->type()];
    if ($active === 0) {
      $message = 'You currently have no active @type in DRD!';
      $name = $this->t('Create your first @type', $args);
    }
    else {
      $name = $this->t('Create another @type', $args);
      $args['@countactive'] = $active;
      $message = '<p class="message">You have @countactive active @types.</p>';
      if ($this->accessView) {
        $args['@list'] = (new Url('entity.drd_' . $this->type() . '.collection'))->toString();
        $message .= '<p>Get all the details in your <a href="@list">@type list</a>.';
      }
    }
    if ($inactive > 0) {
      $args['@countinactive'] = $inactive;
      $message .= '<p>In addition, there are @countinactive inactive @types available as well.</p>';
    }

    if ($this->accessCreate && $this->type() !== 'domain') {
      $message .= '<p class="action"><span class="button">' .
        $this->linkGenerator->generate($name, new Url('entity.drd_' . $this->type() . '.add_form')) . '</span></p>';
    }

    return new FormattableMarkup($message, $args);
  }

}
