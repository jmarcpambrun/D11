# CLAUDE.md

Guidance for working in the **AI Integration - ECA** module (`ai_integration_eca`).

## What this module is

A Drupal contrib module that bridges the [AI module](https://drupal.org/project/ai)
and the [ECA module](https://drupal.org/project/eca) (Event-Condition-Action). It
exposes AI operation types (chat, moderation, embeddings, text-to-speech,
speech-to-text) as ECA **actions** so site builders can wire AI into ECA models
without code.

It supersedes the old `ai_eca` module and the `ai`-submodule integration (both
obsolete; `composer.json` declares `replace: drupal/ai_eca`).

- **Core:** `^10.3 || ^11`
- **Hard deps:** `ai:ai`, `eca:eca`, `drupal:file`, `drupal/token`
- **Soft deps (submodules):** `ai_agents` (agents submodule), `ai_automators`
  (automators submodule), `ai_eca_interceptor` (interceptor submodule)

## Layout

```
src/Plugin/Action/         # The main deliverable: ECA actions (see below)
src/Service/               # AiProviderValidator — validates model config YAML
tests/src/Kernel/          # Kernel tests, mirror the src/ tree
modules/agents/            # Submodule: AI Agents <-> ECA model building
modules/automators/        # Submodule: AI Automators as ECA actions
modules/ai_eca_interceptor/  # Submodule: intercepts AI requests/responses via
                              # ECA events/conditions/actions (PHP attributes
                              # only — see "ai_eca_interceptor conventions" below)
docs/                      # MkDocs source (published to drupalcode pages)
```

## How actions are made

This is the most common task in this repo. **Read an existing action before
writing a new one** — `Chat.php` (full-featured) and `Moderation.php` (minimal)
are the two reference points.

### Anatomy of an action

Actions live in `src/Plugin/Action/`, namespace
`Drupal\ai_integration_eca\Plugin\Action`, and use the legacy `@Action`
annotation (not PHP attributes — match the existing style):

```php
/**
 * @Action(
 *   id = "ai_integration_eca_execute_<name>",
 *   label = @Translation("..."),
 *   description = @Translation("...")
 * )
 */
class MyAction extends AiConfigActionBase { ... }
```

Convention: action `id` is `ai_integration_eca_execute_<operation>` and the class
extends one of the two base classes.

### Choose a base class

Both bases provide the `model`, `token_input` and `token_result` fields and the
`provider_id__model_id` model handling. Actions that do not read `token_input`
(currently `Chat` and `TextToImage`) drop the field by unsetting it in
`defaultConfiguration()`, `buildConfigurationForm()` and
`submitConfigurationForm()` — see how `Chat.php` does it. Pick based on whether
the operation accepts model configuration:

| Base | Adds | Use for |
|------|------|---------|
| `AiActionBase` | model select + token in/out only | operations with no extra config — e.g. `Moderation` |
| `AiConfigActionBase` | a `config` YAML textarea, validated against the provider via `AiProviderValidator`, plus `getModelConfig()` and `getExtraConstraints()` | operations that take provider params (temperature, voice, response_format…) — e.g. `Chat`, `Embedding`, `TextToSpeech`, `SpeechToText` |

`AiConfigActionBase` extends `AiActionBase` extends `eca`'s `ConfigurableActionBase`.

### Required: `getOperationType()`

Every action **must** implement `protected function getOperationType(): string`.
It returns the AI module operation-type machine name and is used both to filter
the model dropdown (`getSimpleProviderModelOptions()`) and to validate config.
Known values: `chat`, `moderation`, `embeddings`, `text_to_speech`,
`speech_to_text`.

### Required: `execute()`

The standard shape (see `Chat::execute()` / `Embedding::execute()`):

```php
public function execute(?object $object = NULL): void {
  $modelData = $this->getModelData();              // ['provider_id' => ..., 'model_id' => ...]
  try {
    $provider = $this->loadModelProvider();        // ProviderProxy
  }
  catch (PluginException $e) {
    return;                                         // fail silently, by convention
  }

  // 1. Read the configured input. Never getTokenData() or replace() on a
  //    configured field directly - see "Reading a configured field" below.
  $input = new ModerationInput($this->resolveTokenString($this->configuration['token_input']));

  // 2. (AiConfigActionBase only) apply model config.
  $provider->setConfiguration($this->getModelConfig());

  // 3. Call the AI operation, passing $modelData['model_id'].
  $response = $provider->moderation($input, $modelData['model_id'])->getNormalized();

  // 4. Write the result back into the token bag for later ECA steps.
  $this->tokenService->addTokenData($this->configuration['token_result'], $result);
}
```

Notes:
- The `model` config value is the string `provider_id__model_id` (double
  underscore). `getModelData()` splits it; `loadModelProvider()` instantiates the
  provider. Both throw `\InvalidArgumentException` on a malformed value.
- `$this->tokenService` is the `eca.token_services` service (from
  `ConfigurableActionBase`). Input comes from `token_input`, output goes to
  `token_result`. For non-scalar results, wrap in
  `Drupal\eca\Plugin\DataType\DataTransferObject::create($array)` (see
  `Moderation`/`Embedding`). Actions that take their input from a dedicated
  free-text field instead (like the Chat prompt) run that text through
  `tokenService->replace()` so `[token]` placeholders resolve against the full
  ECA token scope.
- `Chat` additionally runs the prompt and the system prompt through
  `tokenService->replace()` so both can contain `[token]` placeholders and Drupal
  tokens like `[site:name]`. The system prompt is set with
  `ChatInput::setSystemPrompt()`, never appended to the messages.

### Reading a configured field

`token_input`, `token_mask` and `files` each hold one of three things: the name
of an ECA token, a string containing Drupal tokens, or a literal value. Two
protected methods on `AiActionBase` resolve all three, and every reader in
`src/Plugin/Action/` goes through one of them:

| Method | Returns | Use for |
|--------|---------|---------|
| `resolveTokenValue(string $configured): mixed` | the token data itself, or the replaced string, or NULL | inputs that may be a list or a data transfer object (`Chat::resolveFileValues()`) |
| `resolveTokenString(string $configured): string` | the same, flattened (Entity → ID, TypedData → `getString()`, scalar → string) | everything else |

The order is **token first, replacement second**: `tokenNameFrom()` treats the
value as a candidate token name unless it contains whitespace or a slash (a
bracketed `[name]` is unwrapped first), `getTokenData()` is asked, and only a
`NULL` from that falls through to `tokenService->replace()`. That order matters
— replacement flattens, so a token holding a list would be lost — and the guard
is deliberately negative, because ECA's `TokenDecoratorTrait::normalizeKey()`
ignores case and reads `.` the way it reads `:`, so `my.sub` has always
resolved here.

Do **not** call `getTokenData()` or `replace()` on a configured field directly,
and do not test a field with `hasTokenData()`: that only sees the ECA token
stack, so a Drupal token or a literal value looks absent and is silently
dropped. Resolve first, then check the result for emptiness.

`getTokenString(string $tokenName)` is the old token-name-only reader, kept for
actions outside this module that extend the base. Nothing in here calls it.

### Optional overrides

- `defaultConfiguration()` — add defaults; **always** `+ parent::defaultConfiguration()`.
- `buildConfigurationForm()` / `submitConfigurationForm()` — add your own form
  fields; call `parent::` first and persist values into `$this->configuration`.
  Mark a field that *names* the token to write to with
  `'#eca_token_reference' => TRUE`; mark a field that *holds* a value with
  `'#eca_token_replacement' => TRUE`. The reference flag forbids `[name]`, so a
  field that also takes a Drupal token or a literal value must not carry it —
  the model cannot be saved otherwise. `token_result` is the only reference
  field left in this module.
- `getExtraConstraints()` (AiConfigActionBase) — return Symfony Validator
  constraints keyed by config field to validate the YAML `config` blob. No action
  overrides it today; the base returns an empty array. Reach for it only for a
  provider setting that the provider's own API definition does not describe — a
  setting of the action itself belongs in a form field of its own.

### Test every action

Add a kernel test under `tests/src/Kernel/Plugin/Action/` extending
`AiActionTestBase`. Tests use the `ai_test` module's **EchoAI** provider (see
"Spoofing the AI provider" below) — the model string is **`echoai__ai`** and it
echoes input back including config, so you assert on `'Hello world!'` plus your
own input/config strings. Pattern: build the action via
`$this->actionManager->createInstance($id, $config)`, then
`assertTrue($action->access(NULL))`, `$action->execute()`, and read the result
with `$this->tokenService->replace("[$tokenResultName]")`. `ChatTest` is the most
complete example (covers all-options, minimal-options, token replacement, and a
forbidden-access validation case).

### File-based actions and the `AiFileHelper`

Operations that take or return binary files (images, audio, video) use the
`ai_integration_eca.file_helper` service (`src/Service/AiFileHelper.php`) so they
don't each re-implement file conversion. Inject it in the action's `create()`
(`$container->get('ai_integration_eca.file_helper')`) and use:

- `resolveInput(string $value, string $type, string $fileClass)` — turn a token
  value into an `ImageFile`/`AudioFile`/`VideoFile`. `$type` is the configured
  `input_type`: `fid` (managed file ID), `path` (path/stream URI) or `binary`
  (raw bytes). It is generically typed (`@template T`), so passing
  `ImageFile::class` returns an `ImageFile` — no cast needed. Throws
  `\InvalidArgumentException` on bad input; actions catch it and `return` quietly.
- `buildOutput(array $files, string $type, string $destination)` — turn the
  provider's output file objects into a **list** of token-storable refs. `$type`
  is the configured `output_type`: `managed_file` (default — creates a `File`
  entity, returns its ID), `path` (writes an *unmanaged* file, returns the URI) or
  `binary` (raw bytes). Always returns a list, even for a single file.
- `inputElement()` / `outputElement()` / `destinationElement()` — reusable form
  `select`/`textfield` render arrays. File-input actions add `input_type`;
  file-output actions add `output_type` + `destination` (defaults `public://`).

`Chat` uses the same helper for its optional **Files** field: that field takes a
lone token holding a list of files, a lone token holding one file, or a literal
value, and each resolved file is attached to the outgoing `ChatMessage` via
`setFile()`. Files are resolved as `GenericFile` and re-typed to `ImageFile` when
the MIME type is an image, because providers dispatch on the concrete class.

The nine operation-type actions and their result-token shapes:

| Plugin ID | Operation type | Input | Result token |
|-----------|----------------|-------|--------------|
| `ai_integration_eca_execute_text_to_image` | `text_to_image` | prompt | list of file refs |
| `ai_integration_eca_execute_image_to_image` | `image_to_image` | image file (+prompt/mask) | list of file refs |
| `ai_integration_eca_execute_image_to_video` | `image_to_video` | image file | list of file refs |
| `ai_integration_eca_execute_speech_to_speech` | `speech_to_speech` | audio file | list of file refs |
| `ai_integration_eca_execute_audio_to_audio` | `audio_to_audio` | audio file | list of file refs |
| `ai_integration_eca_execute_image_classification` | `image_classification` | image file (+labels) | assoc `{label: confidence}` |
| `ai_integration_eca_execute_object_detection` | `object_detection` | image file | list of `{label, confidence, box}` |
| `ai_integration_eca_execute_rerank` | `rerank` | query + documents (token array) | provider's ranked results list |
| `ai_integration_eca_execute_translate_text` | `translate_text` | text (+ source/target lang) | translated string |

**Testing caveat:** EchoAI only implements `text_to_image`, `image_to_image` and
`image_classification`, so only those three have end-to-end `execute()` tests. The
other six can't round-trip through EchoAI; their tests cover the form/config and
(for Object Detection and Rerank) the protected `normalizeOutput()` via reflection
against a hand-built Output object. File conversion itself is covered by
`tests/src/Kernel/Service/AiFileHelperTest.php`. Each new operation method called
on `ProviderProxy` needs a magic-method ignore in `phpstan.neon`.

## Submodule actions (different base classes)

The submodule actions do **not** extend `AiActionBase` — they wrap other systems:
- `modules/agents/src/Plugin/Action/TriggerAgent.php` — `eca_ai_trigger_agent`,
  extends `ConfigurableActionBase`, runs an AI Agent.
- `modules/automators/src/Plugin/Action/AiAutomatorRule.php` — `eca_ai_automator`,
  extends ECA `ActionBase` with several traits, runs an AI Automator against an
  entity.

The `agents` submodule is the larger feature: it lets an AI agent read and build
ECA models (services under `modules/agents/src/Services/`, AI agent plugin at
`modules/agents/src/Plugin/AiAgent/Eca.php`).

## Spoofing the AI provider in tests

Real provider calls are non-deterministic and cost money, so tests never hit a
real provider. The AI module ships a spoof provider, **EchoAI**, in
`tests/modules/ai_test` (`drupal/ai`). It has two modes:

1. **Echo mode (what our kernel tests use today).** With no matching recorded
   request, EchoAI echoes the request back (prefixed with `Hello world!`),
   including the model config. That's why `ChatTest` asserts on `'Hello world!'`
   plus its own input/config strings. Just add `ai_test` to the test's
   `$modules` (already done in `AiActionTestBase`) and use the model string
   `echoai__ai`.

2. **Record-and-replay mode (for deterministic, realistic responses).** EchoAI
   can match an incoming request against a predefined response and replay it.
   This is how you test against realistic chat/embedding/etc. output without a
   live API, and it works for both **kernel** and **functional JavaScript**
   tests.

### Recording a request

1. In `settings.php`: `$settings['extension_discovery_scan_tests'] = TRUE;` then
   `drush cr` so the test module is installable, and `drush en ai_test`.
2. At `/admin/config/ai/providers/ai-test`, tick **"Catch results"** (and
   optionally **"Catch processing time"** to record latency for UX work).
3. Make a normal request against a real provider (e.g. via the AI API Explorer or
   AI Assistant using OpenAI/Anthropic).
4. Untick "Catch results". The recorded request appears at
   `/admin/content/ai-mock-provider-result`.

### Replaying it / shipping it as a test fixture

- To replay interactively: edit the recorded result, tick **"Mock enabled"**, name
  it, then re-run the same request against the **EchoAI** provider (`gpt-test`
  model). You get the recorded response, instantly and free.
- To use it in *this* module's automated tests: from the result list dropbutton
  choose **Export**, and drop the YAML file under
  `tests/resources/ai_test/requests/{operation_type}/` — `{operation_type}` is the
  AI operation name (`chat`, `embeddings`, `moderation`, `text_to_speech`,
  `speech_to_text`). EchoAI auto-discovers these files from every enabled module
  (`EchoProvider::$requestTestSubDirectory`) and matches them by request payload.
  No registration needed — same request in a test → same recorded response.

A fixture is a `request:`/`response:` YAML document. The `request:` block is
matched against the outgoing payload (messages, schema, etc.); the `response:`
block holds the `normalized` output EchoAI returns. See
`ai/tests/modules/ai_test/tests/resources/ai_test/requests/chat/HostnameFilterToolBugTrigger.yml`
for a complete chat example.

### Functional JavaScript tests

The same fixtures drive `FunctionalJavascript` (or `Functional`) tests: enable
`ai_test`, configure the EchoAI provider/model in your test setup, place fixtures
under `tests/resources/ai_test/requests/{operation_type}/`, and the JS-driven flow
gets the recorded responses. (This module has no functional JS tests yet — kernel
coverage only. Add fixtures here when you write one.)

Full docs: `ai/docs/developers/virtual_provider.md` ("Mock a Provider").

## ECA conventions & best practices

These actions are ECA plugins, so the ECA module's conventions apply. Key points
(verified against `web/modules/contrib/eca`):

### Annotations vs. attributes

The top-level module (`src/Plugin/Action/`) uses **legacy `@Action` doc-block
annotations**. ECA core has moved to **PHP 8 attributes** — `#[Action(...)]`
plus a second `#[EcaAction(description: ..., version_introduced: '...')]` (see
`eca/modules/log/src/Plugin/Action/LogMessage.php`). The `automators` submodule
already adds ECA metadata (`eca_version_introduced = "2.0.0"`) inside its
annotation. **Match the file you're editing**: keep annotations consistent within
`src/Plugin/Action/`, but be aware the modern ECA convention is attributes, and
prefer recording a `version_introduced` for new actions there.

**`modules/ai_eca_interceptor/` is the exception: it is 100% PHP-attribute-based**
(`#[Action(...)]` / `#[EcaAction(...)]` / `#[EcaCondition(...)]` /
`#[EcaEvent(...)]`), matching the modern ECA convention, not the legacy
annotation style described above. Every plugin definition in that submodule
also declares `version_introduced: '2.0.0'`. When adding a new plugin to
`ai_eca_interceptor`, follow its own attribute-based files as the reference
(e.g. `modules/ai_eca_interceptor/src/Plugin/Action/BlockRequestAction.php`),
not the `@Action` annotation pattern used elsewhere in this module.

### Config schema is auto-generated (don't hand-write it)

ECA actions normally need an `action.configuration.{plugin_id}` entry in
`config/schema/`. **This module ships none and that's intentional** — ECA's
`Drupal\eca\Hook\ConfigSchemaHooks` (`hook_config_schema_info_alter`) augments
action config schemas dynamically, and the direct-instantiation kernel tests here
never save a config entity, so strict schema validation isn't triggered. So: you
do **not** need to add a schema file for a new action to match the existing
pattern. If you ever add a *Functional* test that saves an ECA model with your
action, watch for strict-config-schema failures and add a schema then.

### Token services are the data bus

ECA actions communicate through `$this->tokenService` (`eca.token_services`,
provided by the base `ConfigurableActionBase`). Read inputs with
`getTokenData()`, write outputs with `addTokenData()`, and run user-supplied
strings through `replace()` so `[token]` and Drupal tokens resolve. In this
module, a configured input field is read through `resolveTokenValue()` /
`resolveTokenString()`, which combine the two in the right order — see "Reading
a configured field" above.

In forms, flag a field that names an output token with
`'#eca_token_reference' => TRUE`, and a field that holds a value with ECA's
`'#eca_token_replacement' => TRUE` (or `'#eca_token_select_option' => TRUE` for
selects). The reference flag makes ECA refuse a value written as `[name]`, so
it belongs on output-name fields only.

### Testing pattern

ECA's own kernel tests (e.g.
`eca/modules/render/tests/src/Kernel/...`) follow the same shape used here: pull
`plugin.manager.action` and `eca.token_services` in `setUp()`, build the action
with `createInstance($id, $config)`, seed inputs with
`$tokenService->addTokenData(...)`, `execute()`, and assert on the resulting
token. When an action depends on the triggering event, ECA tests call
`$action->setEvent($event)` before `execute()` (inside an event listener) — not
needed for these AI actions, which don't read the event.

## Conventions & tooling

- **Coding standards:** Drupal `phpcs` standard. CI (`.gitlab-ci.yml`) runs
  `phpcs`, `phpstan` (level 6, see `phpstan.neon`), `cspell`, and `phpunit`
  across previous-major / next-minor / next-major — **all gated, none allowed to
  fail.** Add new domain words to `.cspell-project-words.txt`.
- `ProviderProxy`'s AI operation methods are magic `__call`s, so phpstan can't see
  them — they're explicitly ignored in `phpstan.neon`. If you call a new provider
  operation method, add a matching ignore entry.
- Documentation is MkDocs (`docs/`, `mkdocs.yml`); `docs/usage.md` is still a
  `@todo` stub — worth filling in when you add/expose an operation type.
- Keep failure modes quiet: actions return early (no exception to the user) when a
  provider can't be loaded — preserve that convention.

## Running tests

Kernel tests require a Drupal test environment with `ai`, `eca`, `ai_test` and
`modeler_api` available (the test base enables `modeler_api` because the current
`eca` version's `eca.processor` service depends on it). This project runs tests
inside **DDEV** — the host PHP CLI has no database driver. From the Drupal root:

```
ddev exec "php vendor/bin/phpunit -c web/core web/modules/custom/ai_integration_eca/tests"
ddev exec "php vendor/bin/phpstan analyse -c web/modules/custom/ai_integration_eca/phpstan.neon web/modules/custom/ai_integration_eca/src"
ddev exec "php vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/custom/ai_integration_eca/src web/modules/custom/ai_integration_eca/tests"
```

A passing PHPUnit run prints `OK` — note `OK, but there were issues!` also means
pass (the "issues" are upstream deprecation notices).
