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

> **The bullets above are the eventual scope.** What is implemented today is a much narrower slice — see [Status](#status) below.

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
| Brand source | `.hyper/rules.md` | `.signal/context/brand.md` |

## Scopes

| Scope | Flow | Use case | Status |
|-------|------|----------|--------|
| `quick` | brief → create → review → publish → done | Single content piece | **Implemented (Phase 1)** |
| `campaign` | brief → plan → plan-review → create → review → publish → done | Multi-channel campaign | **Implemented (Phase 2 planning + review)** |
| `strategy` | brief → create → done | Research + recommendation only | **Implemented (Phase 3)** — `signal-research` and `signal-price` workers ship; review/publish are skipped unless output becomes public content |

`recurring` work (e.g. "every Monday, 3 LinkedIn posts") is out of v1; it will arrive as a recipe/automation, not as a scope.

## Status

**What is implemented today (Phase 0-4):**

- The `quick` scope, end-to-end: brief → create → review → publish → done
- The `campaign` planning path: brief → plan → plan-review → create → review → publish → done
- The `strategy` scope: brief → create → done, dispatching `signal-research` or `signal-price`
- All content workers wired up:
  - `signal-social` — LinkedIn, Instagram, X/Twitter, Facebook copy (Phase 1)
  - `signal-copy` — email, blog, landing page, generic long-form copy (Phase 3)
  - `signal-image` — text-to-image prompts for Midjourney / DALL-E / Canva (Phase 3)
  - `signal-video` — scene-by-scene video scripts for RunwayML / HeyGen / Sora (Phase 3)
  - `signal-translate` — locale translation gated on source `review_ai_pass: true` (Phase 3)
  - `signal-research` — competitor / market analysis from `competitors.md` + `past-campaigns.md` + `product.md` (Phase 3)
  - `signal-price` — market- and jurisdiction-aware pricing recommendations from `product.md` + `competitors.md` (Phase 3)
- Campaign planning skills: `signal-plan` and isolated-context `signal-plan-review`
- The `signal` orchestrator, the foundation reference docs, and the asset/brief/compliance schemas
- Disk-based state (`.signal/`), idempotent publish ledger, AI review rubric, prompt storage with hashes
- A three-layer QA testing system: Layer 1 static checks (`scripts/run-signal-fixtures.sh`), Layer 2 live-skill replay (`scripts/replay-fixture.sh` / `scripts/run-all-fixtures.sh`), and Layer 3 manual pre-ship checklist (`evals/qa-checklist.md`). See `evals/signal-fixtures/README.md` for the full documentation.
- The full management surface (Phase 4):
  - `signal-task` — `list`, `status`, `cancel`, `defer`, `create-deferred`, `promote`
  - `signal-backlog` — `add`, `list`, `show`, `promote`, `drop` over `B<N>` entries in `.signal/backlog.md`
  - `signal-handoff` — write `handoff.md` for an in-flight task
  - `signal-retro` — per-task `retro.md` or project-level `.signal/retro.md`
  - `signal-recipe` — `list`, `show`, `create`, `update`, `delete`, `run` over `.signal/recipes/*.md` playbooks

**What is planned but not implemented (Phase 5+):**

- `signal-team` — second-opinion delegation to another AI agent (Phase 5)
- Real channel adapters (LinkedIn, Meta, email tools, CMS) — today's `publish-log.md` is on-disk only
- Performance feedback loop, multi-stakeholder approval routing, recurring/automation primitives

Signal7 today supports campaigns that mix social, long-form, image, video, translation, research, and pricing assets, with full task lifecycle management (defer, cancel, backlog promotion, handoff, retro, recipes). Worker dispatch will only block if the asset uses a type that is not in the routing table at all.

The `Status` section in this README is the source of truth for what is shipped. Individual `SKILL.md` files describe behaviour. `data-model.md`, `gates.md`, and the other reference docs describe the eventual contract — anything they describe that is not yet listed above as "implemented" should be read as future work.

## Skills (current state)

**Implemented and user-invocable:**

| Skill | What it does |
|---|---|
| `signal` | Orchestrator. Start or resume a task. |
| `signal-task` | List, status, cancel, defer, create-deferred, promote. |
| `signal-backlog` | Add / list / show / promote / drop ideas in `.signal/backlog.md`. |
| `signal-handoff` | Write a session handoff doc for an in-flight task. |
| `signal-retro` | Capture per-task or project-level retrospectives. |
| `signal-recipe` | Manage and stage `.signal/recipes/*.md` playbooks. |

**Implemented internal skills (not user-invocable):**

`signal-brief`, `signal-plan`, `signal-plan-review`, `signal-create`, `signal-review`, `signal-publish`, `signal-worker`, `signal-social`, `signal-copy`, `signal-image`, `signal-video`, `signal-translate`, `signal-research`, `signal-price`.

**Planned (will refuse with a clear message until shipped):**

User-facing: `signal-team`.

---

## How to Use Signal7 today

Signal7 turns a single business request into one published-to-ledger piece of social content. You describe what you want, Signal7 asks clarifying questions, generates the content, runs an AI review, asks you to sign off, and appends an idempotent entry to a publish ledger on disk. Everything is markdown/YAML on disk so you can pause, resume, or hand off at any time.

```
/signal Write a LinkedIn post announcing our new product launch next Tuesday
```

### Quick workflow — one piece of content

For a single LinkedIn / Instagram / X / Facebook post. One channel, one language.

1. **Start the task.**
   ```
   /signal Write a LinkedIn post about our Q1 results
   ```
2. **Answer the brief.** Signal7 asks you clarifying questions (one per turn). It asks about your audience, tone, key points, and (only if relevant) regulated claims.
3. **Approve the brief.** Signal7 writes a brief summarising what it understood. Reply `approve` or tell it what to change.
4. **Wait for content.** Signal7 dispatches `signal-social` to generate the post.
5. **Review.** Signal7 runs an automatic AI review (brand voice, channel fit, claim accuracy) and then asks for your approval. Reply `approve` or `reject: <reason>`.
6. **Publish.** Signal7 records an entry in `publish-log.md` with a unique key so you never double-post. The ledger is on disk only; actual platform posting (LinkedIn, Instagram) is a future adapter.

### Campaign workflows

Campaigns run through `brief -> plan -> plan-review -> create -> review -> publish`. The full Phase 3 worker surface is wired up, so a campaign can mix social posts, long-form copy (email / blog / landing page), image prompts, video scripts, translations, research artifacts, and pricing memos in a single content plan.

### Strategy workflows

Strategy tasks (research-only or pricing-only) run through `brief -> create -> done` and dispatch `signal-research` and/or `signal-price`. Review and publish are skipped — the artifact is internal. If you later turn the output into public content, route a follow-up task through `signal-copy` or `signal-social`, where claims compliance is enforced.

### Managing tasks (today)

| Command | What it does |
|---------|-------------|
| `/signal <goal>` | Start a new task or resume an existing one |
| `/signal S<N>` | Resume a specific task |
| `/signal-task list [active\|archive\|all]` | List tasks |
| `/signal-task status [S<N>]` | Show status of one task or list active tasks |
| `/signal-task cancel S<N>` | Cancel a task and archive it |
| `/signal-task defer S<N>` | Park an active task without archiving |
| `/signal-task create-deferred <title>` | Seed a deferred task without entering the workflow |
| `/signal-task promote B<N>` | Promote a backlog entry to a deferred task |
| `/signal-backlog add <title>` | Capture an idea as `B<N>` |
| `/signal-backlog list [open\|promoted\|dropped\|all]` | List backlog entries |
| `/signal-backlog promote B<N>` / `drop B<N>` | Convert or dismiss backlog entries |
| `/signal-handoff [S<N>]` | Write `handoff.md` for the active task |
| `/signal-retro task S<N>` / `project` | Capture retrospective notes |
| `/signal-recipe list \| show \| create \| update \| delete \| run` | Manage `.signal/recipes/*.md` playbooks |

`/signal-team` is not yet implemented. Calling it today returns a "planned, not yet implemented" message.

### Examples

**Quick: LinkedIn post**
```
You: /signal Write a LinkedIn post about our Q1 results
Signal7: Who is the target audience for this post?
You: B2B SaaS buyers, mainly CTOs
Signal7: What is the key takeaway?
You: We grew 40% YoY and added 200 enterprise customers
Signal7: [writes brief] Here is the brief. Approve?
You: approve
Signal7: [generates post via signal-social] Done. Starting review.
Signal7: [AI review passes] AI review passed. Please approve the post.
You: approve
Signal7: [publishes to ledger] Done. Task S1 is complete.
```

**Quick: Instagram post**
```
You: /signal Make an Instagram post promoting our summer sale, casual tone
Signal7: Who is the audience?
You: Gen Z, 18-25
Signal7: [writes brief, you approve, generates post, you approve, publishes] Done.
```

**Resume a paused task**
```
You: /signal S2
Signal7: Resuming S2 — quick LinkedIn post for Q1 results. Currently in review phase. AI review passed; awaiting your approval.
```

**Campaign example**
```
You: /signal Launch campaign for our new mobile app on Instagram + LinkedIn + email
Signal7: [writes brief, then content-plan.md] Instagram and LinkedIn assets dispatch to signal-social; the email asset dispatches to signal-copy.
```

```
You: /signal Translate our launch post into French and German
Signal7: [stamps two translation assets with source_asset pointing at the English source; signal-create gates dispatch on source review_ai_pass]
```

```
You: /signal Write a 500-word blog post on remote work
Signal7: [stamps a blog asset; signal-copy generates the long-form draft, signal-review runs claims and brand checks, you approve and publish]
```

---

## When the rest will land

The implementation order from here is: Phase 5 (`signal-team` — second-opinion delegation), then real channel adapters, performance feedback, multi-stakeholder approval routing, and recurring/automation primitives. See `.hyper/tasks/` in this repo or the impl-plan archived in the parent Hyper repo for the rolling plan.
