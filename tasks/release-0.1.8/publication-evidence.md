# Private Conversation 0.1.8 publication — lead judgment

Committed, pushed and published as PRE-ALPHA on 2026-10-01. Publication covers Private Conversation and its CHIM-Plugins bridge only. No installed extension, game, database, modlist, provider or separate Mind Poisoning authoring work was changed.

## Immutable source and bridge

| Repository | Release commit | Annotated tag object |
| --- | --- | --- |
| CHIM-PrivateConversation | `533bc6339fe4c1d39a0d95157ca216c281164b89` | `28f3a70a89ce2bd4d83cf753876f8b1668b6cabf` |
| CHIM-Plugins PCV bridge | `34ad2a1f9d86bfa4b8811510aef4fee77a662a44` | `1386bd10df7714de5befa56f14c37f59f0853e8e` |

Both tags are `private_conversation-v0.1.8`. Canonical tagged root and committed hub `plugins/private_conversation` trees are exactly `1781b547cdc85f7c00f6801053ce3a161544a812`, proving equality of all tracked paths, modes and blobs. Outside that subtree, the hub commit changes exactly root README, deployment migration guide, development guide and the new PCV release note. Prior release refs remain immutable. Later evidence-only commits do not change either tag or its packages.

## Reviewed gates

The lead reviewed every owner diff and the accepted runtime paths, error handling and captured outputs from the [critique follow-up](../critique-followup-2026-10-01/final-review.md). Reused owners used Ponytail FULL and Superpowers. The lead wrote no product code. [Source review](source-review.md), [hub review](hub-review.md), [package review](package-review.md) and [preservation review](preservation-review.md) retain check details and boundaries.

The fresh package gate found two stale 0.1.7 filename expectations. The original owner corrected only the expected-version source; four existing checks then passed from final committed source. Two independent clean builds and the clean annotated-tag rebuild produced identical four-asset bytes. Runtime inputs match the accepted source and package members are the explicit 20-file allowlist. CRC, internal checksums, manifest identity, tar wrapper and MO2 member comparisons passed. No state, tests or source evidence enters runtime packages.

Both draft releases were downloaded into distinct fresh directories and all four actual files were compared by size, SHA-256 and byte sequence with the verified candidate before publication. After publication, fresh downloads from both public releases again passed all four comparisons: eight draft and eight public asset comparisons total. No historical asset was replaced.

| Asset | Bytes | SHA-256 |
| --- | ---: | --- |
| `private_conversation-0.1.8.dwpkg` | 3352551 | `170a1db317dcf9553c571435bb11ab5ca60c1e61013ec165a240a382ee0aa1c4` |
| `private_conversation.tar.gz` | 3023224 | `00354e95eeeb0e4b6d76bcc6115f41cea7b8de54aa189fbfff3f2cfc5a202e3e` |
| `private_conversation-0.1.8-mo2.zip` | 3025865 | `302f4da940ac63043a1dfab826fc094edbb2d8147797dc643fee52f272b015a8` |
| `SHA256SUMS.txt` | 294 | `e8415e78a775da674aa505a3da90c76cd0ae21cd5780b2f46c26a9de51a0d5d5` |

## Publication order

1. Canonical immutable tag was pushed after clean source gates; main remained at `82b0b84c34f317213a2b81df801ef3af4871ada4`.
2. Verified dedicated draft was published at **2026-10-01 22:59:58 UTC**. Fresh public downloads passed.
3. Exact hub source snapshot was committed/tagged, then its downloaded draft assets verified. The bridge was published at **2026-10-01 23:04:04 UTC**.
4. Only after both releases were public were canonical main and hub main fast-forwarded to the release commits above. Remote starting refs were rechecked; no force push occurred.
5. Fresh public hub downloads passed. Independent final public-state checks are recorded separately in `public-state-review.md`.

Published [dedicated release](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/tag/private_conversation-v0.1.8), [hub bridge](https://github.com/Francisco-boop-001/CHIM-Plugins/releases/tag/private_conversation-v0.1.8), and [user plugin hub](https://github.com/Francisco-boop-001/CHIM-Plugins). The hub is a Markdown README/docs site; GitHub Pages is disabled. No catalog submission or maintainer messaging was performed.

## Limits

This is source, fixture and release/package verification. Installed CHIM, PHP-FPM, real provider/database, native playback and Skyrim behavior remain unverified; the manual operator checklist is unrun. State migration requires quiesced old writers, effective HTTP protection until migration succeeds, and the actual PHP worker temp namespace/UID/install path. Cross-device or invalid migration refuses without copying/resetting. Temporary state can be removed by the OS. Logger contention fallback is best effort through PHP error_log, outside the Logs reader and without durable/no-loss guarantees. These limits remain explicit in shipped guidance; PRE-ALPHA is intentional.
