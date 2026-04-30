---
id: T2
title: Explore QA testing approach for Signal7
phase: discover
scope: feature
created: 2026-04-30T17:57:19
bugfix: false
awaiting: null
---

Explore a way to create a QA test for Signal7 that verifies each step works and what each step returns. The existing fixture suite checks structural consistency but does not run skills end-to-end. Need a design for testing signal, signal-brief, signal-create, signal-review, signal-publish, signal-social, and signal-worker against a real LLM to validate their behavior, output contracts, and edge cases.

## Why

The smoke runner validates `.signal/` folder snapshots but does not execute skills. Real QA requires running skills against a live model to catch regressions in prompt engineering, verdict encoding, output shape, and failure-mode handling.
