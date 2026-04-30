# Fixture: campaign-blocked-at-intake

Guards the Phase 1 campaign refusal. The user gives a multi-channel goal, `signal-brief` writes `brief.md` with `scope: campaign`, but must refuse to advance because `signal-plan` is unimplemented.

This was reviewer #1 critical #1 ("quick-scope redirect to plan is a dead-end") and reviewer #4 critical #2 ("orchestrator dispatches a phase skill that doesn't exist").

## Snapshot point

The fixture freezes the system **after `signal-brief` finished triage but before approval is requested**:

- `task.md` `phase: brief`, `awaiting: null`, `scope: unknown`
- `brief.md` written with `scope: campaign` and the channel/language list
- `compliance.md` written at `status: clear`

## Trigger

`signal` resumes the task; `signal-brief` re-evaluates the brief and detects `scope: campaign`.

## Expected verdict

```yaml
signal_verdict:
  verdict: awaiting-input
  target: null
  summary: "Campaign scope is Phase 2; not yet implemented. Rescope or cancel?"
```

## Expected mutations

- `task.md` `awaiting: user-input` (set by `signal`).
- `task.md` `scope` is **not** advanced to `campaign` because `signal` only mirrors `scope` after `phase-complete`. This is intentional — leaving `scope: unknown` in `task.md` keeps the task out of the unreachable `phase: plan`.
- No `phase: plan` reachable. No `redirect target: plan` emitted.

## Reviewer-flagged regressions guarded

- Issue #1 critical #1, #2, #4: no dispatch into a non-existent skill.
- Issue #4 critical #2: orchestrator does not advance into `phase: plan` while `signal-plan` is unimplemented.
- `intake-triage.md`: must not contradict the Phase 1 awaiting-input fallback.
