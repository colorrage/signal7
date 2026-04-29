# Signal7 State Graph

Signal7 uses scope-specific phase paths.

## quick

```text
brief -> create -> review -> publish -> done
```

Redirects:

- `create -> plan` when derived asset count exceeds 3.
- `review -> create` when AI review fails or a human rejects.

## campaign

```text
brief -> plan -> plan-review -> create -> review -> publish -> done
```

Redirects:

- `plan-review -> plan` when plan review finds fixable issues.
- `review -> create` when asset review fails.

`plan-review` must run in a fresh sub-agent context.

## strategy

```text
brief -> create -> done
```

Strategy create produces internal artifacts such as research or pricing recommendations. It does not publish.

## Gates

`awaiting: user-input` pauses for clarification.

`awaiting: user-approval` pauses for approving durable artifacts:

- brief.md
- content-plan.md
- review.md human approvals

## Asset-level Gates

Asset-level gates do not change the phase graph:

- `external_gate` blocks a single asset in publish.
- `expires_at` blocks a single expired asset in publish.
- `depends[]` controls create-time worker eligibility.
