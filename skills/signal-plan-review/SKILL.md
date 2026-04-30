---
name: signal-plan-review
description: >
  Internal Signal7 phase skill that independently reviews campaign
  content-plan.md before asset creation.
user-invocable: false
---

# signal-plan-review

Review a campaign plan before assets are created. This skill must run in a fresh isolated sub-agent context; inline invocation is not permitted.

Do not write `task.md` `phase:`, `awaiting:`, or `scope:`.

## Read First

- `skills/signal/reference/data-model.md`
- `skills/signal/reference/gates.md`
- `skills/signal/reference/portability.md`
- `skills/signal/reference/cross-system-boundaries.md`
- task `brief.md`
- task `compliance.md`
- task `content-plan.md`
- task asset stubs
- `.signal/context/brand.md`
- `.signal/context/product.md`
- `.signal/context/approved-claims.md`
- `.signal/context/competitors.md` when present
- `.signal/context/past-campaigns.md` when present

## Responsibilities

Write `plan-review.md` with:

- `## Verdict`
- `## Findings`
- `## Coverage`
- `## Asset Routing`
- `## Compliance`
- `## Timeline`
- `## Required Changes`

Check:

- Brief coverage: every requested channel, language, audience, and deliverable is represented.
- Scope honesty: unsupported workers (`email-copy`, `blog`, `landing-page`, `translation`, `research`, `pricing`) are clearly marked as blocked or future-worker dependent, not silently treated as social copy.
- Asset routing: `asset_type` matches channel and source/dependency rules.
- Dependency correctness: every `depends[]` and `source_asset` reference points to an existing same-task asset; no dependency points to `cancelled` stubs.
- Tone consistency: plan uses brand tone guidance per channel.
- Claims and compliance: regulated questions are resolved before public claims; public claims are covered by `approved-claims.md` or task `compliance.md`.
- Timeline feasibility: publish dates and dependency ordering do not require a dependent asset before its source can be reviewed.
- Independence: do not read Hyper7 internals or assume Hyper7 state. External prerequisites must appear as Signal `external_gate` fields.

## Verdict Rules

Return `phase-complete` when the plan is safe to create assets.

Return `redirect target: plan` when findings are fixable by revising `content-plan.md` or stubs. Include the exact remediation items in `plan-review.md`.

Return `awaiting-input` only when the reviewer cannot determine a requirement from the artifacts and needs a human answer rather than a plan revision.

## Output Contract

Allowed verdicts: `phase-complete`, `redirect`, `awaiting-input`.

Plan approved:

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Campaign plan review passed."
```

Plan needs revision:

```yaml
signal_verdict:
  verdict: redirect
  target: plan
  summary: "Campaign plan review found fixable issues."
```

Human input needed:

```yaml
signal_verdict:
  verdict: awaiting-input
  target: null
  summary: "Plan review needs one human decision before it can pass."
```
