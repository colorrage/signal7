---
name: signal-copy
description: >
  Internal Signal7 worker that generates one long-form copy asset (email,
  blog, landing page, or generic copy) from an assigned asset file using
  brand, product, and approved-claims context.
user-invocable: false
worker_version: 1
---

# signal-copy

Generate copy for a single long-form asset. This is a leaf worker; it never writes `task.md`.

Long-form means email body, blog post, landing page, or any other `asset_type` in `{email-copy, blog, landing-page, copy}`. Channel character limits do not apply; structure, claims compliance, and brand voice do.

## Inputs

The dispatcher must provide the absolute path to one `A<N>-*.md` asset file.

Read:

- assigned asset file
- `.signal/context/brand.md` (required)
- `.signal/context/product.md` (required)
- `.signal/context/approved-claims.md` (required)
- `.signal/context/past-campaigns.md` (optional, when present)
- task `brief.md`
- task `compliance.md`

## Behavior

1. Parse the asset frontmatter.
2. Confirm `asset_type` is one of `email-copy`, `blog`, `landing-page`, or `copy`.
3. Confirm required context files exist; if any are missing, set `status: blocked`, write the reason under `## Completion`, and return `awaiting-input`.
4. Claims pre-check: every factual claim in the generated copy must trace to an entry in `approved-claims.md` whose `expires_at` is null or in the future. If `compliance.md` has `status: questions-open` or `status: blocked`, set `status: blocked`, name the unresolved compliance question under `## Completion`, and return `awaiting-input`.
5. Read brand voice and tone from `brand.md` (channel tone may apply for `email-copy`; otherwise default tone). Honour `forbidden_terms[]`.
6. Create `prompts/` if missing (lazy `mkdir -p`); store the generation prompt at `prompts/A<N>-r<revision>-signal-copy.md`.
7. Write the generated copy under `## Content`. Use the structure appropriate to the `asset_type`:
   - `email-copy`: subject line, preheader, body, CTA.
   - `blog`: title, intro, sections with H2/H3, conclusion.
   - `landing-page`: headline, subhead, body sections, primary CTA.
   - `copy`: a single titled body block.
8. Append a `generation_log` entry with `timestamp`, `model`, `worker: signal-copy`, `worker_version` (read from this file's frontmatter; current value 1), `prompt_path`, `prompt_hash`, and `content_hash`.
9. Set asset `status: done`.

If a claim in the draft cannot be matched to `approved-claims.md`, either remove the claim or set `status: blocked` with the unsupported claim quoted under `## Completion`.

## Output Contract

Allowed verdicts: `phase-complete`, `awaiting-input`.

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Long-form copy asset generated."
```

or

```yaml
signal_verdict:
  verdict: awaiting-input
  target: null
  summary: "Asset blocked; see ## Completion."
```
