# Private Conversation 0.1.20 public/source independent review

Read-only review captured 2026-10-16T23:43:03Z. This checks public GitHub metadata and the saved clean-build/download evidence. It does not repeat anonymous downloads, tests, runtime checks, or installation.

## Public release and source identity

- The public release endpoint for tag `private_conversation-v0.1.20` returns release ID `415462809`, `draft=false`, `prerelease=false`, published `2026-10-16T23:36:24Z`. The release page is [Private Conversation 0.1.20 — ALPHA](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/tag/private_conversation-v0.1.20).
- GitHub reports the tag ref as an annotated tag object `d79fdce5f0dcf58694f6712b17db2ff59860c4c8`; the tag object names commit `85e9dd08c8bacbb6f1553e36b0d1f4c2caacf45e`. The tag ref, annotated tag object, commit object, and release metadata were read through the public [GitHub API](https://api.github.com/repos/Francisco-boop-001/CHIM-PrivateConversation/git/ref/tags/private_conversation-v0.1.20).
- A separate `git ls-remote origin` check returned the same `.20` tag object and peeled commit, and returned `refs/heads/main` at `85e9dd08c8bacbb6f1553e36b0d1f4c2caacf45e`. The API `main` ref agrees. The commit tree is `fd1a2ff48cfa65dbb5c4500a7fb364975e48fdcd`, and its parent is the former source main `b7030f259bf25101303f56c03090a060290992a5`; 84 changed paths were counted. This confirms the published source identity and current refs, not the mechanics of the earlier push.

## Asset records and local byte comparison

The current public release API record matches `publication-evidence/public-release.json` on release ID, tag, flags, publication time, and all five asset IDs, names, sizes, and SHA-256 digests. `public-asset-verification.json` has five entries marked anonymous-download and byte-identical. I did not repeat HTTP downloads; I independently compared the retained files in `public-downloads`, the prepared candidate directory, and the clean-build assets directory. All three copies are byte-identical for each asset, and each SHA-256 and size matches the current GitHub API asset record.

| Public asset | GitHub asset ID | Bytes | SHA-256 |
| --- | ---: | ---: | --- |
| `chim-plugin.tar.gz` | 648948436 | 3,053,486 | `50405eca2d8a7b6e7dc9a307740b6ab0518366e21cfcbc743b88e88d94cbcd7b` |
| `private_conversation-0.1.20-mo2.zip` | 648948438 | 3,056,680 | `7bd8c4e67e970416c116965a0470328a48e7ca4921750fafabf759a2826290db` |
| `private_conversation-0.1.20.dwpkg` | 648948435 | 3,475,090 | `d5da301dd332d45a97e962da96c8f0af9269d4d402e2083fe7a221e921090e8c` |
| `private_conversation.tar.gz` | 648948431 | 3,053,559 | `e949fe37d1e7fb2f8091e28f0c4af106ca1039a15809401082ea460572798bb8` |
| `SHA256SUMS.txt` | 648948432 | 381 | `e51d7d230b586ad3f544084b60ad40e4a7fe4fc830d0e35b12061977ad056fee` |

The public [release API record](https://api.github.com/repos/Francisco-boop-001/CHIM-PrivateConversation/releases/415462809) matches these rows. The prior `.19` release API record also matches its saved receipt: release ID `415388759` and all five existing asset IDs/digests are unchanged.

## Clean-build source coupling

`clean-build.json` names source commit `85e9dd08c8bacbb6f1553e36b0d1f4c2caacf45e` and tree `fd1a2ff48cfa65dbb5c4500a7fb364975e48fdcd`, exactly matching the public tag target and commit tree. Its recorded source archive hash `f0024a807312d95ad433593327a6581a7028a8b5f1d0ec97770ceb584e01a38d` matches the retained archive. Archive, extraction, and build exits are all zero; the receipt records all five outputs byte/SHA-identical to the candidate.

I independently checked the 21-file package builder allowlist against the clean source archive and the tagged commit’s Git blob IDs: 21/21 match. The full source archive has all 356 tracked paths, but 36 non-payload text files have CRLF-converted bytes; each normalizes exactly back to its Git blob with CRLF-to-LF conversion. Therefore this review claims exact Git-blob coupling for the package allowlist, not raw-byte identity for every file in the Windows-extracted source tree.

## Limits

This confirms release metadata, tag/main refs, saved anonymous-download evidence, retained asset bytes, and package-source coupling. I did not rerun the recorded fixtures or repeat network downloads. The release description scopes its fixture results and disclaims full CHIM bootstrap, database/provider behavior, live installation, and Skyrim gameplay; this review adds no runtime or gameplay claim.
