# AI Integration - ECA: Agents

WARNING: this agent has the capability of creating ECA models, without
"realizing" that the result may be destructive. Meaning that, if you ask it to
create a model that removes all nodes when a user logs in, nothing is holding
it back. Every model it creates is saved in a disabled state, so a human has to
review and enable it.

USE AT YOUR OWN RISK!

## Requirements

This submodule requires **ECA 3**, and through it the Modeler API and Drupal
11.3 or newer. ECA 3 keeps the label, version and documentation of a model in
`modeler_api` third party settings rather than as entity properties, and the
agent writes models that way.

The parent module still supports ECA 2, so a site on ECA 2 can keep using the
actions and automators; only this submodule is unavailable there.

## Scope

- Answer questions about existing models
- Answer questions about specific components, like events, conditions or actions
- Create new models

Editing, enabling, disabling or deleting an existing model is explicitly out of
scope. The agent refuses those requests and the tool that writes models rejects
a model id that already exists.

## Usage

- Go to `/admin/config/workflow/eca`
- Click on `Ask AI` and write down your question
    - The same button is available on the edit-screen of a model

The agent is a regular AI Agents config entity with the id `eca`, so it
can also be run from anywhere else that agents are available, for instance:

```bash
drush agents:run eca
```

## Architecture

The agent is built on the Tools + ReAct architecture of the AI Agents module.
It consists of an `ai_agent` config entity and five function call plugins:

| Plugin id                                       | Purpose                                                                            |
|-------------------------------------------------|------------------------------------------------------------------------------------|
| `ai_integration_eca:get_model_schema`           | The JSON Schema that a model has to comply with.                                     |
| `ai_integration_eca:list_models`                | The models that exist, as a summary or in full detail.                               |
| `ai_integration_eca:list_components`            | The events, conditions and actions that are available, optionally filtered.          |
| `ai_integration_eca:get_component_details`      | The configuration keys and exposed tokens of specific components.                    |
| `ai_integration_eca:create_model`               | Creates a new, disabled model from a JSON definition. Refuses to touch existing ones. |

The schema, the summary of the existing models and the list of components are
pre-loaded as default information tools, so the agent always starts with them.
The other tools are called by the agent itself during its loop.

Every tool checks that the current user holds one of `administer eca`,
`modeler api administer eca` or `modeler api edit eca`.

Because the agent is a config entity, it can be adjusted without writing code:
change its instructions, tools or loop limit at
`/admin/config/ai/agents/eca`.

## Evaluation

The kernel tests cover the tools in isolation. Whether the agent picks the
right tools and builds a model that actually works is measured separately, by
the harness in `modules/agents/eval/`. It runs a set of prompts against a live
LLM and asserts on behavior: did it write when it was supposed to, does the
model hold together, was an existing model left alone.

Run it by hand after changing the system prompt, the tools or the model:

```bash
drush php:script modules/agents/eval/run.php -- --repeat=3
```

See `modules/agents/eval/README.md` for the options and `eval/results/` for
the recorded runs.

## Roadmap

This has no specific order or priority, just things that I can think of right
now

- Editing existing models, as a separate tool with its own safeguards
- Refactor the custom Typed Data model once config validation is in core
    - [https://www.drupal.org/project/eca/issues/3446331](https://www.drupal.org/project/eca/issues/3446331)
- Create a separate sub-agent that can interpret an image and provide a prompt
  for the main one
    - See Webform Agent

## Resources

- [https://webthesis.biblio.polito.it/31175/1/tesi.pdf](https://webthesis.biblio.polito.it/31175/1/tesi.pdf)
- [https://github.com/RobJenks/gpt-codegen](https://github.com/RobJenks/gpt-codegen)
- [https://github.com/jtlicardo/bpmn-assistant](https://github.com/jtlicardo/bpmn-assistant)
- [https://ceur-ws.org/Vol-3758/paper-15.pdf](https://ceur-ws.org/Vol-3758/paper-15.pdf)
