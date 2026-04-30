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
| `strategy` | brief → create → done | Research + recommendation only | Partial — quick-style brief works; strategy-specific workers are Phase 3 |

`recurring` work (e.g. "every Monday, 3 LinkedIn posts") is out of v1; it will arrive as a recipe/automation, not as a scope.

## Status

**What is implemented today (Phase 0-2):**

- The `quick` scope, end-to-end: brief → create → review → publish → done
- The `campaign` planning path: brief → plan → plan-review → create → review → publish → done
- One content worker: `signal-social` (LinkedIn, Instagram, X/Twitter, Facebook copy)
- Campaign planning skills: `signal-plan` and isolated-context `signal-plan-review`
- The `signal` orchestrator, the foundation reference docs, and the asset/brief/compliance schemas
- Disk-based state (`.signal/`), idempotent publish ledger, AI review rubric, prompt storage with hashes
- A three-layer QA testing system: Layer 1 static checks (`scripts/run-signal-fixtures.sh`), Layer 2 live-skill replay (`scripts/replay-fixture.sh` / `scripts/run-all-fixtures.sh`), and Layer 3 manual pre-ship checklist (`evals/qa-checklist.md`). See `evals/signal-fixtures/README.md` for the full documentation.
- A minimal `signal-task` skill with two operations: `cancel` and `status`

**What is planned but not implemented (Phase 3+):**

- All other content workers: `signal-copy` (email/blog/long-form), `signal-image`, `signal-video`, `signal-translate`, `signal-research`, `signal-price`
- The full management surface: `signal-backlog`, `signal-recipe`, `signal-handoff`, `signal-retro`, `signal-team`
- Beyond cancel/status, all `signal-task` operations (list, defer, create-deferred)

If you ask Signal7 today for a social campaign, it can plan/review/create/publish ledger entries using `signal-social`. If the campaign includes email, blog, web, translation, image, video, research, or pricing assets, Signal7 preserves the correct asset type and blocks at worker dispatch with a clear "worker not yet implemented" message. The scope guards are deliberate, not a bug.

The `Status` section in this README is the source of truth for what is shipped. Individual `SKILL.md` files describe behaviour. `data-model.md`, `gates.md`, and the other reference docs describe the eventual contract — anything they describe that is not yet listed above as "implemented" should be read as future work.

## Skills (current state)

**Implemented and user-invocable:**

| Skill | What it does |
|---|---|
| `signal` | Orchestrator. Start or resume a task. |
| `signal-task` | Cancel a task or report its status. (Other operations are planned.) |

**Implemented internal skills (not user-invocable):**

`signal-brief`, `signal-plan`, `signal-plan-review`, `signal-create`, `signal-review`, `signal-publish`, `signal-worker`, `signal-social`.

**Planned (placeholders may exist; will refuse with a clear message until shipped):**

User-facing: `signal-backlog`, `signal-recipe`, `signal-handoff`, `signal-retro`, `signal-team`.

Content workers: `signal-copy`, `signal-image`, `signal-video`, `signal-translate`, `signal-research`, `signal-price`.

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

Campaigns now run through `brief -> plan -> plan-review -> create -> review -> publish`. The implemented worker surface is still social copy, so campaigns using LinkedIn / Instagram / X / Facebook are the safest path today. Other channels are planned accurately but block until their workers ship.

### Managing tasks (today)

| Command | What it does |
|---------|-------------|
| `/signal <goal>` | Start a new task or resume an existing one |
| `/signal S<N>` | Resume a specific task |
| `/signal-task status [S<N>]` | Show status of one task or list active tasks |
| `/signal-task cancel S<N>` | Cancel a task and archive it |

`/signal-backlog`, `/signal-recipe`, `/signal-team`, `/signal-handoff`, `/signal-retro` are not yet implemented. Calling them today returns a "planned, not yet implemented" message.

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
Signal7: [writes brief, then content-plan.md] The Instagram and LinkedIn assets can be created with signal-social. The email asset is planned as email-copy and will block until signal-copy ships.
```

```
You: /signal Translate our launch post into French and German
Signal7: That requires dependency-aware translation. Translation worker is planned for Phase 3 and is not implemented yet.
```

```
You: /signal Write a 500-word blog post on remote work
Signal7: Blog (long-form copy) requires the signal-copy worker, which is planned for Phase 3. I can produce a LinkedIn / Instagram / X / Facebook post on the same topic today.
```

---

## When the rest will land

The implementation order from here is: Phase 3 (additional workers — `signal-copy` first, then image/video/translate/research) → Phase 4+ (management skills, channel adapters, performance feedback, multi-stakeholder approval routing, recurring/recipes). See `.hyper/tasks/` in this repo or the impl-plan archived in the parent Hyper repo for the rolling plan.
