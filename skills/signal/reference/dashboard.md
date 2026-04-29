# Signal7 Dashboard Rollup

`dashboard.md` is a human-readable task summary. `signal` regenerates rollup sections after phase advances. Phase skills may append decisions but do not regenerate the dashboard.

## Sections

1. `## Goal`
2. `## Brief`
3. `## Plan`
4. `## Assets`
5. `## Review`
6. `## Publish`
7. `## Status`
8. `## Decisions`

## Sources

| Section | Source |
|---|---|
| Goal | task.md body |
| Brief | brief.md summary |
| Plan | content-plan.md summary or placeholder |
| Assets | asset files at task root |
| Review | review.md status or placeholder |
| Publish | publish-log.md last entries or placeholder |
| Status | task.md frontmatter |
| Decisions | preserved append-only section |

Missing artifacts degrade to `_not yet written_`.

## Decisions

The `## Decisions` section is append-only. Entries use:

```markdown
- YYYY-MM-DDTHH:MM:SS - <author> - <decision>
```

Authors are phase names or `user`. Routine phase movement is not a decision; load-bearing choices are.
