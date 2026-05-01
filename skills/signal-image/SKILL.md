---
name: signal-image
description: >
  Internal Signal7 worker that generates one text-to-image prompt from an
  assigned asset file using brand visual guidelines.
user-invocable: false
worker_version: 1
---

# signal-image

Generate a text-to-image prompt for a single `image-prompt` asset. This is a leaf worker; it never writes `task.md`. The output is a prompt for an external image model (Midjourney, DALL-E, Canva, etc.), not a binary image.

## Inputs

The dispatcher must provide the absolute path to one `A<N>-*.md` asset file.

Read:

- assigned asset file
- `.signal/context/brand.md` (required)
- task `brief.md`

## Behavior

1. Parse the asset frontmatter.
2. Confirm `asset_type: image-prompt`.
3. Confirm `brand.md` exists; if not, set `status: blocked`, write the reason under `## Completion`, and return `awaiting-input`.
4. Read `visual_guidelines` from `brand.md` (palette, style, composition, do/don't lists). Honour `forbidden_terms[]` if present.
5. Create `prompts/` if missing (lazy `mkdir -p`); store the generation prompt at `prompts/A<N>-r<revision>-signal-image.md`.
6. Write the text-to-image prompt under `## Content`. Include subject, style, composition, palette, mood, aspect ratio, and any negative cues. Do not embed model-specific tag syntax that would not transfer between hosts; if a host-specific variant is requested, add it as a separate "Variant: <host>" block.
7. Set `usage_rights: original` in the asset frontmatter (the generated prompt is original; the eventual image's rights depend on the chosen tool and must be tracked separately at publish time).
8. Append a `generation_log` entry with `timestamp`, `model`, `worker: signal-image`, `worker_version` (read from this file's frontmatter; current value 1), `prompt_path`, `prompt_hash`, and `content_hash`.
9. Set asset `status: done`.

## Output Contract

Allowed verdicts: `phase-complete`, `awaiting-input`.

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Image prompt generated."
```

or

```yaml
signal_verdict:
  verdict: awaiting-input
  target: null
  summary: "Asset blocked; see ## Completion."
```
