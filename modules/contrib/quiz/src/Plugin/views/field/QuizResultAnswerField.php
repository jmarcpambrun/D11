<?php

namespace Drupal\quiz\Plugin\views\field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\quiz\Entity\QuizQuestion;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\field\PrerenderList;
use Drupal\views\ViewExecutable;
use Drupal\quiz\Services\QuizHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * QuizResultAnswerField handler.
 *
 * Provide a field that pulls answers from a single question on a
 * single quiz result.
 *
 * @ingroup views_field_handlers
 */
#[ViewsField("quiz_result_answer")]
class QuizResultAnswerField extends PrerenderList {

  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected EntityTypeManagerInterface $entityTypeManager, protected QuizHelper $quizHelper) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('quiz.helper'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL): void {
    parent::init($view, $display, $options);

    $this->additional_fields['result_id'] = [
      'table' => 'quiz_result',
      'field' => 'result_id',
    ];
  }

  /**
   * Add this term to the query.
   */
  public function query(): void {
    $this->addAdditionalFields();
    $this->field_alias = $this->aliases['result_id'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    $form['qqid'] = [
      '#title' => $this->t('Question ID'),
      '#type' => 'textfield',
      '#default_value' => $this->options['qqid'],
    ];

    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();

    $options['qqid'] = [
      'default' => NULL,
    ];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(&$values): void {
    $this->items = [];

    $result_ids = [];
    foreach ($values as $value) {
      $result_ids[] = $value->result_id;
    }

    $qqid = $this->options['qqid'];
    $question = QuizQuestion::load($qqid);
    $info = $this->quizHelper->getQuestionTypes();
    $className = $info[$question->bundle()]['handlers']['response'];

    if ($result_ids) {
      $raids = $this->entityTypeManager->getStorage('quiz_result_answer')->getQuery()
        ->accessCheck(FALSE)
        ->condition('question_id', $qqid)
        ->condition('result_id', $result_ids, 'in')
        ->execute();
      if ($raids) {
        $this->items = $className::viewsGetAnswers($raids);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  // phpcs:ignore Drupal.NamingConventions.ValidFunctionName.ScopeNotCamelCaps, Drupal.Commenting.FunctionComment.Missing
  public function render_item($count, $item) {
    return $item['answer'];
  }

}
