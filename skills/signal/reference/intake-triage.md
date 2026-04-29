# Signal7 Intake Triage

Classify work into `quick`, `campaign`, or `strategy` before downstream skills create artifacts.

## Scopes

### quick

Use quick when all are true:

- one channel
- one language
- no more than three derived assets
- public content can be produced without a campaign plan

If `signal-create` derives more than three assets from a quick brief, it must return a redirect verdict with `target: plan`.

### campaign

Use campaign when any are true:

- multiple channels
- multiple languages
- more than three derived assets
- explicit campaign intent
- timeline, sequencing, dependencies, or plan review would reduce risk

### strategy

Use strategy when there is no immediate public deliverable, for example competitor research, pricing analysis, positioning, or recommendations. Strategy uses `brief -> create -> done`.

## Worked Examples

| Request | Scope | Why |
|---|---|---|
| Write one LinkedIn post in English | quick | one channel, one language, one asset |
| Write one LinkedIn post plus two variants | quick | one channel, one language, <=3 assets |
| Write four LinkedIn variants | campaign | exceeds quick asset ceiling |
| Create posts for LinkedIn and Instagram | campaign | multiple channels |
| Translate one approved post to Romanian and French | campaign | multiple languages and dependency ordering |
| Build a launch campaign for a new product | campaign | explicit campaign intent |
| Make a blog post and email announcement | campaign | multiple asset types/channels |
| Research competitors for our product | strategy | internal recommendation, no public deliverable |
| Recommend pricing tiers | strategy | internal recommendation, no public deliverable |
| Create a regulated single social post | quick or campaign | quick if one channel/language/<=3 assets, but compliance.md is required |

## Clarification Rule

Ask one question at a time when scope depends on missing information. Prefer the lower-friction scope only when the ceiling and compliance requirements are still satisfied.
