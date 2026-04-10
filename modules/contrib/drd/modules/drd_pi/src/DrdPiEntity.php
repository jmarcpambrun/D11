<?php

namespace Drupal\drd_pi;

use Drupal\drd\Entity\BaseInterface;

/**
 * Provides abstract class for platform based entities.
 */
abstract class DrdPiEntity implements DrdPiEntityInterface {

  /**
   * Account to which this entity is attached.
   *
   * @var DrdPiAccountInterface
   */
  protected DrdPiAccountInterface $account;

  /**
   * Label of this entity.
   *
   * @var string
   */
  protected string $label;

  /**
   * ID of this entity.
   *
   * @var string
   */
  protected string $id;

  /**
   * DrdEntity which matches this DrdPiEntity.
   *
   * @var \Drupal\drd\Entity\BaseInterface|null
   */
  protected ?BaseInterface $entity = NULL;

  /**
   * Header values for the DRD entity.
   *
   * @var array
   */
  protected array $header = [];

  /**
   * DRD logging service for console output.
   *
   * @var \Drupal\drd\Logging
   */
  protected mixed $logging;

  /**
   * The http client factory.
   *
   * @var \Drupal\Core\Http\ClientFactory
   */
  protected mixed $httpClientFactory;

  /**
   * Construct a DrdPiEntity object.
   *
   * @param DrdPiAccountInterface $account
   *   Account to which this entity is attached.
   * @param string $label
   *   Label of this entity.
   * @param string $id
   *   ID of this entity.
   */
  public function __construct(DrdPiAccountInterface $account, string $label, string $id) {
    $this->account = $account;
    $this->label = $label;
    $this->id = $id;
    // @phpstan-ignore-next-line
    $this->logging = \Drupal::service('drd.logging');
    // @phpstan-ignore-next-line
    $this->httpClientFactory = \Drupal::service('http_client_factory');
  }

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return $this->label;
  }

  /**
   * {@inheritdoc}
   */
  public function setDrdEntity(BaseInterface $entity): DrdPiEntityInterface {
    $this->entity = $entity;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDrdEntity(): BaseInterface {
    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function hasDrdEntity(): bool {
    return $this->entity !== NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function update(): DrdPiEntityInterface {
    if ($this->hasDrdEntity()) {
      $changed = FALSE;
      $foundAuth = FALSE;
      $hasAuth = isset($this->header['Authorization']);
      $headerItems = [];
      /** @var \Drupal\key_value_field\Plugin\Field\FieldType\KeyValueItem $item */
      foreach ($this->entity->get('header') as $item) {
        $value = $item->getValue();
        $isAuth = ($value['key'] === 'Authorization');
        if ($isAuth && !$hasAuth) {
          // Ignore this item.
          $changed = TRUE;
        }
        else {
          if ($isAuth) {
            $foundAuth = TRUE;
            if ($value['value'] !== $this->header['Authorization']) {
              $value['value'] = $this->header['Authorization'];
              $changed = TRUE;
            }
          }
          $headerItems[] = $value;
        }
      }
      if ($hasAuth && !$foundAuth) {
        $headerItems[] = [
          'key' => 'Authorization',
          'value' => $this->header['Authorization'],
        ];
        $changed = TRUE;
      }
      if ($changed) {
        $this->entity->set('header', $headerItems);
        $this->entity->save();
      }
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setHeader(string $key, string $value): DrdPiEntityInterface {
    $this->header[$key] = $value;
    return $this;
  }

}
