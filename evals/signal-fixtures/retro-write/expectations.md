---
dispatch_skill: signal-retro
ignore_fields: [updated_at, timestamp, actual_publish_time]
max_attempts: 3
---

# Fixture: retro-write

Tests that `signal-retro project` writes a dated retro entry to `.signal/retro.md` with required and optional sections.

## Snapshot point

The fixture freezes the system **after** a project-level retro was written:

- `.signal/retro.md` exists with a `# Signal7 Project Retrospectives` heading.
- A dated `## Retro — 2026-05-01T11:30:00` section contains:
  - Required: *What worked*, *What didn't*, *What to do differently next time*.
  - Optional: *About Signal7 itself*, *About the project*.

## Trigger

User ran `signal-retro project` and provided content for all sections.

## Expected verdict

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Retro written."
```

## Expected mutations

- `.signal/retro.md` created (if absent) or appended to with a new dated section.
- Section has three required subsections (*What worked*, *What didn't*, *What to do differently next time*) plus two optional subsections (*About Signal7 itself*, *About the project*).
- No task `phase` or `awaiting` changes (retro is read-only reflection).
- Earlier entries preserved verbatim (append-only).
