# Marketer7 execution-brief integration

Signal7 is one optional executor for Marketer7. It owns content assets, brand/claims review, publication, and its own factual execution records. Marketer7 remains the owner of mission strategy, hypothesis quality, experiment approval, measurement, and evaluation.

## Feature detection and legacy compatibility

No Signal7 schema migration or version field is introduced. Existing task, asset, and ledger files lacking the fields below are legacy-valid and must continue to parse, resume, create, review, publish, archive, and retain their original content.

The optional integration fields are:

```yaml
source_system: marketer7 | null
mission_id: M<N> | null
experiment_id: EX-<NNN> | null
tracking: null | <mapping>
execution_brief_path: null | <source path>
```

Only when `source_system: marketer7` is present must `mission_id`, `experiment_id`, and `tracking` be present and internally consistent. Unknown additive fields remain tolerated. Do not rewrite an existing task merely to add these fields.

## Brief import

`signal-marketer` accepts only the file contract `signal7-execution-brief/v1`. It uses the supplied path as input and records a task-local bounded snapshot; it never reads or writes the producer's `.marketer/` state.

The brief may constrain requested action, audience, channel, claims, stop conditions, and tracking. Signal7 may still block or revise execution for its own brand, claim, compliance, or channel-fit gates. It does not interpret the brief's hypothesis or KPI as a Signal7 success criterion.

## Propagation

For a Marketer-originated task, `signal-create` copies origin IDs and tracking to every newly created asset. `signal-publish` copies the same optional metadata to any new ledger entry and appends a factual event to `execution-result.md` with task ID, asset ID, status, timestamp, tracking, and publication URL when known.

The publish idempotency input remains exactly `task_id + asset_id + channel + publish_at + content_hash`. Metadata does not affect the key. A generated asset, a ledger entry, or a publication event is execution evidence only; none changes a Marketer7 experiment verdict.

## Execution result contract

`execution-result.md` is task-local and has `contract: signal7-execution-result/v1`. Its frontmatter provides origin IDs, `signal_task_id`, tracking, and summary status. Its append-only event rows provide factual per-asset execution information:

```text
asset_id | status | channel | publication_url | timestamp | tracking | publish_ledger_reference
```

It contains no performance metrics and makes no causal or experiment-evaluation claim.
