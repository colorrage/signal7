---
dispatch_skill: signal-backlog
ignore_fields: [updated_at, timestamp, actual_publish_time]
max_attempts: 3
---

# Fixture: backlog-add-promote

Tests two `signal-backlog` operations: `add` writes a backlog entry with `B<N>` id, and `promote B<N>` creates a deferred `S<N>` task folder seeded from the backlog entry.

## Snapshot point

The fixture freezes the system **after** both operations completed:

- `backlog.md` has B1 (`status: promoted`, `promoted_to: S1`) and B2 (`status: dropped`).
- `.signal/tasks/S1-linkedin-pr-post/` exists with `task.md` (`phase: deferred`, seeded from B1 title and description) and `dashboard.md`.
- The `task.md` body includes a footer line `Promoted from backlog B1.`.

## Trigger

User ran:

1. `signal-backlog add "LinkedIn PR post" description: "LinkedIn post about the new enterprise pricing tier" scope: quick channels: linkedin languages: en`
2. `signal-backlog promote B1`

## Expected verdict

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "promote B1 completed."
```

## Expected mutations

- `backlog.md` B1 entry updated: `status: promoted`, `promoted_to: S1`, `promoted_at: <timestamp>`.
- `.signal/tasks/S1-linkedin-pr-post/task.md` created from `templates/task.md` with `id: S1`, `title: LinkedIn PR post`, `phase: deferred`, `scope: quick`.
- `.signal/tasks/S1-linkedin-pr-post/dashboard.md` created from `templates/dashboard.md`.
- B1 remains in backlog (ids are never reused).
- B2 is untouched (`status: dropped`).
