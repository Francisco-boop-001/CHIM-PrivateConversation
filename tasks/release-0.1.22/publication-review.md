# PCV 0.1.22 final publication audit

**Result: PASS.** This bounded read-only audit independently queried GitHub’s public REST API and tagged raw-source URLs on 2026-10-20, then compared the results with the local release receipts. No refs or assets were changed.

The public [0.1.22 release API record](https://api.github.com/repos/Francisco-boop-001/CHIM-PrivateConversation/releases/tags/private_conversation-v0.1.22) returns release ID `418287394`, tag `private_conversation-v0.1.22`, `draft=false`, `prerelease=false`, published `2026-10-20T10:47:56Z`. The anonymous `/releases/latest` endpoint returns the same release. Its published body matches `release-body.md` after newline normalization.

The earlier [draft verification](draft-verification.json) records the same release ID and body/assets while the release was still draft; the public verification receipt records the later `draft=false` state.

The public annotated tag ref resolves through tag object `e25b8977e97dd70affa9378524c578e9484c1955` to source commit `066ffdabcc132e5c43a6c620e8ffeff70f19854a`; that commit’s tree is `049a10126234cec80497dd6dadb3834c08b561ec`. These match the local HEAD, [tag receipt](tag-receipt.json), and [clean-source comparison](package-evidence/clean-source-asset-comparison.json).

The public API’s five asset names, sizes, and SHA-256 digests match both the [public verification receipt](public-verification.json) and the clean-source asset comparison:

| Asset | Bytes | SHA-256 |
| --- | ---: | --- |
| `chim-plugin.tar.gz` | 3,053,773 | `cf5f509570762dbc76cf21055477d582953f1cb563152dd4e07da27e5eaa24a6` |
| `private_conversation-0.1.22-mo2.zip` | 3,057,007 | `a5c4d0619d831c9a6c02400c3ddf1be62ba7abc489087b07c9e75d323abdf00a` |
| `private_conversation-0.1.22.dwpkg` | 3,476,396 | `8e557111e1402a423d1fd5f61fadb43dd35ae30f05d9043bfe8728d90fbd6220` |
| `private_conversation.tar.gz` | 3,053,852 | `7fd2d03a79627c59085cb141596a71ecd36142d021dd7197189071442bfb35da` |
| `SHA256SUMS.txt` | 381 | `7bbc63ba93ade821ddd131114e8e3d2796392da2126aee97ac59230ae2c8f0f3` |

The tagged [README](https://raw.githubusercontent.com/Francisco-boop-001/CHIM-PrivateConversation/private_conversation-v0.1.22/README.md), [player guide](https://raw.githubusercontent.com/Francisco-boop-001/CHIM-PrivateConversation/private_conversation-v0.1.22/docs/player-guide.md), [manifest](https://raw.githubusercontent.com/Francisco-boop-001/CHIM-PrivateConversation/private_conversation-v0.1.22/server/manifest.json), [0.1.22 verification record](https://raw.githubusercontent.com/Francisco-boop-001/CHIM-PrivateConversation/private_conversation-v0.1.22/tasks/critique-fixes-2026-10-20/verification.md), [historical acceptance record](https://raw.githubusercontent.com/Francisco-boop-001/CHIM-PrivateConversation/private_conversation-v0.1.22/tasks/beta-2026-10-17/acceptance-evidence.md), and [0.1.22 distribution note](https://raw.githubusercontent.com/Francisco-boop-001/CHIM-PrivateConversation/private_conversation-v0.1.22/distribution/private_conversation-v0.1.22.md) all returned content at the requested tag. The manifest reports version `0.1.22` and retains schema 2, development-candidate status, candidate channel, and compatibility reference `cf5030f15781637498be86debe26fcf102f5690d`.

Historical limits remain clear in the published docs: the 0.1.20 Pair/Solo results are historical playback-stage evidence; 0.1.21 was runtime-payload-matched to 0.1.20 but was not separately played; 0.1.22 changes runtime code. The 0.1.22 verification record describes synthetic test-distro fixtures and syntax checks, not a fresh installation, full CHIM/provider request, native playback, or Skyrim gameplay. The release body and player guide preserve those boundaries.

The protected [0.1.21 release API record](https://api.github.com/repos/Francisco-boop-001/CHIM-PrivateConversation/releases/tags/private_conversation-v0.1.21) remains release ID `416033891`, published and non-draft/non-prerelease. Its five asset IDs, sizes, and digests still match the saved [publication baseline](publication-baseline.json). This audit did not download binaries again; the prior [public verification receipt](public-verification.json) records anonymous downloads byte-equal to the candidate, and the clean-source comparison records all five assets byte-equal to the build from the tagged commit.

No publication blocker was found. This audit verifies public release metadata, tag/source identity, current tagged source reachability, and agreement of asset metadata with the existing byte-comparison receipts; it does not claim a new installation or runtime/gameplay test.
