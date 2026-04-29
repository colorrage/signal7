# Cross-system Boundaries

Signal7 is independent from Hyper7.

## Independence Rules

Signal7 skills must not:

- import Hyper7 skills
- require Hyper7 to be installed
- read `.hyper/tasks/*/task.md`
- depend on Hyper7 phase names or frontmatter
- invoke Hyper7 skills as part of Signal7 workflow

## Human Process Boundary

IT, website, code, and SEO work should be handled by Hyper7 or another engineering workflow. Signal7 may record that a business asset depends on that work, but the dependency is represented inside Signal artifacts.

Example:

```yaml
external_gate:
  description: Product landing page is live
  source_system: manual
  required_status: live
  status: pending
  evidence: null
  checked_at: null
```

## Manual Bridge

Default mode is manual. The user edits Signal artifacts to mark external gates `satisfied` or `waived`, including evidence such as a URL or release note.

## Future Adapter Rule

Future adapters must be external to both Signal7 and Hyper7. They may write Signal fields such as `external_gate.status`, but Signal7 itself still does not read Hyper7 internals.

This preserves independence even when projects coordinate across systems.
