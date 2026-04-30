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

## Bootstrap

`signal-publish` is a write-side skill (per `bootstrap.md`). On first run in a fresh project, ensure `.signal/` exists before reading `config.yaml`.

## Per-asset Checks

1. Skip `status: cancelled` assets.
2. Require generated content and `status: done`. (Skip `status: needs-revision`/`blocked`/`in-progress` assets and surface them via `awaiting-input` so the user knows publish did not cover them.)
3. External gate:
   - if `external_gate` is `null`, skip this check.
   - if present, require `status: satisfied` or `status: waived`.
   - otherwise record `blocked-external-gate`.
4. Expiry:
   - if `expires_at` is past, record `blocked-expired`.
5. Idempotency:
   - read `content_hash` from the latest `generation_log` entry.
   - if no `content_hash` exists, compute it from the exact text under the asset `## Content` section and append/record the value before using it.
   - assemble the key input: `task_id + asset_id + channel + publish_at + content_hash`. When `publish_at` is unset on the asset, use the literal token `null` in the key input. The token form is intentional: the key stays stable across writes that fill `publish_at` later. If a task wants its eventual `publish_at` value to influence the key, it must be set *before* publishing — a later edit does not retroactively change the published key.
   - compute SHA-256 of the assembled string.
   - if key exists in `publish-log.md`, record or report `skipped-duplicate`.
6. Rate limits:
   - read `publish_rate_limits` from `.signal/config.yaml`. Defaults: `global_per_hour: 10`, `per_channel_per_hour: 3`, `on_limit: defer`.
   - count entries in `publish-log.md` within the last hour, globally and for this asset's channel.
   - if either count is at or above its limit:
     - `on_limit: defer` (default): append `status: blocked-rate-limit` for the asset and surface `awaiting-input` overall ("rate limit reached; rerun publish later"). The user reruns `/signal` after the window passes.
     - `on_limit: block`: append `status: blocked-rate-limit` and treat as terminal for that asset (the user must rescope or wait and then rerun manually). Same per-asset ledger entry; the difference is in how the user-facing summary is phrased.

   Both modes leave the asset itself in `status: done` so a later publish run can pick it up; they do not flip the asset back to `needs-revision`. They do produce a `publish-log.md` entry of `status: blocked-rate-limit` so reruns can detect prior-attempt history.

## Ledger

Append entries to `publish-log.md`. Do not rewrite earlier entries. Pre-existing entries written under earlier idempotency rules remain valid; only new writes follow the current rule.

## Output Contract

Allowed verdicts: `phase-complete`, `awaiting-input`.

All publishable assets published or skipped (no rate-limit deferrals, no blockers):

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Publish ledger updated."
```

Blocked or deferred assets (external gate, expired, rate limit, missing content):

```yaml
signal_verdict:
  verdict: awaiting-input
  target: null
  summary: "Some assets are blocked or rate-limited; see publish-log.md."
```
