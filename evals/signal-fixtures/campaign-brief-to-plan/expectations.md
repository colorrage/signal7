---
dispatch_skill: signal-plan
ignore_fields: [updated_at, timestamp, actual_publish_time]
max_attempts: 3
---

# Fixture: campaign-brief-to-plan

Guards the `signal-plan` phase skill initial dispatch for campaign scope. When a campaign-scope brief is approved, `signal-plan` must read the brief, generate `content-plan.md` with a strategy summary, channel/language matrix, and asset plan table, then create matching asset stubs with correct frontmatter and pinned context versions.

## Snapshot point

The fixture freezes the system **before `signal-plan` first dispatch**:

- `task.md` `phase: plan`, `scope: campaign`, `awaiting: null`
- `brief.md` approved with `scope: campaign`, `channels: [linkedin, instagram]`, `languages: [en]`
- `compliance.md` `status: clear`
- `.signal/context/brand.md` with `brand_version: 1` and channel tone guidelines
- `.signal/context/product.md` with `product_version: 1`
- No `content-plan.md` exists yet

## Trigger

`signal` dispatches `signal-plan` for the first time after the campaign brief was approved.

## Expected verdict

```yaml
signal_verdict:
  verdict: awaiting-approval
  target: null
  summary: "Campaign plan and asset stubs are ready for approval."
```

## Expected mutations

- `content-plan.md` created with frontmatter (`plan_version: 1`, `scope: campaign`, `status: draft`) and 6 required sections:
  - `## Strategy Summary`
  - `## Channel Language Matrix`
  - `## Asset Plan`
  - `## Timeline`
  - `## Assumptions`
  - `## Revision Notes`
- `## Channel Language Matrix` covers both linkedin and instagram channels.
- `## Asset Plan` table has 2 rows: A1-linkedin-en (social-copy) and A2-instagram-en (social-copy).
- `A1-linkedin-en.md` created at task root with frontmatter: `id: A1`, `parent: S1`, `asset_type: social-copy`, `channel: linkedin`, `language: en`, `status: todo`, `brand_version: 1`, `product_version: 1`.
- `A2-instagram-en.md` created at task root with frontmatter: `id: A2`, `parent: S1`, `asset_type: social-copy`, `channel: instagram`, `language: en`, `status: todo`, `brand_version: 1`, `product_version: 1`.
- Asset stubs have `generation_log: []` and `review_ai_pass: null`.
- Asset stubs have `depends: []` — no cross-asset dependencies in this plan.
