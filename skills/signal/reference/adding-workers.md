# Adding Signal7 Workers

Workers generate or transform one assigned asset. They never mutate `task.md`.

## Worker Skeleton

A worker:

1. Reads the assigned `A<N>-*.md` file.
2. Reads only required context files.
3. Stores the prompt under `prompts/A<N>-r<revision>-<worker>.md`.
4. Writes output into the asset body or structured sections.
5. Appends one `generation_log` entry.
6. Sets asset `status: done` or `status: blocked`.

## Dependency Resolution

`signal-create` schedules workers. An asset is eligible when:

- `status: todo`
- every id in `depends[]` points to a same-task asset with `status: done`
- for translation assets, `source_asset` has `review_ai_pass: true`
- no dependency is `blocked` or `cancelled`

Eligible assets with disjoint `writes[]` may run in parallel.

## Context Relevance Matrix

| Context File | social | copy | image | video | translate | research | price | review |
|---|---|---|---|---|---|---|---|---|
| brand.md | required | required | required | required | optional | - | - | required |
| product.md | required | required | - | optional | - | required | required | - |
| competitors.md | - | - | - | - | - | required | required | - |
| past-campaigns.md | optional | optional | - | - | - | required | - | - |
| approved-claims.md | required | required | - | - | - | - | - | required |

## Asset Type Routing

| asset_type | Worker |
|---|---|
| social-copy | signal-social |
| email-copy | signal-copy |
| blog | signal-copy |
| landing-page | signal-copy |
| copy | signal-copy |
| image-prompt | signal-image |
| video-script | signal-video |
| translation | signal-translate |
| research | signal-research |
| pricing | signal-price |

Unknown asset types block with `awaiting-input`.
