# Signal7 State Graph

Signal7 uses scope-specific phase paths.

## quick — Phase 1 (implemented)

```text
brief -> create -> review -> publish -> done
```

Redirects:

- `review -> create` when AI review fails or a human rejects. `signal-review` flips affected assets to `status: needs-revision` before redirecting; `signal-create` increments `revision` and dispatches the worker again.
- `create -> plan` (Phase 2 only) when derived asset count exceeds 3. In Phase 1 this case returns `awaiting-input` instead — no `plan` skill exists yet to dispatch into.

## campaign — Phase 2 (planned)

```text
brief -> plan -> plan-review -> create -> review -> publish -> done
```

Redirects:

- `plan-review -> plan` when plan review finds fixable issues.
- `review -> create` when asset review fails (same flip rule as quick).

`plan-review` must run in a fresh sub-agent context.

While Phase 2 is unimplemented, `signal-brief` refuses `scope: campaign` at intake with `awaiting-input` and a "Phase 2 not yet implemented" summary.

## strategy — Phase 1 (partial)

```text
brief -> create -> done
```

Strategy create produces internal artifacts such as research or pricing recommendations. It does not publish. Most strategy asset types (`research`, `pricing`) require Phase 3 workers; `signal-create` stamps the correct asset type and `signal-worker` blocks with `awaiting-input` until the matching worker exists.

## Gates

`awaiting: user-input` pauses for clarification.

`awaiting: user-approval` pauses for approving durable artifacts:

- `brief.md`
- `content-plan.md` (Phase 2)
- `review.md` human approvals

## Asset-level Gates

Asset-level gates do not change the phase graph:

- `external_gate` blocks a single asset in publish.
- `expires_at` blocks a single expired asset in publish.
- `depends[]` controls create-time worker eligibility.

## Partial Review for Translation Flow

Translation assets depend on the source asset's `review_ai_pass: true`. The default `signal-create -> signal-review` cycle reviews every non-cancelled asset at once, which would never review the source before the translation depends on it.

To break the stall, `signal-review` supports a partial-review mode (engaged automatically when the eligible-set contains translation assets whose source has `review_ai_pass: null`):

```text
create (source only) -> review (source only) -> create (translations + remaining) -> review (full) -> publish
```

The path is a sequence of `redirect target: review` (after source assets are done) and `redirect target: create` (after the source passes review and translation assets are now eligible). `signal-create` keeps already-`done` source assets unchanged; it only dispatches the newly-eligible translation assets. `signal-review` recognises the second invocation as covering both source (already passed) and translations (new); it reuses the prior pass for source rather than re-running the AI rubric.

Phase 1 has no translation worker, so partial review never engages today; the contract is documented here so it lands cleanly with `signal-translate` in Phase 3.
