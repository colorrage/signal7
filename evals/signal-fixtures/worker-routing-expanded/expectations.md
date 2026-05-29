---
dispatch_skill: signal-worker
ignore_fields: [updated_at, timestamp, actual_publish_time]
max_attempts: 3
---

# Fixture: worker-routing-expanded

Static structural snapshot testing that `signal-worker`'s routing table covers all 10 implemented asset types. This fixture contains one asset stub per asset type to exercise every routing entry. Not a replay fixture — structural check only.

## Snapshot point

The fixture freezes the system **before signal-worker dispatch**:

- `task.md` `phase: create`, `scope: quick`, `awaiting: null`
- 10 asset stubs, one per implemented `asset_type`, all `status: todo`:
  - A1: `social-copy` → `signal-social`
  - A2: `email-copy` → `signal-copy`
  - A3: `blog` → `signal-copy`
  - A4: `landing-page` → `signal-copy`
  - A5: `copy` → `signal-copy`
  - A6: `translation` → `signal-translate`
  - A7: `image-prompt` → `signal-image`
  - A8: `video-script` → `signal-video`
  - A9: `research` → `signal-research`
  - A10: `pricing` → `signal-price`

## Trigger

`signal-worker` reads any asset and routes it to the matching worker per the routing table.

## Expected verdict

`signal-worker` dispatches to the correct worker and returns one of its allowed verdicts:

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Asset worker completed."
```

## Routing table requirements

Every `asset_type` listed in the Snapshot must have exactly one entry in `skills/signal-worker/SKILL.md` § Routing. The worker names must match the Phase status ("implemented") and the corresponding skill folder must exist under `skills/`.

## Expected mutations

Structural fixture only. No mutations are expected during the smoke-runner structural check. All 10 asset stubs remain at `status: todo`.

## Reviewer-flagged regressions guarded

- If a new `asset_type` is added to the data model, this fixture must be updated with a corresponding stub.
- If a worker is removed without updating the routing table, the fixture still declaratively documents the expected routing.
