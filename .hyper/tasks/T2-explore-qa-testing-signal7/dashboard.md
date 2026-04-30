# Dashboard — T2: Explore QA testing approach for Signal7

## Goal

Explore a way to create a QA test for Signal7 that verifies each step works and what each step returns. The existing fixture suite checks structural consistency but does not run skills end-to-end. Need a design for testing signal, signal-brief, signal-create, signal-review, signal-publish, signal-social, and signal-worker against a real LLM to validate their behavior, output contracts, and edge cases.

**Why:** The smoke runner validates `.signal/` folder snapshots but does not execute skills. Real QA requires running skills against a live model to catch regressions in prompt engineering, verdict encoding, output shape, and failure-mode handling.

## Plan

_not yet written_

## Progress

_not yet written_

## Verification

_not yet run_

## Status

**Phase:** discover · **Awaiting:** none

## Decisions

- 2026-04-30 — discover — Scope set to research (design only, no code changes)
- 2026-04-30 — discover — Recommended 3-layer QA strategy: enhanced static validation (Layer 1), re-playable fixture tests with LLM (Layer 2), manual QA checklist (Layer 3)
- 2026-04-30 — user — Replay harness lives in this repo (not separate test repo)
- 2026-04-30 — user — Verdict matching: lenient by default (compare verdict + target exactly; summary for key terms only)
