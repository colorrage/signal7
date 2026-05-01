---
name: signal-plan
description: >
  Internal Signal7 phase skill that turns an approved campaign brief into a
  content-plan.md and matching asset stubs.
user-invocable: false
---

# signal-plan

Plan campaign-scope Signal7 work. Do not write `task.md` `phase:`, `awaiting:`, or `scope:`.

## Read First

- `skills/signal/reference/data-model.md`
- `skills/signal/reference/gates.md`
- `skills/signal/reference/intake-triage.md`
- `skills/signal/reference/compliance-and-audit.md`
- task `brief.md`
- task `compliance.md`
- `.signal/context/brand.md`
- `.signal/context/product.md`
- `.signal/context/approved-claims.md`
- `.signal/context/competitors.md` when present
- `.signal/context/past-campaigns.md` when present

## Responsibilities

- Run only for `scope: campaign`.
- Read the approved `brief.md` as the source of truth for objective, audience, channels, languages, tone, compliance constraints, and asset ceiling overflow.
- Write `content-plan.md` using the schema in `data-model.md`.
- Create or update one `A<N>-*.md` asset stub per plan row at the task root.
- Preserve task-scoped asset ids. On re-plan, reuse ids for meaningfully same assets and increment `revision` when the intended content changes.
- Mark removed or obsolete stubs as `status: cancelled` with `cancelled_reason`; never silently delete old stubs.
- Pin `brand_version` and `product_version` from context files when available.
- Set `external_gate: null` unless the brief or compliance artifact names an external prerequisite.
- Ask for plan approval before `create`.

## Asset Planning Rules

Generate one plan row per concrete deliverable. The minimum row fields are:

```text
id | title | asset_type | channel | language | source_asset | depends | publish_at | external_gate | notes
```

Channel to asset type:

- `linkedin`, `instagram`, `twitter`, `facebook` -> `social-copy`
- `email` -> `email-copy`
- `blog` -> `blog`
- `web` -> `landing-page`
- internal strategy/research deliverables -> `research`
- otherwise -> `copy`

For image and video assets, use `image-prompt` and `video-script` respectively. For locale variants, use `translation` and set `source_asset` to the same-task asset id whose review must pass first.

Stamp the correct asset type. `signal-worker` blocks unknown asset types; planning must not coerce channels into `social-copy`.

For multi-language campaigns, prefer direct language-specific social assets when the brief asks for original localized copy. Use `translation` only when the brief asks to translate a specific source asset after review.

## content-plan.md Shape

Write:

```yaml
---
plan_version: 1
scope: campaign
status: draft
---
```

Required sections:

- `## Strategy Summary`
- `## Channel Language Matrix`
- `## Asset Plan`
- `## Timeline`
- `## Assumptions`
- `## Approval`
- `## Revision Notes`

The `## Asset Plan` table must include the required columns from `data-model.md`. Keep dependencies same-task (`A<N>`) only.

## Approval Redispatch

If `content-plan.md` already exists and the user replies `approve`, update:

- frontmatter `status: approved`
- `## Approval` to record `Status: approved`

Then return `phase-complete`.

If the user replies with a change request, revise `content-plan.md` and affected stubs, record the reason in `## Revision Notes`, and return `awaiting-approval` again.

If the reply is ambiguous, return `awaiting-input` with one specific question.

## Output Contract

Allowed verdicts: `awaiting-input`, `awaiting-approval`, `phase-complete`.

Need clarification:

```yaml
signal_verdict:
  verdict: awaiting-input
  target: null
  summary: "One planning question remains."
```

Plan ready for approval:

```yaml
signal_verdict:
  verdict: awaiting-approval
  target: null
  summary: "Campaign plan and asset stubs are ready for approval."
```

Plan approved:

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Campaign plan approved."
```
