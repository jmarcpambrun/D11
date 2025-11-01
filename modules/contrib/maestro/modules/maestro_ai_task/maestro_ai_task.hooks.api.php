<?php


/*
 * The following are all of the Maestro AI Task module hooks that are available for use.
 */


// Found in maestro_ai_task.module
// Provide the AI Task token render for an AI Entity
function hook_maestro_ai_task_render_ai_entity(\Drupal\maestro_ai_task\Entity\MaestroAIStorage $entity, $render_as, $queueID) {
  // You are provided a fully loaded AI task entity and the render_as type. 
  // Using this information, you can render the output as you see fit.

  // 'text' and 'image_url' are the default "render_as" types and already provided in the maestro_ai_task module.
};


