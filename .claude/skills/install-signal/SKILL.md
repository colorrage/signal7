---
name: install-signal
description: >
  Install, uninstall, or check local Signal7 skills by symlinking this repo's
  skills into supported agent skill directories.
---

# install-signal

Use this repo-local helper when the user wants to install Signal7 skills locally.

Run:

```bash
bash .claude/skills/install-signal/scripts/install.sh install
```

Other actions:

```bash
bash .claude/skills/install-signal/scripts/install.sh status
bash .claude/skills/install-signal/scripts/install.sh uninstall
```

The script symlinks every skill under `skills/` into supported agent skill directories it finds. It does not overwrite non-symlink directories.
