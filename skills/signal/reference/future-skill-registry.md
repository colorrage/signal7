# Future Skill Registry

This registry prevents future domain work from being forgotten while keeping v1 focused.

## Placeholder Convention

Placeholder `SKILL.md` files may exist only when:

- the folder is named in this registry
- frontmatter includes `user-invocable: false`
- the body clearly says `TODO`
- no active workflow routes to the placeholder

Do not create a Phase 0 placeholder for `signal-price`. Phase 3 either implements it or creates the TODO placeholder if pricing is deferred.

## Planned Future Skills

| Skill | Purpose | Notes |
|---|---|---|
| signal-sales | sales outreach emails, call scripts, sequences | Future worker or user-facing shortcut |
| signal-labeling | product labeling, package copy, required label translations | Needs compliance-heavy schema |
| signal-image-edit | image modification prompts and asset records | Distinct from text-to-image prompt generation |
| signal-competition | user-facing shortcut for competitor research | MVP uses `signal-research` |
| signal-price | pricing recommendations | Optional Phase 3 worker |
| signal-recurring | scheduled repeated workflows | Out of v1; likely recipe/automation based |

## Recurring Definition

Recurring means repeated scheduled business workflows, for example "create three LinkedIn posts every Monday." It is not part of the initial phase graph.
