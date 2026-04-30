#!/usr/bin/env bash
# Signal7 replay-fixture.sh — Layer 2 QA harness
#
# Replays a fixture through the declared skill via the host's sub-agent
# mechanism. Compares verdict and mutations, retries on behavioral failure.
#
# Usage:
#   ./scripts/replay-fixture.sh <fixture-name> [--host claude] [--strict]
#
# Exit codes:
#   0 — pass (verdict + mutations both match on any attempt)
#   1 — behavioral failure (all retry attempts exhausted)
#   2 — execution error (host unavailable, missing skill, no verdict block)

set -u
set -o pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
FIXTURES_DIR="$ROOT/evals/signal-fixtures"
SKILLS_DIR="$ROOT/skills"

# ─── Configurable defaults ────────────────────────────────────────────────────

HOST="${SIGNAL7_HOST:-claude}"
STRICT=false
VERBOSE=false
FIXTURE_NAME=""
DEFAULT_MAX_ATTEMPTS=3
MAX_HOST_ERRORS=5       # cap on non-budget-consuming retries

# ─── Temp directory ───────────────────────────────────────────────────────────

TMPDIR=""

cleanup() {
    local rc=$?
    if [[ -n "$TMPDIR" && -d "$TMPDIR" ]]; then
        rm -rf "$TMPDIR" 2>/dev/null
    fi
    # Only emit exit on EXIT (not SIGINT/SIGTERM — those already produce a code)
    if [[ -z "${CLEANUP_SIGNAL-}" ]]; then
        exit $rc
    fi
}
trap cleanup EXIT
trap 'CLEANUP_SIGNAL=1 cleanup' SIGINT SIGTERM

# ─── Argument parsing ─────────────────────────────────────────────────────────

usage() {
    echo "Usage: $0 <fixture-name> [--host claude|opencode] [--strict] [--verbose]"
    echo
    echo "  fixture-name   directory name under evals/signal-fixtures/"
    echo "  --host         host CLI to dispatch through (default: claude)"
    echo "  --strict       require exact summary text match (default: lenient key-term check)"
    echo "  --verbose      print dispatch output and diff details"
    exit 2
}

parse_args() {
    if [[ $# -lt 1 ]]; then
        usage
    fi
    # Handle help flag as first (or only) argument
    if [[ "$1" == "-h" || "$1" == "--help" ]]; then
        usage
    fi
    FIXTURE_NAME="$1"
    shift
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --host)
                shift
                [[ $# -lt 1 ]] && usage
                HOST="$1"
                ;;
            --strict) STRICT=true ;;
            --verbose) VERBOSE=true ;;
            -h|--help) usage ;;
            *)
                echo "Unknown flag: $1" >&2
                usage
                ;;
        esac
        shift
    done
}

# ─── YAML / markdown helpers (adapted from run-signal-fixtures.sh) ────────────

# Extract YAML frontmatter from first --- pair in a file.
extract_frontmatter() {
    local file="$1"
    awk 'BEGIN{open=0} /^---[[:space:]]*$/{open++; if(open==1){next}; if(open==2){exit}} {if(open==1) print}' "$file"
}

# Extract the value of a top-level YAML scalar key from stdin.
yaml_scalar() {
    local key="$1"
    awk -v k="$key" '
        $0 ~ "^[[:space:]]*"k"[[:space:]]*:[[:space:]]*" {
            sub("^[[:space:]]*"k"[[:space:]]*:[[:space:]]*","")
            sub("[[:space:]]+#.*$","")
            gsub(/^["'"'"']|["'"'"']$/,"")
            gsub(/^[[:space:]]+|[[:space:]]+$/,"")
            print
            exit
        }'
}

# Extract a YAML list value for a top-level scalar key from stdin.
# Handles: key: [a, b, c]  and  key:\n  - a\n  - b
yaml_list() {
    local key="$1"
    awk -v k="$key" '
        BEGIN{in_key=0; buff=""}
        $0 ~ "^[[:space:]]*"k"[[:space:]]*:[[:space:]]*" {
            sub("^[[:space:]]*"k"[[:space:]]*:[[:space:]]*","")
            gsub(/^[[:space:]]+|[[:space:]]+$/,"")
            line = $0
            if (line ~ /^\[/) {
                gsub(/^\[|\]$/,"",line)
                gsub(/[[:space:]]*,[[:space:]]*/,"\n",line)
                gsub(/["'"'"']/,"",line)
                gsub(/^[[:space:]]+|[[:space:]]+$/,"",line)
                if (line != "") print line
                exit
            }
            # Flow-style scalar case (should not happen for lists, but be safe)
            exit
        }'
}

# Extract a markdown section body (from heading to next ## or EOF).
extract_section() {
    local file="$1" heading="$2"
    awk -v h="^## $heading$" '
        BEGIN{in_sec=0}
        $0 ~ h {in_sec=1; next}
        in_sec && /^## / {exit}
        in_sec {print}
    ' "$file"
}

# Extract TRIGGER from expectations.md (## Trigger section).
extract_trigger() {
    local expectations_md="$1"
    local trigger
    trigger="$(extract_section "$expectations_md" "Trigger")"
    if [[ -z "$trigger" ]]; then
        trigger="$(extract_section "$expectations_md" "Snapshot point")"
    fi
    if [[ -z "$trigger" ]]; then
        trigger="dispatch the declared skill"
    fi
    echo "$trigger"
}

# Extract the last fenced YAML block from stdin.
# Output: body of the last block between ```yaml and ``` (just the YAML text).
extract_last_yaml_block() {
    awk '
        /^```yaml/ { in_block=1; block=""; next }
        in_block && /^```/ { in_block=0; last=block; block=""; next }
        in_block { block = block $0 "\n" }
        END { if (last != "") printf "%s", last }
    '
}

# Extract a nested YAML scalar under signal_verdict: from stdin.
# Example: extract_verdict_field "verdict" reads `verdict: phase-complete`
# when preceded by `signal_verdict:` at a lower indent level.
extract_verdict_field() {
    local field="$1"
    awk -v f="$field" '
        /^[[:space:]]*signal_verdict:/ { in_block=1; next }
        in_block {
            # Match the field at any depth under signal_verdict
            if ($0 ~ "^[[:space:]]*"f"[[:space:]]*:[[:space:]]*") {
                val = $0
                sub("^[[:space:]]*"f"[[:space:]]*:[[:space:]]*", "", val)
                sub("[[:space:]]+#.*$", "", val)
                gsub(/^["'"'"']|["'"'"']$/,"",val)
                gsub(/^[[:space:]]+|[[:space:]]+$/,"",val)
                print val
                exit
            }
            # A new top-level key (not indented) ends the signal_verdict block
            if ($0 ~ /^[a-zA-Z_]/) { exit }
        }
    '
}

# ─── Pre-flight validation ────────────────────────────────────────────────────

die() {
    echo "ERROR: $*" >&2
    exit 2
}

validate_fixture() {
    local fixture_dir
    fixture_dir="$FIXTURES_DIR/$FIXTURE_NAME"
    if [[ ! -d "$fixture_dir" ]]; then
        die "fixture not found: $fixture_dir"
    fi
    local expectations_md="$fixture_dir/expectations.md"
    if [[ ! -f "$expectations_md" ]]; then
        die "missing expectations.md in $fixture_dir"
    fi
    FIXTURE_DIR="$fixture_dir"
    EXPECTATIONS_MD="$expectations_md"
}

validate_skill() {
    local dispatch_skill="$1"
    if [[ -z "$dispatch_skill" || "$dispatch_skill" == "null" ]]; then
        die "missing dispatch_skill in expectations.md frontmatter"
    fi
    local skill_path="$SKILLS_DIR/$dispatch_skill/SKILL.md"
    if [[ ! -f "$skill_path" ]]; then
        die "unknown skill '$dispatch_skill' (no $skill_path)"
    fi
    SKILL_PATH="$skill_path"
}

parse_frontmatter_fields() {
    local expectations_md="$1"
    local fm
    fm="$(extract_frontmatter "$expectations_md")"
    if [[ -z "$fm" ]]; then
        die "$expectations_md has no YAML frontmatter"
    fi

    DISPATCH_SKILL="$(echo "$fm" | yaml_scalar dispatch_skill)"
    local ignore_raw
    ignore_raw="$(echo "$fm" | yaml_list ignore_fields)"
    if [[ -n "$ignore_raw" ]]; then
        IGNORE_FIELDS_ARR=()
        while IFS= read -r field; do
            field="${field// /}"
            [[ -n "$field" ]] && IGNORE_FIELDS_ARR+=("$field")
        done <<<"$ignore_raw"
    else
        IGNORE_FIELDS_ARR=(updated_at timestamp actual_publish_time)
    fi
    local ma
    ma="$(echo "$fm" | yaml_scalar max_attempts)"
    MAX_ATTEMPTS="${ma:-$DEFAULT_MAX_ATTEMPTS}"
    # Ensure integer
    MAX_ATTEMPTS=$(( MAX_ATTEMPTS + 0 ))
    if [[ $MAX_ATTEMPTS -lt 1 ]]; then
        MAX_ATTEMPTS=1
    fi
}

# ─── Temp environment ─────────────────────────────────────────────────────────

create_temp_env() {
    local fixture_dir="$1"
    local ts timestamp
    timestamp="$(date +%Y%m%d-%H%M%S)"
    ts="${FIXTURE_NAME}-${timestamp}"

    # Create inside the project so skills/ is discoverable from the working dir
    TMPDIR="$ROOT/.replay-${ts}"
    mkdir -p "$TMPDIR" || {
        echo "ERROR: cannot create replay directory: $TMPDIR" >&2
        exit 2
    }

    # Copy .signal snapshot from fixture into the replay dir
    if [[ -d "$fixture_dir/.signal" ]]; then
        cp -r "$fixture_dir/.signal" "$TMPDIR/.signal"
    else
        echo "ERROR: fixture has no .signal/ snapshot: $fixture_dir" >&2
        rm -rf "$TMPDIR" 2>/dev/null
        exit 2
    fi
}

# ─── Pre-replay state snapshot ────────────────────────────────────────────────

snapshot_state() {
    local dir="$1" output_file="$2"
    >"$output_file"
    if [[ ! -d "$dir" ]]; then
        return
    fi
    while IFS= read -r -d '' file; do
        local rel="${file#$dir/}"
        [[ "$rel" == .DS_Store ]] && continue
        local hash
        hash="$(shasum -a 256 "$file" 2>/dev/null | awk '{print $1}')"
        if [[ "$rel" == *.md ]]; then
            local fm
            fm="$(extract_frontmatter "$file" 2>/dev/null || true)"
            if [[ -n "$fm" ]]; then
                echo "FILE: $rel sha256:$hash" >> "$output_file"
                while IFS= read -r yline; do
                    local key="${yline%%:*}"
                    key="${key// /}"
                    if [[ -n "$key" && "$key" != "---" && "$key" != "..." ]]; then
                        local val="${yline#*:}"
                        # Trim whitespace and quotes from value
                        val="${val#"${val%%[![:space:]]*}"}"
                        val="${val%"${val##*[![:space:]]}"}"
                        val="${val#\"}"
                        val="${val%\"}"
                        val="${val#\'}"
                        val="${val%\'}"
                        echo "  $key: $val" >> "$output_file"
                    fi
                done <<<"$fm"
                echo "" >> "$output_file"
            else
                # No frontmatter but still track the file with hash
                echo "FILE: $rel sha256:$hash (no-fm)" >> "$output_file"
            fi
        else
            echo "FILE: $rel (non-md) sha256:$hash" >> "$output_file"
        fi
    done < <(find "$dir" -type f -print0 2>/dev/null)
}

# ─── Host dispatch ────────────────────────────────────────────────────────────

generate_dispatch_prompt() {
    local dispatch_skill="$1"
    local trigger_text="$2"
    cat <<PROMPT
You are performing a QA replay test for Signal7. Load the \`$dispatch_skill\` skill by reading \`skills/$dispatch_skill/SKILL.md\`, then follow ALL instructions in that file exactly — including reading any referenced documents under \`skills/signal/reference/\`.

The Signal7 project state for this test lives under \`$TMPDIR/.signal/\`, NOT under the usual \`.signal/\` at the project root. Read the state files under \`$TMPDIR/.signal/tasks/\` and any other files the skill requires. Write all output artifacts into that same \`$TMPDIR/.signal/\` tree — do not touch the project's \`.signal/\` directory.

Trigger event: $trigger_text

Execute the skill completely. Write any artifacts the skill specifies. When finished, your ENTIRE response must consist of exactly one fenced YAML block — the \`signal_verdict\` — followed by nothing else. The verdict must follow the shape defined in the skill's \`## Output Contract\` section:

\`\`\`yaml
signal_verdict:
  verdict: <value>
  target: <value>
  summary: "<text>"
\`\`\`
PROMPT
}

dispatch_claude() {
    local prompt="$1"
    local output rc
    cd "$TMPDIR" || { echo "ERROR: cannot cd to temp dir" >&2; return 2; }
    set +o pipefail
    output="$(claude -p "$prompt" \
        --output-format json \
        --permission-mode bypassPermissions \
        --bare \
        --tools "Read,Write,Edit,Bash,Glob,Grep" \
        --max-budget-usd 5 \
        --max-turns 30 \
        2>&1)"
    rc=$?
    set -o pipefail
    # echo "DEBUG claude rc=$rc" >&2
    # Try to extract the "result" field from JSON output
    local result
    result="$(echo "$output" | python3 -c "
import sys, json
raw = sys.stdin.read()
try:
    data = json.loads(raw)
    print(data.get('result', ''))
except:
    print(raw)
" 2>/dev/null || echo "$output")"
    echo "$result"
    return $rc
}

dispatch_opencode() {
    local prompt="$1"
    local output rc
    set +o pipefail
    # Run from project root so skills/ is discoverable; point .signal/ at the replay dir
    output="$(cd "$ROOT" && opencode run "$prompt" \
        --dir "$ROOT" \
        --dangerously-skip-permissions \
        2>&1)"
    rc=$?
    set -o pipefail
    # opencode outputs plain text with the verdict YAML at the end
    echo "$output"
    return $rc
}

invoke_host() {
    local prompt="$1"
    case "$HOST" in
        claude)
            dispatch_claude "$prompt"
            ;;
        opencode)
            dispatch_opencode "$prompt"
            ;;
        *)
            echo "ERROR: unknown host '$HOST'. Supported: claude, opencode" >&2
            return 2
            ;;
    esac
}

# ─── Verdict comparison ───────────────────────────────────────────────────────

# Extract key terms from a summary string.
# Returns space-separated lowercase words, filtered to meaningful content.
extract_key_terms() {
    local text="$1"
    echo "$text" | tr '[:upper:]' '[:lower:]' | tr -c '[:alnum:]' '\n' | \
        awk 'length($0) >= 3 && $0 !~ /^(the|and|for|are|was|has|not|but|all|any|its|his|her|our|their|this|that|with|from|have|been|will|would|could|should|may|might|shall|can)$/' | \
        sort -u
}

is_in_list() {
    local val="$1"
    shift
    for item in "$@"; do
        [[ "$item" == "$val" ]] && return 0
    done
    return 1
}

compare_verdicts() {
    local actual_verdict="$1" actual_target="$2" actual_summary="$3"
    local expected_verdict="$4" expected_target="$5" expected_summary="$6"

    # Verdict must match exactly
    if [[ "$actual_verdict" != "$expected_verdict" ]]; then
        echo "  verdict mismatch: expected '$expected_verdict', got '$actual_verdict'"
        return 1
    fi

    # Target must match exactly
    if [[ "$actual_target" != "$expected_target" ]]; then
        echo "  target mismatch: expected '$expected_target', got '$actual_target'"
        return 1
    fi

    # Summary comparison
    if $STRICT; then
        # Strict: normalize whitespace and compare
        local norm_actual norm_expected
        norm_actual="$(echo "$actual_summary" | tr -s '[:space:]' ' ' | xargs)"
        norm_expected="$(echo "$expected_summary" | tr -s '[:space:]' ' ' | xargs)"
        if [[ "$norm_actual" != "$norm_expected" ]]; then
            echo "  summary mismatch (strict): expected '$expected_summary', got '$actual_summary'"
            return 1
        fi
    else
        # Lenient: check that key terms from expected appear in actual
        local exp_terms
        exp_terms="$(extract_key_terms "$expected_summary")"
        if [[ -z "$exp_terms" ]]; then
            return 0
        fi
        local actual_lower
        actual_lower="$(echo "$actual_summary" | tr '[:upper:]' '[:lower:]')"
        local missing_terms=()
        for term in $exp_terms; do
            if ! echo "$actual_lower" | grep -qF "$term"; then
                missing_terms+=("$term")
            fi
        done
        if [[ ${#missing_terms[@]} -gt 0 ]]; then
            echo "  summary key-term mismatch (lenient): missing [${missing_terms[*]}] in actual summary"
            return 1
        fi
        # Check if summaries are substantially different
        local norm_actual norm_expected
        norm_actual="$(echo "$actual_summary" | tr -s '[:space:]' ' ' | xargs)"
        norm_expected="$(echo "$expected_summary" | tr -s '[:space:]' ' ' | xargs)"
        if [[ "$norm_actual" != "$norm_expected" ]]; then
            echo "  NOTE: summary text diverges but key terms present (lenient pass)"
        fi
    fi
    return 0
}

# ─── Mutation diff ────────────────────────────────────────────────────────────

# Takes a pre-state snapshot from stdin in a known snapshot format.
mutation_diff() {
    local fixture_dir="$1"

    # Snapshot post-replay state (pre-state was saved before dispatch in main)
    if [[ -d "$TMPDIR/.signal" ]]; then
        snapshot_state "$TMPDIR/.signal" "$TMPDIR/.replay-post.snap"
    fi

    # Diff the snapshots (pre was saved at $TMPDIR/.replay-pre.snap before dispatch)
    local diff_output
    diff_output="$(diff "$TMPDIR/.replay-pre.snap" "$TMPDIR/.replay-post.snap" 2>&1)" || true

    if [[ -z "$diff_output" ]]; then
        echo "  mutation diff: no changes detected (pass)"
        return 0
    fi

    # Parse expected mutations for field-level expectations
    local expectations_md="$fixture_dir/expectations.md"
    local expected_mutations
    expected_mutations="$(extract_section "$expectations_md" "Expected mutations")"

    local any_fail=0
    local line_type=""  # add=post-only, del=pre-only, or change

    # Process diff output
    while IFS= read -r dline; do
        # Classify diff line
        case "${dline:0:1}" in
            "<") line_type="pre" ;;
            ">") line_type="post" ;;
            [0-9]*) line_type="hunk" ;;
            *) line_type="meta" ;;
        esac

        if [[ "$line_type" == "post" ]]; then
            local content="${dline#"> "}"
            if [[ "$content" == "FILE: "* ]]; then
                local filepath="${content#FILE: }"
                # Strip hash suffix: "path/to/file sha256:xxx" or "path/to/file (no-fm) sha256:xxx" or "path/to/file (non-md) sha256:xxx"
                filepath="${filepath%% sha256:*}"
                filepath="${filepath%% (no-fm)*}"
                filepath="${filepath%% (non-md)*}"
                local basename="${filepath##*/}"
                # Check if file existed in pre (any hash variant)
                if ! grep -q "FILE: $filepath" "$TMPDIR/.replay-pre.snap" 2>/dev/null; then
                    if [[ -n "$expected_mutations" ]] && echo "$expected_mutations" | grep -qF "$basename"; then
                        echo "  mutation diff: new file $filepath (expected)"
                        continue
                    fi
                    echo "  mutation diff: UNEXPECTED new file: $filepath"
                    any_fail=1
                elif ! grep -qFx "FILE: $filepath${content#"$filepath"}" "$TMPDIR/.replay-pre.snap" 2>/dev/null; then
                    # Same path but different hash/body. Allow only when the
                    # expected mutations name the file explicitly.
                    if [[ -n "$expected_mutations" ]] && {
                        echo "$expected_mutations" | grep -qF "$basename" ||
                        echo "$expected_mutations" | grep -qF "$filepath"
                    }; then
                        continue
                    fi
                    echo "  mutation diff: unexpected file content change: $filepath"
                    any_fail=1
                fi
            elif [[ "$content" == "  "* ]]; then
                local fentry="${content#  }"
                local fname="${fentry%%:*}"
                local fval="${fentry#*: }"
                # Check if field is in ignore_fields
                local ignored=false
                for ignore in "${IGNORE_FIELDS_ARR[@]}"; do
                    if [[ "$fname" == "$ignore" ]]; then
                        ignored=true
                        break
                    fi
                done
                if $ignored; then
                    if [[ -z "$fval" || "$fval" == "null" ]]; then
                        echo "  mutation diff: ignore-field $fname is null (warn)"
                    fi
                    continue
                fi
                # Check if expected
                if [[ -n "$expected_mutations" ]] && echo "$expected_mutations" | grep -qF "$fname"; then
                    continue
                fi
                echo "  mutation diff: unexpected field change: $fname -> '$fval'"
                any_fail=1
            fi
        elif [[ "$line_type" == "pre" ]]; then
            local content="${dline#"< "}"
            if [[ "$content" == "FILE: "* ]]; then
                local filepath="${content#FILE: }"
                filepath="${filepath%% sha256:*}"
                filepath="${filepath%% (no-fm)*}"
                filepath="${filepath%% (non-md)*}"
                # Check if file still exists in post
                if ! grep -q "FILE: $filepath" "$TMPDIR/.replay-post.snap" 2>/dev/null; then
                    if [[ -n "$expected_mutations" ]] && echo "$expected_mutations" | grep -qi "archive\|move\|remove"; then
                        echo "  mutation diff: removed file $filepath (expected archive/move)"
                        continue
                    fi
                    echo "  mutation diff: UNEXPECTED removed file: $filepath"
                    any_fail=1
                fi
            fi
        fi
    done <<<"$diff_output"

    # Also check for expected mutations that didn't happen
    if [[ -n "$expected_mutations" ]]; then
        # Check for expected file creation
        local exp_files
        exp_files="$(echo "$expected_mutations" | grep -oE '`[^`]+\.md`' | sed 's/`//g' || true)"
        while IFS= read -r exp_file; do
            [[ -z "$exp_file" ]] && continue
            local found=false
            while IFS= read -r -d '' actual_file; do
                if [[ "$actual_file" == *"$exp_file" ]]; then
                    found=true
                    break
                fi
            done < <(find "$TMPDIR/.signal" -type f -name "$exp_file" -print0 2>/dev/null)
            if ! $found; then
                # Check if this is an orchestrator-level mutation (task.md phase changes, archiving)
                if [[ "$exp_file" == "task.md" ]] || [[ "$exp_file" == "dashboard.md" ]]; then
                    if [[ "$DISPATCH_SKILL" != "signal" ]]; then
                        echo "  mutation diff: orchestrator-level mutation not verified: $exp_file (requires signal verdict application)"
                        continue
                    fi
                fi
                echo "  mutation diff: expected file not found: $exp_file"
                any_fail=1
            fi
        done <<<"$exp_files"

        # Check for expected field values in existing files
        local exp_pairs
        exp_pairs="$({
            echo "$expected_mutations" | grep -oE '`[a-zA-Z_]+:[^`]+`' | sed 's/`//g' || true
            echo "$expected_mutations" | awk '/^[[:space:]]+[a-zA-Z_]+:[[:space:]]*/ {
                sub(/^[[:space:]]+/, "")
                print
            }' || true
        } | sort -u)"
        while IFS= read -r pair; do
            [[ -z "$pair" ]] && continue
            local efname="${pair%%:*}"
            local efval="${pair#*: }"
            efval="${efval//\"/}"
            # Skip ignore fields
            local ignored=false
            for ignore in "${IGNORE_FIELDS_ARR[@]}"; do
                [[ "$efname" == "$ignore" ]] && { ignored=true; break; }
            done
            if $ignored; then
                if ! grep -Eq "^[[:space:]]+$efname:[[:space:]]*[^[:space:]]" "$TMPDIR/.replay-post.snap" 2>/dev/null; then
                    echo "  mutation diff: expected ignore-field missing or empty: $efname"
                    any_fail=1
                fi
                continue
            fi
            # Check if present in post-snapshot
            if grep -qF "  $efname: $efval" "$TMPDIR/.replay-post.snap" 2>/dev/null; then
                continue
            fi
            # Not found — but could be orchestrator-level (phase changes etc.)
            if [[ "$efname" == "phase" ]] || [[ "$efname" == "awaiting" ]]; then
                if [[ "$DISPATCH_SKILL" != "signal" ]]; then
                    echo "  mutation diff: orchestrator-level field $efname:$efval not applied (requires signal verdict application)"
                    continue
                fi
            fi
            echo "  mutation diff: expected field-value not found: $efname: $efval"
            any_fail=1
        done <<<"$exp_pairs"

        if [[ "$DISPATCH_SKILL" == "signal" ]] && echo "$expected_mutations" | grep -q '\.signal/archive/'; then
            local expected_archive
            expected_archive="$(echo "$expected_mutations" | grep -oE '\.signal/archive/S[0-9][^/`]*/' | head -1 | sed 's#^\.signal/##; s#/$##')"
            if [[ -n "$expected_archive" && ! -d "$TMPDIR/.signal/$expected_archive" ]]; then
                echo "  mutation diff: expected archive folder not found: .signal/$expected_archive"
                any_fail=1
            fi
        fi
    fi

    if [[ $any_fail -eq 0 ]]; then
        echo "  mutation diff: pass"
    fi
    return $any_fail
}

# ─── Main replay logic ────────────────────────────────────────────────────────

main() {
    parse_args "$@"

    # Step 1: Locate and validate fixture (sets FIXTURE_DIR, EXPECTATIONS_MD)
    validate_fixture

    # Step 2: Parse frontmatter (sets DISPATCH_SKILL, IGNORE_FIELDS_ARR, MAX_ATTEMPTS)
    parse_frontmatter_fields "$EXPECTATIONS_MD"

    # Step 3: Validate skill (sets SKILL_PATH)
    validate_skill "$DISPATCH_SKILL"
    echo "fixture: $FIXTURE_NAME  skill: $DISPATCH_SKILL  host: $HOST  max_attempts: $MAX_ATTEMPTS  strict: $STRICT"

    # Step 4: Read expected verdict from expectations.md
    local expected_verdict_section
    expected_verdict_section="$(extract_section "$EXPECTATIONS_MD" "Expected verdict")"
    if [[ -z "$expected_verdict_section" ]]; then
        die "no ## Expected verdict section in $EXPECTATIONS_MD"
    fi
    local expected_verdict_yaml
    expected_verdict_yaml="$(echo "$expected_verdict_section" | extract_last_yaml_block)"
    if [[ -z "$expected_verdict_yaml" ]]; then
        die "no signal_verdict YAML block in Expected verdict section"
    fi
    EXPECTED_VERDICT="$(echo "$expected_verdict_yaml" | extract_verdict_field verdict)"
    EXPECTED_TARGET="$(echo "$expected_verdict_yaml" | extract_verdict_field target)"
    EXPECTED_SUMMARY="$(echo "$expected_verdict_yaml" | extract_verdict_field summary)"
    if [[ -z "$EXPECTED_VERDICT" ]]; then
        die "cannot parse expected verdict from expectations.md"
    fi

    # Step 5: Read trigger
    local trigger_text
    trigger_text="$(extract_trigger "$EXPECTATIONS_MD")"

    # Step 6: Create temp environment ONCE (reuse across retries)
    create_temp_env "$FIXTURE_DIR"

    # Step 7: Generate dispatch prompt
    local prompt
    prompt="$(generate_dispatch_prompt "$DISPATCH_SKILL" "$trigger_text")"

    # Step 8: Retry loop
    local behavioral_fails=0
    local host_errors=0
    local overall_attempt=0

    while true; do
        overall_attempt=$((overall_attempt + 1))

        if [[ $behavioral_fails -ge $MAX_ATTEMPTS ]]; then
            echo "RESULT: FAIL (exhausted $MAX_ATTEMPTS behavioral retries)"
            exit 1
        fi
        if [[ $host_errors -ge $MAX_HOST_ERRORS ]]; then
            echo "RESULT: ERROR (too many host errors: $host_errors)"
            exit 2
        fi

        echo "--- attempt $overall_attempt (behavioral fails: $behavioral_fails/$MAX_ATTEMPTS, host errors: $host_errors) ---"

        # Reset temp state for retries: remove old .signal, re-copy from fixture
        # Reset state for retry — recreate .signal/ inside the project-local replay dir
        [[ -d "$TMPDIR/.signal" ]] && rm -rf "$TMPDIR/.signal" 2>/dev/null
        mkdir -p "$TMPDIR/.signal" 2>/dev/null
        cp -r "$FIXTURE_DIR/.signal"/* "$TMPDIR/.signal/" 2>/dev/null || {
            echo "  cannot recreate snapshot state (host error), retrying..."
            host_errors=$((host_errors + 1))
            continue
        }

        # Snapshot pre-replay state for mutation diff
        snapshot_state "$TMPDIR/.signal" "$TMPDIR/.replay-pre.snap"

        # Dispatch via host. Clear SIGTERM trap so the timeout signal from the
        # subprocess doesn't fire cleanup() and delete TMPDIR mid-retry.
        local dispatch_output dispatch_rc
        set +o pipefail
        local saved_term
        saved_term="$(trap -p SIGTERM 2>/dev/null || true)"
        trap '' SIGTERM 2>/dev/null
        dispatch_output="$(invoke_host "$prompt")"
        dispatch_rc=$?
        eval "$saved_term" 2>/dev/null || trap 'CLEANUP_SIGNAL=1 cleanup' SIGTERM
        set -o pipefail

        $VERBOSE && echo "--- dispatch output ---" && echo "$dispatch_output" && echo "--- end dispatch ---"

        if [[ $dispatch_rc -ne 0 ]]; then
            echo "  host dispatch returned error (rc=$dispatch_rc), retrying..."
            host_errors=$((host_errors + 1))
            continue
        fi

        # Extract the last YAML block from dispatch output
        local actual_verdict_yaml
        actual_verdict_yaml="$(echo "$dispatch_output" | extract_last_yaml_block)"
        if [[ -z "$actual_verdict_yaml" ]]; then
            echo "  host dispatch produced no YAML block (host error), retrying..."
            host_errors=$((host_errors + 1))
            continue
        fi

        local ACTUAL_VERDICT ACTUAL_TARGET ACTUAL_SUMMARY
        ACTUAL_VERDICT="$(echo "$actual_verdict_yaml" | extract_verdict_field verdict)"
        ACTUAL_TARGET="$(echo "$actual_verdict_yaml" | extract_verdict_field target)"
        ACTUAL_SUMMARY="$(echo "$actual_verdict_yaml" | extract_verdict_field summary)"

        if [[ -z "$ACTUAL_VERDICT" ]]; then
            echo "  cannot parse signal_verdict from YAML block (host error), retrying..."
            host_errors=$((host_errors + 1))
            continue
        fi

        echo "  actual verdict: $ACTUAL_VERDICT | target: $ACTUAL_TARGET | summary: $ACTUAL_SUMMARY"

        # Step 9: Compare verdict
        local verdict_result
        verdict_result="$(compare_verdicts \
            "$ACTUAL_VERDICT" "$ACTUAL_TARGET" "$ACTUAL_SUMMARY" \
            "$EXPECTED_VERDICT" "$EXPECTED_TARGET" "$EXPECTED_SUMMARY" 2>&1)"
        local verdict_rc=$?
        echo "$verdict_result"

        if [[ $verdict_rc -ne 0 ]]; then
            behavioral_fails=$((behavioral_fails + 1))
            continue
        fi

        # Step 10: Mutation diff
        local mutation_result
        mutation_result="$(mutation_diff "$FIXTURE_DIR" 2>&1)"
        local mutation_rc=$?
        echo "$mutation_result"

        if [[ $mutation_rc -ne 0 ]]; then
            behavioral_fails=$((behavioral_fails + 1))
            continue
        fi

        # Success!
        echo "RESULT: PASS (attempt $overall_attempt)"
        exit 0
    done
}

main "$@"
