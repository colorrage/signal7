---
name: signal-price
description: >
  Internal Signal7 worker that produces market- and jurisdiction-aware
  pricing recommendations for a single pricing asset, using product and
  competitor context.
user-invocable: false
worker_version: 1
---

# signal-price

Produce a structured pricing recommendation for one `pricing` asset. This is a leaf worker; it never writes `task.md`. Output is internal strategy, not public content.

Pricing is an optional Phase 3 worker. It is wired into `signal-worker`'s routing table; route real tasks to it only when the pricing context (`product.md`, `competitors.md`, target `market`/`jurisdiction`) is concrete enough for a defensible recommendation.

## Inputs

The dispatcher must provide the absolute path to one `A<N>-*.md` asset file.

Read:

- assigned asset file
- `.signal/context/product.md` (required)
- `.signal/context/competitors.md` (required)
- task `brief.md`

## Behavior

1. Parse the asset frontmatter.
2. Confirm `asset_type: pricing`.
3. Confirm required context files exist; if any are missing, set `status: blocked`, write the reason under `## Completion`, and return `awaiting-input`.
4. Read `market` and `jurisdiction` from the asset frontmatter. If either is null, surface the ambiguity in the recommendation but do not block — call out which jurisdictions the recommendation explicitly does not cover.
5. Create `prompts/` if missing (lazy `mkdir -p`); store the analysis prompt at `prompts/A<N>-r<revision>-signal-price.md`.
6. Write the recommendation under `## Content`. Default structure:
   - `## Summary` — one paragraph
   - `## Competitor Pricing` — table from `competitors.md` (name, current price, tier shape)
   - `## Recommendation` — proposed tiers, anchor price, rationale
   - `## Jurisdiction Notes` — flag jurisdiction-specific regulatory or tax constraints relevant to the listed market
   - `## Risks` — pricing risks (margin, perception, churn, competitive response)
7. Never assert a public claim about a competitor's pricing that is not present in `competitors.md`.
8. Append a `generation_log` entry with `timestamp`, `model`, `worker: signal-price`, `worker_version` (read from this file's frontmatter; current value 1), `prompt_path`, `prompt_hash`, and `content_hash`.
9. Set asset `status: done`.

## Strategy Scope Note

For strategy-scope tasks, `signal-price` is dispatched by `signal-create` directly and the task ends at `done`. Strategy tasks skip review and publish unless the user later turns the pricing memo into public content (in which case the public path runs through `signal-copy` or `signal-social`, which enforce claims).

## Output Contract

Allowed verdicts: `phase-complete`, `awaiting-input`.

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Pricing recommendation generated."
```

or

```yaml
signal_verdict:
  verdict: awaiting-input
  target: null
  summary: "Pricing blocked; see ## Completion."
```
