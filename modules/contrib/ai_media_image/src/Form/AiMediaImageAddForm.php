<?php

namespace Drupal\ai_media_image\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;
use Drupal\media_library\Form\AddFormBase;
use Drupal\media_library\MediaLibraryUiBuilder;
use Drupal\media_library\OpenerResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates a form to create media entities from AI generated images.
 *
 * @internal
 *   Form classes are internal.
 */
class AiMediaImageAddForm extends AddFormBase {

  use AiMediaImageFormTrait;

  /**
   * Constructs a new media image form.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\media_library\MediaLibraryUiBuilder $library_ui_builder
   *   The media library UI builder.
   * @param \Drupal\media_library\OpenerResolverInterface $opener_resolver
   *   The opener resolver.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MediaLibraryUiBuilder $library_ui_builder, ?OpenerResolverInterface $opener_resolver = NULL, ?ConfigFactoryInterface $config_factory = NULL) {
    parent::__construct($entity_type_manager, $library_ui_builder, $opener_resolver);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('media_library.ui_builder'),
      $container->get('media_library.opener_resolver'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return $this->getBaseFormId() . '_ai_media_image';
  }

  /**
   * {@inheritdoc}
   */
  protected function getMediaType(FormStateInterface $form_state) {
    if ($this->mediaType) {
      return $this->mediaType;
    }

    // Validate an image field type.
    $media_type = parent::getMediaType($form_state);
    // The file upload form only supports media types which use a file field as
    // a source field.
    $field_definition = $media_type->getSource()->getSourceFieldDefinition($media_type);
    if (!is_a($field_definition->getClass(), FileFieldItemList::class, TRUE)) {
      throw new \InvalidArgumentException('Can only add media types which use a file field as a source field.');
    }
    return $media_type;
  }

  /**
   * {@inheritdoc}
   */
  public function buildInputElement(array $form, FormStateInterface $form_state) {
    return $this->buildAiForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->processGeneratedFile($form, $form_state);
  }

}
