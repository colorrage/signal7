---
dispatch_skill: signal-worker
ignore_fields: [updated_at, timestamp, actual_publish_time]
max_attempts: 3
---

# Fixture: translate-source-review-gate

Guards the source review gate in signal-translate. A1 is a done, AI-reviewed LinkedIn post in English. A2 is a French translation with `source_asset: A1`. The worker must pass the source review gate (checking `review_ai_pass: true` on A1) before proceeding with translation.

## Snapshot point

The fixture freezes the system **before signal-worker dispatches signal-translate for A2**:

- `task.md` `phase: create`, `scope: campaign`, `awaiting: null`
- `A1-linkedin-en.md` `status: done`, `review_ai_pass: true`, `## Content` populated
- `A2-linkedin-fr.md` `asset_type: translation`, `status: todo`, `source_asset: A1`, `language: fr`
- `compliance.md` `status: clear`
- Context: `brand.md` present for forbidden-terms and brand terminology

## Trigger

`signal-worker` reads `A2-linkedin-fr.md`, matches `asset_type: translation` to `signal-translate`, and dispatches. signal-translate first checks the source review gate: reads A1, confirms `review_ai_pass: true` and `status: done`. Gate passes.

## Expected verdict

The `signal-translate` worker translates A1's content into French and returns:

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Translation generated."
```

## Expected mutations

- `A2-linkedin-fr.md` `status: done`
- `## Content` populated with French translation preserving brand terms
- `generation_log` populated with one entry: `worker: signal-translate`, `worker_version: 1`, `prompt_path`, `prompt_hash`, `content_hash`, `source_asset: A1`, `source_language: en`
- `prompts/A2-r0-signal-translate.md` created
- A1 unchanged (source asset is read-only for translation)

## Reviewer-flagged regressions guarded

- The source review gate must be the first check before any context reading — if the gate were skipped or checked late, a stale A1 could be translated.
- If `review_ai_pass` were false or A1 status were not `done`, the worker must block with `awaiting-input` and the reason "source asset not yet AI-reviewed" or "source asset not yet generated".
