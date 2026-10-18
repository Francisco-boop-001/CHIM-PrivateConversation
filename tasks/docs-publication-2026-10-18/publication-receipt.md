# Publication receipt: PCV documentation and 0.1.21 release body

Verified 2026-10-18. This records publication of the reviewed documentation and body-only release-note update. It does not replace the historical 0.1.21 build/download receipts.

## Canonical PCV documentation

- Canonical commit: 98d9e0bae656b83e6d04cbb348ebb4182896b0ff
- Parent: de909204a73c4744fe2d293ab92c8a6cf5c40563
- Tree: c801df68a2e5a1ac28089ac790b409c0ffb3177e
- Pushed non-force to canonical main. Public commit and all six changed path blob IDs/modes were independently verified.
- The six paths are README.md, docs/player-guide.md, docs/operator-acceptance.md, tasks/beta-2026-10-17/acceptance-evidence.md, tasks/beta-2026-10-17/playback-review-2026-10-18.md, and tasks/todo.md.

## Existing GitHub release body

The approved current body is retained at [release-body.md](release-body.md), SHA-256 9b50adb593b4c8c29aa99f5497d88d94125f8f73d143c60b3959fa0da5873f3d. It was published as a body-only edit to existing release 416033891, and the public readback matched the approved file exactly. It links the updated documentation at the canonical docs commit and distinguishes the five Pair and three Solo native playback-completed records from the dated server-side MP/END review.

The release title, state, tag, and five assets remained unchanged: Private Conversation 0.1.21 — BETA, draft=false, prerelease=false, tag private_conversation-v0.1.21. The annotated tag object e056638a9a083c91634da7619e469645cf2d09bc still peels to source commit f1e1b4b410bca981afb1f7e95de7ea84f9dd2981. The five asset IDs, sizes, and SHA-256 digests read back unchanged:

| Asset ID | Asset | Bytes | SHA-256 |
| ---: | --- | ---: | --- |
| 651480345 | chim-plugin.tar.gz | 3,053,489 | d2f8dfde43ab0cef03aeeb7a06b110c18378aa6f7e322d64975d6d4f5e9a5b55 |
| 651480353 | private_conversation-0.1.21-mo2.zip | 3,056,680 | 8480d15479d0371a7e78979c809d698abf1fab35efb4ef98071ab9f9c811db5e |
| 651480343 | private_conversation-0.1.21.dwpkg | 3,475,089 | 0dd26c6387dcb880caadd410580d63945412f04ce3178505df136fce242eda94 |
| 651480350 | private_conversation.tar.gz | 3,053,560 | 74629c0faa167143f4de7947862326a8465a9fc6058652dd07effdbd29943fe9 |
| 651480347 | SHA256SUMS.txt | 381 | d69825f0173ca5a87bb1ea8ba636b294455b9c915f329f678f5cb34e0f414697 |

No assets were rebuilt or downloaded again for this body-only update. The historical source/body and asset verification records under tasks/release-0.1.21/ remain unchanged; this receipt and the linked approved draft describe the current release body.

## PCV-only CHIM-Plugins hub mirror

The mirror used reviewed hub baseline 7b66cc450f7e02610a59cfc762891de66c4b40b5, preserving its newer Mind Poisoning work. The mirror commit is 2e503161cbdc58ba65f3a9de378dd4147e743922 (tree 9a4d72d52a54f1ab2bd8a65ca42737c14efd9475); the final landing-links commit is 27b835165f8432b807f256dadc971f8156698ace (tree 40987d8b2f4e685627d17bf007e14ab5c23a8b4d).

Independent public verification read the complete 679-entry hub tree. All six changed path blobs/modes matched the approved result, and every other tracked path matched the reviewed baseline. The six-path delta consists of five mirrored documentation paths (three byte-identical and two with nine canonical receipt-link substitutions) plus exactly two PCV URL replacements in the hub README.

## Scope and evidence limits

This publication changed documentation and the existing release body only. Source code, scripts, tests, distribution payloads, product version, compatibility pins, tag, and assets were not changed. No test suite, installation, provider request, database operation, service/distro start, or game action was run for this publication.

The three missing relative links in older historical task-list content predate this work and remain unchanged; they are not new reader-facing documentation links. The previous receipts remain historical records. No new runtime, installation, provider, or gameplay evidence was produced by the body or hub updates.