---
name: signal-brief
description: >
  Internal Signal7 phase skill that clarifies business intent, classifies
  scope, writes brief.md and compliance.md, and returns an approval verdict.
user-invocable: false
---

# signal-brief

Turn the user's request into an approved business brief. Do not write any field of `task.md` — `signal` mirrors `scope` from `brief.md` after this skill returns `phase-complete`.

## Read First

- `skills/signal/reference/data-model.md`
- `skills/signal/reference/gates.md`
- `skills/signal/reference/intake-triage.md`
- `skills/signal/reference/compliance-and-audit.md`

## Responsibilities

- Ask at most one clarification question per dispatch.
- Classify scope as `quick`, `campaign`, or `strategy`.
- Write `brief.md` from `skills/signal/templates/brief.md`. Replace every `<TODO>` sentinel with a resolved value before returning `phase-complete`.
- Create `compliance.md` for every task from `skills/signal/templates/compliance.md`. The template default (`regulated_domain: false`, `status: clear`) is the safe baseline. *Raise* it only when triage detects regulated content.
- On approval re-dispatch, record the user's reply on `brief.md` `## Approval` (`Status: approved` or `Status: rejected — <reason>`) before returning `phase-complete`.
- Never write `task.md` `phase`, `awaiting`, or `scope`.

## Phase 1 Scope Guards

While Phase 2 (`signal-plan` + `signal-plan-review`) is unimplemented, `signal-brief` must keep `task.md` out of the unreachable `plan` / `plan-review` states.

- If triage classifies the request as `campaign`, set `brief.md` `scope: campaign` and return `awaiting-input` with: *"This looks like a campaign (multi-channel / multi-language / >3 assets / explicit campaign intent). The campaign workflow is planned for Phase 2 and is not implemented yet. I can scope it down to a single channel and language for a quick run today, or capture the brief and stop."* Do not advance.
- If triage classifies as `quick` or `strategy`, proceed.

`signal` enforces the same guard at the orchestrator level: even if `brief.md` lands with `scope: campaign`, `signal` will re-emit the same blocked message instead of advancing.

## Compliance Ownership

`signal-brief` owns creation of `compliance.md` for every task.

For non-regulated work (the default): leave the template as-is. The frontmatter already records:

```yaml
regulated_domain: false
approved_claims_required: false
status: clear
```

Ask directly when the request may involve healthcare, finance, legal, children's products, privacy-sensitive data, regulated advertising, evidence-backed claims, or jurisdiction-specific restrictions. If the answer is yes (or the user is unsure), raise the frontmatter to:

```yaml
regulated_domain: true   # or unknown
approved_claims_required: true   # when claims will appear in public content
status: questions-open
```

…and record the open questions under `## Open Questions`. Keep `status: questions-open` until those questions are resolved before review.

The default flipped from `questions-open` to `clear` so non-regulated tasks no longer silently halt in `signal-review` if `signal-brief` forgets to write a non-regulated answer.

## Quick Scope Guard

Quick scope means one channel, one language, and no more than three derived assets. Record `asset_ceiling: 3` in `brief.md`.

## Output Contract

Allowed verdicts: `awaiting-input`, `awaiting-approval`, `phase-complete`.

When asking a question:

```yaml
signal_verdict:
  verdict: awaiting-input
  target: null
  summary: "Question text here."
```

When refusing campaign scope (Phase 1 guard):

```yaml
signal_verdict:
  verdict: awaiting-input
  target: null
  summary: "Campaign scope is Phase 2; not yet implemented. Rescope or cancel?"
```

When ready for approval:

```yaml
signal_verdict:
  verdict: awaiting-approval
  target: null
  summary: "Brief and compliance artifacts are ready for approval."
```

On approval (re-dispatch with brief.md Approval section recorded):

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Brief approved."
```
