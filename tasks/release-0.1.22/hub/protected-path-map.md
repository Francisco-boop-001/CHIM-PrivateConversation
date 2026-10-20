# PCV hub publication boundary

Prepared 2026-10-20T10:34:52.692Z from the public `Francisco-boop-001/CHIM-Plugins` `main` baseline recorded in `hub-baseline.json`.

## Exact base

The local branch `publish/private-conversation-0.1.22` starts at the fetched public `main` commit recorded in the JSON. `git ls-remote` matched the fetched `origin/main` at preparation time. The prior local `main` remains at its separately recorded historical commit; no reset or deletion was used. `pcv-paths.txt` lists the exact tracked contents under the PCV subtree at this base.

## Future PCV-only allowlist

After the canonical PCV 0.1.22 release is public and the root authorizes mirroring:

- `plugins/private_conversation/**` may be updated from that exact canonical release.
- Root `README.md` may change only in the Private Conversation entries: the release stanza at lines 34–42, the PCV downloads row at line 67, the PCV discovery note at line 72, PCV install guidance at lines 80 and 82, and the PCV support link at line 90. These are base-commit line anchors; recheck context before applying any patch.

All other hub paths are protected, including Mind Poisoning, core/plugin-manager content, SHARMAT/Sworn & Scorned, and every non-PCV passage in root `README.md`. A line-number match alone does not authorize an edit; keep each README hunk limited to PCV text and preserve surrounding plugin sections.

## Current state and publication gate

This is local preparation only. No PCV mirror files have been copied or edited, and nothing has been staged, committed, or pushed. Do not publish from this branch until the canonical PCV 0.1.22 release is public and the lead confirms the exact source and allowlist. Recheck public `main` before a later mirror if the branch has not yet been used.