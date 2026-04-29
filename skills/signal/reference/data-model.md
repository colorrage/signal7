# Signal7 Data Model

This is the source of truth for `.signal/` state, task artifacts, asset files, and shared schemas. Signal7 skills must prefer these field names and enum values over local inventions.

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
      prompts/
        A<N>-r<revision>-<worker>.md
      A<N>-<slug>.md
  archive/
  context/
    brand.md
    product.md
    competitors.md
    past-campaigns.md
    approved-claims.md
  recipes/
  backlog.md
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

The `signal` orchestrator owns `phase` and `awaiting`. Phase skills may write classification fields such as `scope`, but do not mutate `phase` or `awaiting`.

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
title: Asset title
status: todo | in-progress | done | blocked | cancelled
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

`depends` contains same-task asset IDs only, for example `["A1", "A2"]`. A dependency is met only when every referenced asset has `status: done`. `blocked` or `cancelled` dependencies block dependents. Translation assets also require the `source_asset` to have `review_ai_pass: true`.

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

The prompt hash is an integrity check for the stored prompt; it is not a substitute for prompt storage.

`content_hash` is the SHA-256 hash of the exact text under the asset `## Content` section after generation. `signal-publish` reads it from the latest generation log entry. If absent, publish computes it from `## Content` and records the value before calculating the publish idempotency key.

## brief.md

Owned by `signal-brief`.

```yaml
---
scope: quick | campaign | strategy
objective: ""
audience: ""
channels: []
languages: []
tone_by_channel: {}
approved_claims_required: false
regulated_domain: unknown | false | true
asset_ceiling: 3
---
```

For quick scope, the normalized brief must describe one channel, one language, and no more than three derived assets. If later derivation exceeds that ceiling, `signal-create` redirects to `plan`.

`signal-brief` also creates `compliance.md` for every task. For non-regulated tasks it records `regulated_domain: false` and an empty pre-check; for regulated or uncertain tasks it records the questions and required checks before review.

## compliance.md

Owned by `signal-brief`; read by `signal-review`.

```yaml
---
regulated_domain: unknown | false | true
jurisdictions: []
approved_claims_required: false
status: clear | questions-open | blocked
---
```

Required sections:

- `## Regulated Domain`
- `## Claims Pre-check`
- `## Required Disclaimers`
- `## Jurisdiction Notes`
- `## Open Questions`

`signal-review` must fail or block public assets when `compliance.md` has `status: questions-open` or `status: blocked`.

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
  updated_at: null
```

If timeout expires and `auto_approve_on_timeout` is `false`, set `status: escalated`, name the delegate or owner in the summary, and keep the approval gate open.

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
  status: published | skipped-duplicate | blocked-expired | blocked-external-gate | failed
  message: ""
```

Idempotency key input:

```text
task_id + asset_id + channel + publish_at + content_hash
```

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
