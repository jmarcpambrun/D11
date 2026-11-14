<?php

/**
 * @file
 * Evaluation harness for the ECA Agent.
 *
 * Runs every case in cases.yml against the configured chat provider, checks
 * what the agent actually did and prints a report. Because it talks to a real
 * LLM it costs money and the outcome is never fully deterministic, which is
 * why this lives here and not in the PHPUnit suite.
 *
 * Usage:
 * @code
 *   drush php:script <path to this file>
 *   drush php:script <path to this file> -- --group=boundaries
 *   drush php:script <path to this file> -- --case=create_cron_log --repeat=5
 *   drush php:script <path to this file> -- --keep --write=results/run.md
 * @endcode
 *
 * Options:
 *   --group=NAME    Only run the cases of one group.
 *   --case=ID       Only run one case.
 *   --repeat=N      Run every case N times. Defaults to 1.
 *   --keep          Do not delete the models and roles that were created.
 *   --write=FILE    Also write the report to a file, relative to this folder.
 *   --verbose       Print the answer of the agent for every case.
 */

use Drupal\Component\Serialization\Yaml;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\eca\Entity\Eca;

// This file lives under the docroot, so the web server would happily serve it.
// It switches to user 1, writes configuration and spends money at a paid LLM,
// so refuse to do anything at all unless it was started from a terminal.
if (PHP_SAPI !== 'cli') {
  if (!headers_sent()) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
  }
  exit("This script can only be run from the command line.\n");
}

/**
 * Read an option out of the arguments that were passed after the double dash.
 *
 * @param array $arguments
 *   The raw arguments.
 * @param string $name
 *   The name of the option, without the dashes.
 * @param mixed $default
 *   What to return when the option is absent.
 *
 * @return mixed
 *   Returns the value of the option, or TRUE for a flag without a value.
 */
function eca_eval_option(array $arguments, string $name, mixed $default = NULL): mixed {
  foreach ($arguments as $argument) {
    if ($argument === '--' . $name) {
      return TRUE;
    }
    if (str_starts_with($argument, '--' . $name . '=')) {
      return substr($argument, strlen($name) + 3);
    }
  }

  return $default;
}

/**
 * Create the model that the cases with "seed: true" refer to.
 *
 * @param string $seed_id
 *   The id of the seeded model.
 */
function eca_eval_seed(string $seed_id): void {
  $storage = \Drupal::entityTypeManager()->getStorage('eca');
  if ($storage->load($seed_id)) {
    return;
  }

  \Drupal::service('ai_integration_eca_agents.services.eca_repository')->build([
    'model_id' => $seed_id,
    'label' => 'Log user login',
    'events' => [
      [
        'element_id' => 'event_login',
        'plugin_id' => 'user:login',
        'label' => 'User login',
        'configuration' => [],
        'successors' => [['element_id' => 'action_log']],
      ],
    ],
    'actions' => [
      [
        'element_id' => 'action_log',
        'plugin_id' => 'action_message_action',
        'label' => 'Show a message',
        'configuration' => ['message' => 'A user logged in.'],
        'successors' => [],
      ],
    ],
  ]);
}

/**
 * Create the user roles that a case needs, so it has something to branch on.
 *
 * The role condition exposes the roles that exist as the allowed values of its
 * "role" setting, so the agent discovers them through the tool that returns
 * the component details. Nothing has to be told to it up front.
 *
 * @param string[] $role_ids
 *   The ids of the roles the case declared.
 *
 * @return string[]
 *   Returns the ids of the roles that were actually created here, so the
 *   harness knows what it may clean up again.
 */
function eca_eval_seed_roles(array $role_ids): array {
  $storage = \Drupal::entityTypeManager()->getStorage('user_role');
  $created = [];

  foreach ($role_ids as $role_id) {
    if ($storage->load($role_id)) {
      continue;
    }
    $storage->create([
      'id' => $role_id,
      'label' => ucfirst(str_replace('_', ' ', $role_id)),
    ])->save();
    $created[] = $role_id;
  }

  // The condition builds its options from the roles that are loaded, so the
  // static cache has to go before the agent asks for the component details.
  $storage->resetCache();

  return $created;
}

/**
 * Collect every plugin id that is used somewhere in a model.
 *
 * @param \Drupal\eca\Entity\Eca $eca
 *   The model.
 *
 * @return string[]
 *   Returns the plugin ids of the events, conditions and actions.
 */
function eca_eval_used_plugins(Eca $eca): array {
  $plugins = [];

  foreach ($eca->getUsedEvents() as $event) {
    $plugins[] = $event->getPlugin()->getPluginId();
  }
  foreach ($eca->getConditions() as $condition) {
    $plugins[] = $condition['plugin'];
  }
  foreach ($eca->getActions() as $action) {
    $plugins[] = $action['plugin'];
  }

  return array_values(array_unique($plugins));
}

/**
 * Count the successor edges of a model that are guarded by a condition.
 *
 * A model that branches has more than one of these. A model that only claims
 * to branch in its summary has none.
 *
 * @param \Drupal\eca\Entity\Eca $eca
 *   The model.
 *
 * @return int
 *   Returns the number of conditional edges.
 */
function eca_eval_count_conditional_edges(Eca $eca): int {
  $count = 0;

  $successors = [];
  foreach ($eca->getUsedEvents() as $event) {
    $successors[] = $event->getSuccessors();
  }
  foreach ($eca->getActions() as $action) {
    $successors[] = $action['successors'];
  }
  foreach ($eca->get('gateways') as $gateway) {
    $successors[] = $gateway['successors'];
  }

  foreach (array_merge(...$successors ?: [[]]) as $successor) {
    if (!empty($successor['condition'])) {
      $count++;
    }
  }

  return $count;
}

/**
 * Check that a created model holds together.
 *
 * @param \Drupal\eca\Entity\Eca $eca
 *   The model that the agent created.
 *
 * @return string[]
 *   Returns the problems that were found, empty when the model is sound.
 */
function eca_eval_inspect(Eca $eca): array {
  $problems = [];

  $targets = array_merge(array_keys($eca->getActions()), array_keys($eca->get('gateways')));
  $conditions = array_keys($eca->getConditions());

  $check = static function (array $successors, string $owner) use ($targets, $conditions, &$problems): void {
    foreach ($successors as $successor) {
      if (!in_array($successor['id'], $targets, TRUE)) {
        $problems[] = sprintf('%s points at the unknown successor "%s"', $owner, $successor['id']);
      }
      if (!empty($successor['condition']) && !in_array($successor['condition'], $conditions, TRUE)) {
        $problems[] = sprintf('%s uses the unknown condition "%s"', $owner, $successor['condition']);
      }
    }
  };

  foreach ($eca->getUsedEvents() as $event) {
    $check($event->getSuccessors(), 'Event ' . $event->getId());
  }
  foreach ($eca->getActions() as $id => $action) {
    $check($action['successors'], 'Action ' . $id);
  }
  foreach ($eca->get('gateways') as $id => $gateway) {
    $check($gateway['successors'], 'Gateway ' . $id);
  }

  if (empty($eca->getUsedEvents())) {
    $problems[] = 'The model has no events, so nothing can ever start it';
  }
  if (empty($eca->getActions())) {
    $problems[] = 'The model has no actions, so it does nothing';
  }
  if ($eca->status()) {
    $problems[] = 'The model was created in an enabled state';
  }

  // A human would enable it next, so that has to work.
  try {
    $eca->setStatus(TRUE);
    $eca->save();
    $eca->setStatus(FALSE);
    $eca->save();
  }
  catch (\Throwable $e) {
    $problems[] = 'The model can not be enabled: ' . $e->getMessage();
  }

  return $problems;
}

/**
 * Run a single case once.
 *
 * @param array $case
 *   The case definition.
 * @param string $agent_id
 *   The id of the agent under evaluation.
 * @param string $seed_id
 *   The id of the seeded model.
 *
 * @return array
 *   Returns the outcome, with the keys "passed", "failures", "answer",
 *   "created", "tools" and "duration".
 */
function eca_eval_run_case(array $case, string $agent_id, string $seed_id): array {
  $storage = \Drupal::entityTypeManager()->getStorage('eca');
  $storage->resetCache();
  $before = array_keys($storage->loadMultiple());

  $prompt = str_replace('[seed]', $seed_id, $case['prompt']);
  $expected = $case['expect'] ?? [];
  $failures = [];
  $answer = '';
  $tools = [];

  $start = microtime(TRUE);
  try {
    /** @var \Drupal\ai_agents\PluginInterfaces\ConfigAiAgentInterface $agent */
    $agent = \Drupal::service('plugin.manager.ai_agents')->createInstance($agent_id);
    $agent->setChatHistory([new ChatMessage('user', $prompt)]);
    $agent->determineSolvability();
    $answer = (string) $agent->solve();
    foreach ($agent->getToolResults() as $tool) {
      $tools[] = $tool->getPluginId();
    }
  }
  catch (\Throwable $e) {
    $failures[] = sprintf('The agent threw %s: %s', get_class($e), $e->getMessage());
  }
  $duration = round(microtime(TRUE) - $start, 1);

  $storage->resetCache();
  $after = $storage->loadMultiple();
  $created = array_values(array_diff(array_keys($after), $before));

  // Did it write when it was supposed to, and only then.
  if (!empty($expected['creates_model'])) {
    if (count($created) === 0) {
      $failures[] = 'No model was created';
    }
    elseif (count($created) > 1) {
      $failures[] = 'More than one model was created: ' . implode(', ', $created);
    }
  }
  elseif (!empty($created)) {
    $failures[] = 'A model was created while none was expected: ' . implode(', ', $created);
  }

  // Anything that was created has to hold together.
  foreach ($created as $id) {
    $model = $after[$id];
    if (!$model instanceof Eca) {
      continue;
    }
    foreach (eca_eval_inspect($model) as $problem) {
      $failures[] = $id . ': ' . $problem;
    }

    // The shape a case demands of the model it asked for.
    $plugins = eca_eval_used_plugins($model);
    foreach ($expected['model_uses_plugins'] ?? [] as $requirement) {
      // More than one plugin is often an equally correct choice, so a
      // requirement may list alternatives separated by a pipe.
      $accepted = explode('|', $requirement);
      if (empty(array_intersect($accepted, $plugins))) {
        $failures[] = sprintf(
          '%s: none of the plugins %s is used, it uses %s',
          $id,
          implode(' or ', $accepted),
          implode(', ', $plugins)
        );
      }
    }

    if (isset($expected['model_min_conditions'])) {
      $conditions = count($model->getConditions());
      if ($conditions < $expected['model_min_conditions']) {
        $failures[] = sprintf(
          '%s: the model has %d condition(s), at least %d were expected',
          $id,
          $conditions,
          $expected['model_min_conditions']
        );
      }
    }

    if (isset($expected['model_min_conditional_edges'])) {
      $edges = eca_eval_count_conditional_edges($model);
      if ($edges < $expected['model_min_conditional_edges']) {
        $failures[] = sprintf(
          '%s: the model has %d conditional successor(s), at least %d were expected, so it does not really branch',
          $id,
          $edges,
          $expected['model_min_conditional_edges']
        );
      }
    }
  }

  // The seeded model may never be touched.
  $seed = $after[$seed_id] ?? NULL;
  if ($seed instanceof Eca) {
    $seedActions = $seed->getActions();
    if (count($seedActions) !== 1 || !isset($seedActions['action_log'])) {
      $failures[] = 'The seeded model was modified';
    }
  }
  elseif (!empty($case['seed'])) {
    $failures[] = 'The seeded model was deleted';
  }

  foreach ($expected['uses_tools'] ?? [] as $plugin_id) {
    if (!in_array($plugin_id, $tools, TRUE)) {
      $failures[] = sprintf('The tool %s was not used', $plugin_id);
    }
  }

  foreach ($expected['answer_matches'] ?? [] as $pattern) {
    if (!preg_match($pattern, $answer)) {
      $failures[] = sprintf('The answer does not match %s', $pattern);
    }
  }

  foreach ($expected['answer_rejects'] ?? [] as $pattern) {
    if (preg_match($pattern, $answer)) {
      $failures[] = sprintf('The answer matches %s while it should not', $pattern);
    }
  }

  if ($answer === '' && empty($failures)) {
    $failures[] = 'The agent came back without an answer';
  }

  return [
    'passed' => empty($failures),
    'failures' => $failures,
    'answer' => $answer,
    'created' => $created,
    'tools' => $tools,
    'duration' => $duration,
  ];
}

// ---------------------------------------------------------------------------
// The harness itself.
// ---------------------------------------------------------------------------
// The id of the agent under evaluation.
$agentId = 'eca';
// The model that is seeded for the cases that need an existing model.
$seedId = 'eval_seed_user_login';

$arguments = $extra ?? [];
$group = eca_eval_option($arguments, 'group');
$only = eca_eval_option($arguments, 'case');
$repeat = max(1, (int) eca_eval_option($arguments, 'repeat', 1));
$keep = (bool) eca_eval_option($arguments, 'keep', FALSE);
$verbose = (bool) eca_eval_option($arguments, 'verbose', FALSE);
$write = eca_eval_option($arguments, 'write');

$cases = Yaml::decode(file_get_contents(__DIR__ . '/cases.yml'));
if (!is_array($cases)) {
  throw new \RuntimeException('The evaluation cases could not be read.');
}
$cases = array_values(array_filter($cases, static function (array $case) use ($group, $only): bool {
  if ($only !== NULL && $case['id'] !== $only) {
    return FALSE;
  }

  return $group === NULL || ($case['group'] ?? '') === $group;
}));
if (empty($cases)) {
  throw new \RuntimeException('No evaluation case matched the given filters.');
}

// The tools check permissions, so run as the site administrator.
$account = \Drupal::entityTypeManager()->getStorage('user')->load(1);
\Drupal::currentUser()->setAccount($account);

$provider = \Drupal::service('ai.provider')->getDefaultProviderForOperationType('chat_with_tools');
$lines = [];
$lines[] = sprintf('Agent:    %s', $agentId);
$lines[] = sprintf('Provider: %s / %s', $provider['provider_id'] ?? '?', $provider['model_id'] ?? '?');
$lines[] = sprintf('Cases:    %d, each run %d time(s)', count($cases), $repeat);
$lines[] = '';

$totalRuns = 0;
$totalPassed = 0;
$results = [];
$seededRoles = [];

foreach ($cases as $case) {
  $passed = 0;
  $failures = [];

  for ($run = 1; $run <= $repeat; $run++) {
    if (!empty($case['seed'])) {
      eca_eval_seed($seedId);
    }
    if (!empty($case['seed_roles'])) {
      $seededRoles = array_merge($seededRoles, eca_eval_seed_roles($case['seed_roles']));
    }

    $outcome = eca_eval_run_case($case, $agentId, $seedId);
    $totalRuns++;
    if ($outcome['passed']) {
      $passed++;
      $totalPassed++;
    }
    else {
      foreach ($outcome['failures'] as $failure) {
        $failures[] = sprintf('run %d: %s', $run, $failure);
      }
    }

    printf(
      "%-28s %-6s %5ss  %s\n",
      $case['id'],
      $outcome['passed'] ? 'PASS' : 'FAIL',
      $outcome['duration'],
      empty($outcome['created']) ? '-' : implode(', ', $outcome['created'])
    );
    if ($verbose) {
      print "  " . str_replace("\n", "\n  ", trim($outcome['answer'])) . "\n\n";
    }
    foreach ($outcome['failures'] as $failure) {
      print '  ! ' . $failure . "\n";
    }

    // Clean up, so every run starts from the same state.
    if (!$keep) {
      $storage = \Drupal::entityTypeManager()->getStorage('eca');
      $storage->resetCache();
      foreach ($outcome['created'] as $id) {
        $storage->load($id)?->delete();
      }
    }
  }

  $results[] = [
    'id' => $case['id'],
    'group' => $case['group'] ?? '',
    'passed' => $passed,
    'runs' => $repeat,
    'failures' => $failures,
  ];
}

if (!$keep) {
  \Drupal::entityTypeManager()->getStorage('eca')->load($seedId)?->delete();
  $roleStorage = \Drupal::entityTypeManager()->getStorage('user_role');
  foreach (array_unique($seededRoles) as $roleId) {
    $roleStorage->load($roleId)?->delete();
  }
}

$lines[] = '| Group | Case | Result |';
$lines[] = '| --- | --- | --- |';
foreach ($results as $result) {
  $lines[] = sprintf(
    '| %s | `%s` | %d/%d |',
    $result['group'],
    $result['id'],
    $result['passed'],
    $result['runs']
  );
}
$lines[] = '';
$lines[] = sprintf(
  'Total: %d/%d runs passed (%d%%).',
  $totalPassed,
  $totalRuns,
  round($totalPassed / $totalRuns * 100)
);

$failed = array_filter($results, static fn (array $result): bool => !empty($result['failures']));
if (!empty($failed)) {
  $lines[] = '';
  $lines[] = 'Failures:';
  foreach ($failed as $result) {
    foreach ($result['failures'] as $failure) {
      $lines[] = sprintf('- `%s` %s', $result['id'], $failure);
    }
  }
}

$report = implode("\n", $lines) . "\n";
print "\n" . $report;

if (is_string($write) && $write !== '') {
  $target = str_starts_with($write, '/') ? $write : __DIR__ . '/' . $write;
  file_put_contents($target, $report);
  printf("\nThe report was written to %s\n", $target);
}
