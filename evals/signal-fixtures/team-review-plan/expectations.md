---
dispatch_skill: signal-team
ignore_fields: [updated_at, timestamp, actual_publish_time]
max_attempts: 3
---

# Fixture: team-review-plan

Tests that `signal-team` dispatches a second AI agent to review a campaign content plan, returns verified findings, and does not auto-apply changes.

## Snapshot point

The fixture freezes the system **before** the team review:

- `task.md` `phase: plan-review`, `scope: campaign`.
- `content-plan.md` exists with `status: approved`, covering a 2-channel (linkedin, twitter) × 2-language (en, es) matrix — 4 assets total.
- `.signal/context/brand.md` and `.signal/context/product.md` are present.

## Trigger

User runs: `signal-team review the content plan for S1`

The lead classifies the intent as `design-review`, confirms the plan with the user, gathers context (content-plan.md, brand.md, product.md, config.yaml), builds a prompt for a teammate provider, dispatches, verifies findings against the source artifacts, and presents results.

## Expected verdict

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Team review complete; see .signal/tasks/S1-multi-language-campaign/team-<provider>-design-review.md."
```

## Expected mutations

- A verified review artifact saved to `.signal/tasks/S1-multi-language-campaign/team-<provider>-design-review.md`.
- Raw teammate output saved to `.signal/team/<timestamp>-S1-<provider>-design-review.md`.
- No mutation to `task.md`, `content-plan.md`, or any task state — `signal-team` is read-only.
- Findings are presented to the user; no auto-apply.

## Reviewer-flagged regressions guarded

- Team review findings must be verified against the actual artifacts on disk before presentation. Unverified output is never shown.
- The `content-plan.md` asset plan must have correct `asset_type`, `channel`, `language` mappings (4 assets × 3 fields).
- Timeline assumptions (staggered publish, no dependencies) must be feasible.
- Tone consistency across languages must be checked — linkedin English and linkedin Spanish should have the same tone profile.
