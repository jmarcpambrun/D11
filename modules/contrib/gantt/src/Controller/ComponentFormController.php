<?php

namespace Drupal\gantt\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class definition for ComponentFormController.
 */
class ComponentFormController extends ControllerBase {

  use AjaxHelperTrait;

  /**
   * Form builder will be used via Dependency Injection.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a ContentEntityForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository service.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder service.
   */
  public function __construct(protected EntityRepositoryInterface $entityRepository, FormBuilderInterface $form_builder) {
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('entity.repository'),
      $container->get('form_builder'),
    );
  }

  /**
   * Provides the paragraphs item submission form.
   *
   * @param \Drupal\paragraphs\Entity\ParagraphsType $paragraph_type
   *   The paragraphs entity for the paragraph item.
   * @param string $entity_type
   *   The type of the entity hosting the paragraph item.
   * @param string $entity_field
   *   Entity field store paragraphs.
   * @param int $entity_id
   *   The id of the entity hosting the paragraph item.
   *
   * @return array
   *   A paragraph item submission form.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function addForm($paragraph_type, $entity_type, $entity_field, $entity_id) {
    $paragraph = $this->newParagraph($paragraph_type);
    $load_form = 'Drupal\paragraphs_table\Form\ParagraphAddForm';
    $form_state = (new FormState())
      ->addBuildInfo('args', [
        $paragraph,
        $entity_type,
        $entity_field,
        $entity_id,
      ]);
    $form = $this->formBuilder->buildForm(
      $load_form,
      $form_state,
      $paragraph,
      $entity_type,
      $entity_field,
      $entity_id,
    );

    if ($this->isAjax()) {
      $param = [$paragraph_type->id(), $entity_type, $entity_field, $entity_id];
      $id = "#" . HTML::getId(implode('-', $param));
      $response = new AjaxResponse();
      $response->addCommand(new ReplaceCommand($id, $form));
      return $response;
    }
    return $form;

  }

  /**
   * Creates a new, empty paragraph empty of the provided type.
   *
   * {@inheritDoc}
   */
  protected function newParagraph($paragraph_type) {
    $entityTypeManager = $this->entityTypeManager();
    $entity_type = $entityTypeManager->getDefinition('paragraph');
    $bundle_key = $entity_type->getKey('bundle');
    /** @var \Drupal\paragraphs\ParagraphInterface $paragraph_entity */
    $paragraph = $entityTypeManager->getStorage('paragraph')
      ->create([$bundle_key => $paragraph_type->id()]);
    return $paragraph;
  }

  /**
   * The _title_callback for the paragraphs_item.add route.
   *
   * @param mixed $paragraph_type
   *   The current paragraphs_type.
   *
   * @return string
   *   The page title.
   */
  public function addPageTitle($paragraph_type) {
    return $this->t('Create @label', ['@label' => $paragraph_type->label()]);
  }

  /**
   * Displays a paragraphs item.
   *
   * @param mixed $paragraph
   *   The Paragraph item we are displaying.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function page($paragraph) {
    return $this->buildPage($paragraph);
  }

  /**
   * The _title_callback for the paragraphs_item.view route.
   *
   * @param mixed $paragraph
   *   The current paragraphs_item.
   *
   * @return string
   *   The page title.
   */
  public function pageTitle($paragraph = NULL) {
    return $this->entityRepository->getTranslationFromContext($paragraph)
      ->label() . ' #' . $paragraph->id();
  }

  /**
   * Builds a paragraph item page render array.
   *
   * @param mixed $paragraph
   *   The field paragraph item we are displaying.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  protected function buildPage(mixed $paragraph): array {
    return [
      'paragraph' => $this->entityTypeManager()
        ->getViewBuilder('paragraph')
        ->view($paragraph),
    ];
  }

}
