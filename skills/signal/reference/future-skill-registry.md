# Future Skill Registry

This registry prevents future domain work from being forgotten while keeping v1 focused.

## Placeholder Convention

Placeholder `SKILL.md` files may exist only when:

- the folder is named in this registry
- frontmatter includes `user-invocable: false`
- the body clearly says `TODO` and a one-line description of why no active workflow routes there yet
- no active workflow routes to the placeholder

Do not create a Phase 0 placeholder for `signal-price`. Phase 3 either implements it or creates the TODO placeholder if pricing is deferred.

## Phase 2 — Implemented

| Skill | Purpose | Notes |
|---|---|---|
| signal-plan | Generate `content-plan.md` for campaign scope | Unblocks the `campaign` flow |
| signal-plan-review | Adversarial review of `content-plan.md` in a fresh sub-agent context | Independence from plan author is the design guarantee |

## Planned Management Skills

| Skill | Purpose | Notes |
|---|---|---|
| signal-backlog | Capture and triage ideas (`B<N>`) before promoting to tasks | Bootstrap-side skill |
| signal-recipe | Save and replay procedural playbooks | Bootstrap-side skill |
| signal-handoff | Write a session handoff doc for an in-flight task | User-triggered |
| signal-retro | Reflect on a finished task or session | User-triggered |
| signal-team | Dispatch a second AI for an independent review | User-triggered |
| signal-task (full) | List, defer, create-deferred operations | Phase 1 ships only `cancel` and `status` |

## Phase 3 — Implemented

| Skill | Purpose | Notes |
|---|---|---|
| signal-copy | Long-form copy worker: email, blog, landing page, generic copy | Claims pre-check against `approved-claims.md` and `compliance.md` |
| signal-image | Image-prompt worker for Midjourney / DALL-E / Canva | Distinct from image editing; sets `usage_rights: original` |
| signal-video | Video-script worker for RunwayML / HeyGen / Sora | Outputs scenes with voiceover, visual direction, timing |
| signal-translate | Translation worker for the `translation` asset type | Requires `source_asset.review_ai_pass: true` and `status: done` to dispatch |
| signal-research | Competitor analysis, SWOT, market research worker | Strategy-scope output; never reads `approved-claims.md` |
| signal-price | Pricing recommendations worker | Optional Phase 3 worker; market- and jurisdiction-aware |

## Future workers

| Skill | Purpose | Notes |
|---|---|---|
| signal-image-edit | Image modification prompts and asset records | Separate from text-to-image generation |
| signal-competition | User-facing shortcut for competitor research | MVP uses `signal-research` |
| signal-sales | Sales outreach emails, call scripts, sequences | Future worker or user-facing shortcut |
| signal-labeling | Product labeling, package copy, required label translations | Compliance-heavy schema |

## Phase 4+ — Beyond workers

| Capability | Notes |
|---|---|
| Channel adapters | Real publish to LinkedIn / Meta / email tools / CMS / ad platforms — today's `publish-log.md` is on-disk only |
| Performance feedback loop | Ingest engagement / conversion metrics back into the system |
| Multi-stakeholder approval | Multiple approvers, parallel/serial routing, delegation, rejected-reason taxonomy |
| signal-recurring | Scheduled repeated workflows ("3 LinkedIn posts every Monday") — likely recipe/automation based |

## Recurring Definition

Recurring means repeated scheduled business workflows. It is not part of the initial phase graph and will arrive as a recipe/automation pattern, not a scope.
