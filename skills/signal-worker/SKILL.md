---
name: signal-worker
description: >
  Internal Signal7 dispatcher for one asset file. Routes asset_type to the
  correct content worker and records asset status.
user-invocable: false
worker_version: 1
---

# signal-worker

Dispatch exactly one asset to the matching worker. Never write `task.md`.

## Inputs

The caller provides the absolute path to one `A<N>-*.md` asset file.

## Routing

| asset_type | Worker | Status |
|---|---|---|
| social-copy | signal-social | **Phase 1 — implemented** |
| email-copy | signal-copy | Phase 2 — planned |
| blog | signal-copy | Phase 2 — planned |
| landing-page | signal-copy | Phase 2 — planned |
| copy | signal-copy | Phase 2 — planned |
| translation | signal-translate | Phase 3 — planned |
| image-prompt | signal-image | Phase 3 — planned |
| video-script | signal-video | Phase 3 — planned |
| research | signal-research | Phase 3 — planned |
| pricing | signal-price | Phase 3 — planned |

For any planned-but-not-implemented `asset_type`, set `status: blocked`, write the reason in `## Completion` (e.g. `"Worker signal-copy is planned for Phase 2 and is not yet implemented."`), and return `awaiting-input`. Do not coerce the asset to `social-copy`.

For unknown asset types (not in the table at all), set `status: blocked` with `"Unknown asset_type: <value>"` and return `awaiting-input`.

## Steps

1. Read `skills/signal/reference/data-model.md`, `skills/signal/reference/adding-workers.md`, and `skills/signal/reference/portability.md`.
2. Parse the assigned asset.
3. If `asset_type` is unsupported (planned-but-not-implemented or unknown), set `status: blocked`, write the completion note, and return `awaiting-input`.
4. Set `status: in-progress`.
5. Invoke the matching worker with the asset path. Dispatch mode follows `portability.md` § Dispatch Contract: inline or sub-agent depending on the parent's batching strategy.
6. After worker completion, re-read the asset.
7. If the worker set `status: done`, return `phase-complete`; otherwise return `awaiting-input`.

## Output Contract

Allowed verdicts: `phase-complete`, `awaiting-input`.

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Asset worker completed."
```

or

```yaml
signal_verdict:
  verdict: awaiting-input
  target: null
  summary: "Asset is blocked; see the asset file for details."
```
