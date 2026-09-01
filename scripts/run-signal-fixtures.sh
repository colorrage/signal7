#!/usr/bin/env bash
# Signal7 fixtures smoke runner.
#
# Walks evals/signal-fixtures/*/ and validates static structural invariants
# against each fixture's `.signal/` snapshot:
#
#   - frontmatter parseable (extracts the YAML head between leading `---` lines)
#   - status enums legal (asset status, compliance status, approver status,
#     publish-log status)
#   - no `<TODO>` sentinels left in places where a phase skill would have
#     replaced them
#   - idempotency_key shape: sha256:<64 hex chars>
#   - expectations.md exists per fixture
#   - explicitly expected-invalid fixtures prove their named rejection errors
#
# This runner does NOT execute Signal7 skills end-to-end. That requires a
# host (Claude Code, Codex, etc.) and a model. The runner is a regression net
# for the contracts in skills/signal/reference/.
#
# Exit status:
#   0  all fixtures structurally valid
#   1  one or more fixtures failed
#   2  bad arguments / missing dependencies

set -u
set -o pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
FIXTURES_DIR="$ROOT/evals/signal-fixtures"
SKILLS_DIR="$ROOT/skills"

# Legal signal_verdict values (from gates.md)
VERDICT_VALUES_RE='^(awaiting-input|awaiting-approval|review-pass-approval-pending|phase-complete|redirect)$'
VERDICT_VALUES_LIST="awaiting-input awaiting-approval review-pass-approval-pending phase-complete redirect"

if [[ ! -d "$FIXTURES_DIR" ]]; then
  echo "fixtures dir not found: $FIXTURES_DIR" >&2
  exit 2
fi

declare -a FAILS=()
PASS_COUNT=0
FAIL_COUNT=0

# Legal enums
ASSET_STATUS_RE='^(todo|needs-revision|in-progress|done|blocked|cancelled)$'
COMPLIANCE_STATUS_RE='^(clear|questions-open|blocked)$'
APPROVER_STATUS_RE='^(pending|approved|rejected|escalated)$'
PUBLISH_STATUS_RE='^(published|skipped-duplicate|blocked-expired|blocked-external-gate|blocked-rate-limit|failed)$'
IDEMPOTENCY_KEY_RE='^sha256:[0-9a-fA-F]{64}$'

# Extract YAML frontmatter to stdout. Empty if file has no frontmatter.
extract_frontmatter() {
  local file="$1"
  awk 'BEGIN{open=0} /^---[[:space:]]*$/{open++; if(open==1){next}; if(open==2){exit}} {if(open==1) print}' "$file"
}

# Find the value of a top-level scalar key in extracted frontmatter.
# Tolerant of quoted strings and trailing comments.
yaml_scalar() {
  local key="$1"
  awk -v k="$key" '
    $0 ~ "^[[:space:]]*"k"[[:space:]]*:[[:space:]]*" {
      sub("^[[:space:]]*"k"[[:space:]]*:[[:space:]]*","")
      sub("[[:space:]]+#.*$","")
      gsub(/^["'"'"']|["'"'"']$/,"")
      print
      exit
    }'
}

# Find the value of a top-level scalar key only. This avoids treating a nested
# external-gate/source field as task- or asset-level integration metadata.
yaml_top_scalar() {
  local key="$1"
  awk -v k="$key" '
    $0 ~ "^"k"[[:space:]]*:[[:space:]]*" {
      sub("^"k"[[:space:]]*:[[:space:]]*","")
      sub("[[:space:]]+#.*$","")
      gsub(/^['"'"']|['"'"']$/ ,"")
      print
      exit
    }'
}

yaml_has_top_key() {
  local key="$1"
  awk -v k="$key" '$0 ~ "^"k"[[:space:]]*:" {found=1; exit} END {exit !found}'
}

# Extract a markdown section (from `## Section` heading to next `##` or EOF).
# prints section body to stdout (excluding the heading line).
extract_section() {
  local file="$1" heading="$2"
  awk -v h="^## $heading$" '
    BEGIN{in_sec=0}
    $0 ~ h {in_sec=1; next}
    in_sec && /^## / {exit}
    in_sec {print}
  ' "$file"
}

# Extract the value of a YAML scalar key from stdin (a single YAML block).
# Supports signal_verdict.verdict, .target, .summary (indented under signal_verdict:).
yaml_block_scalar() {
  local key="$1"
  awk -v k="$key" '
    BEGIN{depth=0}
    /^[[:space:]]*signal_verdict:/ {in_block=1; next}
    in_block && $0 ~ "^[[:space:]]*"k":" {
      val=$0; sub(/^[^:]*:[[:space:]]*/,"",val)
      sub(/[[:space:]]+#.*/,"",val); gsub(/"/,"",val)
      gsub(/^[[:space:]]+|[[:space:]]+$/,"",val)
      print val
      found=1
      exit
    }
    in_block && /^[[:space:]]*[a-zA-Z_]/ && $0 !~ "^[[:space:]]*"k":" {exit}
  '
}

# Extract all fenced YAML blocks from stdin joined by blank-line separators.
# Each block is the body between ```yaml and ``` markers.
extract_yaml_blocks() {
  awk '
    /^```yaml/ {in_block=1; next}
    in_block && /^```/ {in_block=0; print ""; next}
    in_block {print}
  '
}

# Extract backtick-quoted verdict values from `Allowed verdicts:` line in body.
# Body text is stdin (everything before ## Output Contract).
get_allowed_verdicts() {
  awk '
    /^Allowed verdicts/ {
      line=$0
      # Strip qualifier like "when chained from `signal`:" — keep only after the colon
      sub(/^Allowed verdicts[^:]*:[[:space:]]*/, "", line)
      # Extract comma-separated backtick-quoted values
      while (match(line, /`[^`]*`/)) {
        val=substr(line, RSTART+1, RLENGTH-2)
        print val
        line=substr(line, RSTART+RLENGTH)
      }
      exit
    }
  '
}

fail() {
  local fixture="$1"; shift
  local msg="$*"
  FAILS+=("$fixture: $msg")
  FAIL_COUNT=$((FAIL_COUNT + 1))
  echo "  FAIL: $msg"
}

check_no_sentinels() {
  local fixture="$1"
  local task_dir="$2"
  # `<TODO>` is forbidden in fixture state files — phase skills replace
  # sentinels before returning phase-complete, and fixtures are post-phase
  # snapshots.
  while IFS= read -r f; do
    if grep -q '<TODO>' "$f"; then
      fail "$fixture" "leftover <TODO> sentinel in ${f#$task_dir/}"
    fi
  done < <(find "$task_dir" -type f -name '*.md')
}

check_task_md() {
  local fixture="$1"
  local task_md="$2"
  local fm
  fm="$(extract_frontmatter "$task_md")"
  if [[ -z "$fm" ]]; then
    fail "$fixture" "task.md has no frontmatter: ${task_md#$FIXTURES_DIR/}"
    return
  fi
  local id phase scope
  id="$(echo "$fm" | yaml_scalar id)"
  phase="$(echo "$fm" | yaml_scalar phase)"
  scope="$(echo "$fm" | yaml_scalar scope)"
  [[ "$id" =~ ^S[0-9]+$ ]] || fail "$fixture" "task.md id not S<N>: '$id'"
  case "$phase" in
    deferred|brief|plan|plan-review|create|review|publish|done|cancelled) ;;
    *) fail "$fixture" "task.md phase not legal: '$phase'" ;;
  esac
  case "$scope" in
    unknown|quick|campaign|strategy) ;;
    *) fail "$fixture" "task.md scope not legal: '$scope'" ;;
  esac
}

check_compliance_md() {
  local fixture="$1"
  local file="$2"
  [[ -f "$file" ]] || return
  local status
  status="$(extract_frontmatter "$file" | yaml_scalar status)"
  if ! [[ "$status" =~ $COMPLIANCE_STATUS_RE ]]; then
    fail "$fixture" "compliance.md status not legal: '$status'"
  fi
}

check_asset_md() {
  local fixture="$1"
  local file="$2"
  local status asset_type
  status="$(extract_frontmatter "$file" | yaml_scalar status)"
  asset_type="$(extract_frontmatter "$file" | yaml_scalar asset_type)"
  if ! [[ "$status" =~ $ASSET_STATUS_RE ]]; then
    fail "$fixture" "asset $(basename "$file") status not legal: '$status'"
  fi
  if [[ -z "$asset_type" ]]; then
    fail "$fixture" "asset $(basename "$file") missing asset_type"
  fi
}

check_marketer_metadata() {
  local fixture="$1"
  local task_dir="$2"
  local task_md="$task_dir/task.md"
  local task_fm source mission experiment
  task_fm="$(extract_frontmatter "$task_md")"
  source="$(echo "$task_fm" | yaml_top_scalar source_system)"
  [[ "$source" == "marketer7" ]] || return

  mission="$(echo "$task_fm" | yaml_top_scalar mission_id)"
  experiment="$(echo "$task_fm" | yaml_top_scalar experiment_id)"
  [[ "$mission" =~ ^M[0-9]+$ ]] || fail "$fixture" "Marketer task mission_id not M<N>: '$mission'"
  [[ "$experiment" =~ ^EX-[0-9]+$ ]] || fail "$fixture" "Marketer task experiment_id not EX-<NNN>: '$experiment'"
  echo "$task_fm" | yaml_has_top_key tracking || fail "$fixture" "Marketer task missing tracking key"

  local brief="$task_dir/marketer-execution-brief.md"
  if [[ ! -f "$brief" ]]; then
    fail "$fixture" "Marketer task missing marketer-execution-brief.md"
  else
    local brief_fm brief_contract brief_source brief_mission brief_experiment brief_executor
    brief_fm="$(extract_frontmatter "$brief")"
    brief_contract="$(echo "$brief_fm" | yaml_top_scalar contract)"
    brief_source="$(echo "$brief_fm" | yaml_top_scalar source_system)"
    brief_mission="$(echo "$brief_fm" | yaml_top_scalar mission_id)"
    brief_experiment="$(echo "$brief_fm" | yaml_top_scalar experiment_id)"
    brief_executor="$(echo "$brief_fm" | yaml_top_scalar executor)"
    [[ "$brief_contract" == "signal7-execution-brief/v1" ]] || fail "$fixture" "Marketer execution brief has unsupported contract '$brief_contract'"
    [[ "$brief_source" == "marketer7" ]] || fail "$fixture" "Marketer execution brief source_system not marketer7"
    [[ "$brief_mission" == "$mission" ]] || fail "$fixture" "Marketer execution brief mission_id does not match task"
    [[ "$brief_experiment" == "$experiment" ]] || fail "$fixture" "Marketer execution brief experiment_id does not match task"
    [[ "$brief_executor" == "signal7" ]] || fail "$fixture" "Marketer execution brief executor is not signal7"
    echo "$brief_fm" | yaml_has_top_key tracking || fail "$fixture" "Marketer execution brief missing tracking key"
  fi

  local result="$task_dir/execution-result.md"
  if [[ ! -f "$result" ]]; then
    fail "$fixture" "Marketer task missing execution-result.md"
  else
    local result_fm result_contract result_task result_mission result_experiment
    result_fm="$(extract_frontmatter "$result")"
    result_contract="$(echo "$result_fm" | yaml_top_scalar contract)"
    result_task="$(echo "$result_fm" | yaml_top_scalar signal_task_id)"
    result_mission="$(echo "$result_fm" | yaml_top_scalar mission_id)"
    result_experiment="$(echo "$result_fm" | yaml_top_scalar experiment_id)"
    [[ "$result_contract" == "signal7-execution-result/v1" ]] || fail "$fixture" "execution-result.md has unsupported contract '$result_contract'"
    [[ "$result_task" == "$(echo "$task_fm" | yaml_top_scalar id)" ]] || fail "$fixture" "execution-result.md signal_task_id does not match task"
    [[ "$result_mission" == "$mission" ]] || fail "$fixture" "execution-result.md mission_id does not match task"
    [[ "$result_experiment" == "$experiment" ]] || fail "$fixture" "execution-result.md experiment_id does not match task"
    echo "$result_fm" | yaml_has_top_key tracking || fail "$fixture" "execution-result.md missing tracking key"
  fi

  while IFS= read -r asset_md; do
    local asset_fm asset_source asset_mission asset_experiment
    asset_fm="$(extract_frontmatter "$asset_md")"
    asset_source="$(echo "$asset_fm" | yaml_top_scalar source_system)"
    asset_mission="$(echo "$asset_fm" | yaml_top_scalar mission_id)"
    asset_experiment="$(echo "$asset_fm" | yaml_top_scalar experiment_id)"
    [[ "$asset_source" == "marketer7" ]] || fail "$fixture" "Marketer asset $(basename "$asset_md") source_system not propagated"
    [[ "$asset_mission" == "$mission" ]] || fail "$fixture" "Marketer asset $(basename "$asset_md") mission_id not propagated"
    [[ "$asset_experiment" == "$experiment" ]] || fail "$fixture" "Marketer asset $(basename "$asset_md") experiment_id not propagated"
    echo "$asset_fm" | yaml_has_top_key tracking || fail "$fixture" "Marketer asset $(basename "$asset_md") missing tracking key"
  done < <(find "$task_dir" -maxdepth 1 -type f -name 'A[0-9]*.md')

  local ledger="$task_dir/publish-log.md"
  if [[ -f "$ledger" ]]; then
    grep -q '^[[:space:]]*source_system:[[:space:]]*marketer7[[:space:]]*$' "$ledger" || fail "$fixture" "Marketer publish ledger missing source_system"
    grep -q "^[[:space:]]*mission_id:[[:space:]]*$mission[[:space:]]*$" "$ledger" || fail "$fixture" "Marketer publish ledger missing mission_id"
    grep -q "^[[:space:]]*experiment_id:[[:space:]]*$experiment[[:space:]]*$" "$ledger" || fail "$fixture" "Marketer publish ledger missing experiment_id"
    grep -q '^[[:space:]]*tracking:' "$ledger" || fail "$fixture" "Marketer publish ledger missing tracking"
  fi
}

check_publish_log() {
  local fixture="$1"
  local file="$2"
  [[ -f "$file" ]] || return
  # Each entry has `status:` and `idempotency_key:` lines. Validate enum + shape.
  local bad_status
  bad_status="$(awk '
    /^[[:space:]]*-?[[:space:]]*status:/ {
      val=$0; sub(/^[^:]*:[[:space:]]*/,"",val); sub(/[[:space:]]+#.*/,"",val); gsub(/"/,"",val);
      print val
    }' "$file" | grep -Ev "$PUBLISH_STATUS_RE" || true)"
  if [[ -n "$bad_status" ]]; then
    while IFS= read -r v; do
      fail "$fixture" "publish-log.md has illegal status: '$v'"
    done <<<"$bad_status"
  fi
  local bad_key
  bad_key="$(awk '
    /^[[:space:]]*idempotency_key:/ {
      val=$0; sub(/^[^:]*:[[:space:]]*/,"",val); sub(/[[:space:]]+#.*/,"",val); gsub(/"/,"",val);
      print val
    }' "$file" | grep -Ev "$IDEMPOTENCY_KEY_RE" || true)"
  if [[ -n "$bad_key" ]]; then
    while IFS= read -r v; do
      fail "$fixture" "publish-log.md has malformed idempotency_key: '$v'"
    done <<<"$bad_key"
  fi
}

check_review_md() {
  local fixture="$1"
  local file="$2"
  [[ -f "$file" ]] || return
  local bad
  bad="$(awk '
    /^[[:space:]]*-?[[:space:]]*status:/ {
      val=$0; sub(/^[^:]*:[[:space:]]*/,"",val); sub(/[[:space:]]+#.*/,"",val); gsub(/"/,"",val);
      print val
    }' "$file" | grep -Ev "$APPROVER_STATUS_RE" || true)"
  if [[ -n "$bad" ]]; then
    while IFS= read -r v; do
      fail "$fixture" "review.md approver status not legal: '$v'"
    done <<<"$bad"
  fi
}

# ─── Layer 1 static checks ──────────────────────────────────────────────────

# Extract `verdict:` values from fenced YAML blocks inside Output Contract.
# Input: Output Contract section (stdin). Output: one verdict value per line.
extract_yaml_verdict_values() {
  local label="$1"  # for error messages
  awk '
    /^```yaml$/ {in_block=1; next}
    in_block && /^```$/ {in_block=0; next}
    in_block && /^[[:space:]]*verdict:/ {
      val=$0
      sub(/^[[:space:]]*verdict:[[:space:]]*/,"",val)
      sub(/[[:space:]]+#.*$/,"",val)
      gsub(/^"|"$/,"",val)
      if (val != "") print val
    }
  '
}

# check_verdict_encoding_parity:
#   Parse each SKILL.md `## Output Contract` section, extract every fenced
#   YAML block, validate it is a valid `signal_verdict` with keys
#   `verdict`, `target`, `summary` and that `verdict` is one of the 5 legal
#   values from `gates.md`.
check_verdict_encoding_parity() {
  local label="$1" skill_path="$2"
  local output_contract
  output_contract="$(extract_section "$skill_path" "Output Contract")"
  if [[ -z "$output_contract" ]]; then
    echo "  check_verdict_encoding_parity: SKIP (no ## Output Contract section)"
    return 0
  fi
  local tmpfile
  tmpfile="$(mktemp)" || { fail "$label" "check_verdict_encoding_parity: cannot create temp file"; return 1; }
  echo "$output_contract" | extract_yaml_blocks > "$tmpfile"
  if [[ ! -s "$tmpfile" ]]; then
    fail "$label" "check_verdict_encoding_parity: no signal_verdict YAML block in Output Contract"
    rm -f "$tmpfile"
    return 1
  fi
  local block_num=0 any_fail=0 result verdict target summary line
  while IFS= read -r line; do
    if [[ -z "$line" ]]; then
      if [[ $block_num -gt 0 ]]; then
        if [[ -z "$verdict" || -z "$target" || -z "$summary" ]]; then
          local missing=""
          [[ -z "$verdict" ]] && missing="$missing verdict"
          [[ -z "$target" ]]  && missing="$missing target"
          [[ -z "$summary" ]] && missing="$missing summary"
          fail "$label" "check_verdict_encoding_parity: block $block_num missing keys:$missing"
          any_fail=1
        elif ! [[ "$verdict" =~ $VERDICT_VALUES_RE ]]; then
          fail "$label" "check_verdict_encoding_parity: block $block_num illegal verdict '$verdict'"
          any_fail=1
        fi
        verdict=""; target=""; summary=""
      fi
      continue
    fi
    if [[ "$line" =~ ^signal_verdict: ]]; then
      block_num=$((block_num + 1))
      verdict=""; target=""; summary=""
      continue
    fi
    case "${line%%:*}" in
      *verdict) verdict="$(echo "$line" | awk '{sub(/^[^:]*:[[:space:]]*/,""); sub(/[[:space:]]+#.*/,""); gsub(/"/,""); gsub(/^[[:space:]]+|[[:space:]]+$/,""); print}')" ;;
      *target)  target="$(echo "$line" | awk '{sub(/^[^:]*:[[:space:]]*/,""); sub(/[[:space:]]+#.*/,""); gsub(/"/,""); gsub(/^[[:space:]]+|[[:space:]]+$/,""); print}')" ;;
      *summary) summary="$(echo "$line" | awk '{sub(/^[^:]*:[[:space:]]*/,""); sub(/[[:space:]]+#.*/,""); gsub(/"/,""); gsub(/^[[:space:]]+|[[:space:]]+$/,""); print}')" ;;
    esac
  done < "$tmpfile"
  # Process the last block (no trailing blank line)
  if [[ $block_num -gt 0 ]] && [[ -n "$verdict" || -n "$target" || -n "$summary" ]]; then
    if [[ -z "$verdict" || -z "$target" || -z "$summary" ]]; then
      local missing=""
      [[ -z "$verdict" ]] && missing="$missing verdict"
      [[ -z "$target" ]]  && missing="$missing target"
      [[ -z "$summary" ]] && missing="$missing summary"
      fail "$label" "check_verdict_encoding_parity: block $block_num missing keys:$missing"
      any_fail=1
    elif ! [[ "$verdict" =~ $VERDICT_VALUES_RE ]]; then
      fail "$label" "check_verdict_encoding_parity: block $block_num illegal verdict '$verdict'"
      any_fail=1
    fi
  fi
  rm -f "$tmpfile"
  if [[ $any_fail -eq 0 ]]; then
    echo "  check_verdict_encoding_parity: PASS ($block_num blocks)"
  fi
  return 0
}

# check_output_contract_completeness:
#   For each phase skill, grep body text for verdict-indicating words
#   (`return`, `redirect`, `awaiting`) and cross-reference against the
#   `## Output Contract`. Flag any verdict mentioned in body but absent
#   from contract, or vice versa.
check_output_contract_completeness() {
  local label="$1" skill_path="$2"
  local output_contract body_text body_verdicts contract_verdicts
  output_contract="$(extract_section "$skill_path" "Output Contract")"
  if [[ -z "$output_contract" ]]; then
    echo "  check_output_contract_completeness: SKIP (no ## Output Contract section)"
    return 0
  fi
  # Declared verdicts from the Allowed verdicts: line (inside Output Contract section)
  body_verdicts="$(echo "$output_contract" | get_allowed_verdicts | sort -u)"
  if [[ -z "$body_verdicts" ]]; then
    echo "  check_output_contract_completeness: SKIP (no 'Allowed verdicts' line)"
    return 0
  fi
  # Verdicts from YAML blocks in Output Contract
  contract_verdicts="$(echo "$output_contract" | extract_yaml_blocks | while IFS= read -r line; do [[ -z "$line" ]] && continue; echo "$line"; done | awk '
    /^[[:space:]]*verdict:/ {
      val=$0; sub(/^[^:]*:[[:space:]]*/,"",val)
      sub(/[[:space:]]+#.*/,"",val); gsub(/"/,"",val)
      gsub(/^[[:space:]]+|[[:space:]]+$/,"",val)
      print val
    }
  ' | sort -u)"
  if [[ -z "$contract_verdicts" ]]; then
    fail "$label" "check_output_contract_completeness: no verdict YAML blocks in Output Contract"
    return 1
  fi
  local diff1 diff2
  diff1="$(comm -23 <(echo "$body_verdicts") <(echo "$contract_verdicts"))"
  diff2="$(comm -13 <(echo "$body_verdicts") <(echo "$contract_verdicts"))"
  local any_fail=0
  if [[ -n "$diff1" ]]; then
    while IFS= read -r v; do
      fail "$label" "check_output_contract_completeness: verdict '$v' declared in body but absent from Output Contract YAML examples"
    done <<<"$diff1"
    any_fail=1
  fi
  if [[ -n "$diff2" ]]; then
    while IFS= read -r v; do
      fail "$label" "check_output_contract_completeness: verdict '$v' in Output Contract YAML but not in 'Allowed verdicts:' line"
    done <<<"$diff2"
    any_fail=1
  fi
  # Scan body text for backtick-quoted verdict values outside Allowed verdicts line
  local full_body body_backticked
  full_body="$(awk '/^## Output Contract$/{exit} {print}' "$skill_path")"
  body_backticked="$(echo "$full_body" | awk '/^Allowed verdicts/{next} {while(match($0,/`[^`]*`/)){v=substr($0,RSTART+1,RLENGTH-2);$0=substr($0,RSTART+RLENGTH);if(v~/^(awaiting-input|awaiting-approval|review-pass-approval-pending|phase-complete|redirect)$/)print v}}' | sort -u)"
  if [[ -n "$body_backticked" ]]; then
    local undeclared
    undeclared="$(comm -23 <(echo "$body_backticked") <(echo "$body_verdicts"))"
    if [[ -n "$undeclared" ]]; then
      while IFS= read -r v; do
        fail "$label" "check_output_contract_completeness: verdict '$v' referenced in body but not in 'Allowed verdicts:' line"
      done <<<"$undeclared"
      any_fail=1
    fi
  fi
  if [[ $any_fail -eq 0 ]]; then
    echo "  check_output_contract_completeness: PASS"
  fi
  return 0
}

# check_fixture_expectation_parity:
#   Parse each fixture's expectations.md expected-verdict YAML block and
#   validate it matches one of the verdicts declared in the target skill's
#   `## Output Contract`.
check_fixture_expectation_parity() {
  local fixture="$1" fixture_dir="$2"
  local expectations_md="$fixture_dir/expectations.md"
  [[ -f "$expectations_md" ]] || return 0
  local fm declared_skill ignore_fields max_attempts
  fm="$(extract_frontmatter "$expectations_md")"
  declared_skill="$(echo "$fm" | yaml_scalar dispatch_skill)"
  ignore_fields="$(echo "$fm" | yaml_scalar ignore_fields)"
  max_attempts="$(echo "$fm" | yaml_scalar max_attempts)"
  if [[ -z "$declared_skill" ]]; then
    fail "$fixture" "check_fixture_expectation_parity: missing dispatch_skill in expectations.md frontmatter"
    return 1
  fi
  if [[ -z "$ignore_fields" ]]; then
    fail "$fixture" "check_fixture_expectation_parity: missing ignore_fields in expectations.md frontmatter"
    return 1
  fi
  if [[ -z "$max_attempts" ]]; then
    fail "$fixture" "check_fixture_expectation_parity: missing max_attempts in expectations.md frontmatter"
    return 1
  fi
  # Extract the ## Expected verdict section
  local section trial
  section="$(extract_section "$expectations_md" "Expected verdict")"
  trial="$(echo "$section" | extract_yaml_blocks)"
  local exp_verdict
  exp_verdict="$(echo "$trial" | yaml_block_scalar verdict)"
  if [[ -z "$exp_verdict" ]]; then
    fail "$fixture" "check_fixture_expectation_parity: no signal_verdict YAML block in Expected verdict section"
    return 1
  fi
  local target_skill
  target_skill="$declared_skill"
  if [[ "$target_skill" == "signal" ]]; then
    if ! echo "$VERDICT_VALUES_LIST" | tr ' ' '\n' | grep -qFx "$exp_verdict"; then
      fail "$fixture" "check_fixture_expectation_parity: expected verdict '$exp_verdict' is not a legal signal verdict"
      return 1
    fi
    echo "  check_fixture_expectation_parity: PASS (signal orchestrator -> $exp_verdict)"
    return 0
  fi
  # Get that skill's allowed verdicts
  local skill_path="$SKILLS_DIR/$target_skill/SKILL.md"
  if [[ ! -f "$skill_path" ]]; then
    fail "$fixture" "check_fixture_expectation_parity: target skill '$target_skill' not found at $skill_path"
    return 1
  fi
  local allowed
  allowed="$(extract_section "$skill_path" "Output Contract" | get_allowed_verdicts | sort -u)"
  if [[ -z "$allowed" ]]; then
    # Fallback: extract verdict values from YAML blocks when no "Allowed verdicts" line exists
    allowed="$(extract_section "$skill_path" "Output Contract" | extract_yaml_verdict_values "$target_skill" | sort -u)"
  fi
  if [[ -z "$allowed" ]]; then
    # Skill has neither — skip (user-invocable skills like signal-team)
    return 0
  fi
  if ! echo "$allowed" | grep -qFx "$exp_verdict"; then
    fail "$fixture" "check_fixture_expectation_parity: expected verdict '$exp_verdict' not in $target_skill allowed verdicts ($(echo "$allowed" | tr '\n' ' '))"
    return 1
  fi
  echo "  check_fixture_expectation_parity: PASS ($target_skill -> $exp_verdict)"
  return 0
}

# ─── Skill-level scan ────────────────────────────────────────────────────────

scan_skills() {
  echo
  echo "========== Skill Static Checks =========="
  for skill_dir in "$SKILLS_DIR"/*/; do
    [[ -d "$skill_dir" ]] || continue
    local skill_file="$skill_dir/SKILL.md"
    [[ -f "$skill_file" ]] || continue
    local skill_name
    skill_name="$(basename "$skill_dir")"
    echo
    echo "--- skill: $skill_name ---"
    check_verdict_encoding_parity "$skill_name" "$skill_file"
    check_output_contract_completeness "$skill_name" "$skill_file"
  done
}

run_fixture() {
  local fixture_dir="$1"
  local name expectations_fm expected_result expected_error failures_before failures_after fixture_failure
  name="$(basename "$fixture_dir")"
  failures_before=${#FAILS[@]}
  echo
  echo "=== fixture: $name ==="

  if [[ ! -f "$fixture_dir/expectations.md" ]]; then
    fail "$name" "missing expectations.md"
    return
  fi

  expectations_fm="$(extract_frontmatter "$fixture_dir/expectations.md")"
  expected_result="$(echo "$expectations_fm" | yaml_scalar expected_static_result)"
  expected_result="${expected_result:-pass}"
  expected_error="$(echo "$expectations_fm" | yaml_scalar expected_error)"
  case "$expected_result" in
    pass|fail) ;;
    *) fail "$name" "expectations.md expected_static_result must be pass or fail: '$expected_result'"; return ;;
  esac

  echo "expectations:"
  awk '/^# Trigger$|^## Trigger$/{print_p=1; print "  ---"; next}
       /^# Expected verdict$|^## Expected verdict$/{print_p=2; print "  --- expected verdict ---"; next}
       /^# Expected mutations$|^## Expected mutations$/{print_p=0}
       print_p && NF{print "  "$0}' "$fixture_dir/expectations.md" | head -40

  if [[ ! -d "$fixture_dir/.signal" ]]; then
    fail "$name" "missing .signal/ snapshot"
    return
  fi

  while IFS= read -r task_dir; do
    check_no_sentinels "$name" "$task_dir"
    [[ -f "$task_dir/task.md" ]] && check_task_md "$name" "$task_dir/task.md"
    [[ -f "$task_dir/compliance.md" ]] && check_compliance_md "$name" "$task_dir/compliance.md"
    [[ -f "$task_dir/publish-log.md" ]] && check_publish_log "$name" "$task_dir/publish-log.md"
    [[ -f "$task_dir/review.md" ]] && check_review_md "$name" "$task_dir/review.md"
    while IFS= read -r asset_md; do
      check_asset_md "$name" "$asset_md"
    done < <(find "$task_dir" -maxdepth 1 -type f -name 'A[0-9]*.md')
    check_marketer_metadata "$name" "$task_dir"
  done < <(find "$fixture_dir/.signal/tasks" -mindepth 1 -maxdepth 1 -type d 2>/dev/null)

  # Layer 1: fixture expectation parity check
  check_fixture_expectation_parity "$name" "$fixture_dir"

  failures_after=${#FAILS[@]}
  if [[ "$expected_result" == "fail" ]]; then
    if (( failures_after == failures_before )); then
      fail "$name" "expected static failure but fixture passed"
      return
    fi
    if [[ -z "$expected_error" ]]; then
      fail "$name" "expected-invalid fixture is missing expected_error"
      return
    fi
    for ((index=failures_before; index<failures_after; index++)); do
      fixture_failure="${FAILS[index]}"
      if [[ "$fixture_failure" != *"$expected_error"* ]]; then
        fail "$name" "unexpected failure in expected-invalid fixture: ${fixture_failure#"$name: "}"
        return
      fi
    done
    FAILS=("${FAILS[@]:0:failures_before}")
    FAIL_COUNT=$((FAIL_COUNT - (failures_after - failures_before)))
    PASS_COUNT=$((PASS_COUNT + 1))
    echo "  EXPECTED FAIL: $expected_error"
    return
  fi
  if (( failures_after == failures_before )); then
    PASS_COUNT=$((PASS_COUNT + 1))
    echo "  PASS"
  fi
}

echo
echo "============================================================"
echo "Signal7 Static Checks Runner"
echo "============================================================"

scan_skills

for fx in "$FIXTURES_DIR"/*/; do
  [[ -d "$fx" ]] || continue
  case "$(basename "$fx")" in
    .|..) continue ;;
  esac
  run_fixture "$fx"
done

echo
echo "------------------------------------------------------------"
echo "Signal7 checks: $PASS_COUNT passed, $FAIL_COUNT failures"
if (( FAIL_COUNT > 0 )); then
  printf 'FAIL: %s\n' "${FAILS[@]}"
  exit 1
fi
exit 0
