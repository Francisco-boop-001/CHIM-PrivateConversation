# Private Conversation 0.1.20 publication receipt

Publication and the PCV-only hub mirror are complete. This receipt records release identity and evidence pointers; it does not claim live installation or gameplay verification.

## Canonical source and release

- Source commit: 85e9dd08c8bacbb6f1553e36b0d1f4c2caacf45e
- Source tree: fd1a2ff48cfa65dbb5c4500a7fb364975e48fdcd
- Annotated tag: private_conversation-v0.1.20 (tag object d79fdce5f0dcf58694f6712b17db2ff59860c4c8, peeled to the source commit above)
- GitHub release: ID 415462809, published 2026-10-16T23:36:24Z, draft=false, prerelease=false; the release is labeled ALPHA.
- Canonical main advanced non-force from b7030f259bf25101303f56c03090a060290992a5 to the source commit; ls-remote verification passed.

The release was built from a clean export of the exact source commit. All five anonymous public downloads matched the clean build byte-for-byte, including size and SHA-256. Detailed per-asset results are in [public-asset-verification.json](public-asset-verification.json); release metadata is in [public-release.json](public-release.json).

| Public asset | Size (bytes) | SHA-256 |
| --- | ---: | --- |
| SHA256SUMS.txt | 381 | e51d7d230b586ad3f544084b60ad40e4a7fe4fc830d0e35b12061977ad056fee |
| chim-plugin.tar.gz | 3053486 | 50405eca2d8a7b6e7dc9a307740b6ab0518366e21cfcbc743b88e88d94cbcd7b |
| private_conversation-0.1.20-mo2.zip | 3056680 | 7bd8c4e67e970416c116965a0470328a48e7ca4921750fafabf759a2826290db |
| private_conversation-0.1.20.dwpkg | 3475090 | d5da301dd332d45a97e962da96c8f0af9269d4d402e2083fe7a221e921090e8c |
| private_conversation.tar.gz | 3053559 | e949fe37d1e7fb2f8091e28f0c4af106ca1039a15809401082ea460572798bb8 |

## PCV-only hub mirror

The hub main was advanced non-force by commit 7b22123fe1e891fe7d4694b1d14e2acec75c5ce3, with tree 2fc4d4eac2f0e34bf52eec52581ea14ad27fbb34 and parent 301dce06b7e0559180a38449aaba7ba4551ff4a4. The GitHub commits API and ls-remote main both matched that commit.

Independent frozen-index review passed: all 356 PCV path/mode/blob entries equal the canonical source tree; three authorized shared docs were changed; 239 other paths were protected and unchanged. Eight exact CRLF evidence blobs required a command-scoped whitespace exception; no persistent Git setting changed. The detailed staging and index explanation is in [hub update report](hub-sync/hub-update-report.md), and the frozen index was independently checked in [the hub sync review](hub-sync/independent-review/frozen-index-independent-review.md). See the separate [public independent review](public-independent-review.md) for the release assets and remote verification.

The prior 0.1.19 public release and its assets remained unchanged. This update did not change CHIM core or Mind Poisoning, and did not create a new hub release.

## Verification limits retained

The 0.1.20 focused solo/action and duplicate-owner/HTTP refusal fixtures passed as recorded in [solo report](../solo-report.md) and [duplicate report](../duplicate-report.md). The offering fixture exercises PCV helper-shaped action lists and schema enums; it does not rebuild CHIM core actions. The duplicate fixture exercised owner state-API ARM/END/readback, not successful owner Plugin Page ARM/END. Full CHIM bootstrap/schema refresh, live provider/database operation, successful UI ARM/END, client/audio behavior, physical privacy, and Skyrim gameplay remain unverified.

The guarded ACK shutdown fixture was not run because no disposable PostgreSQL target was configured; no database connection was made. A postterminal logger directory_unavailable warning remains unattributed. These limits do not change the public ALPHA status, and the release is not beta or stable certification.

## Reader-first documentation follow-up

Canonical README and player guide are public in commit b6f0e82877038746497719d12952f51253fb59e5; the GitHub Contents API reported the exact README and guide blobs from that commit. The existing 0.1.20 release body was updated after lead review to the reader-first body, SHA-256 f43a584592cd504eff14af95c07ea4a7e7f2aeb489007fc571440f9fd6be4b5f. The initial body-equals-tag-note proof above is historical and describes the original publication only. The annotated source tag, five assets, and draft=false/prerelease=false flags were unchanged. The PCV-only hub documentation mirror was pushed non-force in commit 84dbe4f5c022764372ff23fe65a6b38be229ddf0; no other hub paths changed. See the [documentation publication receipt](../../player-docs-2026-10-16/docs-publication-receipt.md) for exact blobs, assets, protected subtrees, and scope limits.
