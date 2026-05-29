---
dispatch_skill: signal-handoff
ignore_fields: [updated_at, timestamp, actual_publish_time]
max_attempts: 3
---

# Fixture: handoff-write

Tests that `signal-handoff` creates `handoff.md` in the current task folder with all required sections, and appends a `## Decisions` entry to `dashboard.md`.

## Snapshot point

The fixture freezes the system **after** the handoff was written:

- `task.md` `phase: review`, `awaiting: null`.
- `handoff.md` exists in the task folder with a dated heading and six required sections: *Where this stands*, *Decisions made this session*, *Paths already ruled out*, *Uncommitted work*, *Open questions*, *Next step*.
- `dashboard.md` contains a `## Decisions` entry recording the handoff.

## Trigger

User ran `signal-handoff S1` while the task was at `phase: review`.

## Expected verdict

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Handoff written for S1."
```

## Expected mutations

- `handoff.md` created at `.signal/tasks/S1-active-review/handoff.md` with a `## Handoff — <ISO>` heading.
- Required sections present: *Where this stands*, *Decisions made this session*, *Paths already ruled out*, *Uncommitted work*, *Open questions*, *Next step*.
- `dashboard.md` `## Decisions` has new entry: `- <ISO> - user - handoff written: <summary>`.
- `task.md` `phase` and `awaiting` unchanged (handoff does not modify task state).
