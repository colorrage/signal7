# Spec — T1: Phase 0/1 hygiene + minimal Phase 2 enablers

## Acceptance criteria

A1. README accurately states what's implemented (Phase 0/1 only) and what's planned (Phase 2+). The `.signal/brand.md` vs `.signal/context/brand.md` path mismatch is gone.

A2. Intake (`signal-brief` + `intake-triage.md`) refuses `scope: campaign` with a clear "Phase 2 not yet implemented" message until `signal-plan` lands. Quick scope expanding past the asset ceiling returns `awaiting-input` (not the unbacked `redirect target: plan`).

A3. `compliance.md` template ships with `status: clear` by default. `signal-brief` raises to `questions-open` only when triage flags regulated/uncertain. Comment in template explains the chosen default.

A4. Review-failure rework loop works end-to-end: when `signal-review` fails an asset, the asset's `status` flips back to `todo` (or `needs-revision`) so `signal-create` picks it up on redirect.

A5. Every phase-skill `## Output Contract` lists every verdict the skill body can emit (e.g. `signal-create` lists `redirect`; `signal-review` lists an `awaiting-input` for compliance blockers).

A6. `bootstrap.md`, `gates.md`, `archive.md`, `data-model.md`, `adding-workers.md` tag every reference to a non-existent skill (`signal-plan`, `signal-plan-review`, `signal-task`, `signal-backlog`, `signal-recipe`, additional workers) as Phase 2/3 future work, OR move it into `future-skill-registry.md`.

A7. `signal/SKILL.md` documents the dispatch contract for every phase skill (inline vs sub-agent vs Skill tool), not only `signal-plan-review`.

A8. `signal-publish` either excludes `publish_at: null` from the SHA-256 idempotency hash, or requires `publish_at` to be set before publishing. The chosen rule is documented.

A9. `signal-publish` and `data-model.md` define rate-limit semantics: a `blocked-rate-limit` status in the publish-log enum, a documented defer-vs-block rule, and a clear behavior at the limit.

A10. Templates (`brief.md`, `asset.md`, `compliance.md`, `dashboard.md`) replace pre-stamped fallback values (`regulated_domain: unknown`, `asset_type: social-copy`, `channel: none`, un-substituted `<phase>`) with `<TODO>` sentinels. `signal-brief` and `signal-create` validate sentinels are filled before returning `phase-complete`.

A11. `signal/SKILL.md` mirrors `scope` from `brief.md` into `task.md` after `signal-brief` returns `phase-complete`. `signal-brief` no longer writes top-level frontmatter directly. The architectural exception in `gates.md` is removed.

A12. `signal-create` either drops the email/blog/web asset_type mappings (Phase 1 honest scope), or returns a clear "worker not implemented" awaiting-input with channel-specific guidance. README is consistent with whichever is chosen.

A13. A `.signal` fixtures suite under `evals/signal-fixtures/` covers: quick-social happy path, review-rejection rework loop, campaign-blocked-at-intake, publish-duplicate-skipped. A smoke runner under `scripts/run-signal-fixtures.sh` walks each fixture and asserts artifacts produced.

A14. Misc polish: `state-graph.md` listed in `signal/SKILL.md` Read-First; `signal-publish` added to bootstrap-side skills in `bootstrap.md`; workers declare `worker_version` (frontmatter or constant); intake-triage example for "regulated single social post" disambiguated; rejection/redirect path documented in approval gates; `prompts/` ownership documented.

B1. Minimal `signal-task` skill exists with two operations only: `cancel` (sets `phase: cancelled`, archives the task) and `status` (read-only summary). Closes the cancellation ownership gap. Other operations (list, defer, create-deferred) deferred to Phase 2 proper.

B2. Translation flow stops stalling: `signal-review` supports a partial-review mode where source assets are reviewed before their dependent translation assets are dispatched. Path: source create → source review → translation create → translation review. Documented in `state-graph.md`.

B3. `signal-review` records the user's approval/rejection response into the matching approver row in `review.md` on re-dispatch (not just by mutating `awaiting:`). Rejection populates `rejected_reason` and triggers `redirect target: create` after flipping the affected asset back to `todo`.

C0. impl-plan.md (in hyper7 archive) gains a "Phase 0/1 amendments" appendix listing every behavior-changing fix in this task with its rationale. Editorial-only doc fixes do not need entries; behavior changes (A2, A3, A4, A8, A9, A11, B1, B2, B3) do.

## ToC — subtask index

| ID | Title | Depends |
|---|---|---|
| T1.1 | Editorial doc + template hygiene (A1, A6, A10, A14) | — |
| T1.2 | Block campaign + quick-overflow at intake (A2) | — |
| T1.3 | Compliance default flip (A3) | — |
| T1.4 | Review-failure rework loop (A4) | — |
| T1.5 | Output-contract completeness across phase skills (A5) | T1.2, T1.4 |
| T1.6 | Dispatch contract per phase skill (A7) | — |
| T1.7 | Publish hardening: idempotency null-publish_at + rate-limit semantics (A8, A9) | — |
| T1.8 | scope mirror moved from signal-brief to signal (A11) | — |
| T1.9 | Email/blog scope honesty + worker registry (A12) | T1.1 |
| T1.10 | Fixtures + smoke runner (A13) | T1.1–T1.9 |
| T1.11 | Minimal signal-task (cancel + status) (B1) | T1.6 |
| T1.12 | Partial-review for translation flow (B2) | T1.4 |
| T1.13 | Recording approval/rejection responses in review.md (B3) | T1.4 |
| T1.14 | impl-plan.md amendments appendix (C0) | T1.2, T1.3, T1.4, T1.7, T1.8, T1.11, T1.12, T1.13 |

## Out of scope

- `signal-plan` and `signal-plan-review` skills (Phase 2 proper).
- `signal-backlog`, `signal-recipe`, `signal-handoff`, `signal-retro`, `signal-team` (Phase 2/3).
- New content workers (`signal-copy`, `signal-image`, `signal-video`, `signal-translate`, `signal-research`, `signal-price`) — only the email/blog *honesty* call is in scope, no actual worker implementation.
- Strategic Bucket C work: campaign-as-its-own-primitive (`C<N>`), performance feedback loop, stakeholder/role model, cross-jurisdictional compliance, channel adapter contract, cost/budget tracking, portfolio dashboard. These are deferred until the user makes the A-vs-C product call.
- Migration of existing `.signal/` task data — Phase 0/1 has no real users yet, so no migration path is required.

## Edge cases

- A task already in flight when these changes land could have `compliance.md` at `questions-open` from the old default. T1.3 must not retroactively flip in-flight tasks; it changes only the *template* and `signal-brief`'s write behavior. New tasks get the new default.
- A task whose `signal-review` failed assets under the old behavior may have stale `status: done` on assets that should have been `todo`. T1.4 should accept either state on re-dispatch (forward-compatible) and only write the new shape going forward.
- `publish-log.md` entries with `publish_at: null` written under the old idempotency rule must remain valid. T1.7 keeps reads tolerant of the old hash; only new writes adopt the new rule. Document this in `data-model.md`.
- Translation partial-review (T1.12) must not break monolingual quick tasks. The path is opt-in based on the presence of translation assets in the eligible set.
- The minimal `signal-task` (T1.11) must not advertise itself in `signal/SKILL.md` Phase Dispatch as something it isn't. It only handles cancel and status; everything else returns "Phase 2".
