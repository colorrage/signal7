---
name: signal-marketer
description: >
  Internal Signal7 bridge that validates a Marketer7 execution brief and
  records optional origin metadata without taking ownership of an experiment.
user-invocable: false
---

# signal-marketer

Consume a user-supplied `signal7-execution-brief/v1` by path and prepare an ordinary Signal7 task for the usual brief, create, review, and publish workflow.

## Read First

- `skills/signal/reference/data-model.md`
- `skills/signal/reference/marketer7-integration.md`
- `skills/signal/reference/cross-system-boundaries.md`
- task `task.md`

## Input gate

Read the brief only from the supplied path. It must have YAML frontmatter with:

- `contract: signal7-execution-brief/v1`
- `source_system: marketer7`
- `mission_id: M<N>` and `experiment_id: EX-<NNN>`
- `executor: signal7`
- a `tracking` key, which may be empty

Reject an unknown contract major version, malformed/missing IDs, unsupported executor, or a missing claims/execution scope with `awaiting-input`. Do not read `.marketer/`, infer omitted strategy, or follow a path merely mentioned inside the brief.

## Writes

Write only inside the already allocated Signal task directory:

1. Copy the optional `source_system`, `mission_id`, `experiment_id`, `tracking`, and source brief path into `task.md` frontmatter.
2. Save a bounded, source-linked `marketer-execution-brief.md` snapshot containing requested execution, allowed/forbidden claims, and tracking. Do not copy Marketer7 strategy, measurement, or evaluation history.
3. Create `execution-result.md` from `skills/signal/templates/execution-result.md` with `status: pending`, the Signal task ID, origin IDs, tracking, and an empty append-only events section.

The `signal` router retains ownership of `task.md` phase/awaiting/scope. `signal-brief` still performs Signal7's normal brand, claims, and scope gates; `signal-create`, review, and publish remain unchanged in ownership.

## Output Contract

Allowed verdicts: `phase-complete`, `awaiting-input`.

Accepted brief:

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Marketer7 execution brief recorded; continue the normal Signal7 brief gate."
```

Rejected brief:

```yaml
signal_verdict:
  verdict: awaiting-input
  target: null
  summary: "Marketer7 execution brief is invalid or unsupported; no Signal task metadata was changed."
```
