---
name: signal-publish
description: >
  Internal Signal7 phase skill that performs retry-safe publish ledger updates
  with external gates, expiry checks, rate limits, and idempotency.
user-invocable: false
---

# signal-publish

Publish is the release phase for Signal7. In Phase 1 it writes the append-only publish ledger; external platform API calls are not required.

Do not write `task.md` `phase:` or `awaiting:`.

## Read First

- `skills/signal/reference/data-model.md`
- `skills/signal/reference/bootstrap.md`
- `skills/signal/reference/cross-system-boundaries.md`
- task assets
- task `publish-log.md` if present
- `.signal/config.yaml`

## Per-asset Checks

1. Skip `status: cancelled` assets.
2. Require generated content and `status: done`.
3. External gate:
   - if `external_gate` is `null`, skip this check.
   - if present, require `status: satisfied` or `status: waived`.
   - otherwise record `blocked-external-gate`.
4. Expiry:
   - if `expires_at` is past, record `blocked-expired`.
5. Idempotency:
   - read `content_hash` from the latest `generation_log` entry.
   - if no `content_hash` exists, compute it from the exact text under the asset `## Content` section and append/record the value before using it.
   - compute SHA-256 of `task_id + asset_id + channel + publish_at + content_hash`.
   - if key exists in `publish-log.md`, record or report `skipped-duplicate`.
6. Rate limits:
   - use `.signal/config.yaml` `publish_rate_limits`.

## Ledger

Append entries to `publish-log.md`. Do not rewrite earlier entries.

## Output Contract

All publishable assets published or skipped:

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Publish ledger updated."
```

Blocked assets:

```yaml
signal_verdict:
  verdict: awaiting-input
  target: null
  summary: "Some assets are blocked before publish; see publish-log.md."
```
