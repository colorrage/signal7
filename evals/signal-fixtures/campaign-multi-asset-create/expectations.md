---
dispatch_skill: signal-create
ignore_fields: [updated_at, timestamp, actual_publish_time]
max_attempts: 3
---

# Fixture: campaign-multi-asset-create

Guards the `signal-create` campaign code path with mixed worker availability. The `content-plan.md` lists 3 assets: two use `social-copy` (implemented worker `signal-social`) and one uses `email-copy` (planned Phase 3 worker, not yet implemented). `signal-create` must dispatch A1 and A2 successfully and block A3 with a clear "worker not yet implemented" message.

## Snapshot point

The fixture freezes the system **before `signal-create` first dispatch for the campaign**:

- `task.md` `phase: create`, `scope: campaign`, `awaiting: null`
- `brief.md` approved with `channels: [linkedin, instagram, email]`
- `content-plan.md` approved with `status: approved`, listing 3 assets in the asset plan:
  - A1-linkedin-en (social-copy) — implemented worker
  - A2-instagram-en (social-copy) — implemented worker
  - A3-email-en (email-copy) — Phase 3 worker, not yet implemented
- All 3 asset stubs exist at `status: todo`, `review_ai_pass: null`
- `compliance.md` `status: clear`
- No assets have dependencies — all eligible for dispatch

## Trigger

`signal` dispatches `signal-create` for the campaign path after `content-plan.md` is approved and plan-review passes.

## Expected verdict

Either `phase-complete` (if A3 is blocked but A1 and A2 complete, and the system treats non-blocking partial completion as complete) or `awaiting-input` (if blocked A3 requires user input):

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Assets created."
```

## Expected mutations

- `A1-linkedin-en.md` `status: done`, `generation_log` populated with one entry (`worker: signal-social`, `worker_version: 1`), `review_ai_pass: null`.
- `A2-instagram-en.md` `status: done`, `generation_log` populated with one entry (`worker: signal-social`, `worker_version: 1`), `review_ai_pass: null`.
- `A3-email-en.md` `status: blocked`, `## Completion` section records the reason: `"Worker signal-copy is planned for Phase 3 and is not yet implemented."`.
- `A3-email-en.md` `asset_type` remains `email-copy` — signal-worker must NOT coerce it to `social-copy`.
- Content under `## Content` in A1 and A2 is generated (specific text is non-deterministic; this fixture guards the contract and routing, not copy quality).
- `prompts/A1-r0-signal-social.md` and `prompts/A2-r0-signal-social.md` created under the task directory.
