# Signal7 Fixtures

Static `.signal/`-shaped scenarios that exercise the workflow contracts in `skills/signal/reference/`. Each fixture is a self-contained directory with a complete `.signal/` snapshot at a specific narrative point, plus an `expectations.md` describing what the next `signal` dispatch must do.

These fixtures are **specification artefacts**, not live tests. The smoke runner (`scripts/run-signal-fixtures.sh`) walks each fixture, prints the expectations, and validates the static structural invariants (frontmatter, status enums, idempotency key shape, presence of required sections). It does not execute the skills end-to-end — that requires a host (Claude Code, Codex, etc.) and a model.

A fixture is "passing" when:

1. Its `.signal/` snapshot is internally consistent (frontmatter parseable, status enum values legal, no `<TODO>` sentinels left in places where a phase skill would have replaced them).
2. Its `expectations.md` matches the verdict the corresponding phase skill should emit on the described next dispatch.

Any contract change to phase skills should update the matching fixture(s).

## Fixtures

| Fixture | Covers |
|---|---|
| `quick-social-happy/` | The full quick-scope path: brief approved → create → review (AI pass) → user approves → publish. End state is `phase: done`, archived. |
| `review-rejection-rework/` | A user rejection at the review-approval gate flips the asset to `status: needs-revision` and redirects to `create`. Verifies the rework loop spins forward, not in place. |
| `campaign-blocked-at-intake/` | `signal-brief` refuses `scope: campaign` with `awaiting-input` while `signal-plan` is unimplemented. |
| `publish-duplicate-skipped/` | Re-publishing the same asset hits an existing `idempotency_key` in `publish-log.md` and records `skipped-duplicate` rather than appending a duplicate `published` entry. |
| `compliance-default-clear/` | A non-regulated brief sets `compliance.md` `status: clear` (the new default) and `signal-review` does **not** halt. Guards against re-introducing the `questions-open` default. |

## How fixtures are organised

Each fixture directory contains:

```
fixture-name/
  .signal/                          # snapshot of state at the narrative point
    tasks/S1-<slug>/
      task.md
      dashboard.md
      brief.md
      compliance.md
      A1-<slug>.md
      review.md                     # if narrative is past create
      publish-log.md                # if narrative is past review
      prompts/A1-r0-signal-social.md  # if narrative is past create
    archive/                        # for terminal-state fixtures
    context/
      brand.md
      product.md
      approved-claims.md
    config.yaml
  expectations.md                   # what the next dispatch must do
```

`expectations.md` describes:

- **Trigger**: the user input or phase that fires next.
- **Expected verdict** from the phase skill (verbatim YAML block).
- **Expected mutations** to `.signal/` state (which files change, which fields).
- **Reviewer-flagged regressions** if applicable, with the issue number.

## Running

From the repo root:

```bash
scripts/run-signal-fixtures.sh
```

The runner prints each fixture's expectations and runs structural checks. It exits non-zero if any structural invariant fails.
