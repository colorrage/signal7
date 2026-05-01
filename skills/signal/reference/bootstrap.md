# Signal7 Bootstrap

Write-side Signal7 skills ensure `.signal/` exists before writing state. Read-side operations may treat a missing `.signal/` as an empty system.

## Required Shape

```text
.signal/
  tasks/
  archive/         # created lazily on first archive move
  context/
  recipes/         # Phase 2 — planned
  backlog.md       # Phase 2 — planned (signal-backlog)
  config.yaml
```

`archive/` is lazy: bootstrap does not pre-create it. The first archive move (`signal` on phase-driven `done`, or `signal-task` on cancel) runs `mkdir -p .signal/archive` itself.

`recipes/` and `backlog.md` are owned by planned management skills (`signal-recipe`, `signal-backlog`). Until those skills ship, bootstrap creates `backlog.md` as a passive seed file but does not create `recipes/`.

Bootstrap copies these templates into `.signal/context/` when missing:

- `skills/signal/templates/context-brand.md` -> `.signal/context/brand.md`
- `skills/signal/templates/context-product.md` -> `.signal/context/product.md`
- `skills/signal/templates/context-approved-claims.md` -> `.signal/context/approved-claims.md`
- `skills/signal/templates/context-competitors.md` -> `.signal/context/competitors.md`
- `skills/signal/templates/context-past-campaigns.md` -> `.signal/context/past-campaigns.md`

## config.yaml

```yaml
schema_version: 1
default_language: en
enabled_channels:
  - linkedin
  - instagram
  - twitter
  - facebook
  - email
  - blog
  - web
publish_rate_limits:
  global_per_hour: 10
  per_channel_per_hour: 3
  on_limit: defer    # defer | block
review_defaults:
  timeout_hours: 24
  auto_approve_on_timeout: false
external_status:
  mode: manual
  adapters: []
```

`enabled_channels` lists the channels Signal7 can produce content for today. With the Phase 3 worker surface shipped (`signal-copy`, `signal-image`, `signal-video`, `signal-translate`, `signal-research`, `signal-price`), the seeded list covers social, email, blog, and web. Projects can prune entries they do not use.

`publish_rate_limits.on_limit: defer` means `signal-publish` records a `blocked-rate-limit` entry and surfaces `awaiting-input` to the user; the user can rerun later (manual defer). `on_limit: block` means the publish phase fails the asset outright. `defer` is the default and recommended behaviour. See `signal-publish` for the precise rule.

`external_status.mode: manual` means humans or external processes update Signal artifacts directly. Signal7 never reads Hyper7 internals. Future adapters must be third-party writers to Signal fields, not Signal components that inspect Hyper7 files.

## backlog.md

Seed with:

```markdown
# Signal7 Backlog

<!-- Ideas live here once `signal-backlog` ships in Phase 2. Manual entries are tolerated until then. -->
```

## Write-side Skills

These skills ensure `.signal/` exists before writing:

- `signal`
- `signal-publish` — writes `publish-log.md` per task; bootstraps any missing top-level shape
- `signal-task` — `cancel` (Phase 1) and, when shipped, `defer` and `create-deferred`
- Planned management skills: `signal-backlog` add/promote/drop, `signal-recipe` create/update/delete

## Read-side Skills

List/status operations do not bootstrap. Missing state means no Signal7 tasks exist yet. `signal-task status` is read-only and does not bootstrap.
