# Private Conversation 0.1.7 source and staging review

## Verdict

Conditional source allowlist for the authorized PCV-only 0.1.7 release. At this audit snapshot, branch `fix/scene-scope-and-diagnostics` is at `3f7c3681cd5f3a36493eaad5c54b126c58f2ab0d`; `server/manifest.json` is now 0.1.7. The lead has read the stable metadata diff; the fresh immutable-source package comparison remains a gate. This report authorizes no staging, commit, push, installation, or publication by itself.

## Source and builder comparison

I compared the current server and builder tree against `tasks/ack-bug-fix-2026-10-01/source-before.json`, the post-maintenance/handoff, pre-ACK-fix snapshot. The only source-level changes since that snapshot are:

| Path | Baseline SHA-256 | Current SHA-256 | Review status |
| --- | --- | --- | --- |
| [`server/reflection.php`](../../server/reflection.php) | `7ADF7BFFABFA1DC85BB3134105233C828271A0A8DD8F5C40662503C9A8F9809B` | `7BAC856D18D19E31AACDE1F7318965F84BF3B790A2AD5469474E73C0C907E526` | Accepted ACK claim-boundary fix; see [ACK bug-fix review](../ack-bug-fix-2026-10-01/final-review.md) and its [scope record](../ack-bug-fix-2026-10-01/final-scope-check.json). |
| [`server/reflection_receipt.php`](../../server/reflection_receipt.php) | `5E651289635D013BFC83267D6E64E185859B01A0CAD2D1587433C7A53E4523B5` | `A5F4779697FEA7E04D73B51D4A293CBB5B44D90986EA927AB7DADD4B4A960103` | Accepted locked-admission/stale-writer fix; same review and scope record. |
| [`server/manifest.json`](../../server/manifest.json) | `58E839D80DF11D8EAFB4713E2915B7BB2FF87CF37A7477BE278964E289824909` | `4A6C0A37A2377DD9E3179C15A0AA877941DA4D7763C925898328B7CE10EE47F7` | Expected metadata change from 0.1.6 to 0.1.7; [metadata review](metadata-review.md) says version is the only manifest field changed. Lead has read this diff. |

[`scripts/build-package.py`](../../scripts/build-package.py) is unchanged from the accepted package-builder bytes (SHA-256 `053860A61E388EC2B4F062C6B98C1E387EABBC3E8DEE73BCAA60F7C710558D0F`). Its previously reviewed explicit allowlist includes both `server/.htaccess` and `server/reflection_receipt.php`. The rest of the server source/build input tree matches the recorded baseline. A generated `scripts/__pycache__` bytecode file differs but is ignored and is not release input.

The remaining expected release edits are documentation, package expectations and line-ending policy: `README.md`, `docs/logging-revision-2.md`, `distribution/private_conversation-v0.1.7.md`, `tests/package_check.py`, and `.gitattributes` (which gives `.htaccess` LF line endings). At this snapshot their hashes are README `10E7B6DFE31E550F3826D9EBCD907496196D3ED74B704798E36E5326236B35F4`, logging guide `37E9AFAEFA599AFCBA45A6317B9263568CEFFA5CA59AB6ED57F1BD5F9D5F57A4`, and package check `51AE55D791CA388423C20CBF69616A58ED16E8EAB98730ABEE89D4AEA62F028E`. The metadata review records four passing package tests; the owner also reports five focused static checks passed. The immutable clean-source export/package comparison remains the release gate.

## Explicit staging allowlist

Recommended explicit staging allowlist, conditional on the clean-source package/export gate below:

```text
.gitattributes
.gitignore
README.md
distribution/private_conversation-v0.1.7.md
docs/logging-revision-2.md
scripts/build-package.py
server/.htaccess
server/context_pre.php
server/index.php
server/log.php
server/manifest.json
server/prepostrequest.php
server/prerequest.php
server/reflection.php
server/reflection_receipt.php
server/scope.php
server/state.php
tests/http_state_protection_check.py
tests/package_check.py
tests/postrequest_terminal_check.php
tests/reflection_ack_bug_check.php
tests/reflection_ack_database_check.php
tests/reflection_ack_shutdown_check.php
tests/reflection_direct_ack_capacity_check.php
tests/reflection_hook_timing_check.php
tests/reflection_receipt_bug_check.php
tests/reflection_registry_check.php
tests/scope_check.php
tests/state_check.php
tests/ui_check.php
tasks/release-0.1.7/source-review.md
tasks/release-0.1.7/metadata-review.md
tasks/maintenance-2026-10-01/final-review.md
tasks/maintenance-2026-10-01/routing-review.md
tasks/maintenance-2026-10-01/ack-review.md
tasks/maintenance-2026-10-01/state-security-review.md
tasks/ack-handoff-fix-2026-10-01/final-review.md
tasks/ack-handoff-fix-2026-10-01/core-review.md
tasks/ack-handoff-fix-2026-10-01/database-review.md
tasks/ack-handoff-fix-2026-10-01/contract-review.md
tasks/ack-bug-fix-2026-10-01/final-review.md
tasks/ack-bug-fix-2026-10-01/core-review.md
tasks/ack-bug-fix-2026-10-01/storage-review.md
tasks/ack-bug-fix-2026-10-01/integration-review.md
tasks/ack-bug-fix-2026-10-01/final-scope-check.json
tasks/ack-bug-run-2026-10-01/core-review.md
tasks/ack-bug-run-2026-10-01/final-review.md
tasks/ack-bug-run-2026-10-01/storage-review.md
tasks/ack-bug-run-2026-10-01/integration-review.md
```

The selected review set is the local-link closure of the three review links in `README.md`, plus the two original ACK-fix RED reports linked from the accepted fix core report and this source review. `final-scope-check.json` is included because the accepted ACK-fix final review links it; its 0.1.6 manifest/HEAD values are historical baseline evidence, not the 0.1.7 release identity. Other task plans, `tasks/todo.md`, draft/lead notes, before snapshots, source inventories, duplicate source copies, transient database/Apache files, `dist/`, and ignored bytecode are not part of this allowlist. Do not stage the separate `CHIM-Plugins` checkout or Mind Poisoning tree in this PCV commit.

## Gates already evidenced and fresh release checks

Existing focused maintenance, ACK recovery, ACK expiry/stale-writer, isolated PostgreSQL callback, HTTP isolation, PHP lint, package, and whitespace outputs are retained in the linked reviews. Their boundaries are explicit: fixtures/stubs and isolated Apache/PostgreSQL are not live CHIM, provider, installed server, client playback, or Skyrim proof. The acceptance reviews say the source/runtime bytes reviewed for the maintenance, handoff and ACK changes are unchanged here; the manifest/docs/package-test changes are the separate metadata slice.

For release, use a new immutable clean-source commit/tag and perform only the narrow fresh-source gates:

1. Export that exact commit with the repository line-ending attributes intact; verify export revision and manifest version/repository identity.
2. Build twice into separate fresh output directories with the existing builder. Compare every output asset byte-for-byte, every internal archive member against the explicit allowlist/source bytes, and all checksum entries.
3. Run the updated `tests/package_check.py` from that clean export and verify the DWPkg, repository tar and MO2 wrapper each contain the expected 0.1.7 identity, including exact `.htaccess` and receipt-module bytes.
4. Recheck the staged path list and `git diff --check`; only then review the actual hashes and consider publication steps.

No broad suite or SQL rerun is recommended solely for metadata changes. Do not promote the manifest, publish, or claim deployed/runtime behavior from source/package evidence. The separate hub review identifies a PCV-only `CHIM-Plugins` bridge path; any hub snapshot/release needs its own clean-tree review and byte comparison.
