---
name: signal-brief
description: >
  Internal Signal7 phase skill that clarifies business intent, classifies
  scope, writes brief.md and compliance.md, and returns an approval verdict.
user-invocable: false
---

# signal-brief

Turn the user's request into an approved business brief. Do not write `task.md` `phase:` or `awaiting:`.

## Read First

- `skills/signal/reference/data-model.md`
- `skills/signal/reference/gates.md`
- `skills/signal/reference/intake-triage.md`
- `skills/signal/reference/compliance-and-audit.md`

## Responsibilities

- Ask at most one clarification question per dispatch.
- Classify scope as `quick`, `campaign`, or `strategy`.
- Write `brief.md` from `skills/signal/templates/brief.md`.
- Create `compliance.md` for every task from `skills/signal/templates/compliance.md`.
- Write `scope` to `task.md` frontmatter, but never write `phase` or `awaiting`.
- Return `awaiting-approval` when the brief is ready for the user.
- On approval redispatch, return `phase-complete`.

## Compliance Ownership

`signal-brief` owns creation of `compliance.md`.

For non-regulated work:

```yaml
regulated_domain: false
approved_claims_required: false
status: clear
```

For regulated or uncertain work, record questions/checks and keep:

```yaml
regulated_domain: true
status: questions-open
```

Ask directly when the request may involve healthcare, finance, legal, children's products, privacy, regulated advertising, or evidence-backed claims.

## Quick Scope Guard

Quick scope means one channel, one language, and no more than three derived assets. Record `asset_ceiling: 3` in `brief.md`.

## Output Contract

When asking a question:

```yaml
signal_verdict:
  verdict: awaiting-input
  target: null
  summary: "Question text here."
```

When ready for approval:

```yaml
signal_verdict:
  verdict: awaiting-approval
  target: null
  summary: "Brief and compliance artifacts are ready for approval."
```

On approval:

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Brief approved."
```

