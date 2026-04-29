# Compliance and Audit

Signal7 handles public business content, so compliance and audit trails are first-class contracts.

## compliance.md Ownership

`signal-brief` creates `compliance.md` for every task.

- Non-regulated tasks: set `regulated_domain: false` and `status: clear`.
- Regulated or uncertain tasks: set `regulated_domain: true` or `unknown`, record questions, and keep `status: questions-open` until resolved.
- Blocked tasks: set `status: blocked` and explain why.

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

The asset `generation_log` stores both:

```yaml
prompt_path: prompts/A1-r0-signal-social.md
prompt_hash: sha256:<hex>
```

The hash proves integrity of the stored prompt. It is not traceability by itself.

## Generation Log

`generation_log` is append-only by convention. Agents must append entries rather than rewriting history. If audit-grade immutability becomes required later, move logs to separate append-only files.
