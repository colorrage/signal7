---
id: T1
title: Phase 0/1 hygiene fixes from review feedback + minimal Phase 2 enablers
phase: done
scope: feature
created: 2026-04-30T13:05:06
bugfix: false
awaiting: null
---

# Phase 0/1 hygiene fixes from review feedback + minimal Phase 2 enablers

Signal7 Phase 0 + Phase 1 are implemented. Five review issues from
Codex 5.5 and Claude Opus 4.7 (gh.com/colorrage/signal7/issues #1–#5)
identified contract gaps, silent halts, and doc-vs-disk drift that
must be fixed before Phase 2 starts. Implement Bucket A (all 14
hygiene items) plus three small Phase 2 enablers (minimal
`signal-task` cancel/status, partial-review for translation flow,
recording approval responses in review.md). Update
`hyper7/.hyper/archive/T1-design-biz-ops-hyper-system/impl-plan.md`
with a "Phase 0/1 amendments" appendix documenting each
behavior-changing fix and its rationale.

Scope evolved from research to feature after exploration approval:
the user opted to implement the recommendations rather than just
read them.

## Why

The reviewers across Codex 5.5 and Opus 4.7 independently flagged
the same Phase 1 problems, and several are silent-halt traps
(compliance default, review-failure rework loop, missing-skill
dispatch) that would bite a real user immediately. Phase 2 cannot
land safely on top of these.
