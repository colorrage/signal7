# Adding Signal7 Workers

Workers generate or transform one assigned asset. They never mutate `task.md`.

## Worker Skeleton

A worker:

1. Reads the assigned `A<N>-*.md` file.
2. Reads only required context files.
3. Stores the prompt under `prompts/A<N>-r<revision>-<worker>.md`. Creates `prompts/` lazily if missing.
4. Writes output into the asset body or structured sections.
5. Appends one `generation_log` entry, including `worker_version` from its own `SKILL.md` frontmatter.
6. Sets asset `status: done` or `status: blocked`.

Workers must declare `worker_version: <int>` in their `SKILL.md` frontmatter and increment it when generation behaviour changes.

## Dependency Resolution

`signal-create` schedules workers. An asset is eligible when:

- `status: todo` or `status: needs-revision`
- every id in `depends[]` points to a same-task asset with `status: done`
- for translation assets, `source_asset` has `review_ai_pass: true` *and* `status: done`
- no dependency is `blocked`, `needs-revision`, or `cancelled`

Eligible assets with disjoint `writes[]` may run in parallel.

When `status: needs-revision` is picked up after a `review -> create` redirect, `signal-create` increments `revision` on the asset before dispatching, so the next prompt is stored under a fresh `prompts/A<N>-r<revision>-<worker>.md`.

## Context Relevance Matrix

| Context File | social | copy | image | video | translate | research | price | review |
|---|---|---|---|---|---|---|---|---|
| brand.md | required | required | required | required | optional | - | - | required |
| product.md | required | required | - | optional | - | required | required | - |
| competitors.md | - | - | - | - | - | required | required | - |
| past-campaigns.md | optional | optional | - | - | - | required | - | - |
| approved-claims.md | required | required | - | - | - | - | - | required |

## Asset Type Routing

| asset_type | Worker | Status |
|---|---|---|
| social-copy | signal-social | **Phase 1 — implemented** |
| email-copy | signal-copy | Phase 3 — planned |
| blog | signal-copy | Phase 3 — planned |
| landing-page | signal-copy | Phase 3 — planned |
| copy | signal-copy | Phase 3 — planned |
| translation | signal-translate | Phase 3 — planned |
| image-prompt | signal-image | Phase 3 — planned |
| video-script | signal-video | Phase 3 — planned |
| research | signal-research | Phase 3 — planned |
| pricing | signal-price | Phase 3 — planned |

`signal-worker` returns `awaiting-input` for any planned-but-not-implemented `asset_type` with a clear "worker `signal-X` is planned for Phase N and is not yet implemented" message. The asset is set to `status: blocked` with the same reason in its `## Completion` section.

Unknown asset types (not in the table at all) also block with `awaiting-input`.
