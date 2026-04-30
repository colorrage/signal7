# Exploration — T1: Explain GH review issues and recommend Phase 2 scope

## Inputs

- impl-plan.md from hyper7 T1 (`/Users/adrianfilip/hyper/hyper7/.hyper/archive/T1-design-biz-ops-hyper-system/impl-plan.md`)
- 5 open issues at github.com/colorrage/signal7
  - #1 Claude Opus 4.7 — Phase 1 contract gaps and broken cross-references (skills/)
  - #2 Codex 5.5 — Review findings for skills workflow definitions
  - #3 Codex 5.5 — Define Signal7 end-state capabilities and architecture roadmap
  - #4 Claude Opus 4.7 — `/code-review` directory pass over skills/
  - #5 Claude Opus 4.7 — Strategic memo on scope, primitives, and end-state vision
- Phase status: Phase 0 (foundation) + Phase 1 (quick-scope MVP: brief → create
  → review → publish, with `signal-social` as the only worker) shipped. Phase
  2 (campaign + plan-review) and Phase 3+ (additional workers, recurring,
  performance loop) not started.

## How to read this report

Issues #1, #2, #4 are **doc-vs-disk hygiene reviews** — they overlap heavily.
Issue #3 is a **product roadmap** for the eventual end state. Issue #5 is a
**strategic memo** that questions some of the primitives. Restated in plain
terms below, then deduped into a single recommendation list at the end.

---

## Issue #1 (Opus 4.7) — Phase 1 contract gaps in plain terms

A read of all 27 skill/reference/template files to find places where the
docs and the on-disk skills disagree.

### Critical (will break Phase 1 today)

1. **Quick scope can dead-end into a missing skill.** If the user asks for
   a "quick" task but the brief expands into more than 3 assets,
   `signal-create` is told to redirect to `signal-plan`. `signal-plan`
   does not exist yet. The orchestrator will set `phase: plan` and try
   to dispatch nothing. **Fix:** in Phase 1, `signal-create` should
   return `awaiting-input` with a clear message instead of redirecting,
   OR the orchestrator should refuse to advance to a non-existent phase.
2. **Phase Dispatch table lists skills that don't exist.** `signal/SKILL.md`
   advertises `plan -> signal-plan` and `plan-review -> signal-plan-review`.
   If `phase: plan` ever appears in a task's frontmatter (manual edit, leftover
   state, the redirect above), routing fails silently. **Fix:** mark those
   rows "Phase 2" or remove them until those skills land.

### Major (contract gaps)

3. **`signal-create` doesn't list every verdict it can return.** Its
   "Output Contract" only shows `phase-complete` and `awaiting-input`,
   but its body code uses `redirect`. Add `redirect` to the contract.
4. **`signal-task` / `signal-backlog` / `signal-recipe` are referenced
   as if they exist.** Bootstrap, gates, and archive docs assume those
   skills run, but only `signal`, `signal-brief`, `signal-create`,
   `signal-review`, `signal-publish`, `signal-social`, `signal-worker`
   are on disk. **Fix:** mark them Phase 2/3 in those docs, or add
   stub SKILL.md placeholders per `future-skill-registry.md`.
5. **Cancellation has no owner in Phase 1.** Cancellation archiving is
   delegated to `signal-task`, which doesn't exist. There is currently
   no documented way to reach `phase: cancelled`. **Fix:** say so
   explicitly in the docs ("cancellation arrives in Phase 2") or add
   a minimal cancellation path.
6. **Publish rate-limits are mentioned but undefined.** `signal-publish`
   says "use `publish_rate_limits`" but never says what happens at the
   limit (block? defer? skip?). The publish-log status enum has no
   rate-limit value. **Fix:** add a status (e.g. `blocked-rate-limit`)
   and define defer-vs-block semantics.

### Minor (cross-refs, clarity)

7. `state-graph.md` is not in the "Read First" list of `signal/SKILL.md`
   even though it carries asset-level gate semantics that aren't
   duplicated in `gates.md`.
8. `signal-brief` is the only phase skill that writes `scope` directly
   into `task.md`. That works but breaks the "phase skills don't write
   top-level frontmatter" rule. **Suggestion:** have `signal` mirror
   `scope` from `brief.md` after `phase-complete` instead.
9. `signal-publish` writes `publish-log.md` but isn't on the list of
   skills that bootstrap `.signal/`.
10. Workers are required to log a `worker_version`, but no worker
    declares its version anywhere.
11. `templates/asset.md` hardcodes `asset_type: social-copy` (Phase 1's
    only supported type); easy to forget to overwrite.
12. `templates/compliance.md` ships `status: questions-open` (deliberately
    blocking). Worth a comment in the template that this is the
    intentional fail-safe.
13. An intake-triage example is ambiguous about whether "regulated single
    social post" is quick or campaign — the rule itself is unambiguous,
    only the example is.

### Strengths the reviewer called out

Ownership boundaries between `signal` and phase skills are crisp; verdict
encoding is centralized; idempotency contract is sound; cross-system
independence from Hyper7 is well-defended; live-vs-snapshot context
distinction is correctly applied.

---

## Issue #2 (Codex 5.5) — Workflow consistency in plain terms

Heavy overlap with #1. New / sharper points:

### High

- **Campaign routes to skills that don't exist.** Same as #1.2.
- **Review failures cannot trigger rework.** When `signal-review` fails an
  asset, it sets `review_ai_pass: false` and redirects to `create`.
  But `signal-create` only dispatches assets with `status: todo`. The
  failed asset is still `status: done`, so the loop spins without
  doing any revision. **Fix:** when review fails, flip the failed
  asset back to `status: todo` (or a new `status: needs-revision`)
  before redirecting.
- **Quick-scope outputs beyond `social-copy` block immediately.**
  `signal-create` maps `email/blog/web` to non-social asset types,
  but `signal-worker` only routes `social-copy`. README advertises
  email/blog. Anything not social goes straight to `awaiting-input`.
  **Fix:** be honest in the README about what's wired up, or wire
  up at least an email worker.
- **Translation flow stalls.** Translation assets are gated on the
  source asset's `review_ai_pass: true`. Review runs after create,
  and `signal-review` reviews all non-cancelled assets at once
  rather than partial slices. So translations never get a chance to
  run. **Fix:** explicit create → review → create flow for multi-language,
  or partial-review behavior.

### Medium

- Approval gates don't say how a user's `yes/no` reply is *recorded*.
  The orchestrator just re-dispatches; no skill writes the response
  into `review.md`.
- Compliance blocking has no verdict in `signal-review`'s output
  contract. The skill says "block if compliance is open" but the
  output contract only covers approval/redirect/completion.
- Publish rate-limit semantics still missing (same as #1.6).

### Low

- README points users to `.signal/brand.md`, but bootstrap actually
  creates `.signal/context/brand.md`.
- Archive is described as resumable in one place and terminal in
  another. Pick one.

### Suggested follow-up

Add a small `.signal` fixture suite (quick social, rejected review,
campaign, duplicate publish) to catch these regressions automatically.

---

## Issue #4 (Opus 4.7 via /code-review) — Doc-vs-disk gap audit in plain terms

Same scope as #1 and #2; the overlapping items aren't repeated. Fresh items:

### Critical

- **README claims "all 5 phases implemented" — false.** README also
  advertises `/signal-task`, `/signal-backlog`, `/signal-team`,
  `/signal-recipe`, `/signal-handoff`, `/signal-retro`. None on disk.
  **Fix:** demote to a "Phase 0/1 implemented; Phase 2+ planned" note
  with an honest inventory.
- **Worker registry contradicts itself.** `signal-worker` only routes
  `social-copy`, but `adding-workers.md` lists 8 routes as if they
  all exist. **Fix:** mark the 7 missing rows as future, or add
  explicit "not implemented" stubs.
- **Dispatch contract for phase skills is undefined.** `signal/SKILL.md`
  says "Dispatch `signal-brief`" without specifying how — inline?
  sub-agent? Skill tool? Only `signal-plan-review` documents this
  (fresh sub-agent, in `gates.md`). **Fix:** make the dispatch
  mechanism explicit per phase skill.
- **`compliance.md` template default is a silent halt.** Ships with
  `status: questions-open`, which `signal-review` interprets as
  "block". If `signal-brief` forgets to flip it to `clear` for a
  non-regulated task, review silently halts. **Fix:** default to
  `clear`, raise to `questions-open` only when triage flags risk.

### Warning (notable)

- Quick `create -> plan` redirect doesn't say what happens to existing
  assets or the `scope` field.
- Templates ship pre-stamped values (`regulated_domain: unknown`,
  `asset_type: social-copy`, `channel: none`) that become silent
  fallbacks if the agent forgets to overwrite. Use `<TODO>` sentinels
  and validate.
- `signal-publish` doesn't say how `publish_at: null` participates in
  the SHA-256 idempotency hash. A later edit to fill `publish_at` would
  change the key.
- `data-model.md` documents schemas (`content-plan.md`, `recipes/`,
  `backlog.md`) for skills that don't exist.
- `signal-brief` doesn't enforce "scope must not be `unknown` before
  `phase-complete`."
- `dashboard.md` template has un-substituted `<phase>` / `<awaiting>`
  placeholders.
- Approval gates have no rejection/redirect path.

### Top 3 priorities (the reviewer's own ranking)

1. Reconcile docs with reality (README, schema docs, route tables).
2. Close the campaign dispatch hole (block `scope: campaign` at intake
   or refuse `phase: plan` cleanly until `signal-plan` lands).
3. Specify the dispatch contract for every phase skill and worker.

### Reviewer's overall read

> "The reference layer is solid and internally coherent. The
> implemented phase/worker SKILL.md files are concise and consistent
> with it. The main problem is the gap between aspirational docs and
> what's on disk … fixes are mostly editorial, not architectural.
> The `compliance.md` default is the one trap that could bite real
> users immediately."

---

## Issue #3 (Codex 5.5) — End-state capabilities roadmap in plain terms

Not a bug list. A picture of the eventual product if Signal7 keeps
growing. Restated:

Signal7 should eventually be **a durable GTM/business-ops workflow
engine**, with first-class objects for:

- **Business context** — ICPs, personas, positioning, offers, product
  facts, pricing, competitors, visual identity, approved claims, legal
  disclaimers, channel rules, past campaigns, performance history.
- **Campaign strategy artifacts** — objectives, KPIs, funnel stage,
  audience segments, channel mix, timeline, launch dependencies,
  experiments, success criteria.
- **Content calendar** — schedule, cadence rules, campaign windows,
  asset dependencies, owners, approval deadlines.
- **Expanded asset taxonomy** — social, email, blog, landing page, ads,
  image prompts, video scripts, sales outreach, PR, product
  descriptions, translations, labeling, research, pricing.
- **Explicit approval model** — multiple approvers, rejection reasons,
  delegation, timeout policies, legal/compliance signoff, final-release
  approval.
- **Publishing adapter contracts** — LinkedIn, Meta, email, CMS, ads,
  schedulers, asset libraries.
- **Performance loop** — metric ingest, comparison vs goals, recorded
  learnings, playbook updates, pattern reuse.
- **Experimentation** — A/B variants, hypotheses, control/variant
  tracking, winner selection, recommended follow-ups.
- **Governance** — regulated domains, jurisdiction handling,
  evidence-backed claims, usage rights, copyright checks, audit logs,
  model versions, prompt storage, artifact versioning.
- **Collaboration** — owners, reviewers, comments, handoff notes,
  blocked-by, external dependency tracking.
- **Reusable playbooks** — launches, weekly social, product updates,
  promo campaigns, competitor analysis, pricing refreshes,
  localization batches, email sequences.

The architectural recommendation is to organise the system into three
layers — **Strategy** (decide what should exist and why), **Production**
(create and revise assets), **Operations** (approve, schedule, publish,
track, learn) — and to make context onboarding first-class so brand /
product / claim data is detected, asked for once, versioned, reused.
Worker support should move to an explicit registry that defines
supported asset types, required context, outputs, writes, costs, risks,
review checks.

---

## Issue #5 (Opus 4.7) — Strategic memo in plain terms

The hardest-hitting of the five. Reframes Signal7 honestly.

### What Signal7 actually is today

A **single-operator content production runtime for small teams** —
a disk-based orchestration system that walks one user through brief
→ plan → create → review → publish for marketing-ish content, with a
compliance lane bolted on. Not a marketing platform, not a campaign
management system, not a regulated-content pipeline yet. The README
sells more than the architecture models.

### Three products are tangled

- **Product A — "AI marketing intern with a paper trail."** Coherent.
  This is what's actually built.
- **Product B — "regulated content compliance pipeline."** Hinted at
  via `compliance.md`, `approved-claims.md`, `external_gate`,
  `review.md`. But it's single-jurisdiction, single-reviewer,
  single-tenant, with no approval routing, no claim-to-evidence
  linking, no retroactive invalidation. The reviewer calls this
  **"compliance theater, not a compliance pipeline."**
- **Product C — "marketing-ops platform."** Promised in the README
  (campaigns, scheduling, multi-channel). No campaign primitive,
  no scheduler, no channel adapters, no performance feedback in the
  data model.

### What it gets right (don't break)

- Disk-based, git-friendly state (the strongest single design choice).
- Strict gate/verdict separation between orchestrator and phase skills.
- Append-only publish ledger with content-hash idempotency.
- Plan-review in a fresh sub-agent (independence baked into the
  architecture, not a prompt).
- `asset_ceiling` redirect to plan as a real scope-creep brake.
- Snapshot-pinned brand/product vs. live approved-claims (the
  asymmetry is correct: claim retractions must propagate, brand
  evolution must not retroactively invalidate published work).
- Stored prompts + prompt hashes + per-revision generation log
  (reproducibility built in).

### Categorical gaps in the conceptual model (not v1/v2 phasing)

1. **No campaign primitive.** A campaign is modelled as "a task with
   more assets." Real campaigns have hero + supporting, sequencing,
   embargo, launch dates, channel-specific adaptations of the *same*
   message, lifecycle outliving any one asset.
2. **One undifferentiated stakeholder.** No requester, brand owner,
   legal, channel owner, regional lead. No role model, no routing,
   no delegation, no "legal must approve before marketing."
3. **No memory across tasks.** Every task is an island. No structural
   recall of past work, no embedding/index over previous briefs, no
   reuse beyond manual `recipes`.
4. **No performance feedback loop.** Once `publish-log.md` is written,
   the asset is dead to the system. Single biggest gap vs. any real
   marketing tool.
5. **No asset lifecycle past publish.** No refresh, sunset, repurpose,
   re-translate after a brand-voice update. `expires_at` exists but
   nothing watches it.
6. **Multi-channel orchestration is per-asset siloing.** "LinkedIn +
   Instagram + email about the same launch" = three independent
   assets sharing a brief. Cross-channel narrative is not modelled.
7. **Review is shallow.** Approver rows exist, but no multi-reviewer
   arbitration, no required-vs-optional, no parallel/serial routing,
   no revision-rounds counter, no rejected-reasons taxonomy.
8. **Localization is a worker, not a domain.** Real localization is
   market-specific claim variants, jurisdiction-specific disclaimers,
   transcreation vs. translation, locale-specific review.
9. **No external integration model beyond a yes/no gate.** Adding one
   real channel adapter would require redesigning publish.
10. **No cross-jurisdictional compliance.** EU/US/CA pharma needs
    parallel compliance threads with different claim sets and
    reviewers. The model can't represent that.
11. **No cost or budget tracking.** Token spend, image/video gen
    spend — zero visibility for a system that fan-outs to expensive
    workers.
12. **No portfolio-level operator view.** Dashboard is per-task only.
13. **No intake beyond `/signal`.** Real briefs arrive as tickets,
    emails, Slack messages, calendar items, recurring schedules.
14. **No trust/provenance signing.** Hashes exist, nothing is signed.
15. **No failure mode beyond `awaiting-input`.** Worker dies mid-run?
    Asset is `in-progress` forever. No retries, circuit breakers,
    partial-failure recovery.

### Where the primitives strain

The primitives are `task → phase → asset → worker`. They strain at:

- **Campaign as a task.** A launch is a graph spanning months, not
  one folder.
- **Asset as a single channel/language artifact.** A blog → 8 social
  variants × 3 languages × 2 image variants is asset explosion via
  hand-edited `depends[]`.
- **Phase as a global task state.** Real campaigns have assets in
  different phases simultaneously (A1 in review, A2 back in create,
  A3 published). `task.phase` is a single value.

A more honest primitive set: **campaign (long-lived) → message
(channel-agnostic intent) → rendition (concrete artifact) →
review-thread**.

### Strategic top-5 (reviewer's own ranking)

1. **Performance feedback loop.** Without engagement/conversion
   flowing back, Signal7 is open-loop while everything else is
   closed-loop. Highest leverage.
2. **Stakeholder model.** Explicit roles + routing + delegation +
   sign-off audit. Without this, Signal7 cannot serve any team
   larger than two people.
3. **Campaign as a primitive separate from task.**
4. **A real channel adapter contract.** Even if v1 supports one
   channel, define the interface, auth, per-channel contract now.
5. **Cross-jurisdictional compliance** with claim-to-evidence linking
   and retroactive invalidation. The only area where no general agent
   framework gives you a free ride — invest here for a defensible moat.

### Killer use case

**Regulated B2B content for a small in-house team that already lives
in git.** A 5-person marketing team at a fintech / healthtech startup,
20–40 pieces a month. Compliance owns approved-claims. Legal signs
off on numeric/efficacy claims. Today they live in a Google Doc
graveyard. Signal7 gives them every brief, prompt, approval,
claim-check, and publish-receipt on disk. Audit-ready.

Second strong fit: **agencies delivering "a campaign with full
reasoning trail"** where the client wants brief, prompts, approvals,
not just the final asset.

---

## Synthesis — what to implement next

Three buckets. **Do bucket A this week.** Decide on bucket B before
starting Phase 2. Bucket C is a strategic decision that should
gate any further build.

### Bucket A — Phase 1 hygiene (small, mostly editorial, ship first)

These are doc-vs-disk gaps. They are the cheapest win and they unblock
honest communication about what the system does. Single PR plausible.

1. **Honest README.** Replace "all 5 phases implemented" with a Phase 0/1
   inventory + a "planned" section for Phase 2+. Fix the `.signal/brand.md`
   vs `.signal/context/brand.md` path mismatch. (#4.1, #2 low)
2. **Block campaign at intake.** Until `signal-plan` lands, refuse
   `scope: campaign` at intake-triage with a clear "Phase 2 not yet
   implemented" message. Same for the `quick → plan` redirect:
   convert it to `awaiting-input` in Phase 1 (#1.1, #1.2, #2 high, #4.2).
3. **Flip `compliance.md` template default to `clear`.** Have
   `signal-brief` raise to `questions-open` only when triage flags
   regulated/uncertain. The current default is a silent halt trap.
   (#4.5)
4. **Fix the review-failure rework loop.** When `signal-review` fails
   an asset, flip its `status` back to `todo` (or `needs-revision`)
   before redirecting to `create`. Otherwise `signal-create` skips it
   and the loop spins. (#2 high)
5. **Document every verdict in the output contract of every phase
   skill.** Add `redirect` to `signal-create`'s contract; add an
   `awaiting-input` verdict to `signal-review`'s contract for
   compliance blockers. (#1.3, #2 medium, #4 warning)
6. **Mark missing skills explicitly future.** In `bootstrap.md`,
   `gates.md`, `archive.md`, `data-model.md`, `adding-workers.md`:
   any reference to `signal-task`, `signal-backlog`, `signal-recipe`,
   `signal-plan`, `signal-plan-review`, additional workers must be
   tagged "Phase 2/3" or moved to `future-skill-registry.md`.
   (#1.4, #1.5, #4.3, #4 warnings)
7. **Specify dispatch contract per phase skill.** State whether each
   phase skill is invoked inline, as a sub-agent via the host's
   sub-agent mechanism, or via the Skill tool. (#4.4)
8. **Lock down the `publish_at: null` idempotency edge case.** Either
   exclude null `publish_at` from the SHA-256 hash, or require
   `publish_at` to be set before publishing. (#4 warning)
9. **Define rate-limit semantics + add `blocked-rate-limit` status.**
   Decide defer vs block, and add the status enum value to
   `data-model.md`. (#1.6, #2 medium)
10. **Tighten templates against silent fallbacks.** Replace pre-stamped
    `regulated_domain: unknown`, `asset_type: social-copy`,
    `channel: none` with `<TODO>` sentinels; have `signal-brief` and
    `signal-create` validate before `phase-complete`. (#4 warnings)
11. **Mirror `scope` from `brief.md` in `signal` instead of having
    `signal-brief` write top-level frontmatter.** Restores the
    "phase skills don't write task.md top-level" rule. (#1.8)
12. **Decide whether email/blog/etc are advertised.** Either remove
    them from `signal-create`'s asset_type map until workers exist,
    or add a clear "not implemented in Phase 1" return. README must
    match. (#2 high)
13. **Small fixtures.** Add a `.signal` fixture suite (quick social,
    rejected review, campaign-blocked, publish duplicate) and a
    smoke runner. Catches regressions cheaply. (#2 follow-up)
14. **Misc minor polish.** Add `state-graph.md` to `signal/SKILL.md`
    "Read First"; add `signal-publish` to bootstrap-side skills;
    declare `worker_version` (constant or frontmatter); fix the
    intake-triage example; add the rejection/redirect path to
    approval gates; document `prompts/` ownership.

### Bucket B — Phase 2 scope decisions (decide before starting)

These need a decision, not just code. They shape Phase 2.

1. **Land `signal-plan` and `signal-plan-review`.** This is the
   biggest planned Phase 2 item already in impl-plan.md. After
   bucket A, this is the natural next slice.
2. **Add a minimal `signal-task` (cancel + status) and `signal-backlog`
   (add/list/promote/drop).** They unblock the management surface
   that all three reviewers flagged. They're small.
3. **Decide partial-review behaviour for translation flow.** Either
   create → review → create slicing, or a `review_partial` mode.
   This blocks any multi-language work. (#2 high)
4. **Define the second worker.** Pick one of `signal-copy` (email/blog
   long-form) or `signal-research`. Not both. Match it to the killer
   use case (regulated B2B content). Email-copy is the more obvious
   pick because it's a Phase 1 promise.
5. **Record approval responses in `review.md`.** When the user replies
   `yes` / `no`, *some* skill must write that into `review.md` rows,
   not just into `awaiting:`. (#2 medium)
6. **Decide cancellation ownership for Phase 1.** Either ship a tiny
   `signal-task cancel` now (recommended) or formally document that
   cancellation is Phase 2 and tasks can only end in `done`. (#1.5)

### Bucket C — Strategic gates (decide before going further)

These are not Phase-2 code items. They are direction calls. Until they
are decided, more code is premature optimisation.

1. **Decide which product Signal7 is.** A, B, or C from issue #5.
   Today the docs sell C, the architecture builds A, and B is a
   half-step. Pick A as the killer use case (regulated B2B content
   on disk for small teams) — issue #5's recommendation, also the
   coherent one given the existing architecture.
2. **Decide whether `campaign` should remain a `task` or graduate to
   its own primitive.** Issue #5's call: campaign as a long-lived
   `C<N>` separate from `task` (`S<N>` becomes an execution slice).
   This is a bigger refactor than impl-plan.md's Phase 2 currently
   anticipates. The cheap version: keep campaigns inside one task,
   accept the strain, and revisit if/when real campaigns force the
   issue. The right version: lift campaign now before Phase 2 commits
   to the wrong shape.
3. **Decide whether to invest in performance feedback (issue #5,
   gap 4) and stakeholder model (gap 2).** These are the two gaps
   that make Signal7 useful to a real team larger than two people.
   They are the moat against ChatGPT + spreadsheet. Without them,
   Signal7 plateaus. With them, it has a real claim.
4. **Decide on cross-jurisdictional compliance investment (issue #5,
   strategic gap 5).** This is where Signal7 has a defensible niche
   no general agent framework gives you. If the killer use case is
   regulated B2B, this is a yes; if it's general marketing, it isn't.

## Recommended order

The pragmatic order from this synthesis:

1. **Now (Bucket A, single PR or two):** items 1–7 of bucket A.
   These are doc-vs-disk and silent-halt fixes. Cheapest, highest
   confidence, unblock everything else.
2. **Soon (Bucket A continued):** items 8–14. Smaller polish + fixtures.
3. **Decide before any Phase 2 code (Bucket C, decisions only):**
   pick product A vs C; decide whether to lift `campaign` to its own
   primitive *before* writing `signal-plan`; decide on
   performance-feedback and stakeholder-model investment; decide on
   cross-jurisdictional compliance.
4. **Then (Bucket B):** ship `signal-plan` + `signal-plan-review`,
   minimal `signal-task` and `signal-backlog`, the second worker,
   translation partial-review, approval-response recording. The
   shape of these items depends on bucket C.

## Open questions for the user

1. Are Phase 0/1 considered locked, or is bucket A allowed to also
   touch primitives (e.g. add a `C<N>` campaign primitive) rather
   than just doing editorial fixes?
2. Killer use case — confirm regulated B2B content (issue #5
   recommendation) over general GTM platform? This decides whether
   bucket C item 4 (cross-jurisdictional compliance) is in or out.
3. Approval to start Phase 2 with `signal-plan` + `signal-plan-review`
   as planned, or rethink campaign primitive first?

When you've picked from buckets A/B/C, I can convert the chosen items
into a Hyper plan (spec.md + per-slice subtask files) and start
implementation.
