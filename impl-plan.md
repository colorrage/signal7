# Signal7 Implementation Plan

**Date:** 2026-04-28  
**Task:** T1 — Design biz-ops Hyper system  
**Status:** Final — synthesized from Codex (gpt-4.5), Gemini (2.5-flash), OpenCode (DeepSeek v4 Pro), Claude (sonnet-4-6)  
**Target repo:** `/Users/adrianfilip/hyper/signal7/signal7/`

45 unique planned files: 43 shipped Signal7 files plus 2 repo-local installer files. 46 planned file changes because Phase 3 updates `signal-worker` after creating it in Phase 1. 6 phases. Phase 1 is the hardest.

Review decisions folded in:

- Signal7 stays independent from Hyper7. It may document when IT/website/SEO work should be handed to Hyper7, but it must not import Hyper7 skills or depend on Hyper7 internals.
- Domain coverage beyond the MVP is planned explicitly, but future domains can start as TODO skill placeholders until they are worth implementing.
- Competitive research is a first-class business workflow; pricing remains planned but optional until the real operating need is clearer.
- Recurring work is out of v1. It means repeated scheduled workflows such as "make three LinkedIn posts every Monday"; for now this belongs in future recipes/automation, not the initial phase graph.
- Human approval timeouts need a deterministic escalation path before any review skill is written.
- Phase 0 must pin all cross-skill wire contracts before implementation: verdict encoding, fresh-context plan review dispatch, asset stub revision behavior, external gate fields, approver status enum, and quick-scope asset ceiling.
- Phase 0 must also pin task-scoped asset IDs, prompt storage paths, depends[] resolution, config.yaml schema, content-plan.md schema, and the manual external-gate bridge so no Phase 1 skill invents its own convention.

---

## Phase 0 — Foundation `[M/L]`

**Goal:** Every schema, verdict, and transition defined before any SKILL.md is written. Zero ambiguity for skill authors.

**19 shipped files, in creation order:**

| # | File | Purpose |
|---|------|---------|
| 1 | `skills/signal/reference/data-model.md` | ALL schemas: task.md (phase enum includes `plan-review`), task-scoped asset IDs (`A<N>` resets per `S<N>` task; global references are `S<N>.A<N>`), asset file (adds `review_ai_pass: null\|true\|false`, `external_gate: null \| {description, source_system, required_status, status: pending\|satisfied\|waived, evidence, checked_at}`, `depends[]`, prompt path/hash fields), brief.md, compliance.md, full content-plan.md schema, asset stub lifecycle on plan revision (mark removed stubs `status: cancelled`, never silently delete), review.md (per-approver: delegate, timeout_hours, auto_approve_on_timeout, status enum `pending \| approved \| rejected \| escalated`, comment), publish-log.md entry (timestamp, task_id, asset_id, channel, publish_at, actual_publish_time, idempotency_key, status, message), backlog entry (B\<N\>: id, title, description, suggested_scope, suggested_channels, suggested_languages, created, status), approved-claims.md (claims[] with claim_text, claim_type, supporting_evidence, expires_at, jurisdictions[]) |
| 2 | `skills/signal/reference/gates.md` | Verdict vocabulary; exact verdict encoding format: every phase skill ends its response with a fenced `yaml` block containing `signal_verdict: {verdict, target, summary}`; 3 transition tables (quick/campaign/strategy); `review-pass-approval-pending` semantics — skip AI rubric on re-dispatch if review.md shows pass, check human sign-off only; plan-review must be dispatched in a fresh sub-agent context; worker dispatch uses host-neutral sub-agent language with Claude Code Task tool documented only as the portability exception; human approval = edit review.md inline, timeout evaluated at re-dispatch time |
| 3 | `skills/signal/reference/intake-triage.md` | Scope classifier with 10 worked examples: 1 channel + 1 language and ≤3 derived assets → quick; multi-channel or multi-language or >3 assets or explicit campaign intent → campaign; no public deliverable → strategy; signal-create must return redirect target:plan when a quick brief expands past 3 assets |
| 4 | `skills/signal/reference/bootstrap.md` | .signal/ folder setup: tasks/, archive/, context/ (copied from templates), config.yaml defaults and schema: schema_version, publish_rate_limits (10/hour global, 3/channel/hour), review_defaults, external_status mode (`manual` by default), default_language, enabled_channels. External adapters are optional third-party processes that write Signal artifacts/status fields; Signal never reads Hyper7 internals |
| 5 | `skills/signal/reference/state-graph.md` | Full annotated state machine for all 3 scopes including plan-review node. Design-time reference. |
| 6 | `skills/signal/reference/dashboard.md` | Dashboard rollup rules per section; degrade to placeholder when artifact missing |
| 7 | `skills/signal/reference/archive.md` | phase:done → move to .signal/archive/; S\<N\> ids never reused; scan both dirs for id allocation |
| 8 | `skills/signal/reference/adding-workers.md` | Worker extension protocol; context relevance matrix (see below); worker skeleton pattern; asset_type routing table |
| 9 | `skills/signal/templates/task.md` | task.md template with all fields annotated; phase enum includes plan-review |
| 10 | `skills/signal/templates/dashboard.md` | Dashboard placeholder template |
| 11 | `skills/signal/templates/brief.md` | brief.md template: objective, audience, channels (per-channel tone), languages, scope (filled by signal-brief), approved_claims_required |
| 12 | `skills/signal/templates/asset.md` | Complete asset file template with ALL frontmatter fields including `review_ai_pass: null`, `external_gate: null`, and cancellation/remediation fields; body placeholder |
| 13 | `skills/signal/templates/compliance.md` | compliance.md template used by both quick and campaign scopes: regulated-domain answers, claims pre-check, required disclaimers, jurisdiction notes, and unresolved compliance questions. signal-review reads this artifact before approving public assets. |
| 14 | `skills/signal/templates/context-brand.md` | brand.md schema: brand_version, schema_version, tone_guidelines (per-channel), visual_guidelines, forbidden_terms[], approved_voice_examples[]; bootstrap copies to .signal/context/brand.md |
| 15 | `skills/signal/templates/context-product.md` | product.md schema: product_name, schema_version, key_features[], value_props[], differentiation, pricing_tiers[]; bootstrap copies to .signal/context/product.md |
| 16 | `skills/signal/reference/portability.md` | Agent Skills portability contract: every SKILL.md has parseable frontmatter; internal skills set `user-invocable: false`; no plugin.json; no Signal CLI; install/distribution is by copying or symlinking the whole `skills/` suite into agent skill dirs |
| 17 | `skills/signal/reference/cross-system-boundaries.md` | Signal7 independence contract: no imports from Hyper7 skills, no required Hyper7 state shape, no direct reads of Hyper7 `task.md`; IT/website/SEO handoff is documented as a human/process boundary; external gates are resolved manually in Signal artifacts by default, and any future adapter must be external to both Signal7 and Hyper7 rather than a Signal skill reading Hyper files |
| 18 | `skills/signal/reference/compliance-and-audit.md` | Compliance pre-check for quick and campaign scopes through compliance.md; live vs snapshot context rules (`approved-claims.md` live, brand/product usually snapshot-pinned); prompt storage at task root under `prompts/A<N>-r<revision>-<worker>.md` plus prompt_hash in generation_log; generation_log mutability expectations |
| 19 | `skills/signal/reference/future-skill-registry.md` | TODO registry and placeholder convention for future skills: `signal-sales`, `signal-labeling`, `signal-image-edit`, `signal-competition`, `signal-price`, and recurring/automation helpers. Placeholder SKILL.md files may exist only when marked clearly as TODO and `user-invocable: false` unless usable. Do not create a Phase 0 placeholder for `signal-price`; Phase 3 either implements it or creates the TODO placeholder if pricing is deferred. |

**2 repo-local installer files, not shipped as Signal workflow skills:**

| # | File | Purpose |
|---|------|---------|
| 1 | `.claude/skills/install-signal/SKILL.md` | Mirrors Hyper7's repo-local install helper. Human-triggered installer entry point; not part of the distributed Signal workflow suite. |
| 2 | `.claude/skills/install-signal/scripts/install.sh` | Symlinks every `skills/*/SKILL.md` folder into agent skill directories it finds: `~/.claude/skills/`, `~/.codex/skills/`, `~/.agents/skills/`, `~/.pi/agent/skills/`. Supports `install`, `status`, and `uninstall`; leaves non-symlink existing dirs alone. |

**Context relevance matrix** (lives in adding-workers.md):

| Context File | social | copy | image | video | translate | research | price | review |
|---|---|---|---|---|---|---|---|---|
| brand.md | required | required | required | required | optional | — | — | required |
| product.md | required | required | — | optional | — | required | required | — |
| competitors.md | — | — | — | — | — | required | required | — |
| past-campaigns.md | optional | optional | — | — | — | required | — | — |
| approved-claims.md | required | required | — | — | — | — | — | required |

**Dependencies:** None.

**Validation:** gates.md covers every (scope, phase, verdict) combination. data-model.md accounts for every field in the spec. intake-triage.md produces unambiguous output for 10 test inputs. Bootstrap produces valid .signal/ directory. portability.md matches Hyper7's Agent Skills distribution model. cross-system-boundaries.md makes Signal7 usable with no Hyper7 install present. compliance-and-audit.md covers both quick and campaign compliance paths.

**Key risks:** Incomplete schemas cascade into rework across all downstream skills. `plan-review` is missing from the task.md phase enum in the spec — must add it here.

---

## Phase 1 — Quick Scope MVP `[XL]`

**Goal:** "Write a LinkedIn post" → brief → create → review → publish → done. All state on disk. Idempotent publish.

**7 SKILL.md files, leaf-first (bottom of dependency stack first):**

| # | File | Key behavior |
|---|------|-------------|
| 1 | `skills/signal-social/SKILL.md` | Leaf worker — no skill dependencies. Reads brand.md (required) + product.md (required) + asset frontmatter. Generates copy within channel character limits. Writes content to asset body. Appends generation_log entry (timestamp, model, worker, worker_version, prompt_hash). Sets status: done. NEVER writes task.md. |
| 2 | `skills/signal-worker/SKILL.md` | Single-asset dispatcher. Reads assigned asset file. Routes asset_type → worker via the host's sub-agent mechanism when available (Claude Code Task tool with subagent_type=general-purpose is the documented non-portable fallback, mirroring Hyper7's exception). Phase 1 routes: social-copy → signal-social; all others → awaiting-input. Sets status: in-progress before dispatch, status: done or blocked after. NEVER writes task.md. |
| 3 | `skills/signal-brief/SKILL.md` | Asks one clarification question per turn (returns awaiting-input). Classifies scope via intake-triage.md. Writes brief.md from templates/brief.md and creates compliance.md for every task. For non-regulated tasks, compliance.md records `regulated_domain: false` and `status: clear`; for regulated or uncertain tasks, it records questions/checks and keeps `status: questions-open` until resolved. Returns awaiting-approval when done. On approval re-dispatch → phase-complete. Writes scope to task.md frontmatter. Does NOT write phase: or awaiting:. |
| 4 | `skills/signal-create/SKILL.md` | **Two code paths:** (a) Quick scope — reads brief.md, derives one asset per (channel × language): asset_type by channel (instagram/linkedin/twitter/facebook → social-copy; email → email-copy; default → copy). If derived asset count exceeds the quick ceiling from intake-triage.md, return redirect target:plan instead of creating assets. Stamps asset files from templates/asset.md, status: todo, brand_version pinned from brand.md. (b) Campaign scope — reads content-plan.md, enumerates pre-created stubs, ignores `status: cancelled` stubs from earlier plan revisions. Both paths: build eligible list (status: todo + all depends met + for translation assets: source_asset's review_ai_pass: true). Batch by disjoint writes sets. Dispatch signal-worker sub-agents in parallel. Returns phase-complete when all done, awaiting-input if any blocked. |
| 5 | `skills/signal-review/SKILL.md` | **First dispatch:** 3 AI passes — (1) brand compliance vs brand.md, (2) claims compliance vs approved-claims.md and compliance.md, (3) quality/channel-fit. Writes review_ai_pass: true/false on each asset frontmatter. Writes review.md with per-asset findings + per-approver sections. Returns review-pass-approval-pending → signal sets awaiting: user-approval. **Re-dispatch:** if review.md exists with AI pass → skip rubric, check human statuses, auto-approve timed-out approvers only when that row explicitly allows it. If a non-auto-approve approver times out, mark the row escalated, name the blocked approver/delegate in the user-facing summary, keep awaiting: user-approval, and do not advance. All resolved → phase-complete. AI fail → redirect target:create. Any pending or escalated → review-pass-approval-pending again. |
| 6 | `skills/signal-publish/SKILL.md` | Per asset: (1) external dependency gate — read asset `external_gate`; never read Hyper7 internals directly. If `external_gate` is null, skip this check. If present, `external_gate.status` must be `satisfied` or `waived`; otherwise block with reason while other assets proceed. Manual mode means the user edits Signal artifacts with evidence; future adapters are external third-party processes that update the same Signal fields. (2) expires_at — if past → skip, status: blocked-expired. (3) Idempotency — compute SHA-256(task_id + asset_id + channel + publish_at + content_hash), check publish-log.md; if key exists → skip, status: skipped-duplicate. (4) Append entry to publish-log.md (append-only). Check rate limits from config. Returns phase-complete when all assets published or skipped. |
| 7 | `skills/signal/SKILL.md` | Main orchestrator. On first run: bootstrap .signal/ if needed. Reads task.md frontmatter. If awaiting is set and this is not a gate reply → surface to user and stop. Applies scope-to-phase routing per gates.md. Dispatches current phase skill. Reads returned verdict. Writes phase: and awaiting: to task.md. Regens dashboard.md after each phase advance. Archives to .signal/archive/ on phase: done. On redirect target:\<phase\> → clear awaiting, set phase, re-dispatch immediately. NEVER generates content or makes probabilistic decisions. |

**Dependencies:** All Phase 0 files.

**Validation (step-by-step):**
1. Bootstrap → verify .signal/ directory structure
2. Create S1 quick task (scope: quick, channel: linkedin, language: en)
3. Run signal → signal-brief runs, brief.md written, awaiting-approval
4. Approve brief → phase: create
5. Run signal → signal-create writes A1-linkedin-en.md (status: todo, brand_version pinned), dispatches signal-worker → signal-social, A1 status: done, generation_log populated
6. Run signal → signal-review writes review.md, review_ai_pass: true on A1, returns review-pass-approval-pending, awaiting: user-approval
7. Edit review.md (set approver status: approved) → re-run signal → signal-review sees approval, phase-complete → phase: publish
8. Run signal → signal-publish appends to publish-log.md with idempotency key, phase: done
9. Re-run signal → confirm no duplicate publish-log entry (key match → skipped-duplicate)

---

## Phase 2 — Campaign Scope `[M]`

**Goal:** brief → plan → plan-review → create → review → publish → done.

**5 files:**

| # | File | Purpose |
|---|------|---------|
| 1 | `skills/signal/templates/context-competitors.md` | Schema: competitors[] with name, positioning, pricing, known_weaknesses, schema_version |
| 2 | `skills/signal/templates/context-past-campaigns.md` | Schema: campaigns[] with title, channels, performance_summary, lessons, schema_version |
| 3 | `skills/signal/templates/context-approved-claims.md` | Schema: claims[] with claim_text, claim_type, supporting_evidence, expires_at, jurisdictions[], schema_version |
| 4 | `skills/signal-plan/SKILL.md` | Campaign scope only. Reads approved brief.md. Generates content-plan.md using the full Phase 0 schema: strategy_summary, channel_language_matrix entries, asset_plan[] entries with id/asset_type/channel/language/source_asset/depends/publish_at/external_gate, timeline, and assumptions. Creates A\<N\>-slug.md stubs at task root with full frontmatter filled (id, parent, title, status: todo, asset_type, channel, language, due_at, depends[], writes[], brand_version, market, jurisdiction, publish_at, external_gate) and empty body. On re-plan, marks removed stubs status: cancelled rather than deleting. Returns awaiting-approval for content-plan → phase-complete on approval. |
| 5 | `skills/signal-plan-review/SKILL.md` | Adversarial. Dispatched as separate sub-agent by orchestrator (fresh context = independence guarantee; inline invocation is not permitted for this phase). Reads content-plan.md + brief.md + context files. Checks: channel/language coverage gaps vs brief, asset-type-to-channel mismatches, tone consistency, timeline feasibility, plan-level brand compliance. Writes plan-review.md with findings and verdict. Returns phase-complete (approved) or redirect target:plan (rejected with remediation items). |

**Dependencies:** Phase 0 + Phase 1.

**Validation:**
1. Create campaign task (3 channels, 2 languages)
2. Run brief → verify scope: campaign
3. Run plan → verify content-plan.md + stub asset files created with full frontmatter
4. Introduce deliberate flaw (wrong asset_type for channel) → run plan-review → verify redirect:plan returned with findings
5. Fix flaw → re-run plan-review → verify phase-complete
6. Run create → verify signal-create reads content-plan.md (not brief.md) in campaign path
7. Run through review → publish → done

---

## Phase 3 — Remaining Content Workers `[M]`

**Goal:** All 7 content asset types producible. Translation gate validated end-to-end.

**7 changes (6 new SKILL.md + 1 update to signal-worker):**

| # | File | Key behavior |
|---|------|-------------|
| 1 | `skills/signal-copy/SKILL.md` | Long-form (blog, email, landing page). brand.md + product.md + approved-claims.md required. past-campaigns.md optional. Claims compliance check before output. No channel character limits. |
| 2 | `skills/signal-image/SKILL.md` | Generates text-to-image prompt (not binary image). Reads brand.md visual_guidelines. Sets usage_rights: original in asset frontmatter. |
| 3 | `skills/signal-video/SKILL.md` | Scene-by-scene script: per-scene voiceover + visual direction + timing cue. Reads brand.md for tone. |
| 4 | `skills/signal-translate/SKILL.md` | **Gate first:** read source_asset field, read source asset file, check review_ai_pass: true → if false/null → return awaiting-input ("source asset not yet AI-reviewed"). Then translate to target language (from asset frontmatter). Preserve brand terminology. Never translate forbidden_terms. Append generation_log. |
| 5 | `skills/signal-research/SKILL.md` | Reads competitors.md + past-campaigns.md + product.md (all required). Structured analysis, not creative generation. Owns competition research in the MVP; a later user-facing `signal-competition` skill can be added as a shortcut if this becomes common enough. Does not read approved-claims.md. |
| 6 | `skills/signal-price/SKILL.md` | Planned but optional worker. Reads product.md + competitors.md (both required). Market/jurisdiction-aware pricing recommendations. Flags jurisdiction-specific regulatory constraints. If pricing remains unclear during implementation, create a TODO placeholder and keep it out of the critical path. |
| 7 | **Update** `skills/signal-worker/SKILL.md` | Add routing: email-copy/blog → signal-copy, image-prompt → signal-image, video-script → signal-video, translation → signal-translate, research → signal-research, pricing → signal-price |

**Dependencies:** Phase 0–2. signal-translate depends on signal-review writing review_ai_pass (Phase 1).

**Strategy-scope rule:** pure research or pricing tasks do not end at `brief` with no artifact. They use `brief → create → done`, where `create` dispatches `signal-research` and/or `signal-price` and writes the resulting strategy artifact. They skip review/publish unless the user turns the output into public content.

---

## Phase 4 — Management Skills `[S]`

**Goal:** Full operational layer: list/defer/cancel tasks, backlog, handoffs, retros, recipes.

**5 files:**

| # | File | Key behavior |
|---|------|-------------|
| 1 | `skills/signal-task/SKILL.md` | List/status/defer/cancel/create-deferred for S\<N\> tasks. Scans .signal/tasks/ + .signal/archive/ for id allocation. Reads task.md. Never touches phase: or awaiting: except for explicit defer (phase: deferred, clear awaiting) or cancel (phase: cancelled + reason). Updates dashboard.md after state changes. |
| 2 | `skills/signal-backlog/SKILL.md` | Manages .signal/backlog.md (B\<N\> ids). Add/list/promote/drop. Promotion creates S\<N\> folder from templates/task.md seeded from backlog entry (title, description, suggested_scope → scope, suggested_channels, suggested_languages). |
| 3 | `skills/signal-handoff/SKILL.md` | Writes handoff.md to current task folder. Captures: decisions made this session, paths ruled out, uncommitted work, open questions, exact next step. Human-triggered only. |
| 4 | `skills/signal-retro/SKILL.md` | Writes retro.md (per-task) or appends to .signal/retro.md (project-level). Human-triggered only. |
| 5 | `skills/signal-recipe/SKILL.md` | Manages .signal/recipes/*.md playbooks. List/create/run/update/delete. Recipes are procedural markdown, not task-tracked. |

**Dependencies:** Phase 0 schemas. Write after Phase 1 schema is stable.

---

## Phase 5 — signal-team `[S]`

**Goal:** Second-opinion delegation to another AI agent for any Signal7 artifact.

**1 file:**

| # | File | Key behavior |
|---|------|-------------|
| 1 | `skills/signal-team/SKILL.md` | Mirrors hyper-team. Human-triggered only. Task types: design-review (brief/content-plan), code-review (asset copy quality), research (artifact investigation), verify (fact-check). Every teammate finding verified against source file before showing to user. Never auto-applies changes. Covers: brief.md, content-plan.md, A\<N\>-\*.md, review.md. |

**Dependencies:** Phase 0 (artifact schemas). No pipeline dependency.

---

## Critical Path

19 shipped files for a working quick-scope pipeline:

```
1.  skills/signal/reference/data-model.md
2.  skills/signal/reference/gates.md
3.  skills/signal/reference/intake-triage.md
4.  skills/signal/templates/task.md
5.  skills/signal/templates/brief.md
6.  skills/signal/templates/asset.md
7.  skills/signal/templates/compliance.md
8.  skills/signal/templates/context-brand.md
9.  skills/signal/templates/context-product.md
10. skills/signal/reference/portability.md
11. skills/signal/reference/cross-system-boundaries.md
12. skills/signal/reference/compliance-and-audit.md
13. skills/signal-social/SKILL.md
14. skills/signal-worker/SKILL.md
15. skills/signal-brief/SKILL.md
16. skills/signal-create/SKILL.md
17. skills/signal-review/SKILL.md
18. skills/signal-publish/SKILL.md
19. skills/signal/SKILL.md
```

bootstrap.md is needed to run the system but not to author the skills. The installer script is not a workflow file, but it is critical for making local validation realistic.

---

## Complexity Rankings

| Rank | Skill | Why |
|------|-------|-----|
| 1 | **signal** | Owns ALL deterministic transitions, ALL awaiting writes, scope routing, external dependency pre-check, dashboard regen, archival. Every edge case in the system flows here. |
| 2 | **signal-create** | Two code paths (quick derivation vs campaign plan stubs), parallel batch dispatch, dependency resolution, translation gate (review_ai_pass check). |
| 3 | **signal-review** | Three-pass AI rubric, human sign-off with delegation/timeout, two-mode re-dispatch (AI pass: skip rubric; AI fail: redirect), review_ai_pass management on all assets. |
| 4 | **signal-plan** | Content strategy from brief: channel/language matrix, asset type assignment, stub file generation with complete frontmatter. |
| 5 | **signal-publish** | Idempotency key computation, external dependency gating without Hyper7 internals, per-asset expiry, rate limiting, append-only log, retry-safe. |
| 6 | **signal-brief** | Clarification loop (one question/turn), scope classification, per-channel tone normalization, approval loop. |
| 7 | **signal-plan-review** | Adversarial review requiring separate sub-agent session. |
| 8 | **signal-worker** | Dispatch routing, status lifecycle, generation_log. Correctness-critical thin layer. |
| 9 | **signal-translate** | Source review gate logic, locale fidelity, brand terminology preservation. |
| 10 | **signal-team** | Provider abstraction, mandatory verification of all teammate output. |
| 11 | **signal-research** | Synthesis discipline, constrained by context data quality. |
| 12 | **signal-price** | Optional/future market and jurisdiction context, competitor data integration. |
| 13 | **signal-social** | Channel character limits, per-channel tone. |
| 14 | **signal-copy** | Long-form, claims compliance, fewer platform constraints. |
| 15 | **signal-image** | Prompt generation with brand visual constraints. |
| 16 | **signal-video** | Structured scene/voiceover/timing output. |
| 17 | **signal-task** | Deterministic CRUD on task.md. |
| 18 | **signal-backlog** | CRUD + promotion to task. |
| 19 | **signal-handoff** | Structured context capture. |
| 20 | **signal-recipe** | File management for markdown playbooks. |
| 21 | **signal-retro** | Append-only reflection. Simplest skill. |

---

## Architectural Gaps — All Resolved

All 41 gaps identified across the AI plans and follow-up review are resolved in Phase 0 before any workflow SKILL.md is written:

| # | Gap | Where Resolved | Resolution |
|---|-----|----------------|------------|
| 1 | `plan-review` missing from task.md phase enum | data-model.md + templates/task.md | Add to enum |
| 2 | Quick-scope asset derivation undefined | data-model.md | One asset per (channel × language); asset_type by channel |
| 3 | `review-pass-approval-pending` re-dispatch semantics | gates.md | Skip AI rubric if review.md exists with AI pass; check human sign-off only |
| 4 | `review_ai_pass` field missing from asset schema | data-model.md + templates/asset.md | Add `review_ai_pass: null\|true\|false` |
| 5 | Idempotency key formula | data-model.md | SHA-256(task_id + asset_id + channel + publish_at + content_hash) |
| 6 | publish-log.md entry schema | data-model.md | timestamp, task_id, asset_id, channel, publish_at, actual_publish_time, idempotency_key, status, message |
| 7 | External dependency protocol | cross-system-boundaries.md + gates.md | Track required external status/milestone in Signal artifacts; do not read Hyper7 internals; fail closed if unresolved |
| 8 | review.md schema undefined | data-model.md | Per-approver: delegate, timeout_hours, auto_approve_on_timeout, status, comment |
| 9 | Human approval mechanism | gates.md | Edit review.md inline; timeout evaluated at re-dispatch time; non-auto-approved timeout escalates and keeps the gate open |
| 10 | Asset canonical location and ID scope ambiguous | data-model.md | A\<N\>-slug.md at task root = metadata + content body; asset IDs are task-scoped (`S1.A1`, `S2.A1` are distinct); publish idempotency includes task_id; `writes` field guards parallel dispatch only |
| 11 | Worker dispatch mechanism | gates.md + portability.md | Prefer host-neutral sub-agent wording; document Claude Code Task tool as the same limited portability exception Hyper7 uses |
| 12 | Context file relevance matrix | adding-workers.md | Table mapping each worker to required/optional context files |
| 13 | Rate limiting parameters | bootstrap.md | config.yaml defaults: 10/hour global, 3/channel/hour; publish-log.md is count source |
| 14 | signal-plan asset stub field ownership | data-model.md | plan fills metadata fields; workers fill generation_log + body |
| 15 | content-plan.md body schema | data-model.md | strategy summary, channel/language matrix, asset list |
| 16 | Backlog entry schema | data-model.md | B\<N\> ids: id, title, description, suggested_scope, suggested_channels, suggested_languages, created, status |
| 17 | approved-claims.md schema | data-model.md | claims[] with claim_text, claim_type, supporting_evidence, expires_at, jurisdictions[] |
| 18 | "Same author never reviews" enforcement | signal-plan-review | Orchestrator dispatches as separate sub-agent — fresh context is the architectural guarantee |
| 19 | Spec says "20 skills" but names 21 | — | Correct count is 21: signal + 7 phase skills + 6 management + 7 workers |
| 20 | Signal7 install/discovery undefined | portability.md + installer files | Mirror Hyper7: copy/symlink full `skills/` suite into agent skill dirs; no plugin.json, no Signal CLI |
| 21 | Signal7 coupled to Hyper7 internals | cross-system-boundaries.md + signal-publish | Signal may document handoff to Hyper7, but does not read Hyper7 task files or invoke Hyper7 skills |
| 22 | Quick-scope compliance path unclear | compliance-and-audit.md + data-model.md | Compliance pre-check is available to both quick and campaign scopes; regulated quick tasks do not require campaign upgrade solely for compliance |
| 23 | Prompt audit trail incomplete | compliance-and-audit.md + asset template | Store the prompt text/path plus prompt_hash; hash alone is not treated as traceability |
| 24 | Context pinning partial | compliance-and-audit.md | Declare context files live vs snapshot-pinned; `approved-claims.md` is live by default |
| 25 | Approver timeout escalation undefined | gates.md + review.md schema | Non-auto-approved timeout becomes `escalated`, keeps the gate open, names the delegate/owner, and waits for user action |
| 26 | Strategy workers have no phase path | gates.md + signal-create | Strategy scope uses `brief → create → done`; research/pricing workers produce internal artifacts and skip publish |
| 27 | Domain coverage broader than MVP | future-skill-registry.md | Plan TODO/future skills for sales outreach, labeling, image modification, competition shortcut, pricing, and recurring workflows |
| 28 | Recurring scope unclear | future-skill-registry.md | Out of v1; model later as recipes/automation for repeated scheduled workflows, not as an initial phase transition |
| 29 | Verdict encoding unspecified | gates.md | Every phase skill ends with fenced YAML: `signal_verdict: {verdict, target, summary}`; `signal` parses only that block |
| 30 | Plan-review fresh-context guarantee only lived in prose | gates.md + signal-plan-review | Orchestrator must dispatch `signal-plan-review` in a fresh sub-agent context; inline invocation is not permitted |
| 31 | Asset stub lifecycle on rejected plan undefined | data-model.md + signal-create | Revised plans mark removed/obsolete stubs `status: cancelled`; create ignores cancelled stubs and never silently deletes prior plan evidence |
| 32 | External dependency field unnamed | data-model.md + templates/asset.md | Add `external_gate: null \| object` to asset frontmatter and require publish to block unresolved gates |
| 33 | Approver status enum unspecified | data-model.md + gates.md | Define `pending \| approved \| rejected \| escalated` and make `signal-review`/`signal` use those exact values |
| 34 | Quick-scope hidden complexity escape hatch missing | intake-triage.md + signal-create | Define quick asset ceiling and require redirect to plan when a quick brief expands beyond it |
| 35 | Quick-scope asset ceiling value missing | intake-triage.md + signal-create | Quick scope is 1 channel, 1 language, and ≤3 derived assets; >3 assets redirects to plan |
| 36 | Quick-scope compliance artifact missing | templates/compliance.md + compliance-and-audit.md + signal-brief + signal-review | signal-brief creates compliance.md for every task; both quick and campaign scopes use it for regulated-domain answers, pre-checks, disclaimers, and jurisdiction notes |
| 37 | Prompt storage location unspecified | compliance-and-audit.md + data-model.md | Store prompts under task-root `prompts/A<N>-r<revision>-<worker>.md`; generation_log stores prompt_path and prompt_hash |
| 38 | content-plan.md schema underspecified | data-model.md + signal-plan | Define strategy_summary, channel_language_matrix, asset_plan[], timeline, assumptions, and per-asset fields consumed by signal-create |
| 39 | External status adapter contract circular | cross-system-boundaries.md + bootstrap.md + signal-publish | Default bridge is manual Signal artifact updates; future adapters are third-party processes that write Signal fields, not Signal components that read Hyper7 files |
| 40 | depends[] resolution semantics undefined | data-model.md + adding-workers.md + signal-create | All dependencies must resolve to same-task asset IDs with `status: done`; blocked/cancelled dependencies block dependents; translation also requires source review_ai_pass: true |
| 41 | config.yaml schema partial | bootstrap.md | Define schema_version, publish_rate_limits, review_defaults, external_status mode, default_language, and enabled_channels before skills read config |

---

## Build Estimate

| Phase | Files | Size | Rationale |
|-------|-------|------|-----------|
| Phase 0 — Foundation | 21 | M/L | All schemas, portability/install rules, independence boundaries, compliance/audit contracts, and future-skill registry resolved before any workflow SKILL.md. Parallelizable but requires careful auditing. Phase 0 rushed = cascading rework. |
| Phase 1 — Quick Scope | 7 | XL | 3 hardest skills in the system + full pipeline integration test. Critical path bottleneck. |
| Phase 2 — Campaign | 5 | M | signal-plan medium-hard; signal-plan-review medium. |
| Phase 3 — Workers | 6 new + 1 update | M | Each worker is low complexity; volume + translation gate update makes it M. |
| Phase 4 — Management | 5 | S | All deterministic CRUD. Mirrors Hyper7 equivalents. |
| Phase 5 — signal-team | 1 | S | Mirrors hyper-team. |
| **Total** | **45 unique files / 46 file changes** | **~XL** | ~14–18 sessions. Phase 1 dominates. Phase 0 is the hidden risk. |

---

## Phase 0/1 Amendments — 2026-04-30

After Phase 0 + Phase 1 shipped, five review issues opened against `colorrage/signal7` (#1, #2, #4 from Codex 5.5 / Opus 4.7 doc-vs-disk audits, #3 product roadmap, #5 strategic memo). Hyper task `signal7/.hyper/T1-explain-reviews-and-recommend` synthesised those issues into a Bucket A (hygiene) / Bucket B (Phase 2 enablers) / Bucket C (strategy) decomposition. Buckets A and B were implemented; Bucket C deferred. The amendments below record only the **behaviour-changing** fixes — pure editorial / doc reconciliation is out of scope of this appendix because the shipped Signal7 docs are the source of truth for those.

### A2 — Campaign refused at intake until Phase 2 ships

**Change.** `signal-create` no longer emits `redirect target: plan` while `signal-plan` is unimplemented. It returns `awaiting-input` with a "campaign scope is Phase 2; not yet implemented" message instead. `signal-brief` emits the same `awaiting-input` when triage classifies a request as campaign. `signal` enforces a final guard: even if `brief.md` lands with `scope: campaign`, the orchestrator surfaces the same blocked message and refuses to mirror the scope into `task.md`.

**Why.** Phase 0/1 advertised a redirect into a phase whose skill does not exist. `signal` would set `phase: plan` and try to dispatch a non-existent skill. The dispatch hole was reviewer #1 critical #1 and reviewer #4 critical #2.

**Reversal.** Once Phase 2 lands `signal-plan` and `signal-plan-review`, restore the `redirect target: plan` form in `signal-create` and remove the campaign refusal in `signal-brief` and `signal`. The `awaiting-input` summaries are written to be removable as a single edit.

### A3 — `compliance.md` default flipped from `questions-open` to `clear`

**Change.** `templates/compliance.md` now ships at `regulated_domain: false`, `status: clear`. `signal-brief` is responsible for *raising* it to `regulated_domain: true` (or `unknown`) and `status: questions-open` only when triage detects a regulated domain. The original Phase 0 default was the opposite: `signal-brief` was expected to *lower* the status to `clear` for non-regulated tasks.

**Why.** The original "fail-safe" default produced a silent halt in `signal-review` whenever `signal-brief` forgot to write the non-regulated answer. Reviewer #4 critical #5 — "the one trap that could bite real users immediately." With Signal7's positioning as a general marketing-ops engine (not a compliance pipeline), defaulting to `clear` matches the common case and pushes the explicit work onto the regulated path where it belongs.

**Reversal.** None planned; the new default holds at every product positioning that is not "regulated content first."

### A4 — Review-failure rework loop closes

**Change.** When `signal-review` flags an asset as failing the AI rubric or rejected by a human, it now writes `status: needs-revision` on the affected asset before returning `redirect target: create`. `signal-create`'s eligibility set was widened from `status: todo` to `status: todo | needs-revision`, and `revision` is incremented before re-dispatching the worker. A new asset-status enum value `needs-revision` was added to `data-model.md`.

**Why.** Reviewer #2 high — review failures could not trigger rework because `signal-create` only dispatched `todo` assets, but `signal-review` left failing assets at `done`. The redirect loop spun without producing a new revision.

**Reversal.** None planned; this is a correctness fix to the documented contract.

### A8 — `publish_at: null` participation in idempotency hash

**Change.** When `publish_at` is unset on an asset, `signal-publish` uses the literal token `null` in the SHA-256 key input rather than excluding the field. The token form keeps the key stable across writes that fill `publish_at` later: a task that wants its eventual `publish_at` to influence the key must set the field *before* publishing. Pre-existing publish-log entries written under earlier rules remain valid; only new writes follow the rule.

**Why.** Reviewer #4 warning — `publish_at: null` had no defined participation in the hash, so a later edit changing `null` to a real timestamp would break idempotency for the same asset. Reviewer #1 strength: idempotency was the whole point of the publish ledger; the corner case had to be specified.

**Reversal.** None planned.

### A9 — Rate-limit semantics + `blocked-rate-limit` status

**Change.** `data-model.md` publish-log status enum gained `blocked-rate-limit`. `signal-publish` now defines explicit defer-vs-block behaviour driven by a new `publish_rate_limits.on_limit: defer | block` field in `.signal/config.yaml` (default `defer`). On limit, `signal-publish` appends a `blocked-rate-limit` entry to `publish-log.md` and surfaces `awaiting-input` so the user reruns later. The asset itself stays `status: done` so a later run picks it up; rate-limit deferral is not the same as `needs-revision`.

**Why.** Reviewer #1 major and reviewer #2 medium: the rate-limit field was referenced but had no defined behaviour at the limit and no matching status enum value. Implementations would have invented their own.

**Reversal.** None planned.

### A11 — `scope` mirror moved from `signal-brief` to `signal`

**Change.** `signal-brief` no longer writes any field of `task.md`. After `signal-brief` returns `phase-complete`, `signal` reads `scope` from `brief.md` and mirrors it into `task.md` itself. `gates.md` ownership table updated: `signal` (and `signal-task` for explicit cancel) are the only writers of any top-level `task.md` field.

**Why.** Reviewer #1 minor #8: `signal-brief` writing `scope` was the only architectural exception to the "phase skills do not write top-level frontmatter" rule. The exception had no payoff — restoring the rule cleans the contract.

**Reversal.** None planned.

### B1 — Minimal `signal-task` shipped (cancel + status only)

**Change.** A new `skills/signal-task/` skill ships with two operations: `cancel S<N> [reason: ...]` (terminal cancellation, archives the folder, appends a `## Cancellation` section to `task.md`) and `status [S<N>]` (read-only summary; lists active tasks when no id is given). All other operations (`list` full, `defer`, `create-deferred`, `promote`) refuse with a "Phase 2 — not yet implemented" verdict.

**Why.** Reviewer #1 major #5 / reviewer #2 / reviewer #4 critical #2: the cancellation path had no owner in Phase 1. Bootstrap, gates, and archive docs assumed `signal-task` existed; it did not. Adding the cancel + status slice is small (one SKILL.md) and unblocks the cancellation hole without committing to the full Phase 2 management surface.

**Reversal.** None — Phase 2 expands the skill rather than rewriting it.

### B2 — Partial-review path for translation flow

**Change.** `state-graph.md` now documents an explicit `create (source) → review (source) → create (translations) → review (full) → publish` path for tasks whose eligible set contains translation assets with `source_asset.review_ai_pass: null`. `signal-create` returns `redirect target: review` when this state is detected; `signal-review` runs the rubric on the source(s) only and returns `redirect target: create` so translations become eligible. The path never engages in Phase 1 — there is no translation worker yet — but the contract is locked so `signal-translate` lands cleanly in Phase 3.

**Why.** Reviewer #2 high: with the original "review every non-cancelled asset at once" rule, translations could never reach a state where their source had `review_ai_pass: true`, so multi-language work would stall.

**Reversal.** None planned.

### B3 — User approval/rejection responses recorded in `review.md`

**Change.** On redispatch after the user replies to the approval gate, `signal-review` writes the parsed reply into the matching approver row in `review.md`: `status`, `comment`, `rejected_reason`, `recorded_response`, `updated_at`. Previously the orchestrator cleared `awaiting:` and the phase skill redispatched; the user's actual answer was not persisted anywhere durable.

**Why.** Reviewer #2 medium — approval gates "do not define how user replies mutate artifacts." The artifact must be the durable record, not just `task.md` `awaiting`. `data-model.md` per-approver schema gained `rejected_reason` and `recorded_response` fields.

**Reversal.** None planned.

### Items deliberately not changed

- **Bucket C strategic items** — campaign-as-its-own-primitive (`C<N>`), performance feedback loop, multi-stakeholder approval routing, channel adapters, cost/budget tracking, cross-jurisdictional compliance. The user's product positioning for Signal7 is "general marketing-ops with light audit trail" (not "regulated-content compliance pipeline"), so cross-jurisdictional compliance investment specifically is **not** prioritised. The other Bucket C items are deferred until Phase 2 builds out enough to make the right shape obvious.
- **Email/blog/landing-page workers** — the `asset_type` mappings stay in `signal-create`, but `signal-worker` blocks them with a clear "planned for Phase 2; not yet implemented" message rather than coercing to `social-copy`. README § Status names exactly which workers are wired up today. Reviewer #2 high was satisfied by making the surface honest, not by deleting the taxonomy.

### Side effects on the original Phase 2/3 plan

- Phase 2's `signal-plan` and `signal-plan-review` are unchanged in scope but inherit the new dispatch-contract documentation in `portability.md`.
- Phase 4's `signal-task` is shrunk: `cancel` + `status` already shipped, so Phase 4 only needs to add `list` (full), `defer`, `create-deferred`, and the `promote` bridge with `signal-backlog`.
- The Phase 0 future-skill-registry has been rewritten by phase rather than alphabetically, so Phase 2 / Phase 3 / Phase 4+ work is now visible at a glance.

---

## Phase 3 — Shipped 2026-05-01

All seven Phase 3 changes landed: six new content workers and the `signal-worker` routing update. The full `asset_type` surface advertised by `signal-create` and `signal-plan` now has a real implementation behind it.

**Files added:**

| # | File | Status |
|---|------|--------|
| 1 | `skills/signal-copy/SKILL.md` | Implemented. Long-form copy worker for `email-copy`, `blog`, `landing-page`, and generic `copy`. Reads `brand.md`, `product.md`, `approved-claims.md` (required); `past-campaigns.md` optional. Claims pre-check against `approved-claims.md` and `compliance.md` before output; blocks the asset with `awaiting-input` when `compliance.md` is `questions-open` or `blocked`, or when a draft claim cannot be matched. |
| 2 | `skills/signal-image/SKILL.md` | Implemented. Generates a host-neutral text-to-image prompt for `image-prompt` assets. Reads `brand.md` `visual_guidelines` and `forbidden_terms[]`. Sets `usage_rights: original` on the asset (the prompt is original; eventual image rights depend on the rendering tool). |
| 3 | `skills/signal-video/SKILL.md` | Implemented. Scene-by-scene script for `video-script` assets — per-scene voiceover, visual direction, timing cue, optional on-screen text. Reads `brand.md` for tone; `product.md` optional. |
| 4 | `skills/signal-translate/SKILL.md` | Implemented. Source-review gate runs first: `source_asset.review_ai_pass: true` *and* `status: done` are both required. Translates the source asset's `## Content` into the target `language`. Honours `brand.md` `forbidden_terms[]` (verbatim, never translated) and brand/product translation policy when defined. Records `source_asset` and `source_language` in `generation_log`. |
| 5 | `skills/signal-research/SKILL.md` | Implemented. Reads `competitors.md`, `past-campaigns.md`, and `product.md` (all required). Default output: Summary, Competitor Landscape, Differentiation Opportunities, Lessons from Past Campaigns, Open Questions. Cites source rows inline. Does not read `approved-claims.md` — research output is internal; the public-content path enforces claims. |
| 6 | `skills/signal-price/SKILL.md` | Implemented. Reads `product.md` and `competitors.md` (both required). Output: Summary, Competitor Pricing, Recommendation, Jurisdiction Notes, Risks. Surfaces `market`/`jurisdiction` ambiguity in the recommendation rather than blocking. Never asserts a competitor pricing claim absent from `competitors.md`. The optional Phase 3 worker — kept implemented (not a TODO placeholder) since the routing surface advertised it. |
| 7 | **Updated** `skills/signal-worker/SKILL.md` | Routing table flipped from "Phase 3 — planned" to "Phase 3 — implemented" for all six asset_type families above. The "planned-but-not-implemented" branch was removed; the only remaining block is for unknown asset types (not in the table at all), which still set `status: blocked` with `"Unknown asset_type: <value>"` and return `awaiting-input`. |

**Other docs synced to Phase 3:**

- `skills/signal/reference/adding-workers.md` — asset-type routing table flipped to "implemented" for all six new workers; the planned-but-not-implemented language was removed.
- `skills/signal/reference/future-skill-registry.md` — old "Phase 3 — Additional workers" table split into a "Phase 3 — Implemented" section (the six workers above) and a "Future workers" section (`signal-image-edit`, `signal-competition`, `signal-sales`, `signal-labeling`).
- `skills/signal/reference/data-model.md` — top-of-file phase-status note updated to "Phase 0–3 implemented."
- `skills/signal-plan/SKILL.md` — channel→asset_type mapping no longer marks email/blog/web/research/copy as "Phase 3 planned." Added image/video/translation guidance for campaign planning.
- `skills/signal-create/SKILL.md` — quick-scope channel→asset_type mapping updated likewise; the "stamp the correct type even if the worker is not yet implemented" sentence simplified to "stamp the correct type. `signal-worker` blocks unknown asset types."
- `skills/signal/SKILL.md` — Phase 2/3 status paragraph updated to describe the full Phase 3 surface and the strategy-scope dispatch path.
- `README.md` — Status section moved to "Phase 0–3," scope table marks `strategy` as Implemented, the planned-but-not-implemented examples replaced with the working campaign / translation / blog flows.

**Worker contract uniformity (followed across all six new workers):**

- Frontmatter: `name`, `description`, `user-invocable: false`, `worker_version: 1`.
- Behaviour: parse asset, confirm `asset_type`, confirm required context, lazy-create `prompts/`, store the prompt at `prompts/A<N>-r<revision>-<worker>.md`, write content under `## Content`, append one `generation_log` entry with `worker_version`, `prompt_path`, `prompt_hash`, `content_hash`, set `status: done`.
- Verdicts: `phase-complete` on success, `awaiting-input` on block, fenced YAML at end of response per `gates.md`.
- Never write `task.md`.

**What stayed planned (not changed by Phase 3):**

- Phase 4 management surface (`signal-backlog`, `signal-recipe`, `signal-handoff`, `signal-retro`, `signal-team`).
- The rest of `signal-task` (`list`, `defer`, `create-deferred`, `promote`).
- Real channel adapters and the performance feedback loop.
- The future workers in `future-skill-registry.md` (`signal-image-edit`, `signal-competition`, `signal-sales`, `signal-labeling`).

**Phase 3 totals:** 6 new SKILL.md files, 1 updated SKILL.md (`signal-worker`), 7 reference/template docs synced. No schema changes — the Phase 0 data model already accounted for every asset_type these workers consume.
