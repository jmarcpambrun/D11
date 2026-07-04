<?php

namespace Drupal\burndown\Commands;

use Drupal\burndown\Entity\Project;
use Drupal\burndown\Entity\Sprint;
use Drupal\burndown\Entity\Swimlane;
use Drupal\burndown\Entity\Task;
use Drupal\burndown\Event\TaskClosedEvent;
use Drush\Commands\DrushCommands;

/**
 * A drush command file.
 *
 * @package Drupal\burndown\Commands
 */
class BurndownDrushCommands extends DrushCommands {

  /**
   * Drush command that lists Burndown projects.
   *
   * @command burndown:project_list
   * @aliases burndown-project-list bdpl
   * @usage burndown:project_list
   */
  public function project_list() {
    // Output header.
    $text = 'Project                                 Shortcode    # Tasks';
    $this->output()->writeln($text);
    $text = '============================================================';
    $this->output()->writeln($text);

    // Get all projects.
    $projects = Project::loadMultiple();

    // Count open tasks per project.
    if (!empty($projects)) {
      foreach ($projects as $project) {
        if ($project->status->getValue()[0]['value'] != 1) {
          continue;
        }

        // Trim project name to max 38 chars (and then pad to 40).
        $name = $project->getName();
        $name = substr($name, 0, 37);
        $name = str_pad($name, 40, ' ');

        // Trim shortcode to max 10 chars (and then pad)
        $shortcode = $project->getShortcode();
        $shortcode_copy = $shortcode;
        $shortcode = substr($shortcode, 0, 9);
        $shortcode = str_pad($shortcode, 13, ' ');

        $num_tasks = Task::getOpenTasksFor($shortcode_copy);
        $num_tasks = number_format($num_tasks);
        $num_tasks = str_pad($num_tasks, 7, ' ', STR_PAD_LEFT);

        // Output row.
        $text = $name . $shortcode . $num_tasks;
        $this->output()->writeln($text);
      }
    }

    $this->io()->success('Projects: ' . count($projects));
  }

  /**
   * Drush command that lists Burndown tasks for a project.
   *
   * @param string $shortcode
   *   Burndown project shortcode to list tasks for.
   * @param array $options
   *   Array of options settings for backlog, board, and completed.
   *
   * @command burndown:task_list
   * @aliases burndown-task-list bdtl
   * @option backlog
   *   Show tasks in the backlog.
   * @option board
   *   Show tasks on the project board.
   * @option completed
   *   Show completed tasks.
   * @usage burndown:task_list --backlog --board --completed {shortcode}
   */
  public function task_list(string $shortcode = '', array $options = ['backlog' => FALSE, 'board' => FALSE, 'completed' => FALSE]) {
    if ($shortcode == '') {
      $text = "No project shortcode specified.";
      $this->io()->caution($text);
      return;
    }

    // Load project and make sure shortcode is ok.
    $project = Project::loadFromShortcode($shortcode);
    if ($project == FALSE) {
      $text = "Project does not exist.";
      $this->io()->caution($text);
      return;
    }

    // Output header.
    $text = 'Ticket ID   Description           Column       Assigned To';
    $this->output()->writeln($text);
    $text = '============================================================';
    $this->output()->writeln($text);

    $count = 0;

    // If none of the options are set, then show the board.
    if (!$options['backlog'] && !$options['board'] && !$options['completed']) {
      $options['board'] = TRUE;
    }

    if ($options['backlog']) {
      $tasks = Task::getBacklogTasks($shortcode);
      if (!empty($tasks)) {
        foreach ($tasks as $task) {
          $text = $this->output_task_row($task);
          $this->output()->writeln($text);
        }

        $count += count($tasks);
      }
    }

    if ($options['board']) {
      // Get board columns.
      $swimlanes = Swimlane::getBoardSwimlanes($shortcode);
      foreach ($swimlanes as $swimlane) {
        $tasks = Task::getTasksForSwimlane($shortcode, $swimlane->getName());
        if (!empty($tasks)) {
          foreach ($tasks as $task) {
            $text = $this->output_task_row($task);
            $this->output()->writeln($text);
          }

          $count += count($tasks);
        }
      }
    }

    if ($options['completed']) {
      $swimlanes = Swimlane::getCompletedSwimlanes($shortcode);
      foreach ($swimlanes as $swimlane) {
        $tasks = Task::getTasksForSwimlane($shortcode, $swimlane->getName());
        if (!empty($tasks)) {
          foreach ($tasks as $task) {
            $text = $this->output_task_row($task);
            $this->output()->writeln($text);
          }

          $count += count($tasks);
        }
      }
    }

    $this->io()->success('Tasks: ' . $count);
  }

  /**
   * Output a task row.
   */
  private function output_task_row($task) {
    $ticket_id = $task->getTicketId();
    $ticket_id = substr($ticket_id, 0, 10);
    $ticket_id = str_pad($ticket_id, 12, ' ');

    $name = $task->getName();
    $name = substr($name, 0, 20);
    $name = str_pad($name, 22, ' ');

    $swimlane = $task->getSwimlane()->getName();
    $swimlane = substr($swimlane, 0, 13);
    $swimlane = str_pad($swimlane, 15, ' ');

    $assigned_to = $task->getAssignedToName();
    if ($assigned_to === FALSE) {
      $assigned_to = '';
    }
    $assigned_to = substr($assigned_to, 0, 10);
    $assigned_to = str_pad($assigned_to, 11, ' ');

    return $ticket_id . $name . $swimlane . $assigned_to;
  }

  /**
   * Drush command that lists columns for a Burndown project.
   *
   * @param string $shortcode
   *   Burndown project shortcode.
   *
   * @command burndown:swimlane_list
   * @aliases burndown-swimlane-list bdsl burndown-column-list bdcl
   * @usage burndown:swimlane_list {shortcode}
   */
  public function swimlane_list($shortcode) {
    if ($shortcode == '') {
      $text = "No project shortcode specified.";
      $this->io()->caution($text);
      return;
    }

    // Load project and make sure shortcode is ok.
    $project = Project::loadFromShortcode($shortcode);
    if ($project == FALSE) {
      $text = "Project does not exist.";
      $this->io()->caution($text);
      return;
    }

    // Get Columns (swimlanes).
    $swimlanes = Swimlane::loadForProject($shortcode);

    // Output header.
    $text = 'Name          Sort Order    Backlog?    Board?    Completed?';
    $this->output()->writeln($text);
    $text = '============================================================';
    $this->output()->writeln($text);

    foreach ($swimlanes as $swimlane) {
      $name = $swimlane->getName();
      $name = substr($name, 0, 12);
      $name = str_pad($name, 14, ' ');

      $sort_order = $swimlane->getSortOrder();
      $sort_order = number_format($sort_order);
      $sort_order = str_pad($sort_order, 14, ' ');

      $is_backlog = $swimlane->getShowBacklog() ? 'Y' : 'N';
      $is_backlog = str_pad($is_backlog, 12, ' ');

      $is_board = $swimlane->getShowProjectBoard() ? 'Y' : 'N';
      $is_board = str_pad($is_board, 10, ' ');

      $is_completed = $swimlane->getShowCompleted() ? 'Y' : 'N';
      $is_completed = str_pad($is_completed, 10, ' ');

      $text = $name . $sort_order . $is_backlog . $is_board . $is_completed;
      $this->output()->writeln($text);
    }

    $this->io()->success('Columns: ' . count($swimlanes));
  }

  /**
   * Drush command that lists sprints for a Burndown project.
   *
   * @param string $shortcode
   *   Burndown project shortcode.
   *
   * @command burndown:sprint_list
   * @aliases burndown-sprint-list bdspl
   * @usage burndown:sprint_list {shortcode}
   */
  public function sprint_list($shortcode) {
    if ($shortcode == '') {
      $text = "No project shortcode specified.";
      $this->io()->caution($text);
      return;
    }

    // Load project and make sure shortcode is ok.
    $project = Project::loadFromShortcode($shortcode);
    if ($project == FALSE) {
      $text = "Project does not exist.";
      $this->io()->caution($text);
      return;
    }

    // Check if project is actually kanban...
    if (!$project->isSprint()) {
      $text = "This is a kanban project.";
      $this->io()->caution($text);
      return;
    }

    // Load sprints.
    $sprints = Sprint::getSprintsFor($shortcode);
    $count = 0;

    // Output header.
    $text = 'Name       Project  Status     Order  Start       End       ';
    $this->output()->writeln($text);
    $text = '============================================================';
    $this->output()->writeln($text);

    if ($sprints) {
      $count = count($sprints);

      foreach ($sprints as $sprint) {
        $name = $sprint->getName();
        $name = substr($name, 0, 10);
        $name = str_pad($name, 11, ' ');

        $project = $sprint->getProject()->getShortcode();
        $project = substr($project, 0, 8);
        $project = str_pad($project, 9, ' ');

        $status = $sprint->getStatus();
        $status = substr($status, 0, 9);
        $status = str_pad($status, 11, ' ');

        $sort_order = $sprint->getSortOrder();
        $sort_order = substr($sort_order, 0, 5);
        $sort_order = str_pad($sort_order, 5, ' ', STR_PAD_LEFT);
        $sort_order = $sort_order . '  ';

        $start_date = $sprint->getStartDate();
        $start_date = substr($start_date, 0, 10);
        $start_date = str_pad($start_date, 12, ' ');

        $end_date = $sprint->getEndDate();
        $end_date = substr($end_date, 0, 10);
        $end_date = str_pad($end_date, 10, ' ');

        $text = $name . $project . $status . $sort_order . $start_date . $end_date;
        $this->output()->writeln($text);
      }
    }

    $this->io()->success('Sprints: ' . $count);
  }

  /**
   * Drush command for moving a Burndown Task in and out of sprints.
   *
   * @param string $ticket_id
   *   Burndown ticket id for the task.
   *
   * @command burndown:task_sprint_assignment
   * @aliases burndown-task-sprint-assignment bdtsa
   * @usage burndown:task_sprint_assignment {ticket_id}
   */
  public function task_sprint_assignment($ticket_id = '') {
    $task = Task::loadFromTicketId($ticket_id);
    if ($task == FALSE) {
      $text = 'Task does not exist.';
      $this->io()->caution($text);
      return;
    }

    // Get project.
    $project = $task->getProject();
    $shortcode = $project->getShortcode();

    // Check this is a sprint project.
    if (!$project->isSprint()) {
      $text = 'Project "' . $shortcode . '" is not a sprint project. ';
      $text .= 'Please use burndown:send_to_board and burndown:send_to_backlog commands instead.';
      $this->io()->caution($text);
      return;
    }

    // Get list of backlog sprints (and add in backlog as option).
    $sprints = Sprint::getBacklogSprintsFor($shortcode);
    $active_sprint = Sprint::getCurrentSprintFor($shortcode);
    $options = [];
    $sprint_list = [];
    $backlog_option = "Place Task in Backlog";

    if (empty($sprints) && ($active_sprint === FALSE)) {
      $text = 'Project " ' . $shortcode . '" does not have any current sprints. Please add a sprint first.';
      $this->io()->caution($text);
      return;
    }
    else {
      if ($active_sprint) {
        $options[] = $active_sprint->getName();
        $sprint_list[$active_sprint->getName()] = $active_sprint;
      }

      if (!empty($sprints)) {
        foreach ($sprints as $sprint) {
          $options[] = $sprint->getName();
          $sprint_list[$sprint->getName()] = $sprint;
        }
      }

      // Add backlog as an option too.
      $options[] = $backlog_option;
    }

    // Check if already in a sprint.
    $current_sprint = $task->getSprint();
    if (!is_null($current_sprint)) {
      $status = $current_sprint->getStatus();
      $current_sprint_name = $current_sprint->getName();

      // If sprint is completed, make user reopen the task instead.
      if ($status === 'completed') {
        $text = 'Task is in a completed sprint. Please use burndown:reopen_task instead.';
        $this->io()->caution($text);
        return;
      }

      // Warn if sprint is open.
      if ($status === 'started') {
        $text = 'The task is in an open sprint. Moving it will change the sprint size. ';
        $text .= 'Are you sure you want to continue?';
        $continue = $this->io()->confirm($text, FALSE);
        if (!$continue) {
          return;
        }
      }
    }
    else {
      $current_sprint_name = $backlog_option;
    }

    // Ask user where to put the task.
    $new_sprint = $this->io()->choice('Where do you want to move the task?', $options, $current_sprint_name);
    $new_sprint_name = $options[$new_sprint];

    // If new sprint is "backlog":
    if ($new_sprint_name == $backlog_option) {
      // Check if already in backlog.
      if ($current_sprint_name == $backlog_option) {
        $text = 'Task is already in the backlog.';
        $this->io()->caution($text);
        return;
      }

      // Assign to backlog (if not already there).
      $backlog = Swimlane::getBacklogFor($shortcode);
      if ($backlog !== FALSE) {
        $task
          ->setSwimlane($backlog)
          ->set('sprint', NULL)
          ->save();
      }
    }
    // Otherwise:
    else {
      // Get the actual sprint entity from the name.
      $new_sprint = $sprint_list[$new_sprint_name];

      // Check if new sprint is already open and warn.
      if ($new_sprint->getStatus() == 'started') {
        $text = 'The new sprint is already open. Moving it will change the sprint size. ';
        $text .= 'Are you sure you want to continue?';
        $continue = $this->io()->confirm($text, FALSE);
        if (!$continue) {
          return;
        }
      }

      // If the sprint is open, we also need to set swimlane to To Do.
      if ($new_sprint->getStatus() === 'started') {
        $swimlane = Swimlane::getTodoSwimlane($shortcode);
      }
      // Otherwise task should be in Backlog Column.
      else {
        $swimlane = Swimlane::getBacklogFor($shortcode);
      }

      if ($swimlane === FALSE) {
        $text = 'There is a problem with the columns for this project. Please contact your system administrator.';
        $this->io()->caution($text);
        return;
      }

      // Assign to new sprint.
      $task
        ->setSprint($new_sprint)
        ->setSwimlane($swimlane)
        ->save();
    }

    // Success message.
    $this->io()->success('Task has been reassigned.');
  }

  /**
   * Drush command that shows details for a Burndown Task.
   *
   * @param string $ticket_id
   *   Burndown ticket id for the task.
   * @param array $options
   *   Array of option settings.
   *
   * @command burndown:task_details
   * @aliases burndown-task-details bdtd
   * @option show_description
   *   Show long description for the task.
   * @option show_comments
   *   Show comments for the task.
   * @usage burndown:task_details --show_description --show_comments {ticket_id}
   */
  public function task_details(string $ticket_id = '', array $options = ['show_description' => FALSE]) {
    $task = Task::loadFromTicketId($ticket_id);
    if ($task == FALSE) {
      $text = "Task does not exist.";
      $this->io()->caution($text);
      return;
    }

    $label_pad = 20;

    $text = str_pad('ID:', $label_pad, ' ') . $task->id();
    $this->output()->writeln($text);

    $text = str_pad('Ticket ID:', $label_pad, ' ') . $task->getTicketId();
    $this->output()->writeln($text);

    $text = str_pad('Name:', $label_pad, ' ') . $task->getName();
    $this->output()->writeln($text);

    $text = str_pad('Swimlane:', $label_pad, ' ') . $task->getSwimlane()->getName();
    $this->output()->writeln($text);

    $text = str_pad('Completed?:', $label_pad, ' ') . ($task->isCompleted() ? 'Y' : 'N');
    $this->output()->writeln($text);

    $text = str_pad('In Backlog?:', $label_pad, ' ') . ($task->inBacklog() ? 'Y' : 'N');
    $this->output()->writeln($text);

    $text = str_pad('On Project Board?:', $label_pad, ' ') . ($task->onBoard() ? 'Y' : 'N');
    $this->output()->writeln($text);

    $text = str_pad('In a Sprint?:', $label_pad, ' ') . ($task->isSprint() ? 'Y' : 'N');
    $this->output()->writeln($text);

    $text = str_pad('Priority:', $label_pad, ' ') . $task->getPriority();
    $this->output()->writeln($text);

    if (NULL !== $task->getEstimate()) {
      $text = str_pad('Estimate:', $label_pad, ' ') . $task->getEstimate() . ' (' . $task->getEstimateType() . ')';
      $this->output()->writeln($text);
    }

    $text = str_pad('Assigned To:', $label_pad, ' ') . $task->getAssignedToName();
    $this->output()->writeln($text);

    $text = str_pad('Reported By:', $label_pad, ' ') . $task->getOwnerName();
    $this->output()->writeln($text);

    $tags = $task->getTagsFormatted();
    if (!empty($tags)) {
      $text = str_pad('Tags:', $label_pad, ' ');
      foreach ($tags as $tag) {
        $text .= $tag['name'] . ' ';
      }
      $this->output()->writeln($text);
    }

    if ($options['show_description']) {
      $text = str_pad('Description:', $label_pad, ' ');
      $this->output()->writeln($text);
      $text = $task->getDescription();
      $this->output()->writeln($text);
    }

    $this->io()->success('');
  }

  /**
   * Drush command to add a Burndown Task.
   *
   * @command burndown:add_task
   * @aliases burndown-add-task bdat
   * @option shortcode The shortcode for the project.
   * @option task_name The name or short description of the task.
   * @option assignee The username of the person to assign the task to.
   * @option reported_by The username of the person reporting the task.
   * @option priority The priority of the task (Trivial, Low, Medium, High, Critical, Blocker).
   * @option estimate The estimate for the size of the task (depends on the Project's chosen scale).
   * @option description A detailed description of the task.
   * @usage burndown:add_task --shortcode=PROJ --task_name="New Task" --assignee=john --reported_by=admin --priority=Medium --estimate=3 --description="This is a detailed description of the task."
   */
  public function add_task($options = [
    'shortcode' => self::REQ,
    'task_name' => self::REQ,
    'assignee' => self::REQ,
    'reported_by' => self::REQ,
    'priority' => self::REQ,
    'estimate' => self::REQ,
    'description' => self::REQ,
  ]) {
    // Title of the command.
    $this->io()->title('Add a Burndown Task');

    if (!empty($options['shortcode'])) {
      $shortcode = $options['shortcode'];
    }
    else {
      // Get shortcode from user.
      $shortcode = $this->io()->ask('What is the shortcode (i.e. 4 or 5 letter code) for the project?');
    }

    // Check for blank shortcode.
    if ($shortcode == '') {
      $this->io()->caution('No project shortcode specified.');
      return;
    }
    else {
      $shortcode = strtoupper($shortcode);
    }

    // Load project and make sure shortcode is ok.
    $project = Project::loadFromShortcode($shortcode);
    if ($project == FALSE) {
      $this->io()->caution('Project does not exist.');
      return;
    }

    // Default backlog swimlane.
    $backlog = Swimlane::getBacklogFor($shortcode);

    if (!empty($options['task_name'])) {
      $name = $options['task_name'];
    }
    else {
      // Get short title (i.e. name) of the task from user.
      $name = $this->io()->ask('What is the name or short description (max 50 chars) of the task?');
    }
    if ($name == '') {
      $this->io()->caution('Please specify the name or short description of the task.');
      return;
    }
    $name = substr($name, 0, 50);

    if (!empty($options['reported_by'])) {
      $reported_by = $options['reported_by'];
    }
    else {
      // Get reported by user name from the user.
      $reported_by = $this->io()->ask('What is the "reported by" username?', 'admin');
    }
    $reported_by_user = user_load_by_name($reported_by);
    if ($reported_by_user === FALSE) {
      $this->io()->caution('User account does not exist.');
      return;
    }

    // Get assigned to (optional) name from the user.
    $assigned_to_id = NULL;
    if (!empty($options['assignee'])) {
      $assigned_to = $options['assignee'];
    }
    else {
      $assigned_to = $this->io()->ask('What is the "assigned to" username (optional)?');
    }

    if (!empty($assigned_to)) {
      $assigned_to_user = user_load_by_name($assigned_to);
      if ($assigned_to_user === FALSE) {
        $this->io()->caution('User account does not exist.');
        return;
      }
      $assigned_to_id = $assigned_to_user->id();
    }

    // Get priority from user.
    $priorities = [
      'Trivial',
      'Low',
      'Medium',
      'High',
      'Critical',
      'Blocker',
    ];
    if (!empty($options['priority'])) {
      $priority = $options['priority'];
      // Check if priority is valid.
      if (!in_array($priority, $priorities)) {
        $this->io()->caution('Invalid priority specified. Please use one of the following: ' . implode(', ', $priorities));
        return;
      }
    }
    else {
      $priority = $this->io()->choice('What is the priority of the task?', $priorities, $priorities[0]);
    }

    // Get estimate from user.
    $estimate_type = $project->getEstimateType();
    $size_options = $project->getEstimateSizes();
    $estimate_options = ['None'];
    foreach ($size_options as $option) {
      $estimate_option = $option . ' (' . $estimate_type . ')';
      array_push($estimate_options, $estimate_option);
    }
    if (!empty($options['estimate'])) {
      $estimate = $options['estimate'];
      // Check if estimate is valid.
      if (!in_array($estimate, $size_options)) {
        $this->io()->caution('Invalid estimate specified. Please use one of the following: ' . implode(', ', $estimate_options));
        return;
      }
    }
    else {
      $estimate = $this->io()->choice('What is the estimate (' . $estimate_type . ') for the size of the task?', $estimate_options, $estimate_options[0]);
    }
    $estimate = $estimate_options[$estimate];
    if ($estimate == 'None') {
      $estimate = '_none';
    }
    else {
      $estimate = explode(' ', $estimate);
      $estimate = reset($estimate);
      if ($estimate_type === 'geometric') {
        $estimate = $estimate . 'D';
      }
    }

    if (!empty($options['description'])) {
      $description = $options['description'];
    }
    else {
      // Check if user wants to add detailed description.
      $add_desc = $this->io()->confirm('Do you want to add a detailed description of the task?', FALSE);
      if ($add_desc) {
        $description = $this->io()->ask('Description');
      }
      else {
        $description = '';
      }
    }

    // Try to add the task.
    $task = Task::create([
      'type' => 'task',
      'name' => $name,
      'project' => $project->id(),
      'priority' => $priority,
      'user_id' => $reported_by_user->id(),
      'assigned_to' => $assigned_to_id,
      'estimate' => $estimate,
      'description' => $description,
      'swimlane' => $backlog->id(),
      'status' => 1,
    ]);
    $task->save();

    $ticket_id = $task->getTicketId();

    // Success message.
    $this->io()->success('Task ' . $ticket_id . ' has been added');
  }

  /**
   * Drush command to edit a Burndown Task.
   *
   * @param string $ticket_id
   *   Burndown ticket id for the task.
   *
   * @command burndown:edit_task
   * @aliases burndown-edit-task bdet
   * @usage burndown:edit_task {ticket_id}
   */
  public function edit_task($ticket_id = '') {
    $task = Task::loadFromTicketId($ticket_id);
    if ($task == FALSE) {
      $text = "Task does not exist.";
      $this->output()->writeln($text);
      return;
    }

    $project = $task->getProject();

    // Title of the command.
    $this->io()->title('Edit a Burndown Task');

    // Get short description (i.e. name) from user.
    $name = $this->io()->ask('What is the name or short description (max 50 chars) of the task?', $task->getName());
    if ($name == '') {
      $this->io()->caution('Please specify the name or short description of the task.');
      return;
    }
    $name = substr($name, 0, 50);

    // Get reported by user name from the user.
    $reported_by = $this->io()->ask('What is the "reported by" username?', $task->getOwnerName());
    $reported_by_user = user_load_by_name($reported_by);
    if ($reported_by_user === FALSE) {
      $this->io()->caution('User account does not exist.');
      return;
    }

    // Get assigned to (optional) name from the user.
    $assigned_to_id = NULL;
    $assigned_to = $this->io()->ask('What is the "assigned to" username (optional)?', $task->getAssignedToName());
    if (!empty($assigned_to)) {
      $assigned_to_user = user_load_by_name($assigned_to);
      if ($assigned_to_user === FALSE) {
        $this->io()->caution('User account does not exist.');
        return;
      }
      $assigned_to_id = $assigned_to_user->id();
    }

    // Get priority from user.
    $priorities = [
      'Trivial',
      'Low',
      'Medium',
      'High',
      'Critical',
      'Blocker',
    ];
    $priority = $this->io()->choice('What is the priority of the task?', $priorities, $priorities[$task->getPriority()]);

    // Get estimate from user.
    $estimate_type = $project->getEstimateType();
    $options = $project->getEstimateSizes();
    $estimate_options = ['None'];
    foreach ($options as $option) {
      $estimate_option = $option . ' (' . $estimate_type . ')';
      array_push($estimate_options, $estimate_option);
    }
    $estimate = $this->io()->choice('What is the estimate (' . $estimate_type . ') for the size of the task?', $estimate_options, $estimate_options[$task->getEstimate()]);
    $estimate = $estimate_options[$estimate];
    if ($estimate == 'None') {
      $estimate = '_none';
    }
    else {
      $estimate = explode(' ', $estimate);
      $estimate = reset($estimate);
      if ($estimate_type === 'geometric') {
        $estimate = $estimate . 'D';
      }
    }

    // Get description from user.
    $description = $this->io()->ask('Description', $task->getDescription());

    // Save changes.
    $task
      ->setName($name)
      ->setOwnerId($reported_by_user->id())
      ->setAssignedToId($assigned_to_id)
      ->setPriority($priority)
      ->setEstimate($estimate)
      ->setDescription($description)
      ->save();

    $ticket_id = $task->getTicketId();

    // Success message.
    $this->io()->success('Task ' . $ticket_id . ' has been updated');
  }

  /**
   * Drush command to close a Burndown Task.
   *
   * @param string $ticket_id
   *   Burndown ticket id for the task.
   *
   * @command burndown:close_task
   * @aliases burndown-close-task bdct
   * @usage burndown:close_task {ticket_id}
   */
  public function close_task($ticket_id = '') {
    $task = Task::loadFromTicketId($ticket_id);
    if ($task == FALSE) {
      $text = "Task does not exist.";
      $this->io()->caution($text);
      return;
    }

    // Check if task is already closed.
    if ($task->getCompleted() == TRUE) {
      $text = "Task has already been closed.";
      $this->io()->caution($text);
      return;
    }

    // Get list of resolution statuses from config.
    $statuses = Task::getResolutionStatuses();

    // Get project.
    $task = Task::loadFromTicketId($ticket_id);
    $project = $task->getProject();
    $shortcode = $project->getShortcode();

    // Get completed swimlane.
    $completed_lane = Swimlane::getCompletedSwimlanes($shortcode);
    if ($completed_lane !== FALSE) {
      $completed_lane = reset($completed_lane);
    }
    else {
      // This is an error condition, but an admin will need to
      // fix it!
      $this->io()->error('There is no completed Column for ' . $shortcode . '. Please contact your system administrator to fix this problem. The task cannot be closed.');
      return;
    }

    // Get resolution status from user.
    $status = $this->io()->choice('Please enter the resolution status for the task', $statuses, $statuses[0]);

    // Update the task.
    $task
      ->setCompleted(TRUE)
      ->setResolution($status)
      ->setSwimlane($completed_lane)
      ->save();

    // Issue a TaskClosedEvent event.
    $event = new TaskClosedEvent($task);

    // Get the event_dispatcher service and dispatch the event.
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $event_dispatcher->dispatch(TaskClosedEvent::CLOSED, $event);

    $ticket_id = $task->getTicketId();

    // Success message.
    $this->io()->success('Task ' . $ticket_id . ' has been closed');
  }

  /**
   * Drush command to reopen a Burndown Task.
   *
   * @param string $ticket_id
   *   Burndown ticket id for the task.
   *
   * @command burndown:reopen_task
   * @aliases burndown-reopen-task bdrot
   * @usage burndown:reopen_task {ticket_id}
   */
  public function reopen_task($ticket_id = '') {
    $task = Task::loadFromTicketId($ticket_id);
    if ($task == FALSE) {
      $text = "Task does not exist.";
      $this->io()->caution($text);
      return;
    }

    // Check if task is already closed.
    if ($task->getCompleted() == FALSE) {
      $text = "Task is not closed.";
      $this->io()->caution($text);
      return;
    }

    // Get project.
    $task = Task::loadFromTicketId($ticket_id);
    $project = $task->getProject();
    $shortcode = $project->getShortcode();

    // Get backlog.
    $backlog = Swimlane::getBacklogFor($shortcode);

    // Update the task.
    $task
      ->setCompleted(FALSE)
      ->setSwimlane($backlog)
      ->setResolution('')
      ->save();

    // Success message.
    $this->io()->success('Task ' . $ticket_id . ' has been reopened');
  }

  /**
   * Drush command to move a Task between Columns.
   *
   * @param string $ticket_id
   *   Burndown ticket id for the task.
   *
   * @command burndown:change_swimlane
   * @aliases burndown-change-swimlane bdcs
   * @usage burndown:change_swimlane {ticket_id}
   */
  public function change_swimlane($ticket_id = '') {
    $task = Task::loadFromTicketId($ticket_id);
    if ($task == FALSE) {
      $text = "Task does not exist.";
      $this->io()->caution($text);
      return;
    }

    // Check that task isn't completed.
    if ($task->getCompleted() == TRUE) {
      $text = "Task is closed.";
      $this->io()->caution($text);
      return;
    }

    // Check that task is on the board.
    if ($task->inBacklog()) {
      $text = "Task is in the backlog. Please move it to the board before setting the column.";
      $this->io()->caution($text);
      return;
    }

    // Get current swimlane.
    $current_swimlane = $task->getSwimlane()->getName();

    // List swimlanes for the project.
    $project = $task->getProject();
    $shortcode = $project->getShortcode();
    $swimlanes = Swimlane::getBoardSwimlanes($shortcode);
    $options = [];
    if (!empty($swimlanes)) {
      foreach ($swimlanes as $lane) {
        $options[] = $lane->getName();
      }
    }

    // Get swimlane from user.
    $swimlane = $this->io()->choice('Which column do you want to move the task to?', $options, $current_swimlane);
    $swimlane = $options[$swimlane];
    $swimlane = Swimlane::getSwimlane($shortcode, $swimlane);

    // Update task.
    $task
      ->setSwimlane($swimlane)
      ->save();

    $ticket_id = $task->getTicketId();

    // Success message.
    $this->io()->success('Task ' . $ticket_id . ' has been moved to "' . $swimlane->getName() . '"');
  }

  /**
   * Drush command to watch a Burndown Task.
   *
   * @param string $ticket_id
   *   Burndown ticket id for the task.
   * @param string $username
   *   User to add to the watchlist for the task.
   *
   * @command burndown:watch_task
   * @aliases burndown-watch-task bdwt
   * @usage burndown:watch_task {ticket_id} {username}
   */
  public function watch_task($ticket_id = '', $username = '') {
    $task = Task::loadFromTicketId($ticket_id);
    if ($task == FALSE) {
      $text = "Task does not exist.";
      $this->io()->caution($text);
      return;
    }

    $user = user_load_by_name($username);
    if ($user === FALSE) {
      $this->io()->caution('User account does not exist.');
      return;
    }

    // Add user to watchlist.
    $task->addToWatchlist($user)->save();

    // Success message.
    $this->io()->success('User ' . $username . ' is now watching ' . $ticket_id);
  }

  /**
   * Drush command to "unwatch" a Burndown Task.
   *
   * @param string $ticket_id
   *   Burndown ticket id for the task.
   * @param string $username
   *   User to remove from the watchlist for the task.
   *
   * @command burndown:unwatch_task
   * @aliases burndown-unwatch-task bdut
   * @usage burndown:unwatch_task {ticket_id} {username}
   */
  public function unwatch_task($ticket_id = '', $username = '') {
    $task = Task::loadFromTicketId($ticket_id);
    if ($task == FALSE) {
      $text = "Task does not exist.";
      $this->io()->caution($text);
      return;
    }

    $user = user_load_by_name($username);
    if ($user === FALSE) {
      $this->io()->caution('User account does not exist.');
      return;
    }

    // Add user to watchlist.
    $task->removeFromWatchlist($user);

    // Success message.
    $this->io()->success('User ' . $username . ' is no longer watching ' . $ticket_id);
  }

  /**
   * Drush command to send a Burndown Task to the board.
   *
   * @param string $ticket_id
   *   Burndown ticket id for the task.
   *
   * @command burndown:send_to_board
   * @aliases burndown-send-to-board bdstb
   * @usage burndown:send_to_board {ticket_id}
   */
  public function send_to_board($ticket_id = '') {
    $task = Task::loadFromTicketId($ticket_id);
    if ($task == FALSE) {
      $text = "Task does not exist.";
      $this->io()->caution($text);
      return;
    }

    // Get project.
    $project = $task->getProject();
    $shortcode = $project->getShortcode();

    // Check that this is not a sprint project.
    if ($project->isSprint()) {
      $text = "Project is sprint-based. Add this ticket to a sprint instead of moving it directly to the board.";
      $this->io()->caution($text);
      return;
    }

    // Check that task is in backlog.
    if (!$task->inBacklog()) {
      $text = "Task is on the board already.";
      $this->io()->caution($text);
      return;
    }

    // Move task to To Do column on the board.
    $todo = Swimlane::getTodoSwimlane($shortcode);
    if ($todo !== FALSE) {
      $task
        ->setSwimlane($todo)
        ->save();
    }

    // Success message.
    $this->io()->success('Task ' . $ticket_id . ' has been moved to the board.');
  }

  /**
   * Drush command to send a Burndown Task to the backlog.
   *
   * @param string $ticket_id
   *   Burndown ticket id for the task.
   *
   * @command burndown:send_to_backlog
   * @aliases burndown-send-to-backlog bdsfb
   * @usage burndown:send_to_backlog {ticket_id}
   */
  public function send_to_backlog($ticket_id = '') {
    $task = Task::loadFromTicketId($ticket_id);
    if ($task == FALSE) {
      $text = "Task does not exist.";
      $this->io()->caution($text);
      return;
    }

    // Get project.
    $project = $task->getProject();
    $shortcode = $project->getShortcode();

    // Get project.
    $project = $task->getProject();
    $shortcode = $project->getShortcode();

    // Check that this is not a sprint project.
    if ($project->isSprint()) {
      $text = "Project is sprint-based. Add this ticket to a sprint instead of moving it directly to the board.";
      $this->io()->caution($text);
      return;
    }

    // Check that task is on the board.
    if (!$task->onBoard()) {
      $text = "Task is in the backlog already.";
      $this->io()->caution($text);
      return;
    }

    // Place the task in the backlog.
    $backlog = Swimlane::getBacklogFor($shortcode);
    if ($backlog !== FALSE) {
      $task
        ->setSwimlane($backlog)
        ->save();
    }

    // Success message.
    $this->io()->success('Task ' . $ticket_id . ' has been moved to the backlog.');
  }

  /**
   * Drush command to add a Burndown Project.
   *
   * @command burndown:add_project
   * @aliases burndown-add-project bdap
   * @option name The name of the project.
   * @option shortcode The shortcode for the project.
   * @option username The username of the project owner.
   * @option board_type The type of board for the project (kanban or sprint).
   * @option estimate_type The type of estimation for the project (geometric, tshirt, dot).
   * @usage burndown:add_project --name="Project Name" --shortcode="PRJ" --username="admin" --board_type="kanban" --estimate_type="geometric"
   */
    public function add_project(array $options = [
      'name' => self::REQ,
      'shortcode' => self::REQ,
      'username' => self::REQ,
      'board_type' => self::REQ,
      'estimate_type' => self::REQ,
      ]) {
    // Title of the command.
    $this->io()->title('Add a Burndown Project');

    if (!empty($options['name'])) {
      $name = $options['name'];
    }
    else {
      // Get project name from user.
    $name = $this->io()->ask('What is the name of the project (max 50 chars)?');
    }

    // Check for blank name.
    if ($name == '') {
      $this->io()->caution('No project name specified.');
      return;
    }
    else {
      $name = substr($name, 0, 50);
    }

    if (!empty($options['shortcode'])) {
      $shortcode = $options['shortcode'];
    }
    else {
      // Get shortcode from user.
      $shortcode = $this->io()->ask('What is the shortcode (i.e. 4 or 5 letter code) for the project?');
    }

    // Check for blank shortcode.
    if ($shortcode == '') {
      $this->io()->caution('No project shortcode specified.');
      return;
    }
    else {
      // Transform to uppercase alphabetical version of the string.
      $shortcode = preg_replace("/[^a-zA-Z]+/", "", $shortcode);
      $shortcode = strtoupper($shortcode);

      // Ensure the shortcode is unique.
      $project_count = \Drupal::entityQuery('burndown_project')
        ->condition('shortcode', $shortcode)
        ->count()
        ->accessCheck(FALSE)
        ->execute();

      if ($project_count > 0) {
        $this->io()->caution('There is already a project with the shortcode ' . $shortcode . '. The shortcode must be unique.');
        return;
      }
    }

    if (!empty($options['username'])) {
      $username = $options['username'];
    }
    else {
    // Get project owner from user.
    $username = $this->io()->ask('What is the username of the project owner?', 'admin');
    }

    $user = user_load_by_name($username);
    if ($user === FALSE) {
      $this->io()->caution('User account does not exist.');
      return;
    }

    // Get board type from user.
    $board_types = [
      'kanban' => 'Kanban',
      'sprint' => 'Sprint',
    ];
    if (!empty($options['board_type'])) {
      $board_type = $options['board_type'];
      $board_type = strtolower($board_type);
      if (!in_array($board_type, array_keys($board_types))) {
        $this->io()->caution('Invalid board type specified. Must be either "kanban" or "sprint".');
        return;
      }
    }
    else {
      $board_type = $this->io()->choice('Is this a kanban or sprint-based project?', $board_types, $board_types[0]);
    }
    // Estimate type.
    $estimate_types = [
      'geometric' => 'Geometric',
      'tshirt' => 'T-Shirt Sizing',
      'dot' => 'Dot Sizing',
    ];
    if (!empty($options['estimate_type'])) {
      $estimate_type = $options['estimate_type'];
      $estimate_type = strtolower($estimate_type);
      if (!in_array($estimate_type, array_keys($estimate_types))) {
        $this->io()->caution('Invalid estimate type specified. Must be either "geometric", "tshirt", or "dot".');
        return;
      }
    }
    else {
      $estimate_type = $this->io()->choice('What type of estimation do you want to use for this project?', $estimate_types, $estimate_types[0]);
    }

    // Try to add the project.
    $project = Project::create([
      'type' => 'project',
      'name' => $name,
      'shortcode' => $shortcode,
      'user_id' => $user->id(),
      'board_type' => $board_type,
      'estimate_type' => $estimate_type,
      'status' => 1,
    ]);
    $project->save();

    // Success message.
    $this->io()->success('Project ' . $shortcode . ' has been added');
  }

  /**
   * Drush command to edit a Burndown Project.
   *
   * @param string $shortcode
   *   Shortcode of the project to be edited.
   *
   * @command burndown:edit_project
   * @aliases burndown-edit-project bdep
   * @usage burndown:edit_project {shortcode}
   */
  public function edit_project($shortcode) {
    // Title of the command.
    $this->io()->title('Edit a Burndown Project');

    // Check for blank shortcode.
    if ($shortcode == '') {
      $this->io()->caution('No project shortcode specified.');
      return;
    }
    else {
      $shortcode = strtoupper($shortcode);
    }

    // Load project and make sure shortcode is ok.
    $project = Project::loadFromShortcode($shortcode);
    if ($project == FALSE) {
      $this->io()->caution('Project does not exist.');
      return;
    }

    // Get project name from user.
    $name = $this->io()->ask('What is the name of the project (max 50 chars)?', $project->getName());

    // Check for blank name.
    if ($name == '') {
      $this->io()->caution('No project name specified.');
      return;
    }
    else {
      $name = substr($name, 0, 50);
    }

    // Get project owner from user.
    $username = $this->io()->ask('What is the username of the project owner?', $project->getOwner()->getDisplayName());
    $user = user_load_by_name($username);
    if ($user === FALSE) {
      $this->io()->caution('User account does not exist.');
      return;
    }

    // Get board type from user.
    $board_types = [
      'kanban' => 'Kanban',
      'sprint' => 'Sprint',
    ];


    $board_type = $this->io()->choice('Is this a kanban or sprint-based project?', $board_types, $project->getBoardType());

    // Estimate type.
    $estimate_types = [
      'geometric' => 'Geometric',
      'tshirt' => 'T-Shirt Sizing',
      'dot' => 'Dot Sizing',
    ];
    $estimate_type = $this->io()->choice('What type of estimation do you want to use for this project?', $estimate_types, $project->getEstimateType());

    // Check if project should be published.
    $status = $this->io()->confirm('Do you want the project to be published?', $project->getStatus());
    $status = $status ? 1 : 0;

    // Update project.
    $project
      ->setName($name)
      ->setBoardType($board_type)
      ->setEstimateType($estimate_type)
      ->setStatus($status)
      ->save();

    // Success message.
    $this->io()->success('Project ' . $shortcode . ' has been updated');
  }

  /**
   * Drush command to search for a Burndown Task.
   *
   * @command burndown:search_tasks
   * @aliases burndown-search-tasks bdst
   * @usage burndown:search_tasks
   */
  public function search_tasks() {
    // Title of the command.
    $this->io()->title('Search for a Burndown Task');

    // Get shortcode from user.
    $shortcode = $this->io()->ask('What is the shortcode (i.e. 4 or 5 letter code) for the project?');

    // Check for blank shortcode.
    if ($shortcode == '') {
      $this->io()->caution('No project shortcode specified.');
      return;
    }
    else {
      $shortcode = strtoupper($shortcode);
    }

    // Load project and make sure shortcode is ok.
    $project = Project::loadFromShortcode($shortcode);
    if ($project == FALSE) {
      $this->io()->caution('Project does not exist.');
      return;
    }

    // Get search string from user.
    $search = $this->io()->ask('Please enter the search string');

    // Search tasks.
    $tasks = Task::search($search, $shortcode);

    // Output header.
    $text = 'Ticket ID   Description           Column       Assigned To';
    $this->output()->writeln($text);
    $text = '============================================================';
    $this->output()->writeln($text);

    $count = 0;

    if ($tasks !== FALSE) {
      foreach ($tasks as $task) {
        $text = $this->output_task_row($task);
        $this->output()->writeln($text);
      }

      $count = count($tasks);
    }

    // Success message.
    $this->io()->success('Tasks found: ' . $count);
  }

}
