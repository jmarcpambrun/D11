<?php

namespace Drupal\gantt\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\field_group\FormatterHelper;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphsTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AddComponentForm.
 *
 * Builds the form for add a new paragraph.
 */
class AddComponentForm extends FormBase {

  /**
   * The paragraphs.
   *
   * @var \Drupal\paragraphs\ParagraphInterface
   */
  protected $paragraph;

  /**
   * The paragraph type.
   *
   * @var \Drupal\paragraphs\Entity\ParagraphsType
   */
  protected $paragraphType;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'paragraphs_add_form';
  }

  /**
   * Constructs a component form object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityRepositoryInterface $entityRepository,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ParagraphsType|null $paragraph_type = NULL, $entity_type = NULL, $entity_field = NULL, $entity_id = 0) {
    $this->paragraph = $this->newParagraph($paragraph_type);
    $this->initFormLangcodes($form_state);
    $display = EntityFormDisplay::collectRenderDisplay($this->paragraph, 'default');

    $display->buildForm($this->paragraph, $form, $form_state);
    $this->paragraphType = $this->paragraph->getParagraphType();
    $query = $this->getRequest()->query;
    $parent = $query->get('parent');
    $parent_field = $query->get('parent_field');
    if (!empty($parent) && !empty($parent_field) && is_numeric($parent)) {
      $form_state->set($parent_field, $parent);
      if (!empty($form[$parent_field])) {
        $form[$parent_field]["widget"][0]["value"]["#default_value"] = $parent;
      }
    }
    $form_state->set('field_name', $paragraph_type->id);
    $form_state->set('entity_field', $entity_field);
    if (!$form_state->has('entity')) {
      $entity = $this->entityTypeManager->getStorage($entity_type)
        ->load($entity_id);
      $form_state->set('entity', $entity);
    }

    $form += [
      '#title' => $this->formTitle(),
      '#paragraph' => $this->paragraph,
      '#display' => $display,
      '#tree' => TRUE,
      '#after_build' => [
        [$this, 'afterBuild'],
      ],
      '#prefix' => '<div id="' . $this->getFormId() . '">',
      '#suffix' => '</div>',
      'actions' => [
        '#weight' => 100,
        '#type' => 'actions',
        'submit' => [
          '#type' => 'submit',
          '#weight' => 100,
          '#value' => $this->t('Save'),
          '#attributes' => [
            'class' => ['gantt-btn--save'],
            'data-disable-refocus' => 'true',
          ],
        ],
        'cancel' => [
          '#type' => 'button',
          '#weight' => 200,
          '#value' => $this->t('Cancel'),
          '#ajax' => [
            'callback' => '::cancel',
            'progress' => 'none',
          ],
          '#attributes' => [
            'class' => [
              'dialog-cancel',
              'tabs-btn--cancel',
            ],
          ],
        ],
      ],
    ];
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    // Support for Field Group module based on Paragraphs module.
    // @todo Remove as part of https://www.drupal.org/node/2640056
    if ($this->moduleHandler->moduleExists('field_group')) {
      $context = [
        'entity_type' => $this->paragraph->getEntityTypeId(),
        'bundle' => $this->paragraph->bundle(),
        'entity' => $this->paragraph,
        'context' => 'form',
        'display_context' => 'form',
        'mode' => $display->getMode(),
      ];
      // phpcs:ignore
      field_group_attach_groups($form, $context); // @phpstan-ignore-line
      // @phpstan-ignore-next-line
      if (method_exists(FormatterHelper::class, 'formProcess')) {
        // @phpstan-ignore-next-line
        $form['#process'][] = [FormatterHelper::class, 'formProcess'];
      }
      elseif (function_exists('field_group_form_pre_render')) {
        $form['#pre_render'][] = 'field_group_form_pre_render';
      }
      elseif (function_exists('field_group_form_process')) {
        $form['#process'][] = 'field_group_form_process';
      }
    }

    return $form;
  }

  /**
   * After build callback fixes issues with data-drupal-selector.
   *
   * See https://www.drupal.org/project/drupal/issues/2897377
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element.
   */
  public function afterBuild(array $element, FormStateInterface $form_state) {
    $parents = array_merge($element['#parents'], [$this->getFormId()]);
    $unprocessed_id = 'edit-' . implode('-', $parents);
    $element['#attributes']['data-drupal-selector'] = Html::getId($unprocessed_id);
    $element['#dialog_id'] = $unprocessed_id . '-dialog';
    return $element;
  }

  /**
   * Create the form title.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The form title.
   */
  protected function formTitle() {
    return $this->t('Add @type', [
      '@type' => $this->paragraph->getParagraphType()
        ->label(),
    ]);
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $display = $form['#display'];

    $paragraph = clone $this->paragraph;
    $paragraph->getAllBehaviorSettings();

    $paragraph->setNeedsSave(TRUE);
    $display->extractFormValues($paragraph, $form, $form_state);

    $paragraph->isNew();
    $paragraph->save();

    $entity = $form_state->get('entity');
    if (empty($entity)) {
      $buildInfo = $form_state->getBuildInfo();
      $entity_type = $buildInfo['arg'][1];
      $entity_id = end($buildInfo['arg']);
      $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    }
    $entity_field = $form_state->get('entity_field');
    $current = $entity->get($entity_field)->getValue();
    $current[] = [
      'target_id' => $paragraph->id(),
      'target_revision_id' => $paragraph->getRevisionId(),
    ];
    $entity->set($entity_field, $current);
    $entity->save();
    $form_state->disableRedirect(FALSE);
    $form_state->setRedirectUrl(Url::fromRoute('<current>'));
  }

  /**
   * Creates a new, empty paragraph empty of the provided type.
   *
   * {@inheritDoc}
   */
  protected function newParagraph(ParagraphsTypeInterface $paragraph_type) {
    $entity_type = $this->entityTypeManager->getDefinition('paragraph');
    $bundle_key = $entity_type->getKey('bundle');
    /** @var \Drupal\paragraphs\ParagraphInterface $paragraph_entity */
    return $this->entityTypeManager->getStorage('paragraph')
      ->create([$bundle_key => $paragraph_type->id()]);
  }

  /**
   * Builds the paragraph component using submitted form values.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return \Drupal\paragraphs\Entity\Paragraph
   *   The paragraph entity.
   */
  public function buildParagraphComponent(array $form, FormStateInterface $form_state) {
    /** @var Drupal\Core\Entity\Entity\EntityFormDisplay $display */
    $display = $form['#display'];

    $paragraph = clone $this->paragraph;
    $paragraph->getAllBehaviorSettings();

    $paragraph->setNeedsSave(TRUE);
    $display->extractFormValues($paragraph, $form, $form_state);
    return $paragraph;
  }

  /**
   * Initializes form language code values.
   *
   * See Drupal\Core\Entity\ContentEntityForm.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function initFormLangcodes(FormStateInterface $form_state) {
    // Store the entity default language to allow checking whether the form is
    // dealing with the original entity or a translation.
    if (!$form_state->has('entity_default_langcode')) {
      $form_state->set('entity_default_langcode',
        $this->paragraph->getUntranslated()->language()->getId()
      );
    }

    // This value might have been explicitly populated to work with a particular
    // entity translation. If not we fall back to the most proper language based
    // on contextual information.
    if (!$form_state->has('langcode')) {

      // Imply a 'view' operation to ensure users edit entities in the same
      // language they are displayed. This allows to keep contextual editing
      // working also for multilingual entities.
      $form_state->set('langcode',
        $this->entityRepository->getTranslationFromContext($this->paragraph)
          ->language()->getId());
    }
  }

}
