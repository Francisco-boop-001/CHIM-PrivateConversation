# Private Conversation 0.1.7 publication — lead review

Result: committed, pushed and published as PRE-ALPHA on 2026-10-01. User explicitly authorized the release. Mind Poisoning remains a separate plugin; no installed CHIM, game, modlist, provider or live database was changed.

## Immutable release identities

| Repository | Release commit | Annotated tag object |
| --- | --- | --- |
| CHIM-PrivateConversation | `d8f79c6059ac9184c5117c26a0514e18aae76902` | `2a99d557aaf1859688d964306eb8390aeeeac4e6` |
| CHIM-Plugins PCV bridge | `98ed33b4169b4bc3e29835fdc4d710e83e6c2c1a` | `fc9d08de68ef52ddf8501a3a5a0cd89005469544` |

Both tags are `private_conversation-v0.1.7`. GitHub tag-object API reads resolve them to the exact commits above. Source root tree and committed hub `plugins/private_conversation` tree are both `45c6c1f817ad1066010fdac3bfb30bea8c15ca67`, proving all tracked bytes and file modes agree. Canonical commit and tag ZIP exports also match byte-for-byte. Subsequent publication-evidence commits change task documents only; tags and package inputs remain immutable.

## Reviewed checks

- The lead reviewed metadata/source inventories and prior maintenance and ACK-fix acceptance evidence, then inspected the clean-export comparison and actual verification output. The existing builder was reused; no product code was written by the lead.
- Four focused package checks passed from the clean source export. Two separate clean builds produced the same four assets, with exact runtime allowlist/source/member/checksum/manifest proof. Full commands, outputs and source hashes are in [package-review.md](package-review.md) and [package-evidence.json](package-evidence.json).
- Hub root documentation was separately reviewed in [hub-metadata-review.md](hub-metadata-review.md); its staged paths were exactly the PCV subtree plus root README, deployment migration document and new release notes. No root Mind Poisoning product code changed.
- Each draft release was downloaded into its own fresh directory. All four asset sizes and SHA-256 values matched the clean build before publication. After publication, fresh downloads from both release pages again matched all four values below: eight public asset comparisons, all passing. No asset replacement or historical retag was performed.
- Public main-ref and manifest API checks confirmed PCV 0.1.7, dedicated repository identity and candidate channel. The hub root MP manifest remains 0.1.14 and nested PCV manifest is 0.1.7. Historical hub PCV 0.1.6 tag object remains `a1be0ff6d7e30ab9551d45e34ee586fc69f4f1f5`.
- All 52 protected original authoring files remain hash-identical; the pre-existing dirty Mind Poisoning checkout was preserved. The independent [preservation review](preservation-review.md) states its precise inventory/snapshot limits.

## Published assets

| Asset | Bytes | SHA-256 |
| --- | ---: | --- |
| `private_conversation-0.1.7.dwpkg` | 3344250 | `a9347c6ebd4b6eb40b86b9d383785403b8e427a72e2992963088432f41e2f873` |
| `private_conversation.tar.gz` | 3021841 | `cd180949fa4bc12cd2b98daa1bad33ac53ff522e624446d9e7f9cdcd9691779b` |
| `private_conversation-0.1.7-mo2.zip` | 3024485 | `639cbd44e674cb16a9e42fa1724a9e13efabf883e99ce89120e14a1997547ce4` |
| `SHA256SUMS.txt` | 294 | `388d5ff92673dc2119f5e22a96e94ffc06baaeccd40fdcd53ba6a36fb8b40c50` |

These assets are identical on the [dedicated release](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/tag/private_conversation-v0.1.7) and [hub bridge release](https://github.com/Francisco-boop-001/CHIM-Plugins/releases/tag/private_conversation-v0.1.7).

## Publication order and public state

1. Created and pushed new verified immutable tags; neither main update branch advanced yet.
2. Created PRE-ALPHA drafts, uploaded the four verified assets and verified actual draft downloads from both repositories.
3. Published dedicated release at **2026-10-01 19:59:07 UTC** and hub bridge at **19:59:11 UTC**. GitHub confirms `isDraft=false`, `isPrerelease=true` for both.
4. Only afterward fast-forwarded dedicated main from `3f7c3681cd5f3a36493eaad5c54b126c58f2ab0d` to the release source commit and hub main from `74ca8c97d30045822170477aad87824fb39b8222` to the bridge commit. No force push.
5. Verified fresh public downloads, public annotated tag targets and main-manifest identities. Final task evidence is committed separately without altering the tagged source or assets.

The user's plugin site is the [CHIM-Plugins README/docs hub](https://github.com/Francisco-boop-001/CHIM-Plugins). GitHub reports Pages disabled, so no Pages deployment was attempted. No catalog submission or maintainer messaging was requested.

## Limits

This is release/source/package verification, not installed CHIM, real-provider, Skyrim or audio-playback proof. Previously reviewed isolated Apache/PostgreSQL and evaluator fixtures retain their stated boundaries. Early-ACK recovery is bounded, is not guaranteed across every crash/transaction visibility case, and does not cancel already-started work. The shipped README and release notes document those limits; PRE-ALPHA is intentional.
