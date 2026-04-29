---
name: signal-social
description: >
  Internal Signal7 worker that generates one social-copy asset from an assigned
  asset file using brand and product context.
user-invocable: false
---

# signal-social

Generate copy for a single social asset. This is a leaf worker; it never writes `task.md`.

## Inputs

The dispatcher must provide the absolute path to one `A<N>-*.md` asset file.

Read:

- assigned asset file
- `.signal/context/brand.md`
- `.signal/context/product.md`
- `.signal/context/approved-claims.md` when claims are present
- task `brief.md`
- task `compliance.md`

## Behavior

1. Parse the asset frontmatter.
2. Confirm `asset_type: social-copy`.
3. Confirm required context files exist.
4. Read channel tone from `brand.md` frontmatter `tone_guidelines`.
5. Respect channel limits and tone for `linkedin`, `instagram`, `twitter`, or `facebook`.
6. Store the generation prompt at `prompts/A<N>-r<revision>-signal-social.md`.
7. Write generated copy under `## Content`.
8. Append a `generation_log` entry with timestamp, model, worker, worker_version, prompt_path, prompt_hash, and content_hash.
9. Set asset `status: done`.

If required context is missing or the asset type is unsupported, set `status: blocked`, write the reason under `## Completion`, and return `awaiting-input` to the dispatcher.

## Output Contract

Return one final verdict block:

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Social asset generated."
```

