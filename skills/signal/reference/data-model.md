# Signal7 Data Model

This is the source of truth for `.signal/` state, task artifacts, asset files, and shared schemas. Signal7 skills must prefer these field names and enum values over local inventions.

> **Phase status.** All five planned phases (0–5) are implemented: foundation, quick scope, campaign planning + plan-review, the full content worker surface (`signal-social`, `signal-copy`, `signal-image`, `signal-video`, `signal-translate`, `signal-research`, `signal-price`), the full management surface (`signal-task`, `signal-backlog`, `signal-handoff`, `signal-retro`, `signal-recipe`), and `signal-team` for second-opinion delegation. README § Status is the source of truth for what is shipped today.

## State Root

Project state lives under `.signal/` in the user's project, not inside the Signal7 skill repo.

```text
.signal/
  tasks/
    S<N>-<slug>/
      task.md
      dashboard.md
      brief.md
      compliance.md
      content-plan.md
      plan-review.md
      review.md
      publish-log.md
      prompts/                  # created lazily by the first worker
        A<N>-r<revision>-<worker>.md
      A<N>-<slug>.md
  archive/
  context/
    brand.md
    product.md
    competitors.md
    past-campaigns.md
    approved-claims.md
  recipes/                      # created lazily by signal-recipe
  team/                         # created lazily by signal-team (raw output + providers/)
  backlog.md                    # managed by signal-backlog
  retro.md                      # created lazily by signal-retro project-level
  config.yaml
```

Archived tasks keep the same folder shape under `.signal/archive/`. Task ids are never reused.

## task.md

```yaml
---
id: S<N>
title: Short title
phase: deferred | brief | plan | plan-review | create | review | publish | done | cancelled
scope: unknown | quick | campaign | strategy
created: YYYY-MM-DDTHH:MM:SS
awaiting: null | user-input | user-approval
---
```

`signal` (and `signal-task` for explicit cancel/defer) own every mutation of `phase`, `awaiting`, and `scope`. Phase skills do not write any top-level `task.md` field. The previous architectural exception — `signal-brief` writing `scope` directly — has been removed; `signal` now mirrors `scope` from `brief.md` after `signal-brief` returns `phase-complete`.

The `plan` and `plan-review` phase values are reachable for campaign scope.

## Asset IDs

Asset IDs are task-scoped. `A1` may exist in both `S1` and `S2`; the globally unambiguous reference is `S1.A1` or `S2.A1`.

Asset files live at the task root:

```text
.signal/tasks/S1-launch-campaign/A1-linkedin-en.md
```

Publish idempotency keys must include `task_id` so task-scoped asset ids cannot collide.

## Asset File

```yaml
---
id: A<N>
parent: S<N>
source_system: marketer7 | null
mission_id: M<N> | null
experiment_id: EX-<NNN> | null
tracking: null | mapping
title: Asset title
status: todo | needs-revision | in-progress | done | blocked | cancelled
cancelled_reason: null
asset_type: social-copy | email-copy | blog | landing-page | copy | image-prompt | video-script | translation | research | pricing
channel: linkedin | instagram | twitter | facebook | email | blog | web | internal | none
language: en
source_asset: null
depends: []
writes: []
revision: 0
brand_version: null
product_version: null
market: null
jurisdiction: null
publish_at: null
expires_at: null
review_ai_pass: null
external_gate: null
usage_rights: null
generation_log: []
---
```

`status` values:

- `todo` — not yet started.
- `in-progress` — a worker is generating content.
- `done` — content generated; awaiting review or already approved.
- `needs-revision` — `signal-review` flagged this asset as failing the AI rubric or rejected by a human; `signal-create` will pick it up on redirect, increment `revision`, and dispatch a worker again. Set by `signal-review` only.
- `blocked` — a worker reported it could not produce this asset (missing context, unsupported asset type in current phase). Surfaced to the user via `awaiting-input`.
- `cancelled` — kept on disk after a re-plan removed the asset. Never deleted; downstream dependents must treat it as blocking.

## Optional Marketer7 origin metadata

`task.md` and asset frontmatter may feature-detect these optional fields: `source_system`, `mission_id`, `experiment_id`, `tracking`, and `execution_brief_path` (task only). A legacy file without them remains valid unchanged. If `source_system: marketer7` is present, `mission_id`, `experiment_id`, and a `tracking` key are required and every derived asset must retain the same values. These fields identify execution context only; they never affect Signal task phase, review, publish eligibility, or experiment evaluation.

Marketer7's `signal7-execution-brief/v1` is consumed by `signal-marketer`. Signal writes a task-local `marketer-execution-brief.md` snapshot plus `execution-result.md` from `templates/execution-result.md` with `contract: signal7-execution-result/v1`; it never reads or writes `.marketer/`.

Templates ship with `<TODO>` sentinels for `id`, `parent`, `title`, `asset_type`, and `channel`. `signal-create` must replace every sentinel before returning `phase-complete`. A remaining sentinel is a hard error, not a default.

`external_gate` is either `null` or:

```yaml
description: Product page is live
source_system: manual | external-adapter
required_status: live
status: pending | satisfied | waived
evidence: null
checked_at: null
```

If `external_gate` is `null`, `signal-publish` skips the gate check. If `status` is `satisfied` or `waived`, publishing may proceed. Any other status blocks that asset.

`depends` contains same-task asset IDs only, for example `["A1", "A2"]`. A dependency is met only when every referenced asset has `status: done`. `blocked`, `needs-revision`, or `cancelled` dependencies block dependents. Translation assets also require the `source_asset` to have `review_ai_pass: true` *and* `status: done`.

`generation_log` is append-only by convention:

```yaml
- timestamp: YYYY-MM-DDTHH:MM:SS
  model: model-name
  worker: signal-social
  worker_version: 1
  prompt_path: prompts/A1-r0-signal-social.md
  prompt_hash: sha256:<hex>
  content_hash: sha256:<hex>
```

`worker_version` is read from the worker's own `SKILL.md` frontmatter (`worker_version: <int>`). If the worker omits the field, treat it as `1`. Workers must increment when generation behaviour changes in a way downstream review or replay should distinguish.

The prompt hash is an integrity check for the stored prompt; it is not a substitute for prompt storage.

`content_hash` is the SHA-256 hash of the exact text under the asset `## Content` section after generation. `signal-publish` reads it from the latest generation log entry. If absent, publish computes it from `## Content` and records the value before calculating the publish idempotency key.

## brief.md

Owned by `signal-brief`.

```yaml
---
scope: <TODO> | quick | campaign | strategy
objective: ""
audience: ""
channels: []
languages: []
tone_by_channel: {}
approved_claims_required: false
regulated_domain: false
asset_ceiling: 3
---
```

`signal-brief` must replace the `<TODO>` sentinel on `scope` and the empty `objective` / `audience` strings before returning `phase-complete`. `signal` mirrors the resolved `scope` into `task.md` after the brief is approved; `signal-brief` does not write `task.md`.

For quick scope, the normalized brief must describe one channel, one language, and no more than three derived assets. If later derivation exceeds that ceiling, `signal-create` returns `redirect target: plan` and `signal` converts the task to campaign scope.

`signal-brief` also creates `compliance.md` for every task. The template default is non-regulated; `signal-brief` raises to `questions-open` only when triage detects a regulated domain.

## compliance.md

Owned by `signal-brief`; read by `signal-review`.

```yaml
---
regulated_domain: false | true | unknown
jurisdictions: []
approved_claims_required: false
status: clear | questions-open | blocked
---
```

Default state is `regulated_domain: false`, `status: clear`. `signal-brief` raises to `regulated_domain: true` (or `unknown`) and `status: questions-open` when triage detects a regulated domain (healthcare, finance, legal, children's products, privacy, regulated advertising, evidence-backed claims, jurisdiction-specific restrictions). The default flipped from `questions-open` to `clear` to prevent silent halts in `signal-review` when `signal-brief` forgot to write a non-regulated answer.

Required sections:

- `## Regulated Domain`
- `## Claims Pre-check`
- `## Required Disclaimers`
- `## Jurisdiction Notes`
- `## Open Questions`

`signal-review` must fail or block public assets when `compliance.md` has `status: questions-open` or `status: blocked`. It returns the dedicated `awaiting-input` verdict (not `redirect`) for compliance blockers — there is no asset content to redirect.

## content-plan.md

Owned by `signal-plan`; read by `signal-plan-review` and `signal-create`.

```yaml
---
plan_version: 1
scope: campaign
status: draft | approved | rejected
---
```

Required sections:

- `## Strategy Summary`
- `## Channel Language Matrix`
- `## Asset Plan`
- `## Timeline`
- `## Assumptions`
- `## Revision Notes`

The asset plan is a table with these columns:

```text
id | title | asset_type | channel | language | source_asset | depends | publish_at | external_gate | notes
```

`signal-plan` creates matching `A<N>-*.md` stubs for each asset plan row. On re-plan, it marks removed or obsolete stubs as `status: cancelled` with `cancelled_reason`; it does not silently delete them. Reused asset ids keep their file and increment `revision` when their planned content meaningfully changes.

## review.md

Owned by `signal-review`.

Approver status enum:

```text
pending | approved | rejected | escalated
```

Per-approver schema:

```yaml
- name: Owner
  delegate: null
  timeout_hours: 24
  auto_approve_on_timeout: false
  status: pending
  comment: null
  rejected_reason: null
  updated_at: null
  recorded_response: null
```

`recorded_response` captures the literal user reply that drove the most recent state change (`approve`, `reject: <reason>`, or a paste-in delegate decision). `signal-review` writes it on redispatch when it observes a state change driven by user input — the orchestrator sets `awaiting`, `signal-review` materialises the answer in `review.md` so the artifact is the durable record, not just `task.md` `awaiting`.

`rejected_reason` populates from the user's `reject: <reason>` reply or from a delegate's recorded reason.

If timeout expires and `auto_approve_on_timeout` is `false`, set `status: escalated`, name the delegate or owner in the summary, and keep the approval gate open.

When `signal-review` records a `rejected` row (AI rubric fail or human rejection), it must also flip the affected asset's `status` from `done` to `needs-revision` and increment `revision` on the next worker dispatch in `signal-create`. Without this status flip, `signal-create` skips the asset on redirect and the rework loop spins.

## publish-log.md

Append-only ledger owned by `signal-publish`.

```yaml
- timestamp: YYYY-MM-DDTHH:MM:SS
  task_id: S<N>
  asset_id: A<N>
  channel: linkedin
  publish_at: YYYY-MM-DDTHH:MM:SS
  actual_publish_time: YYYY-MM-DDTHH:MM:SS
  idempotency_key: sha256:<hex>
  status: published | skipped-duplicate | blocked-expired | blocked-external-gate | blocked-rate-limit | failed
  message: ""
  source_system: marketer7            # optional
  mission_id: M<N>                    # optional
  experiment_id: EX-<NNN>             # optional
  tracking: {}                        # optional
```

Status values:

- `published` — entry appended successfully.
- `skipped-duplicate` — idempotency key matched a prior entry; nothing appended.
- `blocked-expired` — `expires_at` is in the past.
- `blocked-external-gate` — `external_gate.status` is not `satisfied`/`waived`.
- `blocked-rate-limit` — `publish_rate_limits` reached for this channel or globally; the asset was deferred. See `signal-publish` for the defer-vs-block rule.
- `failed` — terminal error during ledger write.

### Idempotency key

```text
task_id + asset_id + channel + publish_at + content_hash
```

`publish_at` participates as either a non-empty timestamp string (`YYYY-MM-DDTHH:MM:SS`) or the literal token `null` when `publish_at` was unset at publish time. The token form is intentional: it makes the key stable across writes that filled `publish_at` in later. If a task wants its eventual `publish_at` value to influence the key, it must set `publish_at` *before* publishing — a later edit does not retroactively change the published key.

`signal-publish` reads existing publish-log entries written under earlier rules without modification; only new writes follow the rule above.

For a Marketer-originated task, a new ledger entry may add `source_system`, `mission_id`, `experiment_id`, and `tracking`. These optional fields do not participate in idempotency and legacy entries without them remain valid.

## Context Files

`approved-claims.md` is live by default; workers and review read the latest version because claim retractions must propagate.

`brand.md` and `product.md` are usually snapshot-pinned per asset by `brand_version` and `product_version`, but review may still warn when current context has drifted from the pinned version.

Minimum `approved-claims.md` shape:

```yaml
---
schema_version: 1
claims: []
---
```

Each claim:

```yaml
claim_text: ""
claim_type: feature | performance | pricing | legal | other
supporting_evidence: ""
expires_at: null
jurisdictions: []
```

## backlog.md

Owned by `signal-backlog`. `.signal/backlog.md` holds `B<N>` entries as YAML blocks under `## B<N>` headings. `signal-backlog` re-reads on every operation; manual edits between operations are tolerated.

Entries use `B<N>` ids:

```yaml
- id: B<N>
  title: ""
  description: ""
  suggested_scope: quick | campaign | strategy | unknown
  suggested_channels: []
  suggested_languages: []
  created: YYYY-MM-DDTHH:MM:SS
  status: open | promoted | dropped
```
