# Independent publication review — 0.1.23

## Result

The public 0.1.23 release matches the approved source and candidate packages. The prior 0.1.22 release remains published with its recorded body and assets intact. This is a source/package/publication check, not an installation or runtime test.

## Public source and release

At the time of this source-publication check, the public `main` ref and `private_conversation-v0.1.23` annotated tag both target commit `77265202d7f4a47b88e1eb88131b21f4b0752020`; the commit tree is `0a0e0b7d6f55f9992a6aea7d9febfe8b83bf4653`. The tag object is `4f735d8276ec2fab9cdbe52cacc0b6b511e25fc4`. These match the local tag and requested source commit.

GitHub release ID `421054139` is published (`draft=false`, `prerelease=false`) and currently latest. Its body matches the local `distribution/private_conversation-v0.1.23.md` after line-ending and final-newline normalization. The raw document at the public 0.1.23 tag also matches the tagged source copy.

## Five fresh asset downloads

I fetched each public release URL anonymously and hashed the response bytes in memory; no package was installed or written to disk. Every size and SHA-256 matches both `dist/release-v0.1.23-candidate` and `tasks/release-0.1.23/package-evidence/package-inventory.json`.

| Asset | Bytes | SHA-256 |
| --- | ---: | --- |
| `chim-plugin.tar.gz` | 3,053,766 | `4aa0d8d5dd32ce57c59f56324c31a8b0b89e48db6075d0988156e5fc03587d9a` |
| `private_conversation-0.1.23-mo2.zip` | 3,057,014 | `cb009a464a4d75977e9eb15e32df62ac8acdf99d087648c0c75f2fbc517ffd76` |
| `private_conversation-0.1.23.dwpkg` | 3,477,054 | `e743d43ef3c34f7d9dc11e3b2bc3c5162957973e9978d3c784270a2c1314c001` |
| `private_conversation.tar.gz` | 3,053,844 | `e8ba7b6a10d6d8591a291d3da286e5f017b331b816f7454039de465a715d9cdb` |
| `SHA256SUMS.txt` | 381 | `9ac9680d552fc99cf7cea7d46f3b48511780d0da1932f288842ec2d8cb352031` |

The public release API lists exactly these five assets with matching sizes and digests. The candidate inventory independently records exactly five files and a passing four-package checksum check.

## Guide and evidence links

The linked [install and recovery guide](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.23/README.md#install-and-update) resolves at the release tag; its heading is `Install and update`, matching the anchor. The linked [0.1.23 verification record](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.23/tasks/critique-fixes-2026-10-22/verification.md) is available at that tag and records the original failures, corrected fixtures, and limits on runtime claims. The [historical 0.1.20 acceptance evidence](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.22/tasks/beta-2026-10-17/acceptance-evidence.md) is available through its explicit 0.1.22 tag URL.

## Prior release preservation

The public 0.1.22 release (ID `418287394`) still has its frozen tag, body, published state, and five asset records. Its annotated tag object `e25b8977e97dd70affa9378524c578e9484c1955` targets commit `066ffdabcc132e5c43a6c620e8ffeff70f19854a`, as in the frozen receipt `tasks/critique-fixes-2026-10-22/release-before.json`. I compared the release body and stable asset metadata (IDs, names, media types, sizes, digests, timestamps, and URLs) against that receipt; they match. Download counts and which release is latest were excluded because they change after publication.

## Verification boundary

The checks above establish public source/tag/body and asset-byte identity. They do not establish a fresh install, a complete CHIM/provider request, native playback, or Skyrim gameplay. The earlier 0.1.20 gameplay evidence remains version-bounded and is not evidence of 0.1.23 runtime behavior.
