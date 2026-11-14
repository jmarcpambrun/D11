# AI ECA Interceptor

Intercepts AI requests and responses through ECA events, conditions and
actions.

Every call that goes through the AI module's provider proxy dispatches events
before the provider is called, after it has answered, when a stream has
finished, and when the call throws. This submodule exposes those four moments
to ECA, so a site builder can inspect and change AI traffic from a model
instead of from code.

## What you can do with it

- Rewrite a prompt, a system prompt or a provider setting before the request
  leaves the site.
- Reroute a request to a different provider or model, for example to a cheaper
  model for low-value traffic.
- Attach a guardrail set to a request that did not ask for one.
- Block a request or a response, either by returning a fallback answer or by
  throwing.
- Rewrite or replace a response before the caller sees it.
- Recover a failed call: return a canned answer, retry against a backup
  provider, or rewrite the exception message.

## When to reach for it

Reach for the interceptor when you want to govern AI traffic that *other* code
already produces — an AI Assistant, an automator, a custom module. It sits in
the middle of somebody else's call.

If instead you want ECA itself to *make* an AI call, use the actions of the
parent `ai_integration_eca` module (`Chat`, `Moderation`, `Embedding`, …). The
two are complementary and can be enabled together.

## Dependencies

Declared in `ai_eca_interceptor.info.yml`:

- `ai:ai` (>= 1.4) — the failover action relies on
  `AiExceptionEvent::setForcedOutputObject()`, which was added in AI 1.4.0.
- `eca:eca`

Drupal `^10.3 || ^11 || ^12`. The submodule does not depend on the parent
`ai_integration_eca` module, so it can be enabled on its own.

## Enabling it

```bash
drush en ai_eca_interceptor
```

Or enable **AI ECA Interceptor** at `/admin/modules`. There is nothing to
configure: enabling the module makes its event, condition and action plugins
available in the ECA modeler at `/admin/config/workflow/eca`.

## Documentation

The full reference — the four event derivatives, their filters, all 19 actions
and 2 conditions with their real configuration fields, the `[event:*]` tokens,
and worked examples — lives in the project documentation:

[https://project.pages.drupalcode.org/ai_integration_eca/modules/ai_eca_interceptor/](https://project.pages.drupalcode.org/ai_integration_eca/modules/ai_eca_interceptor/)

The source of that page is `docs/modules/ai_eca_interceptor/index.md` in this
repository.
