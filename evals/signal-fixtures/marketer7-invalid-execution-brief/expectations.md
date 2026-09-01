---
dispatch_skill: signal-marketer
ignore_fields: [updated_at, timestamp, actual_publish_time]
max_attempts: 3
expected_static_result: fail
expected_error: experiment_id
---

# Fixture: marketer7-invalid-execution-brief

This snapshot deliberately propagates `EX-021` through executor-derived records while the Marketer-origin Signal task declares `EX-001`. It must fail Marketer association validation and must never be accepted as a legacy task because `source_system: marketer7` is explicit.

## Trigger

The static fixture runner validates a malformed Marketer-origin Signal7 task snapshot.

## Expected verdict

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Fixture is intentionally invalid; static metadata rejection is expected."
```

## Expected mutations

- `task.md` retains a valid Marketer origin with `experiment_id: EX-001`.
- Brief, result, asset, and publish ledger use the mismatched `EX-021` identifier.
- The runner recognizes the named expected static failure while preserving non-zero exit behavior for any unrelated fixture failure.

## Regressions guarded

- Marketer metadata checks cannot be deleted or bypassed.
- A Marketer-origin task cannot evade validation through the legacy optional-metadata path.
