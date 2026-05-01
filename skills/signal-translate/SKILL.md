---
name: signal-translate
description: >
  Internal Signal7 worker that translates one asset's content into the asset's
  target language, after the source asset has passed AI review.
user-invocable: false
worker_version: 1
---

# signal-translate

Translate the content of one source asset into a target language for a single `translation` asset. This is a leaf worker; it never writes `task.md`.

## Source Review Gate (first)

Translation must not run on unreviewed source content.

1. Read the assigned asset's `source_asset` field. It must be a same-task asset id (e.g. `A1`).
2. Read the source asset file.
3. If `source_asset` is null, missing, or refers to a non-existent file: set `status: blocked` with the reason under `## Completion` and return `awaiting-input`.
4. If the source asset's `review_ai_pass` is not `true`, or the source asset's `status` is not `done`: set `status: blocked` with reason `"source asset not yet AI-reviewed"` (or `"source asset not yet generated"`, as appropriate) under `## Completion` and return `awaiting-input`.

The gate is the first check, before reading any context.

## Inputs

The dispatcher must provide the absolute path to one `A<N>-*.md` asset file.

Read (after the gate passes):

- assigned asset file
- source asset file (path resolved from `source_asset`)
- `.signal/context/brand.md` (optional but preferred — used for forbidden-terms and brand terminology)
- task `brief.md`

## Behavior

1. Pass the source review gate (above).
2. Parse the assigned asset frontmatter.
3. Confirm `asset_type: translation`.
4. Read the target language from the assigned asset's `language` field.
5. Read the source's `## Content` section.
6. Honour `brand.md` `forbidden_terms[]`: never translate forbidden terms; preserve them verbatim. Preserve brand and product names per `brand.md` and `product.md` if those define translation policy; otherwise default to keeping proper nouns untranslated.
7. Create `prompts/` if missing (lazy `mkdir -p`); store the translation prompt at `prompts/A<N>-r<revision>-signal-translate.md`.
8. Write the translated content under `## Content`, mirroring the source's section structure where present (e.g. an email's subject/preheader/body/CTA blocks).
9. Append a `generation_log` entry with `timestamp`, `model`, `worker: signal-translate`, `worker_version` (read from this file's frontmatter; current value 1), `prompt_path`, `prompt_hash`, and `content_hash`. Also record `source_asset` and `source_language` inside the entry for replay.
10. Set asset `status: done`.

## Output Contract

Allowed verdicts: `phase-complete`, `awaiting-input`.

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Translation generated."
```

or

```yaml
signal_verdict:
  verdict: awaiting-input
  target: null
  summary: "Translation blocked; see ## Completion."
```
