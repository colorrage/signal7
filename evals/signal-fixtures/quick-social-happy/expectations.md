---
dispatch_skill: signal-publish
ignore_fields: [updated_at, timestamp, actual_publish_time]
max_attempts: 3
---

# Fixture: quick-social-happy

End-to-end happy path. A single LinkedIn post moves from brief → create → review → publish → done with no rejections, no rate-limit deferrals, no compliance blocks.

## Snapshot point

The fixture freezes the system **after publish, before archive**:

- `task.md` `phase: publish`, `awaiting: null`
- `A1-linkedin-en.md` `status: done`, `review_ai_pass: true`
- `review.md` exists with all approver rows `status: approved`
- `publish-log.md` exists with one `published` entry

## Trigger

User runs `/signal S1` (or the orchestrator continues automatically after the publish-phase verdict).

## Expected verdict

The next `signal-publish` dispatch should detect "all assets already published" and return:

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Publish ledger updated."
```

## Expected mutations

- `task.md` `phase: done`, `awaiting: null`.
- The folder moves to `.signal/archive/S1-quick-linkedin-launch/`.
- `dashboard.md` regenerated, `## Status` reads `Phase: done`.

## Reviewer-flagged regressions guarded

- README (issue #4 critical): the README must list `quick` as **implemented** and walk a quick-scope user through this exact path.
- `signal-publish` (issue #1 minor): bootstrap responsibility is documented; the publish phase must run cleanly on a fresh `.signal/`.
- Idempotency-key shape (issue #4 warning): the `idempotency_key` in `publish-log.md` must be SHA-256 of `task_id + asset_id + channel + publish_at + content_hash`. The fixture's `publish-log.md` records a literal `null` token where `publish_at` was unset.
