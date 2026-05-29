---
dispatch_skill: signal-worker
ignore_fields: [updated_at, timestamp, actual_publish_time]
max_attempts: 3
---

# Fixture: copy-generate-blog

End-to-end happy path for `signal-copy` worker generating a blog asset. The worker must read brand.md, product.md, and approved-claims.md context, and produce a long-form blog post.

## Snapshot point

The fixture freezes the system **before signal-worker dispatches signal-copy**:

- `task.md` `phase: create`, `scope: quick`, `awaiting: null`
- `A1-blog-en.md` `asset_type: blog`, `channel: blog`, `status: todo`, `generation_log: []`
- `compliance.md` `status: clear`
- Context: `brand.md` (with blog tone), `product.md`, `approved-claims.md` present

## Trigger

`signal-worker` reads `A1-blog-en.md`, matches `asset_type: blog` to `signal-copy`, and dispatches.

## Expected verdict

The `signal-copy` worker generates blog content using approved claims and returns:

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Long-form copy asset generated."
```

## Expected mutations

- `A1-blog-en.md` `status: done`
- `## Content` populated with blog structure (title, intro, sections with H2/H3, conclusion)
- `generation_log` populated with one entry: `worker: signal-copy`, `worker_version: 1`, `prompt_path`, `prompt_hash`, `content_hash` present
- `prompts/A1-r0-signal-copy.md` created
