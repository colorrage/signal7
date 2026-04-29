---
name: signal-create
description: >
  Internal Signal7 phase skill that creates assets for quick/campaign/strategy
  tasks and dispatches eligible asset workers.
user-invocable: false
---

# signal-create

Create or complete task assets. Do not write `task.md` `phase:` or `awaiting:`.

## Read First

- `skills/signal/reference/data-model.md`
- `skills/signal/reference/gates.md`
- `skills/signal/reference/adding-workers.md`
- task `brief.md`
- task `compliance.md`

For campaign scope, also read `content-plan.md`.

## Quick Scope

1. Read `brief.md`.
2. Derive one asset per channel/language/output variant.
3. Set `asset_type` from channel:
   - `linkedin`, `instagram`, `twitter`, `facebook` -> `social-copy`
   - `email` -> `email-copy`
   - `blog` -> `blog`
   - `web` -> `landing-page`
   - otherwise -> `copy`
4. If the mapped `asset_type` is unsupported in the current phase, still stamp the correct type and let `signal-worker` block it. Do not coerce unsupported types to `social-copy`.
5. If derived count exceeds `asset_ceiling` (default 3), return:

```yaml
signal_verdict:
  verdict: redirect
  target: plan
  summary: "Quick brief expanded past the asset ceiling; campaign planning is required."
```

6. Create missing `A<N>-*.md` files from `skills/signal/templates/asset.md`.
7. Pin `brand_version` and `product_version` from context files when available.
8. Set `external_gate: null` unless the brief requires one.
9. Dispatch eligible assets through `signal-worker`.

## Campaign Scope

1. Read `content-plan.md`.
2. Use `asset_plan[]` / table rows as the source of truth.
3. Create missing stubs.
4. Ignore stubs with `status: cancelled`.
5. Do not delete obsolete stubs; re-plans mark them cancelled.
6. Dispatch eligible assets through `signal-worker`.

## Strategy Scope

Create internal research/pricing assets when requested. In Phase 1, unsupported strategy asset types may block with `awaiting-input` until Phase 3 workers exist.

## Eligibility

An asset is eligible when:

- `status: todo`
- every id in `depends[]` points to a same-task asset with `status: done`
- no dependency is `blocked` or `cancelled`
- translation assets additionally require `source_asset.review_ai_pass: true`

Eligible assets with disjoint `writes[]` may run in parallel.

## Output Contract

All assets done:

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Assets created."
```

Blocked assets:

```yaml
signal_verdict:
  verdict: awaiting-input
  target: null
  summary: "Some assets are blocked; see asset files for details."
```
