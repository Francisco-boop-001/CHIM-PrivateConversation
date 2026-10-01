# Private Conversation 0.1.7 metadata review

## Scope

Prepared the PRE-ALPHA candidate metadata and user-facing docs for the development checkout. Runtime/test behavior was not edited, except for the authorized version literals in `tests/package_check.py`. The source and package verification gates remain with the lead/package owner; this note does not authorize a public manifest update, push or tag.

## Manifest comparison

Only `server/manifest.json`'s `version` changes, from `0.1.6` to `0.1.7`. The preserved fields are: `name=private_conversation`; schema `2`; description `PRE-ALPHA: Stage a two-NPC scene direction or solo reflection for the next eligible ordinary Standard-mode input.`; status `development_candidate`; repo `Francisco-boop-001/CHIM-PrivateConversation`; config URL `../ext/private_conversation/index.php`; server compatibility reference `cf5030f15781637498be86debe26fcf102f5690d`; default channel `candidate`; channel label `Development candidate`; branch `main`; manifest URL `https://raw.githubusercontent.com/Francisco-boop-001/CHIM-PrivateConversation/main/server/manifest.json`; package source `release`; package URL template `https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v<version>/private_conversation.tar.gz`; archive strip count `1`; and `allow_force=false`.

The candidate package URL remains the manifest template shown above; the updater resolves it using the manifest version. No source/API/schema/config or compatibility pin was advanced. The README retains server ref `cf5030f15781637498be86debe26fcf102f5690d` and native ref `12e035d0a810b9b932fe2df1f688407a72cd27a1`. Mind Poisoning 0.1.14 reflection API version 1 remains unchanged and optional for scene direction.

## Documentation and install links

- `README.md` identifies candidate 0.1.7, replaces the old 0.1.6 install URLs and package path with the tag `private_conversation-v0.1.7`, and describes replacement rather than stacking. Intended assets are `private_conversation-0.1.7-mo2.zip`, `private_conversation-0.1.7.dwpkg`, `private_conversation.tar.gz` and `SHA256SUMS.txt`.
- The README and release notes describe narrower scene routing, immediate END, Apache state denial, the negative unmatched-ACK fast path, bounded early-ACK recovery, original receipt-age checks, and fresh locked scope/epoch admission. They state the **8 entries / 8 KiB**, **45-second receipt**, and separate **600-second claim** limits, plus crash/native-row/ACK and already-started-work limits.
- `.gitattributes` adds only `.htaccess text eol=lf`; before the change, `git check-attr` reported `text` and `eol` unspecified while `core.autocrlf=true`. This keeps the packaged state-denial rule's source bytes stable on Windows. No `.htaccess` runtime bytes changed.
- The docs retain the exact CHIM compatibility references and say Mind Poisoning 0.1.14 API v1 is unchanged and optional. No pair correlation, provider success, installed CHIM, or gameplay guarantee is claimed. Logs remain sanitized and manual; route completion is distinguished from a later reflection outcome.
- Apache state denial is labeled with its verified boundary: Apache `mod_authz_core` plus effective overrides; other server configurations need an equivalent rule. Clean deployment is not implied.
- `docs/logging-revision-2.md` now includes candidate 0.1.7 while preserving the historical 0.1.5/0.1.6 and MP 0.1.13/0.1.14 API history.
- `distribution/private_conversation-v0.1.7.md` uses an absolute tag-specific README link so it also works in a hosted release view.

## Package-check version literals

`tests/package_check.py` had all exact 0.1.6 output/member/version literals updated to 0.1.7, including the DWPkg name, MO2 ZIP, internal CHIM path and manifest assertion. Existing allowlist, checksum, deterministic-output and `.htaccess` assertions were preserved. A scan of `tests/` found no other exact 0.1.6 package expectations.

## Verification boundary

Verification:

- `python tests/package_check.py` — exit 0; 4 tests ran; `OK`.
- Inline static manifest/docs check — exit 0, 5 PASS lines: manifest differs from HEAD only by version; channel/schema/status/compatibility/template preserved; all four README asset URLs and DWPkg path match; review links/API-v1/MP0.1.14 boundaries preserved; no stale “unreleased” README/release-note wording or trailing whitespace.
- Path-limited `git diff --check -- .gitattributes server/manifest.json README.md docs/logging-revision-2.md tests/package_check.py` — exit 0.
- `git check-attr text eol -- server/.htaccess` — before: `text`/`eol` unspecified; after: `text: set`, `eol: lf`.
- `rg -n '0\.1\.6|private_conversation-v0\.1\.6|private_conversation-0\.1\.6' tests` — no matches after the package-check literals were updated.

The package test validates deterministic temporary output from this working tree; the package owner still verifies the immutable clean-source export. No live environment check, commit, push or tag was performed by this metadata slice.
