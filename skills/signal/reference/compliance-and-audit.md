# Compliance and Audit

Signal7 handles public business content, so compliance and audit trails are first-class contracts.

## compliance.md Ownership

`signal-brief` creates `compliance.md` for every task.

The template default (`regulated_domain: false`, `status: clear`) is the safe non-regulated baseline. `signal-brief` raises it explicitly during triage:

- Non-regulated tasks: leave the template defaults (`regulated_domain: false`, `status: clear`). No further work needed.
- Regulated or uncertain tasks: set `regulated_domain: true` (or `unknown`), record questions, and raise to `status: questions-open` until resolved.
- Blocked tasks: set `status: blocked` and explain why.

The default flipped from `questions-open` to `clear` because the previous default produced silent halts in `signal-review` whenever `signal-brief` forgot to write a non-regulated answer. `signal-brief` is now responsible for explicitly *raising* the status when triage detects regulated content; absence of a raise means non-regulated.

`signal-review` reads `compliance.md` before approving public assets. It must not approve when compliance status is `questions-open` or `blocked`.

## Regulated Domain Detection

`signal-brief` should ask directly when the request may involve healthcare, finance, legal, children's products, privacy-sensitive data, regulated advertising, claims requiring evidence, or jurisdiction-specific restrictions.

Keyword detection can help, but a direct answer in `compliance.md` is the durable source.

## Live vs Snapshot Context

- `approved-claims.md`: live. Review reads latest claims because retractions must propagate.
- `brand.md`: snapshot-pinned by `brand_version`, with drift warnings allowed.
- `product.md`: snapshot-pinned by version, with drift warnings allowed.
- `competitors.md` and `past-campaigns.md`: live enough for research; public claims still require `approved-claims.md`.

## Prompt Storage

Every worker stores prompts under the task root:

```text
prompts/A<N>-r<revision>-<worker>.md
```

`prompts/` is created lazily by the first worker that writes into it. No phase skill, orchestrator, or bootstrap step pre-creates the directory — workers `mkdir -p prompts` (or the host equivalent) before writing.

The asset `generation_log` stores both:

```yaml
prompt_path: prompts/A1-r0-signal-social.md
prompt_hash: sha256:<hex>
```

The hash proves integrity of the stored prompt. It is not traceability by itself.

## Worker Version

`generation_log` entries record `worker_version: <int>`. The value comes from a `worker_version: <int>` field in the worker's own `SKILL.md` frontmatter. Workers must increment this field when their generation behaviour changes in a way that downstream review or replay should distinguish.

If a worker omits `worker_version` from its frontmatter, treat it as `worker_version: 1` for log purposes.

## Generation Log

`generation_log` is append-only by convention. Agents must append entries rather than rewriting history. If audit-grade immutability becomes required later, move logs to separate append-only files.
