# Dashboard — T1: Phase 0/1 hygiene fixes from review feedback + minimal Phase 2 enablers

## Goal

Implement Bucket A (all 14 hygiene items from the 5 review issues) plus three small Phase 2 enablers: minimal `signal-task` (cancel + status only), partial-review for translation flow, and recording approval responses in `review.md`. Update the hyper7 archive impl-plan.md with a "Phase 0/1 amendments" appendix listing every behavior-changing fix and its rationale.

**Why:** Codex 5.5 and Opus 4.7 independently flagged the same Phase 1 contract gaps and silent-halt traps. Phase 2 cannot land safely on top of these.

## Plan

14 vertical slices indexed in `spec.md` § ToC: editorial doc/template hygiene (T1.1), intake guards (T1.2), compliance default flip (T1.3), review rework loop (T1.4), output-contract completeness (T1.5), dispatch contract (T1.6), publish hardening (T1.7), scope mirror (T1.8), email/blog honesty (T1.9), fixtures (T1.10), minimal `signal-task` (T1.11), partial-review for translation (T1.12), approval-response recording (T1.13), impl-plan.md appendix (T1.14).

## Progress

All 14 slices implemented. Files touched:

- `signal7/README.md`, `signal7/AGENTS.md`
- `signal7/skills/signal/SKILL.md`
- `signal7/skills/signal-brief/SKILL.md`
- `signal7/skills/signal-create/SKILL.md`
- `signal7/skills/signal-review/SKILL.md`
- `signal7/skills/signal-publish/SKILL.md`
- `signal7/skills/signal-worker/SKILL.md`
- `signal7/skills/signal-social/SKILL.md`
- `signal7/skills/signal-task/SKILL.md` (new)
- `signal7/skills/signal/reference/{data-model,gates,bootstrap,archive,state-graph,adding-workers,portability,intake-triage,compliance-and-audit,future-skill-registry}.md`
- `signal7/skills/signal/templates/{compliance,asset,brief,dashboard}.md`
- `signal7/evals/signal-fixtures/` (5 fixtures + README)
- `signal7/scripts/run-signal-fixtures.sh` (new)
- `hyper7/.hyper/archive/T1-design-biz-ops-hyper-system/impl-plan.md` (Phase 0/1 Amendments appendix)

## Verification

5 fixtures pass under `scripts/run-signal-fixtures.sh`. All 14 acceptance criteria from `spec.md` met. See `checks.md` for evidence per criterion.

## Status

**Phase:** done · **Awaiting:** none

## Decisions

<!--
Append-only log of load-bearing choices made during the task.
Format: `- YYYY-MM-DD — <author> — <decision> (<context>)`
Authors: discover | plan | implement | verify | docs | user
-->

- 2026-04-30 — discover — Scope set to research (synthesis + recommendations only, no code changes)
- 2026-04-30 — discover — Used PAT from signal7 git remote to read private repo issues; confirmed by user mid-session
- 2026-04-30 — user — Phase 0/1 not locked; primitive changes allowed if rationale is recorded in impl-plan.md
- 2026-04-30 — user — Phase 2 (signal-plan) starts only after Phase 0/1 fixes land
- 2026-04-30 — user — Approved implementation of priority items across all three buckets
- 2026-04-30 — discover — Scope upgraded from research to feature after exploration approval
- 2026-04-30 — plan — Killer-use-case decision (A vs C from issue #5) deferred; only affects Bucket C
- 2026-04-30 — user — Q2 answered: positioning is mostly C (general marketing-ops) with some A elements, less emphasis on regulations
- 2026-04-30 — user — Plan approved with judgment-call A12; implementation started
- 2026-04-30 — implement — A12 reversed: keep email/blog/copy/landing-page asset_type mappings (more aligned with C-direction); make signal-worker block them honestly with "Phase 2 not yet implemented" rather than dropping the taxonomy
- 2026-04-30 — implement — `compliance.md` template default flipped from `questions-open` to `clear`; `signal-brief` raises only on detected regulated content. Aligns with C-direction (less emphasis on regulations)
- 2026-04-30 — implement — `redirect target: plan` reserved for Phase 2; Phase 1 returns `awaiting-input` instead. `signal` orchestrator enforces a final guard against advancing into unimplemented phases
- 2026-04-30 — implement — Asset-status enum gained `needs-revision`; `signal-review` flips affected assets before redirecting to `create`
- 2026-04-30 — implement — `publish_at: null` participates in the SHA-256 idempotency hash as the literal token `null`; backwards compatible with prior writes
- 2026-04-30 — implement — `publish_rate_limits.on_limit: defer | block` added; default `defer`; `blocked-rate-limit` status added to publish-log enum
- 2026-04-30 — implement — `signal-task` (cancel + status only) ships in Phase 1; full management surface deferred to Phase 2
- 2026-04-30 — implement — Partial-review path documented in `state-graph.md` for translation flow; never engages until Phase 3 `signal-translate` ships
- 2026-04-30 — verify — 5 fixtures pass; all 14 acceptance criteria met; checks.md verdict pass
