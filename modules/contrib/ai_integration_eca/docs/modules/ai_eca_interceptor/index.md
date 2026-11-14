# AI Integration - ECA: Interceptor

Every AI call that goes through the AI module's provider proxy dispatches
events: before the provider is called, after it has answered, when a streamed
answer has finished, and when the call throws. This submodule turns those four
moments into ECA events, so a site builder can inspect and change AI traffic
from a model instead of from code.

The interceptor governs calls that *other* code makes — an AI Assistant, an
automator, a custom module. If you want ECA itself to *make* an AI call, use the
actions of the parent module instead (see [Usage](../../usage.md)). The two are
complementary and can be enabled together.

## Requirements

- `ai:ai` **1.4 or newer**. The failover action relies on
  `AiExceptionEvent::setForcedOutputObject()`, which was added in AI 1.4.0.
- `eca:eca`
- Drupal `^10.3 || ^11 || ^12`

The submodule does not depend on the parent `ai_integration_eca` module, so it
can be enabled on its own:

```bash
drush en ai_eca_interceptor
```

There is nothing to configure. Enabling the module makes its plugins available
in the ECA modeler at `/admin/config/workflow/eca`.

## Events

Four event derivatives, all of them derived from the single event plugin
`ai_eca_interceptor`:

| Plugin id | Label | Fires on |
|-----------|-------|----------|
| `ai_eca_interceptor:ai_request` | AI request: before provider call | `PreGenerateResponseEvent` — before the provider is called |
| `ai_eca_interceptor:ai_response` | AI response: after provider call | `PostGenerateResponseEvent` — after the provider answered |
| `ai_eca_interceptor:ai_stream_finished` | AI response: stream finished | `PostStreamingResponseEvent` — after a streamed answer completed |
| `ai_eca_interceptor:ai_response_failed` | AI response: failed | `AiExceptionEvent` — the provider call threw |

`ai_response_failed` fires for **every** consumer of the AI provider proxy, not
just for chat, and it fires for any exception class.

### Streaming and event order

When the answer is streamed, neither response event fires where its name
suggests, and the ordering decides which of the two an action can still
influence.

On a streamed chat request the provider returns an *iterator*, not text. The
provider proxy dispatches `PostGenerateResponseEvent` — and with it
`ai_response` — before it has looked at what kind of output it is holding: the
check for a streamed iterator, and the attaching of stream metadata, happen only
*after* that dispatch. So `ai_response` runs while the answer is still an
iterator nobody has read from yet, **before the first character is visible to
the client**. "After provider call" is literally true; "the answer is ready"
is not.

`ai_stream_finished` is the derivative that sees the complete answer. It is
dispatched from inside the iterator itself, after the last chunk has been
yielded to the caller and the full text has been reassembled — by the time it
fires, the client already has every character. That is also the only place the
event is dispatched from, which has two further consequences: it never fires for
a non-streamed call, and it never fires for a stream the consumer abandons half
way, because the dispatching code sits past the end of the loop.

For the response actions this means:

- On `ai_response` they are **effective**. Nothing has been sent yet, so
  replacing or blocking the output still changes what the caller receives.
- On `ai_stream_finished` they are **observational**. The chunks are already
  gone; rewriting the text cannot un-send what the client has received.

So `ai_response` is where you intervene in a streamed answer, and
`ai_stream_finished` is where you log, measure or record one. On `ai_response`,
`[event:output_is_streamed]` tells the two cases apart.

### Filtering an event

All four derivatives expose the same three configuration fields:

| Field | Description |
|-------|-------------|
| `operation_type` | Limit to one AI operation type, e.g. `chat`, `embeddings`, `moderation`. |
| `provider_id` | Limit to one provider id. |
| `model_id` | Limit to one model id. |

Each field is matched **exactly**, and **an empty field means `*`** — any value.
The three values are combined into a single wildcard of the form
`operation_type:provider_id:model_id`, which is what ECA subscribes with. So:

- everything empty → `*:*:*` — every AI call
- `chat` / empty / empty → `chat:*:*` — every chat call, whatever provider
- `chat` / `openai` / `gpt-4o-mini` → `chat:openai:gpt-4o-mini` — that one model

Two things to know about these fields:

- They are plain text fields with **no token replacement**. The wildcard is
  computed when the model is saved, so a `[token]` in a filter field would be
  matched literally and never hit.
- The filter is a coarse pre-selection. For anything finer — a tag, a prompt
  length, an exception type — use a condition or a `[event:*]` token inside the
  model.

## Conditions

There are **two** conditions.

| Plugin id | Label | Available on | Configuration |
|-----------|-------|--------------|---------------|
| `ai_eca_interceptor_has_tag` | AI event: has tag | all four derivatives | `tag` (required, supports token replacement) |
| `ai_eca_interceptor_response_failed` | AI response: failed (any exception) | `ai_response_failed` only | none beyond ECA's own `negate` |

`AI event: has tag` matches a tag whether it sits in the tag array as a value or
as a key — see [Tags](#tags) below for why both shapes occur.

`AI response: failed (any exception)` is simply true when the current event is a
failure event. It is useful when a model listens to more than one derivative and
has to branch. To distinguish exception *types*, do not look for more condition
plugins — use the `[event:is_*]` tokens, for example `[event:is_rate_limit]`
equals `1`, with ECA's generic comparison condition.

Both conditions support ECA's standard negation.

## Actions

19 actions. Each one declares which event it can run on, and denies access with
an explicit reason when it is placed on the wrong one — ECA logs that reason and
the process debugger shows the action as access-denied rather than executed. So
a misplaced action is visible, not silent.

The label prefix tells you where a plugin belongs before you place it:

| Prefix | Runs on |
|--------|---------|
| `AI request:` | the request event only (`AI chat request:` narrows that to chat operations) |
| `AI response:` | the response events only, and `AI response failed:` on the failure event |
| `AI event:` | every derivative — request, both responses and the failure event |
| `AI:` | not bound to an AI event at all |

### On `ai_request` (before the provider call)

| Plugin id | Label | Configuration |
|-----------|-------|---------------|
| `ai_eca_interceptor_reroute` | AI request: reroute provider/model | `provider_id` (required), `model_id` (required) — both support token replacement |
| `ai_eca_interceptor_set_config` | AI request: set config value | `key` (required), `value`, `cast` (`string` \| `int` \| `float` \| `bool`, default `string`) |
| `ai_eca_interceptor_unset_config` | AI request: unset config value | `key` (required) |
| `ai_eca_interceptor_attach_guardrail_set` | AI request: attach guardrail set | `guardrail_set_id` (required) — machine name of an `ai_guardrail_set` config entity |
| `ai_eca_interceptor_block_request` | AI request: block | `mode` (`fallback` — default — or `exception`), `message` |
| `ai_eca_interceptor_force_chat_output` | AI request: force chat output | `text` (required), `role` (default `assistant`) — chat operations only |

Notes:

- **Reroute** and **set config** both act on the request that is about to go
  out. `set config` writes into the provider configuration array
  (`temperature`, `max_tokens`, …); the `cast` option exists because a value
  arriving through token replacement is always a string.
- **Attach guardrail set** *adds* to the request rather than replacing, so
  guardrail sets that were already attached survive. It denies access with a
  distinct reason for an empty id and for an id that names no guardrail set.
- **Block request** in `fallback` mode returns a fallback answer without calling
  the provider. That fallback output can currently only be built for `chat`, so
  on any other operation type this mode does nothing at all and the request goes
  through. In `exception` mode it throws a `RequestBlockedException` whatever the
  operation type, which is the mode to use when blocking has to be reliable.
- **Force chat output** is the deliberate version of the same idea: return a
  canned answer and skip the provider entirely.

### On `ai_request`, chat requests only

These four additionally require the request input to be a `ChatInput`, and say
so in their denial reason when it is not.

| Plugin id | Label | Configuration |
|-----------|-------|---------------|
| `ai_eca_interceptor_chat_set_system_prompt` | AI chat request: set system prompt | `prompt` |
| `ai_eca_interceptor_chat_append_message` | AI chat request: append message | `role` (default `user`), `text` |
| `ai_eca_interceptor_chat_replace_message_text` | AI chat request: replace message text | `index` (required, zero-based), `text` |
| `ai_eca_interceptor_chat_set_streamed` | AI chat request: set streamed | `streamed` (checkbox) |

`AI chat request: replace message text` checks the index while deciding access,
so an out-of-bounds index is reported and logged instead of quietly doing
nothing. The denial reason names the index and how many messages the request
actually carries.

### On `ai_response` and `ai_stream_finished`

| Plugin id | Label | Configuration |
|-----------|-------|---------------|
| `ai_eca_interceptor_set_response_text` | AI response: set text | `text` (required) |
| `ai_eca_interceptor_replace_chat_output` | AI response: replace chat output | `text` (required), `role` (default `assistant`) — chat operations only |
| `ai_eca_interceptor_block_response` | AI response: block | `mode` (`exception` — default — or `fallback`), `message` |

Notes:

- **Set text** replaces the textual part of the answer and keeps everything else.
  It understands chat, summarization, translation and speech-to-text output; for
  any other output type it does nothing.
- **Replace chat output** builds a completely fresh chat answer from `text` and
  `role`, discarding the provider's raw output and metadata.
- **Block response** defaults to throwing a `ResponseBlockedException`. Its
  `fallback` mode replaces the answer, and — as its own option label says — that
  replacement can only be built for chat, so on any other operation type this
  mode lets the original answer through unchanged.
- On a **streamed** chat answer, both **Set text** and **Replace chat output**
  end the streaming. The normalized output is an iterator, not a `ChatMessage`,
  so instead of editing the text in place they build a fresh `ChatOutput` around
  a single static `ChatMessage`: the caller receives one complete message where
  it expected a sequence of chunks. Nothing warns about it. That is the price of
  acting at the only point where acting still has an effect — see
  [Streaming and event order](#streaming-and-event-order).

### On `ai_response_failed`

| Plugin id | Label | Configuration |
|-----------|-------|---------------|
| `ai_eca_interceptor_failover_to_provider` | AI response failed: fail over to provider/model | `provider_id` (required), `model_id` (required) — both support token replacement |
| `ai_eca_interceptor_failover_chat_output` | AI response failed: return chat output | `text` (required), `role` (default `assistant`) — chat operations only |
| `ai_eca_interceptor_rewrite_exception_message` | AI response failed: rewrite message | `message` (required) |

Notes:

- **Fail over to provider/model** retries the same operation, with the same
  input and configuration, against the backup target. If the retry produces a
  usable output it becomes the answer the caller receives, and the recovery is
  recorded in event metadata under `ai_eca_interceptor.failover.executed`. If it
  does not, the attempt is logged and recorded under
  `ai_eca_interceptor.failover.failed` (with a `message`), and the original
  exception propagates unchanged. The action never throws an exception of its
  own, precisely so the provider's original cause is not lost.
- Both provider and model are required, on this action and on reroute: model ids
  are not portable between providers, so there is no sensible default.
- **Rewrite message** replaces only the message. The exception *class* is
  preserved by AI core, so existing `catch` blocks keep working. Use it to turn
  a raw provider error into something a user can read.

### On any AI event

| Plugin id | Label | Configuration |
|-----------|-------|---------------|
| `ai_eca_interceptor_set_tag` | AI event: set tag | `tag` (required) |
| `ai_eca_interceptor_set_metadata` | AI event: set metadata value | `key` (required), `value` |

Both run on the request event, both response events and the failure event —
which is what the `AI event:` prefix marks. Metadata is the module's own side
channel: the reroute and failover paths write their results there, and a later
condition can read it back through `[event:metadata]`.

### Not bound to an event

| Plugin id | Label | Configuration |
|-----------|-------|---------------|
| `ai_eca_interceptor_count_tokens` | AI: count tokens | `text` (required), `model`, `token_name` (required) |

`AI: count tokens` runs the text through the AI module's tokenizer and writes the
integer count into the ECA token named by `token_name`, so later conditions can
branch on it. It works in a plain ECA model as well as inside an AI event flow.
Leave `model` empty inside an event flow and it uses the event's model id; with
neither, it falls back to `gpt-3.5-turbo` to pick an encoding. It is the one
action here that denies access for a configuration reason only — no result token
name means the count would have nowhere to go.

## Tokens

The event plugin exposes an `[event:*]` token set. Which tokens carry a value
depends on the derivative.

### Always available

| Token | Description |
|-------|-------------|
| `[event:machine_name]` | Machine name of the triggered ECA event, e.g. `ai.pre_generate_response`. |
| `[event:request_thread_id]` | Unique id of the AI request thread. |
| `[event:request_parent_id]` | Parent request id when this request was started from another AI request. |
| `[event:provider_id]` | The provider that will execute, or did execute, the call. |
| `[event:model_id]` | The model id. |
| `[event:operation_type]` | `chat`, `embeddings`, `moderation`, `speech_to_text`, `text_to_speech`, … |
| `[event:tags]` | Tags attached to the request (array). |
| `[event:tag_count]` | Number of tags attached. |
| `[event:configuration]` | Provider configuration array (`temperature`, `max_tokens`, …). |
| `[event:debug_data]` | Debug data attached to the request. |
| `[event:metadata]` | All metadata on the event, including the reroute and failover keys. |
| `[event:is_chat]` | `1` when the operation type is `chat`, `0` otherwise. |
| `[event:input]` | The input object flattened to an array. |
| `[event:input_class]` | Class name of the input object. |
| `[event:input_text]` | Best-effort text of the input: chat prompts joined with newlines, or the summarization / translation text. |

These are available on all four derivatives, including the failure event —
`AiExceptionEvent` carries the original request, so a failed call still knows its
provider, model, operation type and input.

### Chat only

Present on every request-carrying event, but only meaningful when the input is a
chat input. For anything else they hold empty or zero defaults, so a model can
read them unconditionally.

| Token | Description |
|-------|-------------|
| `[event:chat_tools]` | The declared tools rendered to an array. |
| `[event:chat_tool_names]` | Names of the declared tool functions (array). |
| `[event:chat_tool_count]` | Number of declared tool functions. |
| `[event:chat_has_tools]` | `1` when the request declares any tools. |
| `[event:chat_has_schema]` | `1` when the request declares a structured JSON schema. |
| `[event:chat_streamed]` | `1` when the request asked for streamed output. |

### Response only

On `ai_response` and `ai_stream_finished`.

| Token | Description |
|-------|-------------|
| `[event:output]` | The normalized output as an array. |
| `[event:output_class]` | Class name of the output object. |
| `[event:output_normalized]` | The normalized output, in array form where possible. |
| `[event:output_raw]` | The provider's raw output payload. |
| `[event:output_metadata]` | Metadata returned by the provider. |
| `[event:output_text]` | Best-effort text of the output. |
| `[event:output_is_streamed]` | Chat. `1` when the output is a streamed iterator. |
| `[event:token_usage:input]` | Chat. Input tokens consumed. |
| `[event:token_usage:output]` | Chat. Output tokens produced. |
| `[event:token_usage:total]` | Chat. Total tokens used. |
| `[event:token_usage:reasoning]` | Chat. Reasoning tokens used, if reported. |
| `[event:token_usage:cached]` | Chat. Cached tokens reused, if reported. |
| `[event:rate_limits:max_requests]` | Chat. Rate limit request window. |
| `[event:rate_limits:max_tokens]` | Chat. Rate limit token window. |
| `[event:rate_limits:remaining_requests]` | Chat. Requests left in the window. |
| `[event:rate_limits:remaining_tokens]` | Chat. Tokens left in the window. |
| `[event:rate_limits:reset_requests]` | Chat. Seconds until the request window resets. |
| `[event:rate_limits:reset_tokens]` | Chat. Seconds until the token window resets. |
| `[event:moderation_flagged]` | Moderation. `1` when the response is flagged. |
| `[event:moderation_message]` | Moderation. The moderation response message. |

### Failed event only

On `ai_response_failed`.

| Token | Description |
|-------|-------------|
| `[event:exception_class]` | Class name of the exception that was thrown. |
| `[event:exception_message]` | Exception message. This is the possibly rewritten message. |
| `[event:exception_code]` | Exception code. |
| `[event:exception_file]` | File where the exception originated. |
| `[event:exception_line]` | Line where the exception originated. |
| `[event:is_rate_limit]` | `1` for `AiRateLimitException`. |
| `[event:is_quota]` | `1` for `AiQuotaException`. |
| `[event:is_unsafe_prompt]` | `1` for `AiUnsafePromptException`. |
| `[event:is_missing_feature]` | `1` for `AiMissingFeatureException`. |
| `[event:is_bad_request]` | `1` for `AiBadRequestException`. |
| `[event:is_response_error]` | `1` for `AiResponseErrorException`. |
| `[event:is_request_error]` | `1` for `AiRequestErrorException`. |

The `is_*` tokens replace what used to be one condition plugin per exception
type: branch on them with ECA's generic comparison condition. Note that
`is_bad_request` only fires for exceptions AI core actually wrapped into
`AiBadRequestException`; a provider throwing a plain `RuntimeException` sets
none of the `is_*` flags, and only `exception_class` identifies it.

## Worked example: reroute tagged traffic to a cheaper model

Goal: any chat request tagged `bulk` should be answered by a cheap model, while
everything else keeps using whatever the calling code chose.

1. **Event** — `AI request: before provider call`. Set the operation type filter
   to `chat` and leave provider and model empty, giving the wildcard
   `chat:*:*`.
2. **Condition** on the successor — `AI event: has tag`, with **Tag** set to
   `bulk`.
3. **Action** — `AI request: reroute provider/model`, using the ids from your own
   provider configuration:
    - **Provider id**: `openai`
    - **Model id**: `gpt-4o-mini`

That is the whole model. What happens at runtime is worth understanding, because
the action alone does not perform the switch:

- The action only records the intent, writing
  `ai_eca_interceptor.reroute.provider_id` and `…reroute.model_id` into the
  event metadata.
- A subscriber runs later on the same event, at priority `-100`, and performs the
  actual call against the target provider. It sets the result as the forced
  output, so the original provider is never called.
- On success it records `ai_eca_interceptor.reroute.executed` in the metadata,
  with the provider and model it used. A later model can read that through
  `[event:metadata]`.
- If the target provider throws, the failure is logged and **no** forced output
  is set, so the original provider call proceeds as if nothing had happened.

Because the reroute happens inside the same request, the target call dispatches
its own `AI request: before provider call` event. A recursion guard on the
subscriber makes the reroute action deny access while a reroute is in flight, so
the rerouted call cannot reroute again.

Tagging can be part of the same model: put `AI event: set tag` on the request
event to add `bulk` yourself, based on whatever ECA can see.

## Worked example: fail over to a backup provider

Goal: when the primary provider throws, retry once against a backup, and if that
also fails, hand the user a polite message rather than a stack trace.

1. **Event** — `AI response: failed`, all three filters empty (`*:*:*`).
2. **Action** — `AI response failed: fail over to provider/model`, using the ids
   of your own backup provider configuration:
    - **Backup provider id**: `anthropic`
    - **Backup model id**: `claude-haiku-4-5`
3. **Action** — `AI response failed: rewrite message`, with **Exception
   message** set to something like
   `The assistant is temporarily unavailable. Please try again shortly.`

At runtime:

- The failover action retries the same input and configuration against the
  backup. If that returns a usable output, it becomes the answer the caller
  receives and `ai_eca_interceptor.failover.executed` is written to the event
  metadata.
- If it does not, the failure is logged, written to
  `ai_eca_interceptor.failover.failed` together with a `message`, and the
  original exception continues on its way out.
- Step 3 is safe to leave in place unconditionally, because rewriting the
  message touches only the exception and never the output. Whatever the failover
  did, the answer it produced is not affected.

For a chat call you can go one step further and return an answer instead of an
exception: add `AI response failed: return chat output` **after** the failover.
It stands aside when the backup answered and supplies its canned text only when
nothing else did — see [Ordering these actions
matters](#ordering-these-actions-matters) below.

### Ordering these actions matters

Both output-producing recovery actions — `AI response failed: fail over to
provider/model` and `AI response failed: return chat output` — check first and
do nothing when an output has already been forced. Neither overwrites an answer
another action produced, so **the first one to produce an output wins** and the
order you put them in is what decides the recovery strategy.

That makes the useful combination straightforward: put the failover **first**
and the canned output **after** it. The failover tries the backup provider; if
it answers, that answer is what the caller gets and the canned action steps
aside. If the backup fails too, nothing has been forced, and the canned text
fills in as the last resort instead of the exception propagating.

The reverse order is a trap: `return chat output` forces its output
immediately, so the failover that follows it sees a forced output and returns
without ever trying the backup. Put the last resort last.

`return chat output` on its own is still the right tool when you have no backup
provider and simply want a canned answer for a failed chat call. And when you
want the backup answer *and* a friendlier exception message on the paths where
nothing recovered, use `rewrite message` for the message, as above.

### Failover recursion protection

The backup call is made synchronously, so if the backup fails it dispatches its
own failure event, which would trigger the same failover again. The guard that
prevents this tracks **specific backup targets**, keyed `provider_id:model_id`
and reference counted, rather than a single global "a failover is running" flag:

- A failure on a target that is *currently being attempted* is suppressed. That
  is the backup failing on its own target — the genuine loop.
- Any other failure that happens while the backup runs is free to fail over on
  its own. This matters in practice: an LLM-based guardrail evaluated on the
  backup request may classify the prompt with its own chat call, and that call
  must be recoverable — even when it happens to reuse the provider and model
  whose failure started the whole thing.

## Tags

Tags reach the event from two directions, and they end up in two different
shapes in the same array:

- The AI module seeds the event with a plain **list** of tag strings.
- `AI event: set tag` stores the tag name as an array **key**.

So a request that already carried tags and then had one added by ECA ends up
with something like `[0 => 'alpha', 1 => 'beta', 'bulk' => TRUE]`. `AI event: has
tag` accepts either shape, so a model can match a tag without caring where it
came from.

When the reroute or failover path calls another provider, it first normalizes
that array into a clean list before passing it on:

- A string key is a tag name; a string value is a tag name.
- Where both could apply, the string **value** wins.
- Empty entries are dropped, duplicates are dropped keeping the first
  occurrence, and the original order is preserved — list tags first, then tags
  added later.
- Normalization produces the list handed to the target provider; the event's own
  tags are left untouched, so later listeners still see what they expect.

The practical consequence: tags survive a reroute or a failover, which keeps
provider-side routing, logging and cost attribution correct on the retry.

## Guardrails and the subscriber order

`AI request: attach guardrail set` needs some care, because AI core's own
guardrails subscriber runs at priority `0` — the same priority as ECA — and wins
the tie by module registration order. By the time ECA attaches a guardrail set,
core has already looked and found none.

The submodule therefore ships a second subscriber that applies the
*pre*-generate guardrails ECA attached, after ECA has finished. Post-generate
guardrails need no help: they fire on the response event, by which time the
guardrail set is already on the input.

The resulting order on `AI request: before provider call` is fixed, and pinned by
a test:

| Priority | Listener |
|----------|----------|
| `0` | AI core's guardrails subscriber |
| `0` | ECA — runs the interceptor's actions |
| `-10` | Guardrail applier — applies guardrail sets attached by an ECA action |
| `-100` | Reroute subscriber — executes reroutes after all actions and guardrails |

All four event derivatives declare `subscriber_priority: 0`, and that value is
load-bearing rather than incidental. It is what keeps ECA ahead of the two
subscribers above.

## Troubleshooting

- **The model never fires.** Check the event filter first. All three fields match
  exactly and do not resolve tokens, so a typo in a provider or model id, or a
  token placed in a filter field, silently narrows the wildcard to something no
  call matches. Empty is `*`.
- **An action is skipped.** Look at the ECA log. Every action here denies access
  with a specific reason — wrong event, wrong operation type, input that is not a
  chat input, a key that is empty after token replacement — and ECA records the
  denial along with that reason. The process debugger shows the action as
  access-denied rather than executed.
- **A configured value silently disappears.** Actions that gate on a value —
  provider ids, model ids, configuration keys, metadata keys, tags, guardrail set
  ids — resolve it with a token replacement that *removes* tokens it cannot
  resolve, rather than leaving the literal `[event:something]` behind. So a
  mistyped token produces an empty value and a logged denial, instead of a
  configuration key or tag literally named `[event:something]`.
- **A reroute did nothing.** The reroute is executed by a subscriber after all
  ECA actions have run. If its call to the target provider throws, that is logged
  and the original provider is used instead, so the request still succeeds —
  check the log for `AI ECA reroute failed`.
