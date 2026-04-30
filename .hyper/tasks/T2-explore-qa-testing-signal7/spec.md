# Spec — T2: Signal7 QA Testing System

## Acceptance Criteria

1. `scripts/run-signal-fixtures.sh` exits 0 on all 7 fixtures and runs three new Layer 1 checks (verdict-encoding parity, output-contract completeness, fixture-expectation parity) that complete in under 5 seconds without an LLM.
2. All 7 fixture `expectations.md` files contain YAML frontmatter with `dispatch_skill:`, `ignore_fields:` (default `[updated_at, timestamp, actual_publish_time]`), and `max_attempts:` (default `3`).
3. `scripts/replay-fixture.sh <fixture-name>` copies the fixture to a temp directory, dispatches the declared skill via the host's sub-agent, extracts the final `signal_verdict` YAML block, compares verdict+target exactly and summary for key-term presence (lenient default), and exits 0 on pass / 1 on mismatch / 2 on execution error.
4. The harness retries each fixture up to `max_attempts:` times (default 3). A single passing attempt marks the fixture as pass. Host-level errors (sub-agent crash, timeout, missing skill) do not count against the retry budget.
5. The harness performs field-level diff of post-replay `.signal/` state against expected mutations, skipping fields in `ignore_fields:` (exact values ignored, presence checked), and flags unexpected file changes as failures.
6. `scripts/run-all-fixtures.sh` replays all 7 fixtures sequentially, prints a per-fixture summary table, and exits 0 only if all pass.
7. Two new orchestrator-level fixtures exist: `review-redirect-create` (verifies `signal-review → redirect → signal-create` routing spans two skills) and `publish-done-archive` (verifies `publish → phase-complete → done → archive` full terminal transition).
8. `evals/qa-checklist.md` contains the 5-step manual pre-ship checklist.
9. No fixture `.signal/` snapshot or skill `SKILL.md` is modified during replay (temp directory isolation).
10. `evals/signal-fixtures/README.md` documents all 7 fixtures, the metadata fields, the three-layer strategy, and how to run the harness.

## Subtasks

- **T2.1** — Layer 1: Enhanced static checks in smoke runner → [T2.1-layer-1-static-checks.md](T2.1-layer-1-static-checks.md)
- **T2.2** — Fixture metadata enrichment (dispatch_skill, ignore_fields, max_attempts) → [T2.2-fixture-metadata-enrichment.md](T2.2-fixture-metadata-enrichment.md)
- **T2.3** — Two new orchestrator-level fixtures → [T2.3-orchestrator-level-fixtures.md](T2.3-orchestrator-level-fixtures.md)
- **T2.4** — replay-fixture.sh harness (verdict replay, mutation diff, retry, lenient/strict) → [T2.4-replay-fixture-harness.md](T2.4-replay-fixture-harness.md)
- **T2.5** — run-all-fixtures.sh batch runner + evals/qa-checklist.md → [T2.5-run-all-qa-checklist.md](T2.5-run-all-qa-checklist.md)
- **T2.6** — Update evals README and repo README → [T2.6-update-readmes.md](T2.6-update-readmes.md)
- **T2.7** — Phase 2 QA fixtures — campaign plan, plan-review rework, multi-asset create → [T2.7-phase-2-fixtures.md](T2.7-phase-2-fixtures.md)
- **T2.8** — Remove stale campaign-blocked-at-intake fixture + update QA checklist → [T2.8-remove-stale-fixture-checklist.md](T2.8-remove-stale-fixture-checklist.md)

## Out of scope

- CI/CD integration (GitHub Actions, pre-commit hooks). Signal7 has no CI yet.
- Cross-host verification (running fixtures on Codex, Gemini, etc.). Initial implementation targets Claude Code only.
- Content-quality testing (is the generated copy good?). Subjective; not testable deterministically.
- Coverage metrics (% of skill paths tested).
- Mocked/prompt-based unit tests with simulated agent responses.
- Testing of Phase 2/3 skills (`signal-plan`, `signal-plan-review`, content workers) — they don't exist yet.
- Performance benchmarking.

## Edge cases

1. **No `signal_verdict` block in sub-agent output.** Harness extracts nothing → exit 2 (execution error). This catches the case where skill instructions don't lead the model to emit the verdict.
2. **Multiple `signal_verdict` blocks in output.** Harness uses the *last* fenced YAML block per the contract that verdict comes at the end of the response.
3. **Summary text completely diverges from expected key terms in lenient mode.** Verdict+target match → pass with logged warning. Strict mode → fail.
4. **Host dispatch failure.** Sub-agent crashes, times out, or returns no output → exit 2, does not count against retry budget.
5. **Missing `dispatch_skill:` in expectations.md.** Harness exits 2 with "missing dispatch_skill" error.
6. **`dispatch_skill:` references non-existent skill.** Harness exits 2 with "unknown skill" error.
7. **Unexpected file mutation.** Model writes a file or field not listed in expected mutations and not in `ignore_fields:` → mutation diff reports specific failure.
8. **Fixture expects file creation but it doesn't happen.** Mutation diff flags missing file.
9. **Timestamp field drift.** `updated_at` differs on every replay → `ignore_fields:` handles this. Field existence is checked; exact value is not.
10. **All retry attempts fail.** Fixture recorded as fail; `run-all-fixtures.sh` exits 1.
11. **Concurrent replays.** `run-all-fixtures.sh` runs sequentially to avoid temp directory collisions and LLM rate limits.
12. **Model non-determinism causes pass on attempt 1, fail on attempt 2.** The harness only needs one passing attempt — additional attempts are skipped once pass is recorded.

## Plan synthesis notes

This spec synthesizes plans from 4 models (Kimi K2.6, Qwen3.6 Plus, GLM 5.1, MiniMax M2.7). Qwen's plan formed the structural base and was the strongest. Specific improvements folded in from:
- **GLM:** specific edge case list (no-verdict-block, multi-block, unknown dispatch_skill), fixture-not-modifiable invariant
- **Kimi:** aggregate reporting in run-all-fixtures.sh, whitespace-drift tolerance
- **MiniMax:** exit code semantics (0/1/2 contract), README update as a subtask
- All 4 models independently flagged orchestrator-level routing tests as the biggest gap — T2.3 covers this.

Models split harness features (lenient/strict comparison, mutation diffing) into separate subtasks — these are horizontal decomposition and were merged into one vertical slice (T2.4).
