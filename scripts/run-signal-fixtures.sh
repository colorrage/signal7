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

run_fixture() {
  local fixture_dir="$1"
  local name
  name="$(basename "$fixture_dir")"
  echo
  echo "=== fixture: $name ==="

  if [[ ! -f "$fixture_dir/expectations.md" ]]; then
    fail "$name" "missing expectations.md"
    return
  fi

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
  done < <(find "$fixture_dir/.signal/tasks" -mindepth 1 -maxdepth 1 -type d 2>/dev/null)

  if (( FAIL_COUNT == 0 )) || ! printf '%s\n' "${FAILS[@]}" | grep -q "^$name:"; then
    PASS_COUNT=$((PASS_COUNT + 1))
    echo "  PASS"
  fi
}

for fx in "$FIXTURES_DIR"/*/; do
  [[ -d "$fx" ]] || continue
  case "$(basename "$fx")" in
    .|..) continue ;;
  esac
  run_fixture "$fx"
done

echo
echo "------------------------------------------------------------"
echo "Signal7 fixtures: $PASS_COUNT passed, $FAIL_COUNT failures"
if (( FAIL_COUNT > 0 )); then
  printf 'FAIL: %s\n' "${FAILS[@]}"
  exit 1
fi
exit 0
