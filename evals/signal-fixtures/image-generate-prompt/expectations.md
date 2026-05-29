---
dispatch_skill: signal-worker
ignore_fields: [updated_at, timestamp, actual_publish_time]
max_attempts: 3
---

# Fixture: image-generate-prompt

End-to-end happy path for `signal-image` worker generating a text-to-image prompt. The worker must read visual_guidelines from brand.md and produce a prompt with subject, style, composition, palette, mood, and aspect ratio.

## Snapshot point

The fixture freezes the system **before signal-worker dispatches signal-image**:

- `task.md` `phase: create`, `scope: quick`, `awaiting: null`
- `A1-image-prompt.md` `asset_type: image-prompt`, `channel: none`, `status: todo`, `usage_rights: null`
- Context: `brand.md` present with `visual_guidelines` (palette, style, composition, do/don't lists)

## Trigger

`signal-worker` reads `A1-image-prompt.md`, matches `asset_type: image-prompt` to `signal-image`, and dispatches.

## Expected verdict

The `signal-image` worker generates an image prompt using brand visual guidelines and returns:

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Image prompt generated."
```

## Expected mutations

- `A1-image-prompt.md` `status: done`
- `usage_rights: original` (the prompt is original; eventual image rights depend on chosen tool)
- `## Content` populated with text-to-image prompt (subject, style, composition, palette, mood, aspect ratio)
- `generation_log` populated with one entry: `worker: signal-image`, `worker_version: 1`, `prompt_path`, `prompt_hash`, `content_hash`
- `prompts/A1-r0-signal-image.md` created

## Reviewer-flagged regressions guarded

- `usage_rights` must be set to `original` by the worker, not left null or set to a wrong value.
- The prompt must not embed model-specific tag syntax that wouldn't transfer between hosts.
