**AI Integration - ECA** is the bridge between Drupal's [AI module](https://www.drupal.org/project/ai) and the [ECA](https://www.drupal.org/project/eca) (Events-Conditions-Actions) framework. It turns AI into a first-class building block in your no-code workflows: every AI operation the AI module can perform - chat, moderation, embeddings, text-to-speech, speech-to-text, image and audio generation, classification, object detection, translation - becomes an **action** you can drop into an ECA model, wire to any Drupal event, and gate with any condition, without writing code.

## Important

This module replaces the earlier attempts to integrate AI and ECA, both now obsolete: the standalone [AI - ECA](https://www.drupal.org/project/ai_eca) module and the ECA integration submodule that shipped inside the main [AI](https://www.drupal.org/project/ai) module. All of that functionality now lives here - the package declares `replace: drupal/ai_eca`. If you are coming from either, this is the module to use going forward.

## What it provides

### AI operations as ECA actions

The heart of the module is a set of ECA actions, one per AI operation type:

- **Text** - Chat, Moderation, Embeddings, Translate Text
- **Speech** - Text to Speech, Speech to Text
- **Images & media** - Text to Image, Image to Image, Image to Video, Speech to Speech, Audio to Audio
- **Understanding** - Image Classification, Object Detection

Each action selects a provider and model for its operation type, reads its input from an ECA token, and writes its result back into another token for later steps in the model. Chat and Text to Image take their prompt from a dedicated field instead, where ECA tokens are replaced automatically. The file-based actions (the image/audio/video ones) accept input as a managed file, a path or URI, or raw binary, and always return their results as a list of file references.

The complete action reference - inputs, outputs, and result-token shapes - is in the [usage documentation](https://project.pages.drupalcode.org/ai_integration_eca/usage/).

### Token-based data flow

Like all ECA plugins, these actions communicate through ECA's token system: input comes from a configurable token, results are stored into another, and any `[token]` placeholder in a prompt or configuration value resolves against the full ECA token scope. That is what lets an AI action slot into a larger model - a content event can feed its text into a moderation or embedding action, and that action's result can drive the next condition or action in the flow.

## Modeler Assistant

The module also ships an **AI chat assistant embedded in the ECA modeler**. While editing a model you can ask it questions about the model on your canvas, or ask which component fits the task at hand. When you ask it to build something, it proposes a one-click plan - adding nodes, connecting them, and attaching conditions - validated against your site before you can apply it. It runs through the AI module's chat-processor pipeline using your default chat provider, so it works wherever the AI module and a configured chat provider do, and it can be swapped for a custom processor if you want different behavior.

## Submodules

The package includes three optional submodules.

### AI ECA Agents

Enable an LLM to answer questions about your existing ECA models and components - and to **create new models** from a natural-language description. The agent is a regular AI Agents config entity (`eca`) built on the Tools + ReAct architecture, so it can be run from the ECA admin screen, the model edit screen, or anywhere else agents are available (for example `drush agents:run eca`). It can also be triggered from inside a model with the *AI: Trigger Agent* action. Every model it creates is saved in a **disabled** state for human review - read the [agents documentation](https://project.pages.drupalcode.org/ai_integration_eca/modules/agents/) before use. (This submodule requires ECA 3 / Drupal 11.3+.)

### AI ECA Automators

Brings [AI Automators](https://www.drupal.org/project/ai_automators) into ECA as an action. *AI Automator Trigger* runs a predefined automator rule against an entity from within a model, so AI-driven field interpolation can be triggered at exactly the point your workflow needs it - with options to overwrite existing values and save the entity afterwards.

### AI ECA Interceptor

Turns the AI module's provider proxy into something your models can watch and steer. Every AI call dispatches events - before the provider is called, after it answers, when a streamed answer finishes, and when it fails - and this submodule exposes those moments as ECA events. A rich set of conditions and actions (tagging, rerouting to a cheaper model, blocking requests, attaching guardrails, failing over to a backup provider, counting tokens, and more) lets you govern AI request/response traffic - including calls made by *other* modules - from a model instead of from code. Unlike the other two submodules it does not depend on the parent module and can be enabled on its own. Details are in the [interceptor documentation](https://project.pages.drupalcode.org/ai_integration_eca/modules/ai_eca_interceptor/).

## Documentation

The [full documentation](https://project.pages.drupalcode.org/ai_integration_eca) contains the usage guide, the action reference, and the submodule guides.
