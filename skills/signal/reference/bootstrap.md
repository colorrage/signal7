# Signal7 Bootstrap

Write-side Signal7 skills ensure `.signal/` exists before writing state. Read-side operations may treat a missing `.signal/` as an empty system.

## Required Shape

```text
.signal/
  tasks/
  archive/
  context/
  recipes/
  backlog.md
  config.yaml
```

Bootstrap copies these templates into `.signal/context/` when missing:

- `skills/signal/templates/context-brand.md` -> `.signal/context/brand.md`
- `skills/signal/templates/context-product.md` -> `.signal/context/product.md`
- `skills/signal/templates/context-approved-claims.md` -> `.signal/context/approved-claims.md`

Phase 2 adds optional templates for:

- competitors.md
- past-campaigns.md

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
review_defaults:
  timeout_hours: 24
  auto_approve_on_timeout: false
external_status:
  mode: manual
  adapters: []
```

`external_status.mode: manual` means humans or external processes update Signal artifacts directly. Signal7 never reads Hyper7 internals. Future adapters must be third-party writers to Signal fields, not Signal components that inspect Hyper7 files.

## backlog.md

Seed with:

```markdown
# Signal7 Backlog
```

## Write-side Skills

These skills bootstrap before writing:

- `signal`
- `signal-task` create/cancel/defer operations
- `signal-backlog` add/promote/drop operations
- `signal-recipe` create/update/delete operations

## Read-side Skills

List/status operations do not bootstrap. Missing state means no Signal7 tasks exist yet.
