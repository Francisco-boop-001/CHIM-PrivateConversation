# Private Conversation 0.1.20 documentation follow-up receipt

This post-release documentation update changes no product code, version, package, or source tag.

## Canonical docs and release body

Canonical README and player guide were pushed non-force in commit b6f0e82877038746497719d12952f51253fb59e5 (tree b7f118af870feedc4d718654ff1a5ef7dd07c3af; parent 85e9dd08c8bacbb6f1553e36b0d1f4c2caacf45e). The GitHub Contents API returned the same blobs as that commit: README b6e03e1985002eb58e0090ea51e036fd16b144c5 and docs/player-guide.md d6ac482e2b70d1e420252dc14765fddaa09c0052. See the source-backed [README review](readme-review.md) and [guide review](guide-review.md).

After lead review, the existing 0.1.20 GitHub release body (release ID 415462809) was updated from [release-body.md](release-body.md), SHA-256 f43a584592cd504eff14af95c07ea4a7e7f2aeb489007fc571440f9fd6be4b5f. It now opens with a player-use-case introduction, guide/README links, an already-installed quickstart, and an End now reminder before the existing technical evidence and limits. The technical tail only normalizes the final relative README installation link to an absolute GitHub URL. The body-equals-tag-note statement in the original [release receipt](../release-0.1.20/publication-evidence/final-publication-receipt.md) describes initial publication only; the current body is the reviewed update. Release flags remain draft=false and prerelease=false. The annotated source tag was not moved or rewritten: object d79fdce5f0dcf58694f6712b17db2ff59860c4c8 still peels to source commit 85e9dd08c8bacbb6f1553e36b0d1f4c2caacf45e.

The same five asset IDs, sizes, and digests remain recorded in the original [public release metadata](../release-0.1.20/publication-evidence/public-release.json), [anonymous download verification](../release-0.1.20/publication-evidence/public-asset-verification.json), and [current release API snapshot](docs-refresh-public-release.json). The existing download proof was not repeated; no asset was uploaded or rebuilt.

The initial docs push succeeded even though its summary formatter reported an error after a bare true result. A corrected GitHub Contents API read verified both public document blobs before the release-body update.

## PCV-only hub documentation mirror

CHIM-Plugins main advanced non-force by commit 84dbe4f5c022764372ff23fe65a6b38be229ddf0 (tree 2fe5b76815555273b562a81b1d45a789a0be0963; parent 7b22123fe1e891fe7d4694b1d14e2acec75c5ce3). The GitHub commits API and remote main matched that commit.

Exactly three paths changed: the hub root README PCV link line, plugins/private_conversation/README.md, and plugins/private_conversation/docs/player-guide.md. The mirrored documents use the exact canonical blobs above; the root link now points to the current guide and README installation section. All other hub entries remain unchanged. PCV server, scripts, and tests trees match the prior hub commit at cf2418fdcaf7f67fdf2b293e6340c99893c52401, 26bc4ce982bf6e01248267d1ac294e10bde7290e, and ae8ce974d94f565065d0f400880955ddef61aaeb respectively. See the independent pre-commit [hub documentation review](hub-docs-independent-review.md).

## Limits

This was a static documentation review and publication update. No new runtime, database, CHIM bootstrap, provider, installation, client-sync, or gameplay checks were run. Earlier bounded test-distro fixture evidence and its limits remain in the [0.1.20 release receipt](../release-0.1.20/publication-evidence/final-publication-receipt.md).
