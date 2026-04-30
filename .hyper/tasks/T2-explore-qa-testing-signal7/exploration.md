# Exploration — T2: QA testing approach for Signal7

## Findings

### What exists today

The fixture suite at `evals/signal-fixtures/` has 5 scenarios, each a static `.signal/` snapshot at a known point in the workflow plus an `expectations.md` describing the next trigger, expected verdict, and expected mutations.

The smoke runner at `scripts/run-signal-fixtures.sh` validates structural invariants:
- Frontmatter parseability
- Legal enum values (asset status, compliance status, approver status, publish-log status)
- No `<TODO>` sentinels left in fixture state
- Idempotency key shape (`sha256:<64 hex chars>`)

The runner exits 0 when all invariants hold. It does **not** execute any Signal7 skill. Per the runner's own header comment: *"This runner does NOT execute Signal7 skills end-to-end. That requires a host (Claude Code, Codex, etc.) and a model."*

### The gap

No test verifies that a skill, when invoked by a real agent with a real model against a fixture's `.signal/` state, actually produces the expected verdict and the expected file mutations. The expectations are described but never exercised.

Two concrete failure modes the current suite misses:
- A skill's `## Output Contract` says it returns `redirect target: create` but the body instructions don't actually lead the model to emit that verdict.
- A skill's body says "set `status: needs-revision`" but the model chooses a different status value or writes it to the wrong file.

### What makes Signal7 testing different

Signal7 skills are not executable code. They are markdown instruction files that an AI agent interprets at runtime. Testing them end-to-end means running them inside an agent with a real LLM — inherently non-deterministic and expensive per run.

This also means the most valuable tests are narrow: *given this exact state, does the skill produce this exact verdict?* The fixture model already captures exactly this. The missing piece is the execution harness.

### Key interface to test

Every phase skill and worker ends its response with exactly one fenced YAML block:

```yaml
signal_verdict:
  verdict: <value>
  target: <value or null>
  summary: "<text>"
```

This is the narrowest, highest-signal contract. If the verdict is correct, the orchestrator routes correctly. Content quality (is the generated LinkedIn post good?) is subjective and not testable deterministically. File mutations are the second layer — testable as structural diffs on the resulting `.signal/` state.

---

## Approach — Three-layer QA strategy

### Layer 1: Enhanced static validation (no LLM, CI-safe)

Keep the existing smoke runner. Add three new checks that run without a model:

1. **Verdict-encoding parity.** Parse each SKILL.md's `## Output Contract`, extract every YAML block, and validate that each is a valid `signal_verdict` block with the right keys (`verdict`, `target`, `summary`) and legal enum values per `gates.md`.

2. **Output-contract completeness.** For each phase skill, cross-reference its `## Output Contract` with the verdicts its body text mentions (grep for `return`, `redirect`, `awaiting`). Flag any discrepancy.

3. **Fixture-expectation parity.** For each fixture, parse `expectations.md`'s "Expected verdict" block and validate it matches the skill's `## Output Contract` for the described phase.

These run in the smoke runner, are deterministic, and catch the *"doc says X but contract says Y"* class of bugs. They can run on every commit.

### Layer 2: Re-playable fixture tests (requires LLM)

The core addition. Each fixture already encodes the test case:
- **State**: the `.signal/` snapshot
- **Trigger**: the user message or phase that fires next
- **Expected verdict**: verbatim YAML
- **Expected mutations**: which files change and how

A re-playable fixture test harness would:
1. Create a temporary directory with a fresh `.signal/` copied from the fixture.
2. Invoke the target skill as a sub-agent, passing the trigger message and pointing it at the temp `.signal/`.
3. Capture the skill's full output. Extract the final `signal_verdict` YAML block.
4. Compare the verdict against the expected verdict (key-by-key, ignoring summary text for non-exact-match fixtures).
5. Diff the resulting `.signal/` state against the expected mutations from `expectations.md` (check that the right fields changed, the right files were created/deleted, the right status values were set).
6. Report pass/fail per fixture.

**Host binding.** This requires a real agent host. The harness is host-specific: on Claude Code it would use the Task tool to dispatch `signal` as a sub-agent. On Codex it would use the equivalent. The harness scripts live in `scripts/` and accept a `--host` flag. The initial implementation targets Claude Code (the documented non-portable exception in `portability.md` § Dispatch Contract).

**Non-determinism handling.** Since LLM output varies, the verdict comparison should be tolerant of:
- Summary text wording differences (compare `verdict` and `target` exactly; compare `summary` for presence of key terms only)
- Minor whitespace differences in the YAML block
- Extraneous human-readable text before the final `signal_verdict` block

A fixture can opt into "strict" comparison (exact YAML match) or "lenient" comparison (key fields only) via a field in `expectations.md`.

**Which fixtures are re-playable today.** The 5 existing fixtures are designed for re-playability:

| Fixture | Re-playable? | What's needed |
|---------|-------------|---------------|
| `quick-social-happy` | Yes | Snapshot is at `phase: publish` with all assets done. Trigger: run `signal` → advance to `done` + archive. |
| `review-rejection-rework` | Yes | Snapshot is at `phase: review, awaiting: user-approval`. Trigger: user reply `reject: tone too casual` → `signal-review` redispatches. |
| `campaign-blocked-at-intake` | Yes | Snapshot is at `phase: brief` with campaign brief. Trigger: run `signal` → `signal-brief` refuses. |
| `publish-duplicate-skipped` | Yes | Snapshot has one published entry. Trigger: re-run publish → idempotency catches duplicate. |
| `compliance-default-clear` | Yes | Snapshot has `compliance.md` at `status: clear`. Trigger: run `signal-review` → does not halt. |

All 5 can be re-played without modification. The expectations.md files already document the trigger, expected verdict, and expected mutations.

### Layer 3: Manual QA checklist (no LLM needed)

A human-readable checklist in `evals/qa-checklist.md` for pre-ship verification after Phase changes:

1. Bootstrap `.signal/` from scratch (`/signal` on an empty project).
2. Run the LinkedIn quick-scope happy path end-to-end.
3. Reject a review and verify the rework loop.
4. Attempt a campaign and verify the Phase 2 refusal message.
5. Publish, re-publish, and verify duplicate detection.

This catches the *"does the system actually work for a real user?"* class of issues that automated tests cannot.

---

### Why this over alternatives

**Alternative 1 — Fully automated CI with a model.** Run every fixture against a real LLM on every PR. Rejected because: (a) Signal7 is pre-launch with zero users, (b) LLM API costs for 5+ fixtures would be significant and non-deterministic results would cause false failures, (c) we have no CI infrastructure yet.

**Alternative 2 — Skip E2E testing entirely, rely on structural validation only.** Rejected because: the reviewer-flagged regressions from T1 (compliance default causing silent halt, review-failure rework loop spinning without revision, campaign redirect into a non-existent skill) were all *behavioral* bugs — the fixture state was correct, the skill contract was documented, but the model would not produce the right output. Structural validation alone would not have caught any of them.

**Alternative 3 — Prompt-based unit tests with mocked agent responses.** Write a test framework that feeds each SKILL.md to a model and asserts a specific output string. Rejected because: (a) it's brittle — prompt wording changes would break tests, (b) it doesn't test the orchestrator-phase skill interaction, (c) the fixture replay model already encodes the full input state and expected output, making per-skill unit tests redundant.

### Recommended order

1. **Layer 1 first** — add the three enhanced static checks to the smoke runner. No model needed. Catches doc-vs-contract drift immediately. ~30 lines of bash.
2. **Layer 2 second** — build the fixture replay harness for Claude Code. Write one script (`scripts/replay-fixture.sh`) that takes a fixture name and replays it. Start with `quick-social-happy` (simplest, most important). Add the other 4 fixtures once the harness works.
3. **Layer 3 last** — write the QA checklist. This is a markdown file, no code.

### Test harness shape

```
scripts/
  run-signal-fixtures.sh      # existing smoke runner + new Layer 1 checks
  replay-fixture.sh            # new: replays one fixture against a real LLM
  run-all-fixtures.sh          # new: orchestrates replay of all fixtures

evals/
  signal-fixtures/             # existing fixtures (unchanged)
  qa-checklist.md              # new: manual QA checklist
```

`replay-fixture.sh` signature:
```bash
./scripts/replay-fixture.sh <fixture-name> [--host claude|codex] [--strict]
```

It copies the fixture to a temp directory, invokes the skill via the host, captures the verdict, compares against expectations, and reports. Exit 0 on match, 1 on mismatch, 2 on execution error.

## Out of scope

- Content-quality testing (is the generated copy good?). This is subjective and belongs in the review phase, not QA.
- Cross-host verification (running the same fixture on Claude Code, Codex, and Gemini to compare behavior). Interesting but premature — pick one host first.
- CI/CD integration (GitHub Actions, pre-commit hooks). Signal7 has no CI yet; add when there's a reason.
- Coverage metrics (what percentage of skill paths are tested). The 5 fixtures cover the 5 reviewer-flagged regressions. Add more fixtures as new phases land.

## Resolved questions

1. Should the fixture replay harness live in this repo (`signal7/scripts/`) or in a separate test repo?  
   **Answer:** In this repo. Simpler, keeps fixtures colocated with skills. One directory to update when contracts change.

2. Lenient vs strict verdict matching: should the default be lenient (compare `verdict` + `target` only) or strict (compare full YAML including `summary`)?  
   **Answer:** Lenient by default. Compare `verdict` and `target` exactly; check `summary` for presence of key terms only. Strict mode opt-in per fixture via `expectations.md`. Rationale: `summary` is informative, not contractual — the orchestrator only consumes `verdict` and `target`.

## Post-review amendments (2026-04-30)

After team review by GLM 5.1, Kimi K2.6, MiniMax M2.7, and Qwen3.6 Plus, four gaps were folded into the approach:

**1. Add orchestrator-level fixtures.** The existing 5 fixtures test single-skill dispatches (`signal-review` in isolation, `signal-publish` in isolation). No fixture tests that `signal` correctly routes between skills — e.g., that `signal-review` returning `redirect target: create` causes `signal` to dispatch `signal-create`. The campaign-redirect-into-nonexistent-skill regression from T1 was precisely this kind of routing bug. Two new orchestrator-level fixtures are needed: one covering `review → redirect → create` (verify the rework loop spans two skills), and one covering `publish → phase-complete → done → archive` (verify the full terminal transition).

**2. Add `dispatch_skill:` field to `expectations.md`.** The exploration claimed "all 5 fixtures are re-playable without modification." This is false — the harness needs to know *which skill* to dispatch. `quick-social-happy` triggers `signal-publish`; `review-rejection-rework` triggers `signal-review` with a user reply; `campaign-blocked-at-intake` triggers `signal-brief` re-evaluation. Without a machine-readable `dispatch_skill:` field, the harness must hardcode mappings or guess. Each `expectations.md` gains this field.

**3. Handle `updated_at` timestamps in diff comparison.** Expected mutations specify `updated_at: <now>`, but every replay produces a different timestamp. Field-level diffing must fuzzy-match or ignore timestamp fields. The harness accepts a per-fixture `ignore_fields:` list (default `[updated_at, timestamp, actual_publish_time]`).

**4. Define retry policy for Layer 2.** LLM non-determinism acknowledged. The harness uses a default retry of 3 attempts per fixture; if any attempt passes (verdict + mutations match), the fixture is recorded as pass. If all 3 fail, it's a failure. The retry count is configurable per fixture via `max_attempts:` in `expectations.md` (default 3).
