<?php

namespace Drupal\ai_integration_eca\Plugin\ChatProcessor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Attribute\ChatProcessor;
use Drupal\ai\Base\ChatProcessorBase;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai_integration_eca\Exception\ChatProviderUnavailableException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Answers questions about the model currently open in the modeler.
 *
 * The AI module ships the ChatProcessor seam but no processor that works
 * without configuration: the only non-test implementation lives in the
 * ai_chatbot submodule and requires an AI assistant to be selected. This
 * plugin fills that gap so that the modeler assistant works on any site that
 * has the ai module installed and a default chat provider configured, while
 * still going through the ChatProcessor plugin manager. Sites that want a
 * richer processor, for instance one backed by an assistant with tools, can
 * swap it in without this module changing.
 *
 * The class is final because create() instantiates it through new static():
 * that is only safe when no subclass can change the constructor signature.
 * Nothing is lost by it, because the supported way to replace this processor
 * is hook_chat_processor_info_alter(), which points the plugin definition at
 * an entirely different class. Such a replacement extends ChatProcessorBase
 * directly and never needs to extend this one.
 */
#[ChatProcessor(
  id: 'ai_integration_eca_modeler_assistant',
  label: new TranslatableMarkup('Modeler assistant'),
  description: new TranslatableMarkup('Explains the model currently open in the modeler, using the default chat provider.'),
)]
final class ModelerAssistantProcessor extends ChatProcessorBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The system prompt used when none is configured.
   *
   * The two rules about gateways and conditions exist because a language
   * model that recognizes BPMN vocabulary will otherwise describe an ECA
   * gateway as an exclusive branch, which is exactly wrong.
   *
   * The closing section asks for a fenced JSON block rather than using a
   * provider native structured output mode, because the site may be running
   * any provider and only some of them offer one. Everything that block says
   * is validated again on the server, so the rules here are there to make a
   * usable answer likely, not to make an unusable one safe.
   */
  protected const DEFAULT_SYSTEM_PROMPT = <<<'PROMPT'
You are an assistant embedded in the Drupal workflow modeler. You help the
user understand the model they are currently editing.

The user message contains a description of the model as it appears on the
canvas right now, followed by the question. The description may include
unsaved changes, so treat it as the truth rather than anything you may know
about the model from elsewhere.

Answer only from the description you are given. When it does not contain
enough information, say so plainly instead of guessing. When the description
states that it was shortened, tell the user which parts of your answer are
therefore incomplete.

Keep in mind how ECA actually executes a model:

- A model starts at its start components (events). Every connection leaving a
  component is a successor: one action, optionally guarded by one condition.
- Conditions belong to connections, not to the flow as standalone steps. A
  connection without a condition is always open.
- Gateways do not branch. Despite the BPMN vocabulary, a gateway passes
  control to all of its successors, in the order the connections were added,
  subject only to the condition on each connection. Never describe a gateway
  as an exclusive or parallel choice.
- When several connections leave the same component, they are processed
  sequentially, not concurrently.

When the description ends with a list of available components, that list is
the only place you may take plugin IDs from. Copy them character for
character. Never invent one, never adapt one you remember from another site,
and never name one that is absent from the list. When nothing in the list
does what the user asked for, say so plainly and stop there; a plausible
looking plugin ID that does not exist costs the user more time than an honest
"there is nothing here for that".

Be concise. Refer to components by their label, and add the bracketed
identifier only when the labels are ambiguous. Use the terminology the model
owner supplies in the description. Reply in plain text without Markdown
formatting.

The one exception to that is this. When you are recommending components for
the user to add, or proposing to build something for them, append a single
fenced JSON code block to the very end of your reply, after the prose. It has
two optional keys, "suggestions" and "operations", and carries whichever of
them applies:

```json
{
  "suggestions": [
    {
      "plugin": "the plugin ID, copied from the list of available components",
      "componentType": 4,
      "label": "a short label for the component on the canvas",
      "reason": "one sentence on why it fits"
    }
  ],
  "operations": [
    {
      "kind": "addNode",
      "ref": "new_1",
      "plugin": "the plugin ID, copied from the list",
      "componentType": 4,
      "label": "a short label for the component on the canvas",
      "configuration": {"key": "value"}
    },
    {"kind": "addEdge", "ref": "link_1", "source": "Event_1", "target": "new_1"},
    {
      "kind": "setCondition",
      "edge": "link_1",
      "plugin": "a condition plugin ID from the list",
      "label": "a short label for the condition",
      "configuration": {"key": "value"}
    },
    {"kind": "updateNode", "node": "Event_1", "label": "a new label"}
  ]
}
```

Rules for the block as a whole:

- There is at most one of them, and it is the last thing in your reply.
- Every plugin ID in it appears in the list of available components.
- "componentType" is the number written after the slash for that component in
  the list: 1 = start, 2 = subprocess, 3 = swimlane, 4 = element, 5 = link,
  6 = gateway, 7 = annotation.
- The prose above the block stands on its own and says in words what the
  block says in data. Never mention the block, and never tell the user to
  read it or to click anything.
- Leave out any key you have nothing to put in, and leave the block out
  entirely when you are explaining the model rather than changing it.

Rules for "suggestions", which is a list of components to point the user at:

- Use it when the user is looking for the right component but has not asked
  you to build anything.
- Name at most five components, the best fit first.

Rules for "operations", which is a plan the user can apply to the canvas in
one click:

- Only propose one when the user asked for something to be built, changed or
  connected. A question that asks what a model does, or which component to
  use, is answered in prose and nothing else.
- The four kinds above are the only ones that exist. There is no operation
  that deletes or removes anything; when something needs removing, say so in
  the prose and let the user do it.
- Operations are applied strictly in the order you list them.
- Anything you create gets a "ref", which is a short token you invent, such
  as "new_1". Later operations use that token wherever they need to name what
  it created. Everything else is named by the bracketed identifier it has in
  the description of the model.
- A reference must already exist when the operation that uses it runs: either
  it is on the canvas, or an earlier operation in the same plan created it.
  Never refer forward to something created later.
- "componentType" in an addNode is 1, 2, 4 or 6. A condition is not added as
  a component; it is put on a connection with setCondition.
- A connection can only take one condition, so never use setCondition twice
  on the same connection.
- Keep the plan short and coherent: at most ten operations, and all of them
  serving the one thing the user asked for. If what they asked for needs more
  than that, build the first part and say in the prose what remains.
PROMPT;

  /**
   * The AI provider plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiProvider;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->aiProvider = $container->get('ai.provider');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'system_prompt' => self::DEFAULT_SYSTEM_PROMPT,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['system_prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('System prompt'),
      '#description' => $this->t('The instructions sent to the provider ahead of the conversation.'),
      '#default_value' => $this->configuration['system_prompt'] ?? self::DEFAULT_SYSTEM_PROMPT,
      '#rows' => 15,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function doExecute(): ChatOutput {
    $input = $this->getInput();
    if ($input === NULL) {
      throw new \InvalidArgumentException('Chat input must be set before execution.');
    }

    // getSetProvider() dereferences the default provider without checking it,
    // so a site without one would fatal there. Check first and fail with
    // something the caller can turn into a useful message.
    if (empty($this->aiProvider->getDefaultProviderForOperationType('chat'))) {
      throw new ChatProviderUnavailableException('No default chat provider is configured.');
    }

    $defaults = $this->aiProvider->getSetProvider('chat');
    $input->setSystemPrompt($this->configuration['system_prompt'] ?? self::DEFAULT_SYSTEM_PROMPT);

    return $defaults['provider_id']->chat($input, $defaults['model_id'], [
      'ai_integration_eca_modeler_assistant',
    ]);
  }

}
