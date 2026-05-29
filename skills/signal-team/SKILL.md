---
name: signal-team
description: >
  Delegate any Signal7 artifact to another AI agent CLI for a second opinion,
  adversarial review, research, or fact-check. Human-triggered only — invoke
  directly with a goal and a provider (e.g. "use signal-team with codex to
  review the brief", "ask gemini to research competitor positioning"). The
  running agent leads; the called agent is the teammate. Every finding from
  the teammate is verified against the source artifact before being shown to
  the user.
allowed-tools:
  - Bash
  - Read
  - Grep
  - Glob
  - Write
argument-hint: "[natural language: ask codex to review the brief, get gemini's opinion, etc.]"
---

# signal-team

Delegate Signal7 artifacts to AI agent teammates via natural language.

Verification is non-negotiable. Every teammate finding is checked against the actual artifact on disk before it reaches the user. Edits are never auto-applied from a review — findings are presented, the user decides whether to apply them through the normal Signal7 phase flow.

This skill is **human-triggered only**. Invoke it directly with any goal. It does not run as part of any Signal7 phase workflow and is not auto-invoked by other skills (including the orchestrator `signal`). For structured task work, use the `signal` skill — `signal-team` is a standalone tool for getting a teammate involved on demand.

## Independence

`signal-team` is part of Signal7 and follows `cross-system-boundaries.md`: it does **not** read `.hyper/`, does not import any Hyper7 skill, and does not depend on Hyper7 being installed. Provider definitions live under `.signal/team/providers/` (project-local) or `~/.signal/team/providers/` (user-level). Hyper7's `hyper-team` may exist on the same machine; the two skills do not share state.

## Core Concept

The agent running this skill is always the **team lead**. The agent(s) being called are **teammates**. The lead parses intent, builds prompts, executes, verifies output, and presents results. Teammates receive a prompt and return findings — nothing more.

This skill works from any agent platform that can shell out to another CLI. Claude can lead Codex. Codex can lead Gemini. Any host with `signal-team` installed can lead any provider it can reach.

## Workflow

### Step 1 — Understand intent

Read the user's natural-language input and infer three things:

1. **Provider(s)** — which teammate(s) to call.
2. **Task type** — what kind of work.
3. **Target/scope** — which Signal7 artifact to review, research, or verify.

**Intent classification (Signal7-flavoured):**

| Task type | Signal words | Purpose | Typical targets |
|-----------|-------------|---------|-----------------|
| `design-review` | "review the brief", "review the plan", "challenge the strategy", "tradeoffs" | Adversarial review of strategy / structure | `brief.md`, `content-plan.md`, `compliance.md` |
| `code-review` | "review the copy", "check this asset", "is the LinkedIn post good", "find issues" | Adversarial review of generated content quality and brand fit | `A<N>-*.md` asset files, `review.md` |
| `research` | "how", "why", "investigate", "what do competitors do", "summarise past campaigns" | Read-only investigation across context files | `.signal/context/*.md`, archived task artifacts, `publish-log.md` |
| `verify` | "verify", "fact-check", "is it true that", "confirm this claim" | Fact-check a specific claim against source artifacts | `approved-claims.md`, `compliance.md`, asset bodies |

`code-review` is the historical name from `hyper-team` and is reused here for "asset content review." It is not about source code — Signal7 has none. The label is kept to make multi-system muscle memory work; the targets list keeps the meaning explicit.

Reviews are adversarial by default. There is no "gentle review" mode. v1 supports read-only task types only; write-capable delegation (let the teammate edit a brief, regenerate copy) is deferred — Signal7's phase skills own all artifact mutation.

**Provider detection:** explicit in the request ("ask Codex," "give this to Gemini") or multi-provider ("ask Codex and Gemini"). If the provider is unclear, **ask** — never guess. Task type and target are inferred from context; only ask about provider.

### Step 2 — Confirm plan

Present the inferred plan to the user before proceeding:

> "I'll ask **Codex** to **design-review** the `brief.md` for S3. Go?"

> "I'll ask **Codex** and **Gemini** to **code-review** the LinkedIn copy in `S3/A1-linkedin-en.md`. Go?"

The user can correct any part. **Never proceed without confirmation.**

### Step 3 — Load provider(s) and setup check

For each provider, resolve the provider file in this order:

1. `.signal/team/providers/{provider}.md` in the current project root (project-local teammates).
2. `~/.signal/team/providers/{provider}.md` (user-level teammates).

Use the first file that exists. If neither location has `{provider}.md`, stop and tell the user: list every available provider — the union of `.signal/team/providers/*.md` and `~/.signal/team/providers/*.md`, excluding `_template.md` — and ask which to use, or to create one (see § Adding a teammate). Never guess.

A provider file declares:

- a one-line `description`
- a `check` shell command (e.g. `command -v codex`)
- an `auth` shell command for first-run authentication
- the `invoke` template (the CLI invocation, with `{PROMPT_FILE}` and `{TIMEOUT}` placeholders)
- an optional `signal_aware` check that reports whether the teammate has the Signal7 skills installed locally

Once the file is loaded, verify readiness:

1. **CLI installed?** — Run `check`. If not installed, show the install command from the provider file and offer to run it (ask first — system-level change). Verify with `--version` after.
2. **Authenticated?** — If installed but not authenticated, show the auth command and offer to run it.
3. **Signal-aware?** — Run the optional `signal_aware` check. If not configured, warn: "Teammate won't have Signal7 awareness — the prompt will compensate by inlining the relevant context." Do not block.
4. **Self-delegation warning** — If the lead and the teammate are the same provider (Claude asking Claude), warn: *"You're asking me to review my own work — this won't give you an independent perspective. Proceed anyway?"* Only proceed on explicit confirmation.

If a CLI cannot be installed or authenticated, stop — do not proceed without a working CLI.

### Step 4 — Gather context

Gather context based on task type. The same context feeds all providers.

| Task type | Context to gather |
|-----------|------------------|
| `design-review` | Target artifact (`brief.md` / `content-plan.md` / `compliance.md`) plus `.signal/context/brand.md`, `.signal/context/product.md`, the task's `task.md` frontmatter |
| `code-review` | Target asset file(s), task `brief.md`, `.signal/context/brand.md`, `.signal/context/product.md`, `.signal/context/approved-claims.md`, `compliance.md`, `review.md` if present |
| `research` | The user's question, plus the named context files: `.signal/context/competitors.md`, `.signal/context/past-campaigns.md`, `.signal/context/product.md`, archived `S<N>` task folders if relevant |
| `verify` | The claim text, plus `.signal/context/approved-claims.md`, `.signal/context/product.md`, `compliance.md`, and any asset that quotes the claim |

Always include: `.signal/config.yaml` (so the teammate knows enabled channels and rate limits), the project's `README.md` if present, and any user-supplied custom instructions.

If the user's request references a Signal7 artifact ("review the brief," "check the asset") and the current directory is inside a Signal7 task folder (`.signal/tasks/S<N>-*/`), read the corresponding artifact directly. Otherwise the user names the target and the lead resolves it.

### Step 5 — Build prompt

Build the prompt inline. The prompt must include:

1. **Role**: tell the teammate it is a senior Signal7 reviewer. Define what Signal7 is in one short paragraph (marketing-ops workflow, disk-state, asset files with frontmatter).
2. **Task type and target**: what kind of review and which artifact(s).
3. **Constraints**: brand voice, regulated-domain status from `compliance.md`, approved claims (full file inlined for `verify` and `code-review`), forbidden terms.
4. **Scope discipline**: read-only — never propose changes that mutate `task.md`, `phase`, or `awaiting`. Findings only. The Signal7 phase skills own mutation.
5. **Output contract**: structured XML or JSON the lead can parse. Sections:
   - `<finding severity="critical|major|minor">` with `<location>file:line</location>`, `<concern>…</concern>`, `<reasoning>…</reasoning>`, `<suggestion>…</suggestion>`.
   - `<strengths>` block listing what the artifact does well.
   - `<scope_notes>` if the teammate could not access something it needed.

Multi-provider runs share the same prompt. Do not customise per provider.

If the prompt is too large, reduce context scope (one asset instead of all task assets, summary instead of full `past-campaigns.md`, etc.) and rebuild.

### Step 6 — Execute

Follow the provider file for CLI invocation.

- **Single provider**: run and wait.
- **Multi-provider**: run all providers in parallel. Each invocation is independent.
- **Timeout**: 600 seconds per provider (override per provider file if needed). On timeout, save partial output, mark coverage partial, offer retry.
- **On error or empty output**: retry once. Second failure → abort that provider and report.
- **All providers fail**: report the failures with details. Never fabricate results.

Save raw output to `.signal/team/`. Filename: `{YYYY-MM-DD}-{HHmm}-{S<N>?}-{provider}-{task-type}.md`. The same timestamp ties a multi-provider run together. `.signal/team/` is created lazily by `signal-team` on first save; bootstrap does not pre-create it.

Metadata header on every raw file:

```yaml
---
prompt: |
  {first 200 chars of prompt}...
command: {exact CLI command used}
exit_code: {0 or error code}
duration: {seconds}
provider_version: {output of --version}
sandbox_mode: {read-only|writable}
timestamp: {ISO 8601}
---
```

### Step 7 — Verify output (mandatory)

Never present unverified teammate output to the user.

**Single provider**: read the raw output, check each finding against the artifact on disk. The teammate's `<location>` must point at a real file:line; the described content must match what is actually written; the reasoning must hold up against `brand.md` / `approved-claims.md` / `compliance.md` etc. Mark each finding as **verified**, **partially correct** (with correction), or **rejected** (with reason). The lead may add findings the teammate missed. Never invent a finding to replace a hallucinated one.

**Multi-provider**:

1. Merge findings across providers.
2. Deduplicate overlapping issues (same artifact, same concern).
3. Verify the combined set once — not per provider.
4. Every finding keeps **provenance** — which provider reported it.

**When providers disagree**: present both perspectives and let the lead break the tie with reasoning. The lead has the conversation context and project knowledge the teammates lack. No majority vote.

The lead gives a full opinion on subjective design calls — the lead is a senior reviewer, not a neutral aggregator.

Maximum two clarification rounds per provider. If output is still unclear after that, work with what is available and note the gaps in `<scope_notes>` of the verified artifact.

### Step 8 — Present results and save artifact

**For reviews (`design-review`, `code-review`):**

1. **Summary**: provider(s), task type, target, coverage (full/partial).
2. **Verified findings** grouped by severity (critical > major > minor), each with provenance.
3. **Lead's opinion** on disagreements.
4. **New issues from verification** — things the lead found that teammates missed.
5. **Strengths** the teammates noted (verified).

**STOP rule:** present findings and ask the user which to act on. **Never auto-apply changes.** Signal7 phase skills (`signal-create` for asset content, `signal-plan` for plan, `signal-brief` for brief) are how the user actually applies findings — `signal-team` only surfaces them.

**For `research` and `verify`**: present the verified output directly. Include provenance when multi-provider. Add the lead's assessment and corrections.

**Save the verified artifact** to:

- the task folder when the target was task-scoped: `.signal/tasks/S<N>-*/team-{providers}-{task-type}.md` (or `.signal/archive/...` for archived tasks);
- `.signal/team/` otherwise.

The verified artifact is durable — it survives across sessions and supports later review. Include the same fields as the raw header plus the verified findings.

## Adding a teammate

To add a project-local teammate:

1. Create `.signal/team/providers/<name>.md` (run `mkdir -p` first).
2. Use this template:

   ```markdown
   ---
   description: One-line description of the provider CLI.
   check: command -v <cli>
   auth: <cli> auth login
   invoke: <cli> --read --timeout {TIMEOUT} --prompt-file {PROMPT_FILE}
   signal_aware: ls ~/.<cli>/skills/signal/SKILL.md
   ---

   # <name>

   Notes for humans: install command, free-tier limits, anything provider-specific.
   ```

3. Fill every field. The `invoke` line is the exact shell command; `{PROMPT_FILE}` is replaced with the prompt path, `{TIMEOUT}` with the per-provider timeout in seconds.

For a teammate that should be available across all your Signal7 projects, write the same file under `~/.signal/team/providers/<name>.md`. Project-local files take precedence on identical names.

`.signal/` is gitignored by default. To share a project-local teammate across a team, either un-ignore the path or commit individual files explicitly.

## Storage

- **Raw output** under `.signal/team/`: unverified teammate responses with metadata headers. Preserved for traceability.
- **Verified artifacts**: in the task folder when task-scoped, or `.signal/team/` otherwise. Contain provider names, task type, target, findings with provenance, verification notes, and the lead's opinion.

`signal-team` does not delete previous runs. Old output piles up in `.signal/team/`; the user (or a future cleanup recipe) prunes manually.

## Output Contract

`signal-team` is user-invocable and does not participate in the phase-skill verdict protocol — it is outside the `signal` orchestrator's loop. When chained from `signal` (uncommon), return:

```yaml
signal_verdict:
  verdict: phase-complete
  target: null
  summary: "Team review complete; see <artifact path>."
```

If the run could not complete (no provider available, all providers failed, missing target):

```yaml
signal_verdict:
  verdict: awaiting-input
  target: null
  summary: "<reason>"
```

## Notes

- `signal-team` is read-only over Signal7 artifacts. Findings drive the user's next `/signal` action; they are not applied directly.
- Reviews on archived tasks are allowed — verified artifacts are appended to the archived folder for traceability. The archive's read-only-for-state rule does not apply to review artifacts (consistent with `signal-retro`).
- `signal-team` does not wrap, reformat, or summarise raw provider output beyond the verification step — the raw file always remains for audit.
