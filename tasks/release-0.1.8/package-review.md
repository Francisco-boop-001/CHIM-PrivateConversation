# Private Conversation 0.1.8 clean-source package review

## Source export

- Source commit: `533bc6339fe4c1d39a0d95157ca216c281164b89`.
- Export: `dist/release-0.1.8-check/source-533bc633/source-533bc633.zip` (3,375,433 bytes; SHA-256 `ada5b33bb830ba17397317a282f3bb72cb02262c7e8f1ad70ee513ca3294665c`).
- The ZIP passed member-path and symlink checks and extracted independently to `clean-export-a`, `clean-export-b`, and `fresh-unbuilt-copy`; each extraction has 140 ZIP members and 123 files.
- The 20 `SERVER_FILES` runtime inputs match the accepted worktree byte-for-byte. `.gitattributes`, `.gitignore`, `scripts/build-package.py`, and `tests/package_check.py` match after LF normalization. The two clean source trees match the archive exactly; the package check generated one ignored `scripts/__pycache__/build-package.cpython-312.pyc` in export A, excluded from source-tree comparison. Runtime-tree SHA-256: `724488e5e6e98e000d62bd5c8b3de356d3f4757de1b58efe7c0b3df3ea1efd99`; complete archived source-tree SHA-256: `e40a29c86945072eb264b7b83dcda3c047d6de41dc2fb86420a5d429c455409c`.

Selected source hashes:

| Path | SHA-256 |
| --- | --- |
| `server/manifest.json` | `b167ff7575897f7bbe7e2ca68c69ec71e134023862f0b910c67e2095d378bb5f` |
| `server/.htaccess` | `ccd754e3ccb7ac8b8a8eafd60f087d6e5a4b936c17459720d5c512b5b927c05a` |
| `scripts/build-package.py` | `053860a61e388ec2b4f062c6b98c1e387eabbc3e8dee73bcaa60f7c710558d0f` |
| `tests/package_check.py` | `2871c93c555123636eb170c040d2e13b41bd386bbdc8f52ddb0964f866792146` |

The preserved earlier RED export is keyed separately at `dist/release-0.1.8-check/source-482422b.zip` (3,372,819 bytes; SHA-256 `5af6fa1deaef635c6db4b5dc1f1f8d3a986ea27cef86c0d08c28a2a8ed91483f`). Its package check had two failures because the tests expected 0.1.7 output filenames. The corrected source derives those expectations from the manifest version; the final gate below used only commit `533bc63`.

## Package checks and builds

Command from `dist/release-0.1.8-check/source-533bc633/clean-export-a`:

```text
python tests/package_check.py
```

Exit 0; raw output:

```text
....
----------------------------------------------------------------------
Ran 4 tests in 2.543s

OK
```

From each of `clean-export-a` and `clean-export-b`, ran:

```text
python scripts/build-package.py --format release --release-dir dist/release-v0.1.8
```

Both exited 0 and printed identical outputs:

```text
Built and verified dist\release-v0.1.8\SHA256SUMS.txt (294 bytes, sha256 e8415e78a775da674aa505a3da90c76cd0ae21cd5780b2f46c26a9de51a0d5d5)
Built and verified dist\release-v0.1.8\private_conversation-0.1.8-mo2.zip (3025865 bytes, sha256 302f4da940ac63043a1dfab826fc094edbb2d8147797dc643fee52f272b015a8)
Built and verified dist\release-v0.1.8\private_conversation-0.1.8.dwpkg (3352551 bytes, sha256 170a1db317dcf9553c571435bb11ab5ca60c1e61013ec165a240a382ee0aa1c4)
Built and verified dist\release-v0.1.8\private_conversation.tar.gz (3023224 bytes, sha256 00354e95eeeb0e4b6d76bcc6115f41cea7b8de54aa189fbfff3f2cfc5a202e3e)
```

| Candidate upload asset | Clean export A path | Bytes | SHA-256 |
| --- | --- | ---: | --- |
| DWPkg | `dist/release-0.1.8-check/source-533bc633/clean-export-a/dist/release-v0.1.8/private_conversation-0.1.8.dwpkg` | 3,352,551 | `170a1db317dcf9553c571435bb11ab5ca60c1e61013ec165a240a382ee0aa1c4` |
| Repository tar.gz | `dist/release-0.1.8-check/source-533bc633/clean-export-a/dist/release-v0.1.8/private_conversation.tar.gz` | 3,023,224 | `00354e95eeeb0e4b6d76bcc6115f41cea7b8de54aa189fbfff3f2cfc5a202e3e` |
| MO2 sync ZIP | `dist/release-0.1.8-check/source-533bc633/clean-export-a/dist/release-v0.1.8/private_conversation-0.1.8-mo2.zip` | 3,025,865 | `302f4da940ac63043a1dfab826fc094edbb2d8147797dc643fee52f272b015a8` |
| `SHA256SUMS.txt` | `dist/release-0.1.8-check/source-533bc633/clean-export-a/dist/release-v0.1.8/SHA256SUMS.txt` | 294 | `e8415e78a775da674aa505a3da90c76cd0ae21cd5780b2f46c26a9de51a0d5d5` |

All four files are byte-identical across both builds. Independent verification passed: DWPkg has exactly 22 members (20 allowlisted server files plus manifests/checksums), schema 4, version 0.1.8, and `mutable_paths: ["state"]`; checksums, CRC and source-byte comparisons pass. The tar has one `private_conversation` root plus the 20 allowlisted files, with exact source payload. The MO2 ZIP contains only `CHIM/server-plugins/private_conversation/0.1.8.dwpkg`, with valid CRC and bytes identical to the verified DWPkg. No tests, source tree, or runtime state are packaged.

## Immutable-tag rebuild

The lead-created annotated tag `private_conversation-v0.1.8` (tag object `28f3a70a89ce2bd4d83cf753876f8b1668b6cabf`) resolves to source commit `533bc6339fe4c1d39a0d95157ca216c281164b89`. A clean `git archive` of the tag is byte-identical to the commit ZIP above (3,375,433 bytes; SHA-256 `ada5b33bb830ba17397317a282f3bb72cb02262c7e8f1ad70ee513ca3294665c`). One validated extraction had 140 members and 123 files. Rebuilding all release assets from that tag extraction with the same builder command exited 0; each of the four rebuilt files matched the clean-commit candidate byte-for-byte and retained the hashes in the table above.

## Hub subtree synchronization

After the lead confirmed hub metadata was stable, copied the fresh unbuilt export `dist/release-0.1.8-check/source-533bc633/fresh-unbuilt-copy` into `K:\ActorwrightExchange\projects\CHIM-Plugins-release-0.1.7\plugins\private_conversation`. Before the copy, the source contained 123 files and the target 105: 18 were missing, 16 existing paths differed, and there were no target-only files. The post-copy target has exactly 123 files with path, size, and SHA-256 inventory identical to the unbuilt export; target tree SHA-256 is `2cf6a867069ff3a202af7860a53364a8477e0d3c7c6f43452e38e7de67a060b5`.

The 269-entry outside-subtree inventory had SHA-256 `8fdff15409e0bec31f5b5688b214c91752ca048935faa2993e659408a01705c9` both before and after copying; it was unchanged. No target-only files had to be removed. This copy did not create a hub commit or tag.

These package checks prove source allowlisting and reproducibility only; they do not prove installation, live CHIM behavior, provider behavior, or gameplay. The canonical source tag was created locally by the lead. This reviewer did not push or publish a release.
