# Signal7 — Manual QA Checklist

Pre-ship verification steps. Run each step interactively and check the boxes below when verified.

## 1. Bootstrap from scratch

Run `/signal` on an empty project (no `.signal/` directory present).

- [ ] `.signal/` directory is created
- [ ] `brand.md` is copied to `.signal/context/brand.md`
- [ ] `product.md` is copied to `.signal/context/product.md`
- [ ] `config.yaml` exists with valid YAML at `.signal/config.yaml`
- [ ] No errors in output; command completes cleanly

## 2. Quick-scope happy path

Run `/signal Write a LinkedIn post about our Q1 results`

- [ ] `signal-brief` runs and produces a brief with `scope: quick`
- [ ] `signal-create` dispatches `signal-social` and produces at least one asset (`A1-linkedin-en.md`)
- [ ] `signal-review` runs automatically (AI review) and assets get `review_ai_pass: true`
- [ ] User approves at the review gate (`awaiting: user-approval`) and the orchestrator advances
- [ ] `signal-publish` runs and records a `published` entry in `publish-log.md`
- [ ] All artifacts present: `brief.md`, `A1-linkedin-en.md`, `review.md`, `publish-log.md`, `dashboard.md`
- [ ] No `<TODO>` sentinels in any artifact file
- [ ] Verdict transitions correct: `brief → awaiting-approval → create → review → user-approval → publish → phase-complete → done`
- [ ] `task.md` shows `phase: done` at the end

## 3. Review rejection rework

Start from step 2 but stop at the review approval gate (`awaiting: user-approval`). Reply `reject: too casual` at the gate.

- [ ] Affected asset flips from `status: done` to `status: needs-revision`
- [ ] `review.md` records the rejection: approver row has `status: rejected`, reason is visible
- [ ] `signal-review` returns `verdict: redirect, target: create`
- [ ] Orchestrator applies the redirect; `task.md` shows `phase: create`
- [ ] System awaits the next asset completion — no infinite loop

## 4. Campaign planning

Run `/signal Create a campaign for Instagram + LinkedIn`

- [ ] `signal-brief` writes `brief.md` with `scope: campaign`
- [ ] After brief approval, `signal-plan` writes `content-plan.md`
- [ ] Matching `A<N>-*.md` asset stubs are created at the task root
- [ ] `signal-plan-review` writes `plan-review.md` from a fresh context
- [ ] Passing plan review advances to `create`; rejected plan review redirects to `plan`
- [ ] System does not crash, hang, or produce an empty response

## 5. Duplicate publish

**Setup:** Complete a full publish (step 2) so that `publish-log.md` contains at least one `published` entry. Then re-run publish (trigger `/signal` on the done task, or a direct publish re-dispatch).

- [ ] `publish-log.md` records a `skipped-duplicate` entry with the same `idempotency_key` as the original publish
- [ ] No new `published` entry is appended for the same asset + channel pair
- [ ] The `idempotency_key` matches the expected format (`sha256:<64 hex chars>`)
- [ ] Verdict is `phase-complete` (not `awaiting-input` or error)
