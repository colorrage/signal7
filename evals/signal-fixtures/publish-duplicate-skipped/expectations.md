---
dispatch_skill: signal-publish
ignore_fields: [updated_at, timestamp, actual_publish_time]
max_attempts: 3
---

# Fixture: publish-duplicate-skipped

Guards idempotent publish. Re-running publish on a task whose assets already have entries in `publish-log.md` must record `skipped-duplicate` rather than appending a fresh `published` entry.

## Snapshot point

The fixture freezes the system **after one publish run completed but before the user re-ran `/signal`**:

- `publish-log.md` has one `status: published` entry for `S1.A1` at idempotency key `sha256:1111…`.
- `task.md` `phase: publish`, `awaiting: null` (the user reran `/signal` to retry, but the system has not redispatched yet).

## Trigger

`signal` redispatches `signal-publish`.

## Expected verdict

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Publish ledger updated."
```

## Expected mutations

- `publish-log.md` gains one entry with `status: skipped-duplicate` for `S1.A1`. The existing `published` entry is **not** modified or duplicated.
- `task.md` `phase: done`, `awaiting: null` (set by `signal` after `phase-complete`).
- The folder moves to `.signal/archive/`.

## Reviewer-flagged regressions guarded

- Issue #1 strength: idempotency contract is solid.
- Issue #4 warning: `publish_at: null` participation in the SHA-256 hash is now defined (literal token `null`); rerunning with the same null `publish_at` produces the same key.
- The `idempotency_key` value in the new `skipped-duplicate` entry must exactly match the prior `published` entry's key.
