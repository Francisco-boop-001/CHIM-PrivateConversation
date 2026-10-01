# Private Conversation 0.1.7 clean-source package review

## Source export

- Source commit: `d8f79c6059ac9184c5117c26a0514e18aae76902`.
- Export command: `git archive --format=zip --output=dist/release-0.1.7-check/source-d8f79c6.zip d8f79c6059ac9184c5117c26a0514e18aae76902`.
- Export: `dist/release-0.1.7-check/source-d8f79c6.zip`, 3,324,768 bytes, SHA-256 `441d81ae494a65cf1922a0a05f4917a70299cca297023be982db4ecd90efb4c1`.
- Extracted twice with Python 3.12.4 `zipfile`, after checking member paths and file types. Each extraction contained 120 ZIP entries and passed.
- The lead-created local tag `private_conversation-v0.1.7^{commit}` resolves to the same source commit. `git archive --format=zip` of the tag produced a second 3,324,768-byte ZIP with the same SHA-256; the ZIP files compared byte-for-byte, so no rebuild was needed.
- The source worktree has task-authoring drift after this commit. The known changed `tasks/release-0.1.7/source-review.md` was excluded from product-input comparison; no server, script, test, README, docs, or release metadata files in scope differ from the commit.

## Worktree input comparison

The clean export's 20 `SERVER_FILES` matched the accepted source worktree byte-for-byte. `.gitattributes`, `.gitignore`, `scripts/build-package.py`, and `tests/package_check.py` matched after CRLF-to-LF normalization. `server/.htaccess`, `server/reflection_receipt.php`, and `server/manifest.json` SHA-256 values are respectively `ccd754e3ccb7ac8b8a8eafd60f087d6e5a4b936c17459720d5c512b5b927c05a`, `a5f4779697fea7e04d73b51d4a293cbb5b44d90986ea927ab7dadd4b4a960103`, and `4a6c0a37a2377dd9e3179c15a0aa877941da4d7763c925898328b7ce10ee47f7`. The comparison confirmed the manifest change from the previous commit is only `version: 0.1.6` to `version: 0.1.7`.

## Checks and artifacts

`python tests/package_check.py` from `dist/release-0.1.7-check/clean-export-a` exited 0:

```text
....
----------------------------------------------------------------------
Ran 4 tests in 2.706s

OK
Exit code: 0
```

Ran `python scripts/build-package.py --format release --release-dir dist/release-v0.1.7` from each clean export. Both commands exited 0. Each printed the same verified results:

```text
Built and verified dist\release-v0.1.7\SHA256SUMS.txt (294 bytes, sha256 388d5ff92673dc2119f5e22a96e94ffc06baaeccd40fdcd53ba6a36fb8b40c50)
Built and verified dist\release-v0.1.7\private_conversation-0.1.7-mo2.zip (3024485 bytes, sha256 639cbd44e674cb16a9e42fa1724a9e13efabf883e99ce89120e14a1997547ce4)
Built and verified dist\release-v0.1.7\private_conversation-0.1.7.dwpkg (3344250 bytes, sha256 a9347c6ebd4b6eb40b86b9d383785403b8e427a72e2992963088432f41e2f873)
Built and verified dist\release-v0.1.7\private_conversation.tar.gz (3021841 bytes, sha256 cd180949fa4bc12cd2b98daa1bad33ac53ff522e624446d9e7f9cdcd9691779b)
```

The four assets reside under both `dist/release-0.1.7-check/clean-export-{a,b}/dist/release-v0.1.7/` directories.

| Asset | Size | SHA-256 |
| --- | ---: | --- |
| `private_conversation-0.1.7.dwpkg` | 3,344,250 bytes | `a9347c6ebd4b6eb40b86b9d383785403b8e427a72e2992963088432f41e2f873` |
| `private_conversation.tar.gz` | 3,021,841 bytes | `cd180949fa4bc12cd2b98daa1bad33ac53ff522e624446d9e7f9cdcd9691779b` |
| `private_conversation-0.1.7-mo2.zip` | 3,024,485 bytes | `639cbd44e674cb16a9e42fa1724a9e13efabf883e99ce89120e14a1997547ce4` |
| `SHA256SUMS.txt` | 294 bytes | `388d5ff92673dc2119f5e22a96e94ffc06baaeccd40fdcd53ba6a36fb8b40c50` |

The focused verifier exited 0: all four artifacts compare byte-for-byte across the two builds; DWPkg allowlist/source/checksums/manifest and repository tar root/member payload were verified by the existing builder; the MO2 ZIP has exactly `CHIM/server-plugins/private_conversation/0.1.7.dwpkg`, valid CRC, and exact DWPkg bytes; SHA sums matched. Package verification includes the exact `.htaccess` and `reflection_receipt.php` bytes. This establishes clean-source package reproducibility, not server installation, provider behavior, or live gameplay.

## Hub synchronization status

Copied the fresh, unbuilt `dist/release-0.1.7-check/clean-export-copy/` extracted from the same immutable ZIP into `CHIM-Plugins-release-0.1.7/plugins/private_conversation/`. Immediately before copy, the source had 105 files and the existing target had 74: there were zero stale extras and 31 missing paths. After copy, all 105 target files matched the exact archive export; the deterministic relative-path/content tree SHA-256 is `d168bb398de74e3ea80d9adf6ad5d1a834278933ec31e1e87c611326d39a946a`. No PCV target files were deleted.

The copy script hashed all 167 files outside the PCV target before and after the copy. They were unchanged; the outside-tree inventory SHA-256 was `23b2287d8b7b50ed11193e6bf5bccd3fe4b084917c8fd813059e25f3454bbfb3` at both points. Hub metadata edits outside this target are a separate owner path and were not included or overwritten.

## Committed hub-tree gate

The lead's hub commit is `98ed33b4169b4bc3e29835fdc4d710e83e6c2c1a`. The source commit root tree and the hub commit's `plugins/private_conversation` tree both resolve to `45c6c1f817ad1066010fdac3bfb30bea8c15ca67`. This Git tree-object equality proves all tracked file bytes and modes in the PCV subtree match the source commit exactly.

The only commit paths outside the PCV subtree are `README.md`, `distribution/private_conversation-v0.1.7.md`, and `docs/deployment-migration.md`; this exactly matches the separate hub README, release-note and migration-note allowance. The hub root `server/manifest.json` remains MP `0.1.14`; the nested PCV manifest is `0.1.7`. The hub worktree is clean and one commit ahead of `origin/main` at this check. No hub tag or push was performed by this reviewer.

The lead-created PCV local tag export matches the executed commit export exactly. This reviewer did not create or push Git refs, publish a release, install files, or verify runtime behavior.
