---
name: signal-research
description: >
  Internal Signal7 worker that produces structured competitor / market
  research from competitor, past-campaign, and product context for a single
  research asset. Synthesis discipline, not creative generation.
user-invocable: false
worker_version: 1
---

# signal-research

Produce a structured research artifact for one `research` asset. This is a leaf worker; it never writes `task.md`.

`signal-research` owns competitor research in the MVP. A user-facing `signal-competition` shortcut may be added later; today, all competitor research routes here.

## Inputs

The dispatcher must provide the absolute path to one `A<N>-*.md` asset file.

Read:

- assigned asset file
- `.signal/context/competitors.md` (required)
- `.signal/context/past-campaigns.md` (required)
- `.signal/context/product.md` (required)
- task `brief.md`

`signal-research` does not read `approved-claims.md`. Research output is internal and not subject to the public-claims compliance gate; if the research is later turned into public content, the public-asset path runs through `signal-copy` or `signal-social` and that path enforces claims.

## Behavior

1. Parse the asset frontmatter.
2. Confirm `asset_type: research`.
3. Confirm required context files exist; if any are missing, set `status: blocked`, write the reason under `## Completion`, and return `awaiting-input`.
4. Create `prompts/` if missing (lazy `mkdir -p`); store the analysis prompt at `prompts/A<N>-r<revision>-signal-research.md`.
5. Write the analysis under `## Content`. Default structure (adapt to the brief):
   - `## Summary` — one paragraph
   - `## Competitor Landscape` — table of competitors with positioning, pricing, known weaknesses
   - `## Differentiation Opportunities` — bullet list keyed to product strengths
   - `## Lessons from Past Campaigns` — bullet list referencing `past-campaigns.md` entries
   - `## Open Questions` — bullet list of items that competitor data alone cannot answer
6. Cite the source rows used from `competitors.md` and `past-campaigns.md` inline (e.g. `(competitors.md → Acme)`). Do not invent competitors not present in the context file.
7. Append a `generation_log` entry with `timestamp`, `model`, `worker: signal-research`, `worker_version` (read from this file's frontmatter; current value 1), `prompt_path`, `prompt_hash`, and `content_hash`.
8. Set asset `status: done`.

## Strategy Scope Note

For strategy-scope tasks, `signal-research` is dispatched by `signal-create` directly and the task ends at `done` after research is written. Strategy tasks skip review and publish unless the user later promotes the research to public content.

## Output Contract

Allowed verdicts: `phase-complete`, `awaiting-input`.

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Research artifact generated."
```

or

```yaml
signal_verdict:
  verdict: awaiting-input
  target: null
  summary: "Research blocked; see ## Completion."
```
