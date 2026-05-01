---
name: signal-video
description: >
  Internal Signal7 worker that generates one scene-by-scene video script
  from an assigned asset file using brand tone and (optionally) product
  context.
user-invocable: false
worker_version: 1
---

# signal-video

Generate a scene-by-scene video script for a single `video-script` asset. This is a leaf worker; it never writes `task.md`. The output is a script and storyboard prompt set for an external video tool (RunwayML, HeyGen, Sora, etc.).

## Inputs

The dispatcher must provide the absolute path to one `A<N>-*.md` asset file.

Read:

- assigned asset file
- `.signal/context/brand.md` (required)
- `.signal/context/product.md` (optional, when product is referenced)
- task `brief.md`

## Behavior

1. Parse the asset frontmatter.
2. Confirm `asset_type: video-script`.
3. Confirm `brand.md` exists; if not, set `status: blocked`, write the reason under `## Completion`, and return `awaiting-input`.
4. Read brand tone from `brand.md` (`tone_guidelines`). Honour `forbidden_terms[]` if present.
5. Create `prompts/` if missing (lazy `mkdir -p`); store the generation prompt at `prompts/A<N>-r<revision>-signal-video.md`.
6. Write the script under `## Content` as an ordered list of scenes. Each scene includes:
   - `Scene <N>` header
   - `Voiceover:` line(s)
   - `Visual:` shot/composition direction
   - `Timing:` duration cue (e.g. `0:00–0:05`)
   - optional `On-screen text:` line
7. Append a `generation_log` entry with `timestamp`, `model`, `worker: signal-video`, `worker_version` (read from this file's frontmatter; current value 1), `prompt_path`, `prompt_hash`, and `content_hash`.
8. Set asset `status: done`.

## Output Contract

Allowed verdicts: `phase-complete`, `awaiting-input`.

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Video script generated."
```

or

```yaml
signal_verdict:
  verdict: awaiting-input
  target: null
  summary: "Asset blocked; see ## Completion."
```
