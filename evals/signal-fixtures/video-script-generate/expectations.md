---
dispatch_skill: signal-worker
ignore_fields: [updated_at, timestamp, actual_publish_time]
max_attempts: 3
---

# Fixture: video-script-generate

End-to-end happy path for `signal-video` worker generating a scene-by-scene video script. The worker must read brand tone guidelines and produce a script with Voiceover, Visual, Timing, and optional On-screen text per scene.

## Snapshot point

The fixture freezes the system **before signal-worker dispatches signal-video**:

- `task.md` `phase: create`, `scope: quick`, `awaiting: null`
- `A1-video-script.md` `asset_type: video-script`, `channel: none`, `status: todo`
- Context: `brand.md` present with `tone_guidelines` and `video_tone`
- Context: `product.md` present with key features and value props

## Trigger

`signal-worker` reads `A1-video-script.md`, matches `asset_type: video-script` to `signal-video`, and dispatches.

## Expected verdict

The `signal-video` worker generates a scene-by-scene script using brand tone and returns:

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Video script generated."
```

## Expected mutations

- `A1-video-script.md` `status: done`
- `## Content` populated with ordered list of scenes, each including `Voiceover:`, `Visual:`, `Timing:`, optional `On-screen text:`
- `generation_log` populated with one entry: `worker: signal-video`, `worker_version: 1`, `prompt_path`, `prompt_hash`, `content_hash`
- `prompts/A1-r0-signal-video.md` created
