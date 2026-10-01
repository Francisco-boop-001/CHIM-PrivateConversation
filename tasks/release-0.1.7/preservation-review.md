# Private Conversation 0.1.7 preservation review

## Result

Confirmed: the 52 protected Mind Poisoning authoring files still match their pre-maintenance SHA-256 inventory. The canonical `private_conversation-v0.1.7` annotated tag points to the clean-source release commit `d8f79c6059ac9184c5117c26a0514e18aae76902`. The isolated collection/hub commit `98ed33b4169b4bc3e29835fdc4d710e83e6c2c1a` is limited to the PCV snapshot and its README/migration/release-note surfaces; the Mind Poisoning root `server/` tree and manifest match baseline `74ca8c97d30045822170477aad87824fb39b8222` exactly.

This review performs no staging, source edits, release operations, or changes to the Mind Poisoning authoring checkout or isolated hub.

## Protected Mind Poisoning authoring checkout

The inventory is [`tasks/maintenance-2026-10-01/protected-before.json`](../maintenance-2026-10-01/protected-before.json), captured before the maintenance work. I rehashed each listed path under `K:\ActorwrightExchange\projects\CHIM-MindPoisoning`:

```text
PROTECTED_TOTAL=52 MATCHED=52 MISMATCHED=0
```

Command shape:

```powershell
$baseline = Get-Content 'tasks/maintenance-2026-10-01/protected-before.json' -Raw | ConvertFrom-Json
$matched = 0; $mismatches = @()
foreach ($item in $baseline) {
  $actual = (Get-FileHash -LiteralPath $item.Path -Algorithm SHA256).Hash
  if ($actual -eq $item.SHA256) { $matched++ } else { $mismatches += $item.Path }
}
"PROTECTED_TOTAL=$($baseline.Count) MATCHED=$matched MISMATCHED=$($mismatches.Count)"
```

Git required a command-local `safe.directory` override for the shared-drive checkout. It was not persisted:

```text
git -c "safe.directory=K:\ActorwrightExchange\projects\CHIM-MindPoisoning" -C K:\ActorwrightExchange\projects\CHIM-MindPoisoning status --short --untracked-files=all
 M plugins/private_conversation/tasks/logging-improvements-plan.md
?? critique.md
?? distribution/submission/discord-message.md
?? tasks/catalog-submission.md
?? tasks/drama-llama-desktop-night.png
?? tasks/drama-llama-mobile-day.png
exit=0
```

That is the current authoring-checkout porcelain snapshot (one modified file and five untracked files). The accepted [maintenance review](../maintenance-2026-10-01/final-review.md) says the pre-existing dirty/untracked set was retained. Its saved protected-file inventory contains hashes, not the original `git status` path list, so exact historical porcelain-list equality cannot be independently recomputed from the retained artifact. The stronger product-file check is complete: all 52 protected paths are byte-identical. No cleanup was attempted.

## Canonical PCV source and tag

Read-only Git checks in `CHIM-PrivateConversation-dev` returned:

```text
HEAD=d8f79c6059ac9184c5117c26a0514e18aae76902
tag=private_conversation-v0.1.7
tag object type=tag
tag object=2a99d557aaf1859688d964306eb8390aeeeac4e6
tag^{commit}=d8f79c6059ac9184c5117c26a0514e18aae76902
HEAD_EQUALS_TAG=True
tag manifest version=0.1.7
tag manifest repository=Francisco-boop-001/CHIM-PrivateConversation
```

The [clean-source package evidence](package-evidence.json) records that the commit archive and tag archive are byte-identical (3,324,768 bytes, SHA-256 `441d81ae494a65cf1922a0a05f4917a70299cca297023be982db4ecd90efb4c1`), and that the four release assets built from two clean exports compare byte-for-byte. The [package review](package-review.md) retains the commands, asset hashes and comparison boundaries. Those checks establish source/tag/package identity, not installed-server or gameplay behavior.

The canonical working tree also contains preserved untracked task snapshots and reports. A path-limited `git status --short -- server scripts tests README.md docs distribution .gitattributes .gitignore` produced no output; the only tracked post-tag change is the task-only edit to `tasks/release-0.1.7/source-review.md`. This new review is also a task artifact. The package evidence explicitly excluded the former task-note edit and confirms the server allowlist, builder and test inputs used for the tag. No product-path changes after the tag were observed.

## Isolated `CHIM-Plugins` hub commit

The isolated hub is `K:\ActorwrightExchange\projects\CHIM-Plugins-release-0.1.7`. Its working tree is clean at `main`, commit `98ed33b4169b4bc3e29835fdc4d710e83e6c2c1a`, titled “Publish Private Conversation 0.1.7 bridge snapshot and migration guide.” `git status --short --untracked-files=all` returned no output (exit 0). I compared it with its recorded base `74ca8c97d30045822170477aad87824fb39b8222`:

```text
git diff-tree --no-commit-id --name-status -r 74ca8c97d30045822170477aad87824fb39b8222 98ed33b4169b4bc3e29835fdc4d710e83e6c2c1a
HUB_UNEXPECTED_PATHS=0
git diff --quiet 74ca8c97d30045822170477aad87824fb39b8222 98ed33b4169b4bc3e29835fdc4d710e83e6c2c1a -- server
ROOT_SERVER_DIFF_EXIT=0
git diff --quiet 74ca8c97d30045822170477aad87824fb39b8222 98ed33b4169b4bc3e29835fdc4d710e83e6c2c1a -- server/manifest.json
ROOT_MP_MANIFEST_DIFF_EXIT=0
```

Every changed path is either the collection `README.md`, `docs/deployment-migration.md`, `distribution/private_conversation-v0.1.7.md`, or within `plugins/private_conversation/`. The changed plugin subtree is the intended PCV snapshot. The top-level root manifest bytes compare equal at both commits and parse as `name=mind_poisoning`, `version=0.1.14`, `git_repo=Francisco-boop-001/CHIM-MindPoisoning`. This independently confirms that the hub commit did not alter the root Mind Poisoning server payload or version.

The [package review](package-review.md) and [`package-evidence.json`](package-evidence.json) record the broader synchronization check: all 105 PCV target files matched the clean source export, and the inventory/hash of 167 files outside that target was unchanged by the copy. This report is limited to the isolated local snapshot; public-ref and release confirmation are separate lead evidence.

## Boundaries

- Protected-file evidence covers the 52 inventoried original Mind Poisoning/product and pinned PCV files; it is not a full byte inventory of every file in that authoring checkout.
- The historical exact dirty/untracked pathname list was not retained in the hash inventory. Current paths are listed above; the prior maintenance review records that its dirty set was preserved at that time.
- The hub findings concern the isolated local checkout and its local commits; they make no deployment or gameplay claim.
- No live CHIM, database, model provider, client, or Skyrim check was run as part of this preservation audit.
