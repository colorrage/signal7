# Signal7 Bootstrap

Write-side Signal7 skills ensure `.signal/` exists before writing state. Read-side operations may treat a missing `.signal/` as an empty system.

## Required Shape

```text
.signal/
  tasks/
  archive/         # created lazily on first archive move
  context/
  recipes/         # created lazily by signal-recipe on first create
  team/            # created lazily by signal-team on first delegation; holds raw + verified team artifacts and providers/
  backlog.md       # seeded by bootstrap; managed by signal-backlog
  retro.md         # created lazily by signal-retro on first project-level retro
  config.yaml
```

`archive/` is lazy: bootstrap does not pre-create it. The first archive move (`signal` on phase-driven `done`, or `signal-task cancel`) runs `mkdir -p .signal/archive` itself.

`recipes/` is also lazy: `signal-recipe create` runs `mkdir -p .signal/recipes` on first write.

`backlog.md` is seeded by bootstrap as a passive markdown stub; `signal-backlog` reads/writes `B<N>` blocks inside it.

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

<!-- B<N> entries are managed by signal-backlog. Manual edits are tolerated; signal-backlog re-reads on every operation. -->
```

## Write-side Skills

These skills ensure `.signal/` exists before writing:

- `signal`
- `signal-publish` — writes `publish-log.md` per task; bootstraps any missing top-level shape
- `signal-task` — `cancel`, `defer`, `create-deferred`
- `signal-backlog` — `add`, `promote`, `drop`
- `signal-recipe` — `create`, `update`, `delete`, `run` (creates `.signal/recipes/` lazily)
- `signal-handoff` — writes `handoff.md` into the active task folder
- `signal-retro` — writes per-task `retro.md` or appends to `.signal/retro.md`
- `signal-team` — creates `.signal/team/` lazily for raw teammate output and verified team artifacts

## Read-side Skills

List/status operations do not bootstrap. Missing state means no Signal7 tasks exist yet. `signal-task status`, `signal-task list`, `signal-backlog list`, `signal-backlog show`, and `signal-recipe list`/`show` are read-only and do not bootstrap.
