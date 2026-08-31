---
dispatch_skill: signal-marketer
ignore_fields: [updated_at, timestamp, actual_publish_time]
max_attempts: 3
---

# Fixture: marketer7-execution-brief

An approved Marketer7 execution brief is imported into a normal Signal7 task. The fixture freezes state after Signal7 has created, reviewed, and recorded a local publish event. It validates only executor-side association metadata, never performance or a Marketer experiment verdict.

## Trigger

User supplies a path to a `signal7-execution-brief/v1` while starting Signal7 work.

## Expected verdict

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Marketer7 execution brief recorded; continue the normal Signal7 brief gate."
```

## Expected mutations

- `task.md` records optional Marketer7 origin IDs, tracking, and the source brief path.
- Derived asset and new publish-ledger entry retain the same optional metadata.
- `execution-result.md` records task/asset/status/timestamp/tracking association without performance metrics.
- Signal7 continues its normal brief, claims, review, and publish gates; no `.marketer/` path is read or written.

## Regressions guarded

- Legacy Signal7 files without experiment metadata remain valid.
- Publish idempotency remains based only on task, asset, channel, publish time, and content hash.
- Signal7 does not turn a publish event into a Marketer experiment verdict.
