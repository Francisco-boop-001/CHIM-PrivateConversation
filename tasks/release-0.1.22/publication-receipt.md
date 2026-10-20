# Private Conversation 0.1.22 BETA publication receipt

## Canonical release: verified

- Released source: `066ffdabcc132e5c43a6c620e8ffeff70f19854a`; tree `049a10126234cec80497dd6dadb3834c08b561ec`.
- Annotated tag: `private_conversation-v0.1.22`; tag object `e25b8977e97dd70affa9378524c578e9484c1955`, peeled to that source commit.
- [Public release](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/tag/private_conversation-v0.1.22): ID `418287394`, titled **Private Conversation 0.1.22 — BETA**, `draft=false`, `prerelease=false`, selected as latest for CHIM discovery. These technical flags do not certify maturity.
- Canonical main advanced non-force only after the five public downloads passed. See [source publication](canonical-source-publication.json) and [tag readback](tag-receipt.json).

## What the evidence establishes

The four bounded fixes are D1 captured action-prompt scoping, D2 scene-card validation feedback, D3 first-refusal clock rollback handling, and R7 final eligibility-check reason and wording. The lead reviewed every product diff, connected callers and failure paths, and original executable output. Independent reviews found no supported release blocker. The [focused verification record](../critique-fixes-2026-10-20/verification.md) preserves both failed baseline assertions and corrected results, including the initial UI-fixture mistake and its clean baseline comparison.

The existing package gate passed four tests; [the independent inventory](package-evidence/package-inventory.json) passed 14 content/integrity checks covering all 21 allowlisted server files. The [clean committed-source export and rebuild](package-evidence/clean-source-asset-comparison.json) produced five files byte-identical to the approved candidate. [Draft downloads](draft-verification.json), then all five [fresh anonymous public downloads](public-verification.json), matched those bytes and SHA-256 hashes. The release body matched the reviewed useful player guidance rather than an inferred list of changes. The [independent publication audit](publication-review.md) separately checked public release flags/body, tag/source identity, asset metadata, tagged documentation reachability and the protected prior release.

The original preflight transcript retains its trailing blank line. That one original-output whitespace warning was excluded from the staged whitespace gate; all other staged content passed. No failed receipt was rewritten into a pass.

## Protected boundaries

The 0.1.21 release ID, title, flags and all five asset IDs/sizes/digests remained unchanged after publication. Its annotated tag and peeled source commit also remained unchanged. CHIM core, SHARMAT and Mind Poisoning were not modified. Unrelated pre-existing untracked PCV evidence was not staged.

PHP fixtures ran only in `DwemerAI4Skyrim3-test`, using isolated synthetic state. The [test window was released](../critique-fixes-2026-10-20/runtime/runtime-release-corrected.json) to Department before host-only packaging/publication. PCV did not re-enter or stop that distro afterward. The gaming VHD check compared length and exact UTC timestamp, not disk contents; the initial false timestamp comparison is retained and explained in the corrected receipt.

## Hub mirror

The [PCV-only hub commit](https://github.com/Francisco-boop-001/CHIM-Plugins/commit/66e92d77048bdb75669f4d506d5e51cf71ddcfe7) is public: `66e92d77048bdb75669f4d506d5e51cf71ddcfe7`, tree `438494604c69934b5db9e14f7845d4f57379bd6e`. It follows hub base `c1cd9d0cb3739c0908e6561e4c8ddd2436d3a70c` by a non-forced push. The lead reviewed the six PCV-specific root README line edits, exact staged path set and resulting source mapping. All 109 mapped files match the canonical source commit by blob and mode, including all 21 server payload files. The 258-entry protected tree and every non-PCV README passage are preserved; historical hub task-list adaptations were not overwritten. Fresh anonymous API readback confirmed the entire public tree and version 0.1.22 manifest. See [local mirror verification](hub/mirror-verification.json), [committed mapping](hub/hub-commit.json), and [public hub verification](hub/public-hub-verification.json). The hub worktree and index are clean. The hub base and canonical source belong to different repositories and have separately recorded identities.

## Verification limits

This release changes runtime code. Historical 0.1.20 Pair/Solo native playback evidence is retained with its version and operator/log distinctions; the metadata-only 0.1.21 payload comparison does not establish 0.1.22 gameplay. No fresh installation, provider request, complete CHIM request, native playback, Skyrim gameplay, or third-party action execution was run for this maintenance release. Fixture, source, package and public-download checks are the evidence for these fixes.
