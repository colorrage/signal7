# Signal7 Project Retrospectives

## Retro — 2026-05-01T11:30:00

### What worked

- The quick-scope path (brief → create → review → publish) ran cleanly end-to-end with no redirects.
- signal-review caught a claim that wasn't in approved-claims.md before the user saw it.
- The tone_by_channel mapping in brief.md produced LinkedIn copy that matched the brand voice on the first pass.

### What didn't

- The first brief was too vague ("write a LinkedIn post about our product"). signal-brief rejected it with awaiting-input; rewording to a specific objective unblocked it.
- signal-publish took longer than expected because the publish_at field was left null and the rate-limit check fired a spurious defer. Setting an explicit publish time resolved it.

### What to do differently next time

- Write briefs with a concrete objective, specific audience, and one primary call-to-action.
- Always set publish_at explicitly in the brief or asset frontmatter rather than relying on the immediate-publish default.
- For multi-channel campaigns, stagger publish times by 15 minutes to avoid rate-limit collisions.

### About Signal7 itself

- signal-brief should warn earlier when the objective is too vague, rather than waiting for the full brief to be submitted and then rejecting.
- The rate-limit checker in signal-publish should treat null publish_at as "now" for the check but still record the actual time as the publish moment (the current behavior is confusing).

### About the project

- The brand.md tone section for LinkedIn is slightly off — it says "warm and approachable" but the executive audience responds better to "confident and direct." Consider updating brand.md.
- Competitor analysis surfaced that two competitors now have dedicated pricing pages with interactive calculators. A future Signal7 task could benchmark against them.
