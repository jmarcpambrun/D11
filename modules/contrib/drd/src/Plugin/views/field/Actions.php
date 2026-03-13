<?php

namespace Drupal\drd\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\drd\ActionWidgetInterface;
use Drupal\drd\Entity\BaseInterface;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\field\BulkForm;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a DRD Entity operations bulk form element.
 *
 * @ViewsField("drd_entity_actions")
 */
final class Actions extends BulkForm {

  /**
   * The action widget service.
   *
   * @var \Drupal\drd\ActionWidgetInterface
   */
  protected ActionWidgetInterface $actionWidget;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): Actions {
    /** @var \Drupal\drd\Plugin\views\field\Actions $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->actionWidget = $container->get('drd.action.widget');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL): void {
    parent::init($view, $display, $options);
    /* @noinspection PhpUnhandledExceptionInspection */
    $this->actionWidget->setMode($this->getEntityType());
  }

  /**
   * {@inheritdoc}
   */
  public function viewsForm(mixed &$form, FormStateInterface $form_state): void {
    // Make sure we do not accidentally cache this form.
    // @todo Evaluate this again in https://www.drupal.org/node/2503009.
    $form['#cache']['max-age'] = 0;

    // Add the tableselect javascript.
    $form['#attached']['library'][] = 'core/drupal.tableselect';
    $use_revision = FALSE;
    $query = $this->view->getQuery();
    if ($query !== NULL) {
      $use_revision = array_key_exists('revision', $query->getEntityTableInfo());
    }

    // Only add the bulk form options and buttons if there are results.
    if (!empty($this->view->result)) {
      // Render checkboxes for all rows.
      /* @noinspection DuplicatedCode */
      $form[$this->options['id']]['#tree'] = TRUE;
      foreach ($this->view->result as $row_index => $row) {
        $entity = $this->getEntityTranslationByRelationship($this->getEntity($row), $row);

        $form[$this->options['id']][$row_index] = [
          '#type' => 'checkbox',
          // We are not able to determine a main "title" for each row, so we can
          // only output a generic label.
          '#title' => $this->t('Update this item'),
          '#title_display' => 'invisible',
          '#default_value' => !empty($form_state->getValue($this->options['id'])[$row_index]) ? 1 : NULL,
          '#return_value' => $this->calculateEntityBulkFormKey($entity, $use_revision),
        ];
      }

      // Ensure consistent container for filters/operations in the view header.
      $form['header'] = [
        '#type' => 'container',
        '#weight' => -100,
      ];

      // Build the bulk operations action widget for the header.
      // Allow themes to apply .container-inline on this separate container.
      $form['header'][$this->options['id']] = [];
      $this->actionWidget->buildForm($form['header'][$this->options['id']], $form_state, $this->options);
      $form['actions'] = $form['header'][$this->options['id']]['actions'];
    }
    else {
      // Remove the default actions build array.
      unset($form['actions']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function viewsFormValidate(mixed &$form, FormStateInterface $form_state): void {
    $this->actionWidget->validateForm($form, $form_state);
    parent::viewsFormValidate($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function viewsFormSubmit(mixed &$form, FormStateInterface $form_state): void {
    if ($form_state->get('step') !== 'views_form_views_form') {
      return;
    }

    // Filter only selected checkboxes.
    $selected = array_filter($form_state->getValue($this->options['id']));
    $entities = [];
    foreach ($selected as $bulk_form_key) {
      $entity = $this->loadEntityFromBulkFormKey($bulk_form_key);
      if ($entity instanceof BaseInterface) {
        $entities[$bulk_form_key] = $entity;
      }
    }

    $this->actionWidget
      ->setSelectedEntities($entities)
      ->submitForm($form, $form_state);
    $action = $this->actionWidget->getSelectedAction();

    $operation_definition = $action->getPlugin()->getPluginDefinition();
    if (!empty($operation_definition['confirm_form_route_name'])) {
      $options = [
        'query' => $this->getDestinationArray(),
      ];
      $form_state->setRedirect($operation_definition['confirm_form_route_name'], [], $options);
    }
    else {
      $count = $this->actionWidget->getExecutedCount();
      if ($count) {
        /** @var \Drupal\drd\Plugin\Action\BaseInterface $actionPlugin */
        $actionPlugin = $action->getPlugin();
        if ($actionPlugin->canBeQueued()) {
          $this->messenger()->addMessage($this->formatPlural($count, '@action was queued for @count item.', '@action was queued for @count items.', [
            '@action' => $action->label(),
          ]));
        }
        else {
          $this->messenger()->addMessage($this->formatPlural($count, '@action was executed for @count item.', '@action was executed for @count items.', [
            '@action' => $action->label(),
          ]));
        }
      }
    }
  }

}
