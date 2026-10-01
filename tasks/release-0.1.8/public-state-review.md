# Private Conversation 0.1.8 public-state review

## Result

Fresh read-only GitHub API checks confirm both 0.1.8 releases are published, non-draft PRE-ALPHA prereleases. The canonical and hub `main` branches point at the published tag targets. The canonical manifest and embedded hub PCV manifest both identify version 0.1.8 and repository `Francisco-boop-001/CHIM-PrivateConversation`; the hub root manifest remains Mind Poisoning 0.1.14. The public hub README contains its 0.1.8 tag, note and three asset links. All 52 protected hashes still match.

## Public refs and releases

Read-only API output:

```text
canonical main: 533bc6339fe4c1d39a0d95157ca216c281164b89
hub main: 34ad2a1f9d86bfa4b8811510aef4fee77a662a44
canonical 0.1.8 annotated-tag object: 28f3a70a89ce2bd4d83cf753876f8b1668b6cabf
hub 0.1.8 annotated-tag object: 1386bd10df7714de5befa56f14c37f59f0853e8e
canonical 0.1.8 tag target: commit 533bc6339fe4c1d39a0d95157ca216c281164b89
hub 0.1.8 tag target: commit 34ad2a1f9d86bfa4b8811510aef4fee77a662a44
canonical release: private_conversation-v0.1.8 false true 2026-10-01T22:59:58Z
hub release: private_conversation-v0.1.8 false true 2026-10-01T23:04:04Z
```

The release fields are `tag_name`, `draft`, `prerelease`, and `published_at`, in that order.

Historical 0.1.7 refs remain unchanged:

```text
canonical tag object: 2a99d557aaf1859688d964306eb8390aeeeac4e6
canonical peeled target: d8f79c6059ac9184c5117c26a0514e18aae76902
hub tag object: fc9d08de68ef52ddf8501a3a5a0cd89005469544
hub peeled target: 98ed33b4169b4bc3e29835fdc4d710e83e6c2c1a
canonical release: private_conversation-v0.1.7 false true 2026-10-01T19:59:07Z
hub release: private_conversation-v0.1.7 false true 2026-10-01T19:59:11Z
```

The historical tag objects and targets match the prior preservation review. No ref was changed in this review.

## Public manifest and README checks

Public Git tree/blob reads returned:

```text
canonical_main_manifest=0.1.8,Francisco-boop-001/CHIM-PrivateConversation,candidate
hub_pcv_manifest=0.1.8,Francisco-boop-001/CHIM-PrivateConversation,candidate
hub_root_manifest=0.1.14,Francisco-boop-001/CHIM-MindPoisoning
```

The tag targets above equal their respective `main` commits, so these manifests are also the content of the 0.1.8 tagged commits. README substring checks on public `CHIM-Plugins/main` returned:

```text
README_LINK_OK https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/tag/private_conversation-v0.1.8
README_LINK_OK distribution/private_conversation-v0.1.8.md
README_LINK_OK https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.8/private_conversation-0.1.8-mo2.zip
README_LINK_OK https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.8/private_conversation-0.1.8.dwpkg
README_LINK_OK https://github.com/Francisco-boop-001/CHIM-PrivateConversation/releases/download/private_conversation-v0.1.8/private_conversation.tar.gz
```

The checks confirm the published text and metadata. This review did not download release assets; the lead reports separate byte/hash verification.

## Protected-file hashes

After publication, I re-read `tasks/maintenance-2026-10-01/protected-before.json` and recalculated every listed file's SHA-256:

```text
protected_count=52
protected_mismatch_count=0
protected_sha256=all_match
```

This comparison covers only the 52 listed files, not every path in the shared Mind Poisoning checkout.

## Limits

This was a public metadata/ref check plus local hashing only. No release assets were downloaded by this review, and no packaging, install, PHP-FPM, live database/provider or gameplay checks were run. The release asset byte/hash evidence is recorded by the lead separately.
