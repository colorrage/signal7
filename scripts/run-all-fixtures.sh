#!/usr/bin/env bash
# Signal7 run-all-fixtures.sh — Layer 2 batch replay runner
#
# Walks all fixture directories under evals/signal-fixtures/ and replays each
# via replay-fixture.sh, collecting results into an aggregate summary report.
#
# Exit codes:
#   0 — all fixtures pass
#   1 — one or more fixtures fail (behavioral)
#   2 — one or more fixtures have execution errors
#
# Usage:
#   ./scripts/run-all-fixtures.sh [--host claude|opencode] [--strict] [--verbose]

set -u
set -o pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
FIXTURES_DIR="$ROOT/evals/signal-fixtures"
REPLAY_SCRIPT="$SCRIPT_DIR/replay-fixture.sh"

HOST="${SIGNAL7_HOST:-claude}"
STRICT=false
VERBOSE=false

usage() {
    echo "Usage: $0 [--host claude|opencode] [--strict] [--verbose]"
    echo
    echo "  --host      host CLI to dispatch through (default: claude)"
    echo "  --strict    require exact summary text match (default: lenient)"
    echo "  --verbose   print full replay output for each fixture"
    exit 2
}

parse_args() {
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

# ─── Per-fixture accumulators ──────────────────────────────────────────────────

declare -a FIXTURE_NAMES=()
declare -a FIXTURE_VERDICTS=()
declare -a FIXTURE_MUTATIONS=()
declare -a FIXTURE_ATTEMPTS=()
declare -a FIXTURE_STATUSES=()

# ─── Parse a single fixture's replay output ────────────────────────────────────
#
# Extracts verdict-result, mutation-result, attempt-count, and overall status
# from the text produced by replay-fixture.sh.

parse_fixture_output() {
    local output="$1"
    local fixture_name="$2"

    local verdict="PASS"
    local mutations="PASS"
    local attempts=0
    local status="PASS"

    # ── Overall status from RESULT line ──────────────────────────────────────
    local result_line
    result_line="$(echo "$output" | grep -E '^RESULT:' | tail -1)"

    if echo "$result_line" | grep -q "PASS"; then
        status="PASS"
    elif echo "$result_line" | grep -q "FAIL"; then
        status="FAIL"
    elif echo "$result_line" | grep -q "ERROR"; then
        status="ERROR"
    else
        status="ERROR"   # default: no RESULT line means the harness crashed
    fi

    # ── Attempt count ────────────────────────────────────────────────────────
    if [[ "$result_line" =~ attempt[[:space:]]([0-9]+) ]]; then
        attempts="${BASH_REMATCH[1]}"
    fi

    # ── Verdict result ───────────────────────────────────────────────────────
    if echo "$output" | grep -qE '(^\s+verdict mismatch|^\s+summary key-term mismatch|^\s+summary mismatch \(strict\))'; then
        verdict="FAIL"
    fi

    # ── Mutation result ──────────────────────────────────────────────────────
    if echo "$output" | grep -qE '(^\s+mutation diff:\s+(UNEXPECTED|unexpected|expected file not found))'; then
        mutations="FAIL"
    elif echo "$output" | grep -qE '(^\s+mutation diff:\s+(pass|no changes detected))'; then
        mutations="PASS"
    elif [[ "$status" == "FAIL" ]]; then
        # Behavioral failure without explicit mutation-fail lines → verdict-only failure
        mutations="PASS"
    fi

    # ── Error case: downgrade untested columns ───────────────────────────────
    if [[ "$status" == "ERROR" ]]; then
        if [[ "$verdict" == "PASS" ]]; then
            verdict="ERR"
        fi
        if [[ "$mutations" == "PASS" ]] && echo "$output" | grep -q "cannot parse signal_verdict"; then
            mutations="ERR"
        fi
    fi

    FIXTURE_NAMES+=("$fixture_name")
    FIXTURE_VERDICTS+=("$verdict")
    FIXTURE_MUTATIONS+=("$mutations")
    FIXTURE_ATTEMPTS+=("$attempts")
    FIXTURE_STATUSES+=("$status")
}

# ─── Replay one fixture ────────────────────────────────────────────────────────

run_one_fixture() {
    local fixture_name="$1"

    local replay_args=("$fixture_name" "--host" "$HOST")
    $STRICT && replay_args+=("--strict")
    $VERBOSE && replay_args+=("--verbose")

    set +o pipefail
    "$REPLAY_SCRIPT" "${replay_args[@]}" 2>&1
    local rc=$?
    set -o pipefail
    return $rc
}

# ─── Print summary table ───────────────────────────────────────────────────────

print_summary_table() {
    echo
    echo "============================================================"
    echo "                     Replay Summary"
    echo "============================================================"
    printf "%-30s %-8s %-10s %-8s %-8s\n" \
        "Fixture" "Verdict" "Mutations" "Attempts" "Status"
    printf "%-30s %-8s %-10s %-8s %-8s\n" \
        "------------------------------" "--------" "----------" "--------" "--------"

    local i
    for i in "${!FIXTURE_NAMES[@]}"; do
        printf "%-30s %-8s %-10s %-8s %-8s\n" \
            "${FIXTURE_NAMES[$i]}" \
            "${FIXTURE_VERDICTS[$i]}" \
            "${FIXTURE_MUTATIONS[$i]}" \
            "${FIXTURE_ATTEMPTS[$i]}" \
            "${FIXTURE_STATUSES[$i]}"
    done

    local total passed failed errored
    total="${#FIXTURE_NAMES[@]}"
    passed=0; failed=0; errored=0
    for s in "${FIXTURE_STATUSES[@]}"; do
        case "$s" in
            PASS)  passed=$((passed + 1)) ;;
            FAIL)  failed=$((failed + 1)) ;;
            ERROR) errored=$((errored + 1)) ;;
        esac
    done

    echo
    echo "------------------------------------------------------------"
    echo "Total: $total | Passed: $passed | Failed: $failed | Errors: $errored"
    echo "------------------------------------------------------------"
}

# ─── Main ──────────────────────────────────────────────────────────────────────

main() {
    parse_args "$@"

    if [[ ! -d "$FIXTURES_DIR" ]]; then
        echo "ERROR: fixtures directory not found: $FIXTURES_DIR" >&2
        exit 2
    fi
    if [[ ! -x "$REPLAY_SCRIPT" ]]; then
        echo "ERROR: replay-fixture.sh not found or not executable: $REPLAY_SCRIPT" >&2
        exit 2
    fi

    echo
    echo "============================================================"
    echo "Signal7 All-Fixtures Replay Runner"
    echo "  host: $HOST   strict: $STRICT"
    echo "============================================================"
    echo

    # Collect fixture names (sorted for deterministic output)
    local fixture_names=()
    for fx in "$FIXTURES_DIR"/*/; do
        [[ -d "$fx" ]] || continue
        local name
        name="$(basename "$fx")"
        [[ "$name" == "." || "$name" == ".." ]] && continue

        if [[ ! -f "$fx/expectations.md" ]]; then
            echo "SKIP: $name (no expectations.md — skipped, not an error)"
            continue
        fi
        fixture_names+=("$name")
    done

    if [[ ${#fixture_names[@]} -eq 0 ]]; then
        echo "No fixtures found with expectations.md under $FIXTURES_DIR"
        exit 0
    fi

    IFS=$'\n' fixture_names=($(sort <<<"${fixture_names[*]}"))
    unset IFS

    local total=0
    local passed=0 failed=0 errored=0

    for name in "${fixture_names[@]}"; do
        total=$((total + 1))

        echo "----------------------------------------"
        echo "Fixture: $name ($total/${#fixture_names[@]})"
        echo "----------------------------------------"

        local output rc
        if $VERBOSE; then
            output="$(run_one_fixture "$name")"
            rc=$?
        else
            output="$(run_one_fixture "$name")"
            rc=$?
            echo "$output" | grep -E '^(fixture:|  actual verdict|  verdict|  summary|  mutation diff:|  NOTE:|RESULT:)' || echo "$output" | tail -5
        fi

        parse_fixture_output "$output" "$name"

        case "${FIXTURE_STATUSES[-1]}" in
            PASS)  passed=$((passed + 1)) ;;
            FAIL)  failed=$((failed + 1)) ;;
            ERROR) errored=$((errored + 1)) ;;
        esac
        echo
    done

    print_summary_table

    if [[ $errored -gt 0 ]]; then
        exit 2
    elif [[ $failed -gt 0 ]]; then
        exit 1
    fi
    exit 0
}

main "$@"
