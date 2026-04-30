# Checks — T1: Phase 0/1 hygiene + minimal Phase 2 enablers

**Overall:** pass

This is a docs-and-skill-instructions repository (no source code). Verification is structural rather than test-suite based: the `evals/signal-fixtures/` suite locks in the contracts that the reviewers flagged as broken, and the smoke runner asserts every fixture's `.signal/` snapshot is internally consistent and follows the new contracts.

## stabilize

```
$ bash scripts/run-signal-fixtures.sh
=== fixture: campaign-blocked-at-intake ===   PASS
=== fixture: compliance-default-clear ===     PASS
=== fixture: publish-duplicate-skipped ===    PASS
=== fixture: quick-social-happy ===           PASS
=== fixture: review-rejection-rework ===      PASS
Signal7 fixtures: 5 passed, 0 failures
```

Each fixture pins one reviewer-flagged regression (campaign dispatch hole, compliance default, publish idempotency, quick-scope happy path, review-failure rework loop). Future contract drift on any of those points fails the runner.

## review

Spec-compliance check against `spec.md` § Acceptance criteria:

| AC | Status | Evidence |
|---|---|---|
| A1 — honest README | pass | `README.md` § Status names exactly the 8 implemented `SKILL.md` files; `.signal/context/brand.md` path corrected throughout |
| A2 — campaign / overflow blocked at intake | pass | `signal-brief` refusal, `signal-create` Phase 1 awaiting-input branch, `signal` orchestrator final guard, `gates.md` "must name an implemented phase" rule, `campaign-blocked-at-intake` fixture |
| A3 — compliance default `clear` | pass | `templates/compliance.md` ships at `status: clear` with explanatory comment; `signal-brief` raises only on detection; `compliance-default-clear` fixture |
| A4 — review-failure rework loop | pass | `signal-review` flips to `status: needs-revision`; `signal-create` widened eligibility; `data-model.md` enum gained `needs-revision`; `review-rejection-rework` fixture |
| A5 — output contracts complete | pass | every phase skill enumerates allowed verdicts and shows YAML for each |
| A6 — missing skills tagged Phase N | pass | `bootstrap.md`, `gates.md`, `archive.md`, `data-model.md`, `adding-workers.md`, `future-skill-registry.md` all tag unimplemented references explicitly |
| A7 — dispatch contract per skill | pass | new `portability.md` § Dispatch Contract table covers every call site |
| A8 — `publish_at: null` idempotency rule | pass | `signal-publish` and `data-model.md` define the literal `null` token rule; backwards compatible with prior writes |
| A9 — rate-limit semantics + status | pass | `blocked-rate-limit` enum value; `on_limit: defer \| block` config; `signal-publish` defines defer-vs-block |
| A10 — `<TODO>` sentinels | pass | `templates/asset.md` (6), `templates/brief.md` (3), `templates/dashboard.md` (2); `signal-create` validates before phase-complete |
| A11 — scope mirror in `signal` | pass | `signal-brief` no longer writes any `task.md` field; `signal/SKILL.md` documents the mirror; `gates.md` ownership table lists only `signal` and `signal-task` |
| A12 — email/blog honesty | pass | mappings retained but `signal-worker` blocks with explicit "Phase 2" message; README scoped down to social-only |
| A13 — fixtures + smoke runner | pass | 5 fixtures, runner exits 0 |
| A14 — misc polish | pass | `state-graph.md` in `signal/SKILL.md` Read First; `signal-publish` listed in `bootstrap.md` write-side; `worker_version` declared on `signal-social` and `signal-worker`; intake-triage example fixed; rejection path documented in `gates.md`; `prompts/` ownership documented in `compliance-and-audit.md` |
| B1 — minimal `signal-task` | pass | `skills/signal-task/SKILL.md` ships `cancel` + `status`; refuses other operations |
| B2 — partial-review for translation | pass | `state-graph.md` § Partial Review; `signal-create` `redirect target: review`; `signal-review` partial-review block |
| B3 — record approval responses | pass | `signal-review` redispatch writes `recorded_response` / `rejected_reason` / `comment` / `updated_at`; `data-model.md` per-approver schema gained the fields |
| C0 — impl-plan.md amendments appendix | pass | new "Phase 0/1 Amendments — 2026-04-30" section in `hyper7/.hyper/archive/T1-design-biz-ops-hyper-system/impl-plan.md` |

Bug-finding sub-pass — checked for the specific reviewer-flagged traps:

- No skill emits `redirect target: plan` or `redirect target: plan-review` while their skills are unimplemented. Every reference is in narrative context that names the Phase 2 condition.
- `compliance.md` default cannot silently halt non-regulated tasks (template ships at `clear`).
- Review failures cannot loop without revision (status flip + eligibility widening + revision increment).
- `publish_at: null` idempotency is deterministic across writes.
- No template ships pre-filled with a fallback that masks a missing answer (`<TODO>` sentinels everywhere they matter).

Standards / conventions sub-pass — host-neutral language preserved (`portability.md` § Tool Neutrality), Hyper7 independence preserved (`cross-system-boundaries.md` unchanged in spirit; only doc cleanups applied), append-only ledger contract preserved (`data-model.md` publish-log section unchanged in shape, only enum extended).

## QA

User-facing acceptance criteria mapped to behaviour:

- "Phase 0/1 not locked; we can change things, but document why" → impl-plan.md gained the **Phase 0/1 Amendments** appendix with one sub-section per behaviour-changing fix and an explicit Why / Reversal block on each. Editorial-only changes (README rewording, doc tagging) are excluded by design and noted as such.
- "Less emphasis on regulations" → compliance default flipped, `enabled_channels` config no longer foregrounds compliance-heavy channels, the "Items deliberately not changed" section of the impl-plan amendments names cross-jurisdictional compliance as **not prioritised**, memory note saved at `~/.claude/projects/-Users-adrianfilip-hyper/memory/signal7_product_direction.md`.
- "Phase 2 starts only after Phase 0/1 fixes" → Phase 1 guards in `signal-brief` and `signal` keep `task.md` out of the unreachable `phase: plan`. Clear "Phase 2 not yet implemented" messages route the user to either rescope or cancel.
- "Implement priority items" → 14 of 14 acceptance criteria pass; 5 fixtures green; impl-plan amendments record the shape of every behaviour change so the next session can pick up Phase 2 work without re-deriving.

## Risks / open follow-ups

1. The 14 empty Phase 2/3 skill folders (`signal-backlog`, `signal-copy`, `signal-handoff`, `signal-image`, `signal-plan`, `signal-plan-review`, `signal-price`, `signal-recipe`, `signal-research`, `signal-retro`, `signal-team`, `signal-translate`, `signal-video`) under `skills/` have no `SKILL.md` and no host-loadable content. They are harmless (no host will load a folder without a SKILL.md), but they are also not the documented `future-skill-registry.md` placeholders. If the registry's placeholder convention is desired, each folder needs a minimal `TODO` SKILL.md. Deferred — small, mechanical, can land with Phase 2 first work.
2. The smoke runner does **not** execute Signal7 skills end-to-end. End-to-end verification still requires running Signal7 on a real host (Claude Code) with a real `.signal/` and a real model. The fixtures are a contract net, not a substitute.
3. `evals/signal-fixtures/quick-social-happy` includes a complete `.signal/context/` triple, but the four other fixtures rely on the same baseline implicitly. Adding them once at the suite root and pointing each fixture's `expectations.md` at the shared baseline would deduplicate ~80 lines. Deferred for cosmetic cleanup.
