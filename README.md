# Signal7

Signal7 is an AI-agent workflow system for business operations — the GTM/marketing companion to [Hyper7](../hyper7).

Hyper7 handles IT, code, and SEO. Signal7 handles the business side of launching a product to market:

- Social media (copy, scheduling, image creation)
- Marketing campaigns (multi-channel, multi-asset)
- Content writing (blog, email, landing page, product descriptions)
- Sales outreach (email sequences, call scripts)
- Product pricing strategy (competitive benchmarking, margin analysis)
- Competition research (SWOT, market positioning, feature comparison)
- Product labeling and translation (regulatory-compliant, multi-language)
- Product image generation and modification (prompts for Midjourney, DALL-E, Canva)
- Video generation (scripts, storyboards, prompts for RunwayML, HeyGen)

## Architecture

Signal7 mirrors Hyper7's architecture: disk-based state (`.signal/` folder), gate/verdict phase system, parallel worker dispatch. It uses business-domain vocabulary instead of dev-domain vocabulary.

| Concept | Hyper7 | Signal7 |
|---------|--------|---------|
| State folder | `.hyper/` | `.signal/` |
| Orchestrator | `hyper` | `signal` |
| Phase 1 | `discover` | `brief` |
| Phase 2 | `plan` | `plan` |
| Phase 3 | `implement` | `create` |
| Phase 4 | `verify` | `review` |
| Phase 5 | `docs` | `publish` |
| Work units | subtask files (`T<N>.<M>-*.md`) | asset files (`A<N>-*.md`) |
| Plan artifact | `spec.md` | `content-plan.md` |
| Brief artifact | `exploration.md` | `brief.md` |
| Verify artifact | `checks.md` | `review.md` |
| Brand source | `.hyper/rules.md` | `.signal/brand.md` |

## Scopes

| Scope | Flow | Use case |
|-------|------|----------|
| `quick` | brief → create → review → publish → done | Single content piece |
| `campaign` | brief → plan → plan-review → create → review → publish → done | Multi-channel campaign |
| `strategy` | brief → create → done | Research + recommendation only |
| `recurring` | future recipe/automation | Weekly/monthly ongoing content |

## Skills

**Orchestrator:** `signal`

**User-facing:** `signal-task`, `signal-backlog`, `signal-recipe`, `signal-handoff`, `signal-retro`, `signal-team`

**Phase skills (internal):** `signal-brief`, `signal-plan`, `signal-create`, `signal-review`, `signal-publish`, `signal-worker`

**Content workers (internal):** `signal-copy`, `signal-social`, `signal-image`, `signal-video`, `signal-translate`, `signal-research`, `signal-price`

## Status

Phase 0 foundation contracts and Phase 1 quick-scope MVP skill contracts are implemented. Campaign planning, additional content workers, management skills, and `signal-team` remain planned.
