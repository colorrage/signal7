#!/usr/bin/env bash
set -euo pipefail

action="${1:-install}"

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source_dir="$(cd "$script_dir/../../../../skills" && pwd)"

if [ ! -d "$source_dir" ]; then
  echo "error: source skills directory not found at $source_dir" >&2
  exit 1
fi

default_targets=(
  "$HOME/.claude/skills"
  "$HOME/.codex/skills"
  "$HOME/.agents/skills"
  "$HOME/.pi/agent/skills"
)

if [ -n "${SIGNAL_INSTALL_TARGETS:-}" ]; then
  IFS=':' read -r -a targets <<<"$SIGNAL_INSTALL_TARGETS"
else
  targets=()
  for t in "${default_targets[@]}"; do
    parent="$(dirname "$t")"
    if [ -d "$parent" ]; then
      targets+=("$t")
    fi
  done
fi

if [ "${#targets[@]}" -eq 0 ]; then
  echo "error: no install targets found" >&2
  echo "hint: none of ${default_targets[*]} have an existing parent dir" >&2
  exit 1
fi

skills=()
while IFS= read -r skill_dir; do
  skills+=("$(basename "$skill_dir")")
done < <(
  find "$source_dir" -mindepth 1 -maxdepth 1 -type d \
    -exec test -f '{}/SKILL.md' ';' -print | LC_ALL=C sort
)

if [ "${#skills[@]}" -eq 0 ]; then
  if [ "$action" = "status" ]; then
    echo "source: $source_dir"
    echo "no installable skills found yet"
    exit 0
  fi
  echo "error: no skills found under $source_dir" >&2
  echo "hint: Phase 0 only defines references/templates; install after SKILL.md folders exist" >&2
  exit 1
fi

install_one() {
  local target="$1"
  mkdir -p "$target"
  echo "-> $target"
  for s in "${skills[@]}"; do
    local src="$source_dir/$s"
    local dst="$target/$s"
    if [ -L "$dst" ]; then
      local current
      current="$(readlink "$dst")"
      if [ "$current" = "$src" ]; then
        echo "    ok     $s"
      else
        echo "    skip   $s (link points elsewhere: $current)"
      fi
    elif [ -e "$dst" ]; then
      echo "    skip   $s (exists and is not a symlink)"
    else
      ln -s "$src" "$dst"
      echo "    link   $s"
    fi
  done
}

uninstall_one() {
  local target="$1"
  echo "<- $target"
  if [ ! -d "$target" ]; then
    echo "    skip   (target does not exist)"
    return
  fi
  for s in "${skills[@]}"; do
    local src="$source_dir/$s"
    local dst="$target/$s"
    if [ -L "$dst" ] && [ "$(readlink "$dst")" = "$src" ]; then
      rm "$dst"
      echo "    unlink $s"
    elif [ -e "$dst" ]; then
      echo "    skip   $s"
    fi
  done
}

status_one() {
  local target="$1"
  echo "@ $target"
  if [ ! -d "$target" ]; then
    echo "    (target does not exist)"
    return
  fi
  for s in "${skills[@]}"; do
    local src="$source_dir/$s"
    local dst="$target/$s"
    if [ -L "$dst" ]; then
      local current
      current="$(readlink "$dst")"
      if [ "$current" = "$src" ]; then
        printf "    %-24s linked (this repo)\n" "$s"
      else
        printf "    %-24s linked to %s\n" "$s" "$current"
      fi
    elif [ -e "$dst" ]; then
      printf "    %-24s exists (not a symlink)\n" "$s"
    else
      printf "    %-24s not installed\n" "$s"
    fi
  done
}

case "$action" in
  install)
    echo "source: $source_dir"
    for t in "${targets[@]}"; do install_one "$t"; done
    ;;
  uninstall)
    echo "source: $source_dir"
    for t in "${targets[@]}"; do uninstall_one "$t"; done
    ;;
  status)
    echo "source: $source_dir"
    for t in "${targets[@]}"; do status_one "$t"; done
    ;;
  *)
    echo "usage: $(basename "$0") [install|uninstall|status]" >&2
    exit 1
    ;;
esac
