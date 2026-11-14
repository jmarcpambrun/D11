# Evaluation of the ECA Agent

The kernel tests in `tests/` cover the tools: given this input, does the tool
produce that output. They say nothing about the thing that actually decides
whether this module is useful, which is whether the agent picks the right
tools and builds a model that works.

That is what this folder is for. It runs the agent against a real LLM and
checks what it did, not what it said it did.

## Why this is not a PHPUnit test

- It needs a configured chat provider and it costs money on every run.
- The outcome is not deterministic. The same prompt can produce a different,
  equally correct model twice in a row, and it can fail once in ten runs.

So this never runs in CI. Run it by hand when you change the system prompt,
the tools, or the model you point the agent at, and compare the score with
the last recorded run in `results/`.

## Running it

```bash
# Every case once.
drush php:script web/modules/.../modules/agents/eval/run.php

# One group, or one case ten times to measure how flaky it is.
drush php:script .../eval/run.php -- --group=boundaries
drush php:script .../eval/run.php -- --case=create_cron_log --repeat=10

# Keep what was created so you can look at it, and record the report.
drush php:script .../eval/run.php -- --keep
drush php:script .../eval/run.php -- --write=results/2026-07-29-gpt-5.2.md
```

| Option | Meaning |
| --- | --- |
| `--group=NAME` | Only run the cases of one group. |
| `--case=ID` | Only run one case. |
| `--repeat=N` | Run every case N times. |
| `--keep` | Do not delete the models that were created. |
| `--write=FILE` | Write the report to a file, relative to this folder. |
| `--verbose` | Print the answer of the agent for every case. |

The harness runs as user 1, seeds a fixture model for the cases that need one,
and deletes everything it created afterwards unless `--keep` is given.

## What the cases assert

The assertions are about behavior, never about wording. A case passes when
all of this holds:

1. **Write or do not write.** A creation case has to end in exactly one new
   model. Every other case has to end in none. This is what catches an agent
   that quietly edits something when it was asked a question.
2. **The model holds together.** Every successor resolves to an action or a
   gateway that exists, every referenced condition exists, there is at least
   one event and one action, the model is disabled, and it can be enabled
   without an exception. A model that imports but can never run is a failure.
3. **The seeded model is untouched.** Create-only means create-only.
4. **The declared tools were used**, where the case names them.
5. **The model has the shape that was asked for**, where the case says so:
   which plugins it has to use, how many conditions it needs, and how many of
   its successors are actually guarded by a condition. That last one is what
   separates a model that really branches from one that only claims to in its
   summary. A plugin requirement may list equally correct alternatives
   separated by a pipe, because there is usually more than one right answer.
6. **The answer matches the given patterns**, kept loose on purpose.

A case can declare `seed_roles`, and the harness creates those user roles
before it runs. Nothing tells the agent that they exist: it has to find them
in the allowed values of the role condition, through
`eca_get_component_details`. The roles are removed again afterwards.

## The cases

| Group | What it probes |
| --- | --- |
| `creation` | Straight line models: one event, one or two actions. |
| `branching` | Models that need a gateway and a condition, touch a second entity, or route on user role. |
| `boundaries` | Edit, delete and id collision. All three have to be refused. |
| `questions` | Questions may never end in a write. |

`cases.yml` documents the format at the top. Adding a case is a matter of
appending to that file.

## Interpreting a run

A single failure is not a regression. Run the case ten times with `--repeat`
before you conclude anything, because the failure rate is what matters, not
one bad roll.

What is worth acting on:

- A **boundaries** case failing at all. Those are safety properties, and they
  should never fail.
- A creation case that fails because the model does not hold together, rather
  than because the agent declined. A refusal because the site genuinely has no
  component for the job is correct behavior.
- A drop across a whole group after a prompt change.

## Recorded runs

See `results/`. Record a new one whenever you change the system prompt or move
to another model, so there is something to compare against.

Do not read a clean sheet as proof that nothing is flaky. Three runs per case
is enough to catch a case that fails half the time and nowhere near enough to
catch one that fails one time in twenty. While these cases were being written,
`create_presave_trim` and `create_cross_entity` were both seen to fail once
and then pass on the next few attempts, and two of the boundary cases refused
correctly but worded it in a way the pattern did not match. If a case matters
to you, run it on its own with a much higher `--repeat`.

Because the cases depend on which components a site has, a run is only
comparable against another run on a comparable site. The recorded runs were
made with `eca_base`, `eca_content`, `eca_user`, `eca_log`, `eca_misc`,
`eca_form`, `eca_config`, `eca_file`, `eca_views` and `eca_workflow` enabled,
which gives the agent 78 events, 32 conditions and 171 actions to pick from.
On a site with only `eca_base` most of the creation cases will correctly fail,
because the components simply are not there.
