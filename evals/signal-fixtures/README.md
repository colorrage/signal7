# Signal7 Fixtures

Static `.signal/`-shaped scenarios that exercise the workflow contracts in `skills/signal/reference/`. Each fixture is a self-contained directory with a complete `.signal/` snapshot at a specific narrative point, plus an `expectations.md` describing what the next `signal` dispatch must do.

These fixtures are **specification artefacts**, not live tests. The smoke runner (`scripts/run-signal-fixtures.sh`) walks each fixture, prints the expectations, and validates the static structural invariants (frontmatter, status enums, idempotency key shape, presence of required sections). It does not execute the skills end-to-end — that requires a host (Claude Code, Codex, etc.) and a model.

A fixture is "passing" when:

1. Its `.signal/` snapshot is internally consistent (frontmatter parseable, status enum values legal, no `<TODO>` sentinels left in places where a phase skill would have replaced them).
2. Its `expectations.md` matches the verdict the corresponding phase skill should emit on the described next dispatch.

Any contract change to phase skills should update the matching fixture(s).

## Fixtures

All 9 fixtures are listed below. The last five are orchestrator-level and Phase 2 campaign fixtures.

| Fixture | Covers |
|---|---|
| `quick-social-happy/` | The full quick-scope path: brief approved → create → review (AI pass) → user approves → publish. End state is `phase: done`, archived. |
| `review-rejection-rework/` | A user rejection at the review-approval gate flips the asset to `status: needs-revision` and redirects to `create`. Verifies the rework loop spins forward, not in place. |
| `publish-duplicate-skipped/` | Re-publishing the same asset hits an existing `idempotency_key` in `publish-log.md` and records `skipped-duplicate` rather than appending a duplicate `published` entry. |
| `compliance-default-clear/` | A non-regulated brief sets `compliance.md` `status: clear` (the new default) and `signal-review` does **not** halt. Guards against re-introducing the `questions-open` default. |
| `review-redirect-create/` | **Orchestrator-level.** A user rejection at the review gate triggers `signal-review` returning `redirect target: create`; `signal` must then dispatch `signal-create`. Verifies cross-skill routing. |
| `publish-done-archive/` | **Orchestrator-level.** After all assets are published, `signal-publish` returns `phase-complete`; `signal` advances to `phase: done`, regenerates the dashboard, and moves the task to `.signal/archive/`. Verifies the full terminal transition. |
| `campaign-brief-to-plan/` | **Campaign.** A campaign brief (`scope: campaign`) is approved and `signal-plan` generates `content-plan.md` with asset stubs. Verifies the brief→plan transition. |
| `plan-review-reject-rework/` | **Campaign.** A plan review rejects the content plan, triggering a redirect back to `signal-plan` for rework. Verifies the plan-review rework loop. |
| `campaign-multi-asset-create/` | **Campaign.** A campaign plan with multiple channels and languages produces matching `A<N>-*.md` asset stubs. Verifies correct asset generation from the content plan. |

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

`expectations.md` contains YAML frontmatter with dispatch metadata, followed by markdown sections:

```yaml
---
dispatch_skill: signal-publish
ignore_fields: [updated_at, timestamp, actual_publish_time]
max_attempts: 3
---
```

| Field | Required | Default | Description |
|---|---|---|---|
| `dispatch_skill:` | yes | — | The skill to dispatch (e.g. `signal-brief`, `signal-publish`, or `signal` for orchestrator-level fixtures). Must match a skill folder under `skills/`. |
| `ignore_fields:` | no | `[updated_at, timestamp, actual_publish_time]` | Frontmatter fields whose exact values are ignored during mutation diffing. Field existence is still checked; the value is not compared. |
| `max_attempts:` | no | `3` | Maximum retry attempts for behavioral failures. A single passing attempt marks the fixture pass. Host-level errors (sub-agent crash, timeout, missing skill) do not count against this budget. |

The markdown body of `expectations.md` describes:

- **Trigger**: the user input or phase that fires next.
- **Expected verdict** from the dispatched skill, or from the final orchestrator transition when `dispatch_skill: signal` (verbatim YAML block).
- **Expected mutations** to `.signal/` state (which files change, which fields).
- **Orchestrator routing** (orchestrator-level fixtures only): the multi-skill dispatch sequence `signal` must execute.
- **Reviewer-flagged regressions** if applicable, with the issue number.

## QA strategy (three layers)

Signal7 QA is organised into three layers:

### Layer 1 — Static checks

`scripts/run-signal-fixtures.sh` validates structural invariants without executing skills or calling a model. Completes in under 5 seconds. Checks:

- Frontmatter parseable in all `.md` files.
- Legal status enums (asset, compliance, approver, publish-log).
- No `<TODO>` sentinels left in post-phase fixture state.
- Idempotency key shape: `sha256:<64 hex chars>`.
- Verdict encoding parity: every skill's `## Output Contract` YAML block must contain valid `signal_verdict` keys (`verdict`, `target`, `summary`) and legal verdict values.
- Output contract completeness: all verdicts declared in a skill's body must appear in its `## Output Contract` YAML examples and vice versa.
- Fixture expectation parity: each fixture's expected verdict must match an allowed verdict in the target skill's output contract.

### Layer 2 — Replay fixtures

`scripts/replay-fixture.sh <fixture-name>` copies the fixture to a temp directory, dispatches the declared skill through the host's sub-agent, extracts the final `signal_verdict` YAML block, and compares verdict + target exactly plus summary for key-term presence (lenient mode). `scripts/run-all-fixtures.sh` replays all 7 fixtures sequentially and prints a summary table.

Both scripts pass `--host claude|opencode` to select the dispatch CLI. Run `--strict` for exact summary text comparison. Run `--verbose` for full dispatch output.

### Layer 3 — Manual QA checklist

`evals/qa-checklist.md` contains a 5-step pre-ship verification checklist. Steps cover: bootstrap from scratch, quick-scope happy path, review rejection rework, campaign planning, and duplicate publish.

---

## Retry policy

The replay harness retries each fixture up to `max_attempts:` times (default 3). A single passing attempt marks the fixture as pass. Additional attempts are skipped once pass is recorded.

Host-level errors (sub-agent crash, timeout, missing skill, no verdict block produced) do **not** count against the retry budget. There is a separate cap of 5 host errors per fixture, after which the fixture exits 2 (execution error).

## Lenient vs strict comparison

| Mode | Behaviour | Flag |
|---|---|---|
| **Lenient** (default) | Verdict and target must match exactly. Summary is checked for key-term presence — expected key terms must appear somewhere in the actual summary. Minor text divergence is tolerated. | *(none)* |
| **Strict** | Verdict, target, and summary must all match exactly after whitespace normalisation. Use for fixtures where exact phrasing matters. | `--strict` |

## Exit code contract

All QA scripts follow the same exit code semantics:

| Exit code | Meaning |
|---|---|
| `0` | Pass — all checks passed or all fixtures replayed successfully. |
| `1` | Mismatch / behavioral failure — a check or fixture failed on content (wrong verdict, unexpected mutation, exhausted retries). |
| `2` | Execution error — bad arguments, missing dependencies, host unavailable, unknown skill, no verdict block produced. |

## Running

From the repo root:

```bash
# Layer 1: static structural checks (no LLM, <5s)
scripts/run-signal-fixtures.sh

# Layer 2: replay a single fixture through a live model
scripts/replay-fixture.sh <fixture-name> [--host claude|opencode] [--strict] [--verbose]

# Layer 2: replay all fixtures sequentially
scripts/run-all-fixtures.sh [--host claude|opencode] [--strict] [--verbose]

# Layer 3: manual pre-ship checklist (interactive)
cat evals/qa-checklist.md
```

`run-signal-fixtures.sh` prints each fixture's expectations and runs structural checks. It exits non-zero if any structural invariant fails.

`replay-fixture.sh` and `run-all-fixtures.sh` require a live host (`claude` or `opencode`) and a model to execute skills end-to-end. See the exit code contract above for interpreting results.
