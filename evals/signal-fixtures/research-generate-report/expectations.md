---
dispatch_skill: signal-worker
ignore_fields: [updated_at, timestamp, actual_publish_time]
max_attempts: 3
---

# Fixture: research-generate-report

End-to-end happy path for `signal-research` worker producing a structured research artifact. The worker must read competitors.md, past-campaigns.md, and product.md, and produce analysis with Competitor Landscape, Differentiation Opportunities, and Lessons from Past Campaigns.

## Snapshot point

The fixture freezes the system **before signal-worker dispatches signal-research**:

- `task.md` `phase: create`, `scope: strategy`, `awaiting: null`
- `A1-research-report.md` `asset_type: research`, `channel: none`, `status: todo`
- Context: `competitors.md` present with 4 competitors (WidgetFlow, DataSync Pro, CloudOps, Taskly)
- Context: `past-campaigns.md` present with 4 past campaigns
- Context: `product.md` present with key features and value props

## Trigger

`signal-worker` reads `A1-research-report.md`, matches `asset_type: research` to `signal-research`, and dispatches.

## Expected verdict

The `signal-research` worker produces a structured research artifact and returns:

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Research artifact generated."
```

## Expected mutations

- `A1-research-report.md` `status: done`
- `## Content` populated with: `## Summary`, `## Competitor Landscape` (table), `## Differentiation Opportunities` (bullets), `## Lessons from Past Campaigns` (bullets), `## Open Questions` (bullets)
- `generation_log` populated with one entry: `worker: signal-research`, `worker_version: 1`, `prompt_path`, `prompt_hash`, `content_hash`
- `prompts/A1-r0-signal-research.md` created
- Source citations inline referencing `competitors.md` and `past-campaigns.md` entries

## Strategy scope note

This is a strategy-scope task. The fixture ends at `create` — strategy tasks skip review and publish. The research artifact is internal strategy output.

## Reviewer-flagged regressions guarded

- `signal-research` does NOT read `approved-claims.md` — research is internal and not subject to public claims compliance. If the worker reads claims context, that's a regression.
- Competitors must not be invented — every competitor cited must trace to an entry in `competitors.md`.
