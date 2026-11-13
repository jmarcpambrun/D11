<?php

namespace Drupal\group\Plugin\views\access;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\group\Access\GroupPermissionHandlerInterface;
use Drupal\views\Attribute\ViewsAccess;
use Drupal\views\Plugin\views\access\AccessPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Access plugin that provides group permission-based access control.
 *
 * @todo Remove the 'group_permission' option and its schema in Group 5.
 *
 * @ingroup views_access_plugins
 */
#[ViewsAccess(
  id: 'group_permission',
  title: new TranslatableMarkup('Group permission'),
  help: new TranslatableMarkup('Instructions on how to determine view access by group permission'),
)]
class GroupPermission extends AccessPluginBase implements CacheableDependencyInterface {

  /**
   * {@inheritdoc}
   */
  protected $usesOptions = TRUE;

  /**
   * The group entity from the route.
   *
   * @var \Drupal\group\Entity\GroupInterface
   */
  protected $group;

  /**
   * The group context from the route.
   *
   * @var \Drupal\Core\Plugin\Context\ContextInterface
   */
  protected $context;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected GroupPermissionHandlerInterface $permissionHandler,
    protected ModuleExtensionList $moduleHandler,
    ContextProviderInterface $context_provider,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $contexts = $context_provider->getRuntimeContexts(['group']);
    $this->context = $contexts['group'];
    $this->group = $this->context->getContextValue();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('group.permissions'),
      $container->get('extension.list.module'),
      $container->get('group.group_route_context')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    if (!isset($this->options['group_permission'])) {
      return TRUE;
    }

    if (!empty($this->group)) {
      return $this->group->hasPermission($this->options['group_permission'], $account);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route) {
    if (!isset($this->options['group_permission'])) {
      $route->setRequirement('_access', 'TRUE');
      return;
    }

    $route->setRequirement('_group_permission', $this->options['group_permission']);

    // Upcast any %group path key the user may have configured so the
    // '_group_permission' access check will receive a properly loaded group.
    $route->setOption('parameters', ['group' => ['type' => 'entity:group']]);
  }

  /**
   * {@inheritdoc}
   */
  public function summaryTitle() {
    if (!isset($this->options['group_permission'])) {
      return $this->t('Informational only');
    }

    $permissions = $this->permissionHandler->getPermissions(TRUE);
    if (isset($permissions[$this->options['group_permission']])) {
      return $permissions[$this->options['group_permission']]['title'];
    }

    return $this->t($this->options['group_permission']);
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['group_permission'] = ['default' => NULL];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    if (isset($this->options['group_permission'])) {
      $form['deprecated'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('This functionality is deprecated, refer to the how-to below for instructions.'),
      ];

      $form['deprecated']['converted']['#markup'] = $this->t('
        <p>When you have converted your view to use the approach outlined
        below, select the "None, I am using contextual filters instead" option
        in the "Group permission" dropdown below and this part of the form will
        be hidden going forward.</p>
      ');

      // Get list of permissions.
      $permissions = ['_none' => $this->t('None, I am using contextual filters instead')];
      foreach ($this->permissionHandler->getPermissions(TRUE) as $permission_name => $permission) {
        $display_name = $this->moduleHandler->getName($permission['provider']);
        $permissions[$display_name . ' : ' . $permission['section']][$permission_name] = strip_tags($permission['title']);
      }

      $form['group_permission'] = [
        '#type' => 'select',
        '#fieldset' => 'deprecated',
        '#options' => $permissions,
        '#title' => $this->t('Group permission'),
        '#default_value' => $this->options['group_permission'],
        '#description' => $this->t('
          Only users with the selected group permission will be able to access
          this display.<br /><strong>Warning:</strong> This will only work if
          there is a {group} parameter in the route. If not, it will always deny
          access.
        '),
      ];

      $how_to_intro = $this->t('
        <p>This plugin will always try to get the group from the route:
        Something that does not work well with modules such as Views Bulk
        Operations, or modules such as Group Sites that try to feed a group from
        other sources. Instead, we get the group where we can configure it: The
        contextual filters.</p>
      ');
    }
    else {
      $how_to_intro = $this->t('
        <p>In order to determine access based on a group permission, we first
        need to answer one question: Which group? The "Access" part of the "Page
        settings" on a view cannot answer this, but the "Contextual filters"
        can.</p>
      ');

      // Replace the "Apply" and "Cancel" buttons with an "Ok" button.
      $form_state->set('ok_button', TRUE);
    }

    $form['how_to'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('How to properly check for a group permission.'),
    ];

    $form['how_to']['info']['#markup'] = $how_to_intro . $this->t('
      <p><strong>What you need to do</strong> to properly check for a group
      permission, is as follows:</p>
    ');

    $form['how_to']['instructions'] = [
      '#theme' => 'item_list',
      '#list_type' => 'ol',
      '#items' => [
        $this->t('On the right, add a contextual filter of type group. Example: "Group relationship: Parent group"'),
        $this->t('Configure what you like for "When the filter value is <em>NOT</em> in the URL", although we advise the "Display Access Denied" option'),
        $this->t('Under "When the filter value <em>IS</em> in the URL or a default is provided", select "Specify validation criteria"'),
        $this->t('Select "Group permission" as the validator and configure as you see fit'),
        $this->t('<strong>Important:</strong> Set "Action to take if filter value does not validate" to "Display Access Denied"'),
        $this->t('In the dropdown above, select the "None, I am using contextual filters instead" to turn off this access plugin, but keep the instructions handy'),
      ],
    ];

    $form['how_to']['benefits']['#markup'] = $this->t('
      <p>By doing as described above, you can now check for group permissions
      from various sources. Any view placed in a block will give the option to
      select a context provider where you can, for instance, choose the same
      provider you used to set up Group Sites.</p>
    ');
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    if ($form_state->getValue(['access_options', 'group_permission']) === '_none') {
      $form_state->unsetValue(['access_options', 'group_permission']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    if (!isset($this->options['group_permission'])) {
      return Cache::PERMANENT;
    }
    return Cache::mergeMaxAges(Cache::PERMANENT, $this->context->getCacheMaxAge());
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    if (!isset($this->options['group_permission'])) {
      return [];
    }
    return Cache::mergeContexts(['user.group_permissions'], $this->context->getCacheContexts());
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    if (!isset($this->options['group_permission'])) {
      return [];
    }
    return $this->context->getCacheTags();
  }

}
