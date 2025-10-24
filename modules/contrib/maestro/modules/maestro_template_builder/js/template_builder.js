(function (Drupal, once, $, Maestro) {
  Drupal.behaviors.maestroTemplateBuilderDiagram = {
    attach(context, settings) {
      const container = once('maestro-diagram', '#template_canvas', context).pop();
      if (!container) return;
      TemplateBuilder.setup();
     
      TemplateBuilder.createTemplateCanvas(container);

      // For each task, create the display
      // Connection lines are drawn after all tasks are displayed.
      Maestro.tasks.forEach((task) => {
        TemplateBuilder.drawOneTask(task);
      });
      
      // Now for each of the displayed tasks we will draw their connectors.
      for(var taskID in Maestro.maestroDisplayedTasks) {
        TemplateBuilder.drawConnectors(taskID);
      }
    }
  };

  // General Drupal functions

  // Bind to the task menu's close button and initialize the task clicked value
  $('#close-task-menu').click(function() {
    $('#maestro-task-menu').css('display', 'none');
    $('[name="task_clicked"]').val('');
  });

  // Simple removal function used in the task menu
  Drupal.maestroRemoveTaskMenuSelect = function(event) {
    var x = confirm('Remove this task?');
    if(x) {
      jQuery('#edit-remove-task-complete').trigger('mousedown');
    }
  }

  // Drupal AjaxCommands
  Drupal.AjaxCommands.prototype.addNewTask = function(ajax, response, status) {
    task = {};
    task.id = response.id;
    task.type = response.type;
    task.taskname = response.label;
    task.uilabel = response.uilabel;
    task.top = 15;
    task.left = 15;
    task.lines = [];
    task.to = [];
    task.nextfalsestep = [];
    task.falsebranch = [];
    task.pointedfrom = [];
    task.capabilities = response.capabilities;
    task.participate_in_workflow_status_stage = '';
    task.workflow_status_stage_number = 0;
    task.workflow_status_stage_message = '';
    Maestro.maestroDrawOneTask(task);
  }

  Drupal.AjaxCommands.prototype.alterCanvas = function(ajax, response, status) {
    $('#template_canvas').width(response.width);
    $('#template_canvas').height(response.height);
  }

  Drupal.AjaxCommands.prototype.signalValidationRequired = function(ajax, response, status) {
    // Turn on the needs validation flag here.
    // Remove and re-add the fadeout
    $('#maestro-template-validation').removeClass('fade-out');
    $('#maestro-template-validation').css('display', 'block');
    $('#maestro-template-validation').css('width', 'auto');
    $('#maestro-template-validation').css('height', 'auto');
    
    setTimeout(function() {
      $('#maestro-template-validation').addClass('fade-out');
    }, 500);
    
  }

  Drupal.AjaxCommands.prototype.turnOffValidationRequired = function(ajax, response, status) {
    // Turn off the needs validation flag here.
    $('#maestro-template-validation').css('display', 'none');
  }

  Drupal.AjaxCommands.prototype.maestroCloseTaskMenu = function(ajax, response, status) {
    $('#maestro-task-menu').css('display', 'none');
  }

  Drupal.AjaxCommands.prototype.maestroEditTask = function(ajax, response, status) {
    $('#edit-edit-task-complete').trigger('click');
  }
  
  Drupal.AjaxCommands.prototype.maestroShowSavedMessage = function(ajax, response, status) {
    //turn on the task's saved notification in ID save-task-notificaiton
    $('#drupal-modal').animate({ scrollTop: 0 }, "fast");
    $('#save-task-notificaiton').css('display', 'block');
    $('#save-task-notificaiton').fadeOut(3000);
  }

  Drupal.AjaxCommands.prototype.maestroUpdateMetaData = function(ajax, response, status) {
    const newLabel = response.label;
    const taskID = response.taskid || '';
    const statusNumber = response.workflow_status_stage_number || '';
    const statusMessage = response.workflow_status_stage_message || '';
    
    var sampleText, task;
    sampleText = newLabel.substring(0,14);
    if(sampleText != newLabel) {
      sampleText += '...';
    }

    // Within each task object is the ID of the task we're editing.
    task = Maestro.maestroGetTaskReference(taskID);
    // task is a reference to the Dialog.js task drawn.
    // Update the metadata on the task such as the label and the status number (if applicable)
    const labelElement = document.querySelector(`text[data-task-label="${taskID}"]`);
    if (labelElement) {
      labelElement.textContent = sampleText; // Set the new label in the SVG Text 
      task.taskName = newLabel; // Set our stored task's new label as Diagram.js redraws the rectangle after drag operations.
    }
    // Now set the status text, if it exists.
    const statusNumberElement = document.querySelector(`text[data-task-status-number="${taskID}"]`);
    if(statusNumberElement) {
      statusNumberElement.textContent = statusNumber;
      task.workflowStatusMessage = statusMessage;
      task.workflowStatusNumber = statusNumber;
    }
  }

  Drupal.AjaxCommands.prototype.maestroRemoveTaskLines = function(ajax, response, status) {
    Maestro.maestroRemoveTaskLines(response.task);
  }

  Drupal.AjaxCommands.prototype.maestroRemoveTask = function(ajax, response, status) {
    // First, remove all connections to/from this task
    Maestro.maestroRemoveTaskLines(response.task);
    
    // Get the task reference
    var task = Maestro.maestroGetTaskReference(response.task);
    if (!task) {
        console.warn('Task not found:', response.task);
        return;
    }
    
    // Remove the shape/element from the canvas
    const canvas = Maestro.maestroDiagram.get('canvas');
    canvas.removeShape(task);
    
    // Remove from your Drupal storage object
    delete Maestro.maestroDisplayedTasks[response.task];
    Drupal.AjaxCommands.prototype.maestroCloseTaskMenu(null, null, null);
  }

  Drupal.AjaxCommands.prototype.maestroDrawLineTo = function(ajax, response, status) {
    Maestro.maestroClearCanvasSeletedElement();
    Maestro.maestroLineFrom = response.taskid;
    var message = Drupal.t('Please choose the task to draw the line to.');
    $('.maestro-template-message-area').html(message);
    $('.maestro-template-message-area').css('display', 'block');
  }

  Drupal.AjaxCommands.prototype.maestroDrawFalseLineTo = function(ajax, response, status) {
    Maestro.maestroClearCanvasSeletedElement();
    Maestro.maestroFalseLineFrom = response.taskid;
    var message = Drupal.t('Please choose the task to draw the FALSE line to.');
    $('.maestro-template-message-area').html(message);
    $('.maestro-template-message-area').css('display', 'block');
  }

})(Drupal, once, jQuery, window.Maestro);
