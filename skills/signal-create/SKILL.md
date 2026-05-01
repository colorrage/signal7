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
- `skills/signal/reference/state-graph.md`
- `skills/signal/reference/adding-workers.md`
- `skills/signal/reference/portability.md`
- task `brief.md`
- task `compliance.md`

For campaign scope, also read `content-plan.md`. Phase 2 reaches this branch after `signal-plan` and `signal-plan-review` complete.

## Quick Scope

1. Read `brief.md`.
2. Derive one asset per channel/language/output variant.
3. Set `asset_type` from channel:
   - `linkedin`, `instagram`, `twitter`, `facebook` -> `social-copy` *(worker = `signal-social`)*
   - `email` -> `email-copy` *(worker = `signal-copy`)*
   - `blog` -> `blog` *(worker = `signal-copy`)*
   - `web` -> `landing-page` *(worker = `signal-copy`)*
   - otherwise -> `copy` *(worker = `signal-copy`)*

   Stamp the correct type. `signal-worker` blocks unknown asset types; never coerce a channel into `social-copy`.
4. If the derived asset count exceeds `asset_ceiling` (default 3), return `redirect target: plan`. `signal` will treat this as a campaign conversion, set task `scope: campaign`, and dispatch `signal-plan`.

5. Replace every `<TODO>` sentinel in `templates/asset.md` with the resolved value when stamping `A<N>-*.md` files. Required: `id`, `parent`, `title`, `asset_type`, `channel`. A remaining sentinel is a hard error and must be surfaced as `awaiting-input` rather than left in place.
6. Pin `brand_version` and `product_version` from context files when available.
7. Set `external_gate: null` unless the brief requires one.
8. Dispatch eligible assets through `signal-worker` (see `portability.md` § Dispatch Contract). Sequential dispatch is permitted; parallel dispatch requires a sub-agent per asset and disjoint `writes[]`.

## Campaign Scope

1. Read `content-plan.md`.
2. Use `asset_plan[]` / table rows as the source of truth.
3. Create missing stubs.
4. Ignore stubs with `status: cancelled`.
5. Do not delete obsolete stubs; re-plans mark them cancelled.
6. Dispatch eligible assets through `signal-worker`.

## Strategy Scope

Create internal `research` and `pricing` assets when requested. Strategy tasks dispatch `signal-research` and/or `signal-price` directly, write the artifact, and end at `done` — review and publish are skipped unless the user later promotes the artifact to public content.

## Eligibility

An asset is eligible when:

- `status: todo` or `status: needs-revision`
- every id in `depends[]` points to a same-task asset with `status: done`
- no dependency is `blocked`, `needs-revision`, or `cancelled`
- translation assets additionally require `source_asset.review_ai_pass: true` *and* `source_asset.status: done`

When an asset is `status: needs-revision`, increment `revision` before dispatching the worker; the worker will store its prompt under `prompts/A<N>-r<revision>-<worker>.md`.

Eligible assets with disjoint `writes[]` may run in parallel.

## Partial Review (translation flow)

If the eligible set after a worker pass contains translation assets whose `source_asset` has `review_ai_pass: null`, do not block them — return `redirect target: review` so `signal-review` runs the source-asset rubric first. After `signal-review` redirects back, the translations become eligible (source has `review_ai_pass: true` and `status: done`) and `signal-create` dispatches their workers. See `state-graph.md` § Partial Review for Translation Flow.

`signal-translate` is the worker dispatched after the partial-review redirects resolve.

## Output Contract

Allowed verdicts: `phase-complete`, `awaiting-input`, `redirect`.

All assets done:

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Assets created."
```

Asset-ceiling overflow:

```yaml
signal_verdict:
  verdict: redirect
  target: plan
  summary: "Quick brief expanded past the asset ceiling; campaign planning is required."
```

Source review needed before translations:

```yaml
signal_verdict:
  verdict: redirect
  target: review
  summary: "Source assets ready; review them before dispatching translations."
```

Blocked assets (worker not implemented, missing context, sentinel left in template):

```yaml
signal_verdict:
  verdict: awaiting-input
  target: null
  summary: "Some assets are blocked; see asset files for details."
```
