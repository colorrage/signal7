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

All 5 phases implemented. Quick, campaign, and strategy workflows are end-to-end: brief, plan, create, review, publish, plus management commands and second-opinion review.

---

## How to Use Signal7

Signal7 is an AI assistant that turns your business goals into publish-ready content. You describe what you need in plain English, Signal7 asks clarifying questions, creates the content, lets you review and approve it, then publishes it to your ledger. Everything is tracked on disk so you can pause, resume, or hand off work at any time.

Every interaction starts with a command. Signal7 uses these commands:

| Command | What it does |
|---------|-------------|
| `/signal <goal>` | Start a new task or resume an existing one |
| `/signal-task <action>` | List, defer, cancel, or check status of tasks |
| `/signal-backlog <action>` | Save ideas for later, promote them to tasks |
| `/signal-team <action>` | Ask a second AI to review your work |
| `/signal-recipe <action>` | Create, run, or manage repeatable playbooks |
| `/signal-handoff` | Save your session so you or someone else can resume later |
| `/signal-retro` | Reflect on what worked and what didn't |

There are three workflows depending on how much content you need.

### Quick workflow — one piece of content

For a single social post, one email, one blog article. One channel, one language.

**Step by step:**

1. **Start the task.** Type `/signal` followed by what you want:
   ```
   /signal Write a LinkedIn post announcing our new product launch next Tuesday
   ```

2. **Answer the brief.** Signal7 asks you clarifying questions (one at a time). It asks about your audience, tone, key points to highlight, and whether the content involves regulated claims. Answer each question.

3. **Approve the brief.** Signal7 writes a brief summarizing what it understood. Review it. If it looks right, say "approved." If not, tell it what to change.

4. **Wait for content.** Signal7 generates the post. You do not need to write anything — the AI produces the copy.

5. **Review the content.** Signal7 runs an automatic quality check (brand voice, channel fit, claim accuracy) and then asks for your approval. If it looks good, say "approved." If the AI flagged issues, Signal7 sends it back for revision.

6. **Publish.** Signal7 records the publish entry with a unique key so you never double-post. At this stage the publish ledger is on-disk only; actual platform posting (LinkedIn, Instagram, etc.) can be automated later.

### Campaign workflow — multiple channels, assets, or languages

For a launch campaign, a multi-platform announcement, a blog + email combo, or content translated into several languages.

**Step by step:**

1. **Start the task.** Type `/signal` followed by your campaign description:
   ```
   /signal Create a launch campaign for our new skincare line: Instagram + LinkedIn + email newsletter. English and French.
   ```

2. **Answer the brief.** Signal7 asks about your audience per channel, tone, timeline, key messages, product claims, and regulatory requirements.

3. **Approve the brief.** Review the brief and approve.

4. **Review the plan.** Signal7 generates a content plan showing every asset it will create (channel, language, type, publish date, dependencies). A separate AI reviews the plan for gaps (missing channels, tone mismatches, timeline issues). If the plan reviewer finds problems, Signal7 revises the plan before creating anything.

5. **Approve the plan.** Review the asset list and timeline. Approve or request changes.

6. **Wait for content.** Signal7 dispatches workers in parallel for assets that can be created independently. Translations wait until their source asset passes review. You can walk away and come back.

7. **Review all assets.** Signal7 checks brand voice, claims, compliance, and channel fit for every asset. Assets that fail go back for revision. Once you approve all human sign-offs, the campaign advances.

8. **Publish.** Signal7 writes every asset to the publish ledger with rate limiting and duplicate prevention.

### Strategy workflow — research and recommendations

For competitor analysis, pricing research, positioning recommendations — work that produces an internal document, not public content.

**Step by step:**

1. **Start the task:**
   ```
   /signal Research our top 3 competitors: pricing, messaging, and feature gaps
   ```

2. **Answer the brief.** Signal7 asks about which competitors, what dimensions to compare, and any constraints.

3. **Approve the brief.** Review the research scope and approve.

4. **Receive the report.** Signal7 produces the research artifact. There is no review or publish phase — the output is an internal reference document.

### Examples

**Example 1 — Quick: LinkedIn post**
```
You: /signal Write a LinkedIn post about our Q1 results
Signal7: Who is the target audience for this post?
You: B2B SaaS buyers, mainly CTOs
Signal7: What's the key takeaway you want readers to remember?
You: We grew 40% YoY and added 200 enterprise customers
Signal7: [writes brief] Here's the brief. Approve?
You: approved
Signal7: [generates LinkedIn post] Done. Starting review.
Signal7: [AI review passes] AI review passed. Please approve the post.
You: approved
Signal7: [publishes to ledger] Done. Task S1 is complete.
```

**Example 2 — Quick: Instagram post**
```
You: /signal Make an Instagram post promoting our summer sale, casual tone
Signal7: Who's the audience?
You: Gen Z, 18-25
Signal7: [writes brief, you approve, generates post, you approve, publishes] Done.
```

**Example 3 — Quick: Blog article**
```
You: /signal Write a 500-word blog post on why remote work improves developer productivity
Signal7: [asks audience, tone, key points, any claims to back up]
You: [answers, approves brief]
Signal7: [generates blog, runs review, you approve, publishes] Done.
```

**Example 4 — Campaign: Multi-channel launch**
```
You: /signal Launch campaign for our new mobile app. Instagram post, LinkedIn article, and email to existing users.
Signal7: [asks audience per channel, tone, timeline, product claims]
You: Instagram for consumers 20-35 casual, LinkedIn for industry professionals, email formal. Launch April 15.
Signal7: [writes brief] Approve?
You: approved
Signal7: [generates content plan with 3 assets, plan reviewer checks for gaps]
Signal7: Plan is ready. 3 assets: A1-Instagram, A2-LinkedIn, A3-Email. All publish April 15.
You: approved
Signal7: [creates all 3 assets, dispatches workers, runs review]
Signal7: All 3 assets passed AI review. Please review and approve.
You: [checks each one] approved
Signal7: [publishes all to ledger] Done. Task S2 is complete.
```

**Example 5 — Campaign: Multi-language**
```
You: /signal Translate our approved product launch post into French, German, and Spanish
Signal7: [scope: campaign — multi-language + dependency ordering]
Signal7: [plan: source post → French, German, Spanish. Translations wait for source review.]
You: approved
Signal7: [creates 3 translation assets, dispatches workers after source passes]
Signal7: [reviews all, you approve, publishes] Done.
```

**Example 6 — Strategy: Competitor research**
```
You: /signal Analyze our top competitors: Notion, Coda, and Confluence. Compare pricing, features, and positioning.
Signal7: [asks about your product, target market, specific features to compare]
You: [answers]
Signal7: [writes brief] Approve?
You: approved
Signal7: [produces research report with comparison tables and recommendations] Done. Task S3 is complete.
```

**Example 7 — Resume a paused task**
```
You: /signal S2
Signal7: Resuming S2 — Launch campaign for mobile app. Currently in review phase. 3 assets pending your approval.
```

**Example 8 — Save an idea for later**
```
You: /signal-backlog add: Halloween promotion — Instagram and TikTok posts, spooky theme, Gen Z audience
Signal7: Added to backlog as B4.
```

**Example 9 — Promote a backlog idea to a task**
```
You: /signal-backlog promote B4
Signal7: Created S5 — Halloween promotion. Starting brief phase.
```

**Example 10 — Get a second opinion**
```
You: /signal-team review the content plan for S2
Signal7: [dispatches second AI, returns independent findings]
```

### Managing tasks

Signal7 keeps a list of all your tasks. You can pause and resume at any time.

| Command | What it does |
|---------|-------------|
| `/signal-task list` | Show all active tasks with their IDs, phases, and titles |
| `/signal-task status S2` | Show detailed status of task S2 |
| `/signal S2` | Resume task S2 where you left off |
| `/signal-task defer S3` | Pause S3 for later |
| `/signal-task cancel S4` | Cancel S4 permanently |
| `/signal-backlog list` | Show all saved ideas |
| `/signal-backlog add: <idea>` | Save a new idea for later |
| `/signal-backlog promote B4` | Turn backlog item B4 into an active task |

### Getting a second opinion

At any point, you can ask another AI to review a brief, content plan, or generated asset:

```
/signal-team review the content plan for S2
```

The second AI checks independently and reports findings. You decide what to act on.

### Recipes

For repeated workflows (e.g., "every Monday, draft 3 LinkedIn posts"), save a recipe:

```
/signal-recipe create "Weekly LinkedIn" — brief 3 LinkedIn posts about product updates, create, review, publish
```

Then run it anytime:

```
/signal-recipe run "Weekly LinkedIn"
```

| Command | What it does |
|---------|-------------|
| `/signal-recipe list` | Show all saved recipes |
| `/signal-recipe create "<name>" — <steps>` | Save a new playbook |
| `/signal-recipe run "<name>"` | Execute a saved playbook |
| `/signal-recipe update "<name>" — <new steps>` | Edit an existing recipe |
| `/signal-recipe delete "<name>"` | Remove a recipe |

### Handoff and retrospectives

| Command | What it does |
|---------|-------------|
| `/signal-handoff` | Save your current task state so you or someone else can resume exactly where you left off |
| `/signal-retro` | Reflect on the last task or session — what worked, what didn't, what to do differently |
