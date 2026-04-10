<?php

namespace Drupal\gantt\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Paragraph Edit Form class.
 */
class EditComponentForm extends ContentEntityForm {

  /**
   * The content translation manager.
   *
   * @var \Drupal\content_translation\ContentTranslationManagerInterface
   */
  protected ?ContentTranslationManagerInterface $translationManager;

  /**
   * Constructs a paragraphs edit form object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module Handler service.
   * @param \Drupal\content_translation\ContentTranslationManagerInterface|null $content_translation_manager
   *   The translation manager.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, protected LanguageManagerInterface $languageManager, ModuleHandlerInterface $moduleHandler, ?ContentTranslationManagerInterface $content_translation_manager = NULL) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->translationManager = $content_translation_manager;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('language_manager'),
      $container->get('module_handler'),
      $container->has('content_translation.manager') ? $container->get('content_translation.manager') : NULL,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'paragraphs_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseFormId() {
    return $this->getFormId();
  }

  /**
   * {@inheritdoc}
   *
   * Overridden to store the root parent entity.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?EntityInterface $paragraph = NULL) {
    if (empty($this->entity)) {
      $this->setEntity($paragraph);
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function init(FormStateInterface $form_state) {
    if ($this->entity->isTranslatable()) {
      $langcode = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
      $form_state->set('langcode', $langcode);

      if (!$this->entity->hasTranslation($langcode)) {
        $manager = $this->translationManager;

        $translation_source = $this->entity;

        $host = $this->entity->getParentEntity();
        $host_source_langcode = $host->language()->getId();
        if ($host->hasTranslation($langcode)) {
          $host = $host->getTranslation($langcode);
          $host_source_langcode = $manager->getTranslationMetadata($host)->getSource();
        }

        if ($this->entity->hasTranslation($host_source_langcode)) {
          $translation_source = $this->entity->getTranslation($host_source_langcode);
        }

        $this->entity = $this->entity->addTranslation($langcode, $translation_source->toArray());
        $manager->getTranslationMetadata($this->entity)->setSource($translation_source->language()->getId());
      }
    }

    parent::init($form_state);
  }

  /**
   * The _title_callback for the paragraphs_item.view route.
   *
   * {@inheritDoc}
   */
  public function pageTitle(Paragraph $paragraph) {
    return $this->entityRepository->getTranslationFromContext($paragraph)->label() . ' #' . $paragraph->id();
  }

}
