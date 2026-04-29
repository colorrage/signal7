---
name: signal-worker
description: >
  Internal Signal7 dispatcher for one asset file. Routes asset_type to the
  correct content worker and records asset status.
user-invocable: false
---

# signal-worker

Dispatch exactly one asset to the matching worker. Never write `task.md`.

## Inputs

The caller provides the absolute path to one `A<N>-*.md` asset file.

## Routing

Phase 1 supports:

| asset_type | Worker |
|---|---|
| social-copy | signal-social |

All other asset types block with `awaiting-input` until Phase 3 workers exist.

## Steps

1. Read `skills/signal/reference/data-model.md` and `skills/signal/reference/adding-workers.md`.
2. Parse the assigned asset.
3. If `asset_type` is unsupported, set `status: blocked`, write a completion note, and return `awaiting-input`.
4. Set `status: in-progress`.
5. Invoke the matching worker with the asset path. Use host-neutral sub-agent dispatch when available; Claude Code Task tool with `subagent_type: general-purpose` is the known fallback.
6. After worker completion, re-read the asset.
7. If the worker set `status: done`, return `phase-complete`; otherwise return `awaiting-input`.

## Output Contract

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

