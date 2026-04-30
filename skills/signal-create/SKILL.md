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

For campaign scope (Phase 2), also read `content-plan.md`. Phase 1 will not reach this branch — `signal-brief` and `signal` block campaign at intake.

## Quick Scope

1. Read `brief.md`.
2. Derive one asset per channel/language/output variant.
3. Set `asset_type` from channel:
   - `linkedin`, `instagram`, `twitter`, `facebook` -> `social-copy` *(Phase 1 implemented)*
   - `email` -> `email-copy` *(worker = `signal-copy`, Phase 2)*
   - `blog` -> `blog` *(worker = `signal-copy`, Phase 2)*
   - `web` -> `landing-page` *(worker = `signal-copy`, Phase 2)*
   - otherwise -> `copy` *(worker = `signal-copy`, Phase 2)*

   Stamp the correct type even if the worker is not yet implemented; `signal-worker` blocks unsupported types with a clear "worker not yet implemented" message rather than coercing to `social-copy`.
4. If the derived asset count exceeds `asset_ceiling` (default 3): in **Phase 1** return `awaiting-input` with the campaign-not-implemented message; in **Phase 2** (once `signal-plan` ships) return `redirect target: plan`. Never emit `redirect target: plan` while `signal-plan` is unimplemented — `signal` cannot dispatch into a non-existent skill.

   Phase 1:

   ```yaml
   signal_verdict:
     verdict: awaiting-input
     target: null
     summary: "Quick brief expanded past the asset ceiling. Campaign scope is Phase 2; not yet implemented. Reduce scope or cancel?"
   ```

5. Replace every `<TODO>` sentinel in `templates/asset.md` with the resolved value when stamping `A<N>-*.md` files. Required: `id`, `parent`, `title`, `asset_type`, `channel`. A remaining sentinel is a hard error and must be surfaced as `awaiting-input` rather than left in place.
6. Pin `brand_version` and `product_version` from context files when available.
7. Set `external_gate: null` unless the brief requires one.
8. Dispatch eligible assets through `signal-worker` (see `portability.md` § Dispatch Contract). Sequential dispatch is permitted; parallel dispatch requires a sub-agent per asset and disjoint `writes[]`.

## Campaign Scope — Phase 2

1. Read `content-plan.md`.
2. Use `asset_plan[]` / table rows as the source of truth.
3. Create missing stubs.
4. Ignore stubs with `status: cancelled`.
5. Do not delete obsolete stubs; re-plans mark them cancelled.
6. Dispatch eligible assets through `signal-worker`.

## Strategy Scope

Create internal research/pricing assets when requested. In Phase 1, unsupported strategy asset types (`research`, `pricing`) block with `awaiting-input` until Phase 3 workers exist.

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

This path never engages in Phase 1 because no translation worker exists yet. The contract is documented now so `signal-translate` lands cleanly in Phase 3.

## Output Contract

Allowed verdicts: `phase-complete`, `awaiting-input`, `redirect`.

All assets done:

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Assets created."
```

Asset-ceiling overflow (Phase 1):

```yaml
signal_verdict:
  verdict: awaiting-input
  target: null
  summary: "Quick brief expanded past the asset ceiling. Campaign scope is Phase 2; not yet implemented."
```

Asset-ceiling overflow (Phase 2):

```yaml
signal_verdict:
  verdict: redirect
  target: plan
  summary: "Quick brief expanded past the asset ceiling; campaign planning is required."
```

Source review needed before translations (Phase 3):

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
