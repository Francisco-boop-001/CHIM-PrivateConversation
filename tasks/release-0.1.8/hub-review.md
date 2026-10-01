# Private Conversation 0.1.8 hub route review

## Result

`CHIM-Plugins` is the Markdown release and migration hub, not an active Pages site. Its clean local checkout was `main` at `98ed33b4169b4bc3e29835fdc4d710e83e6c2c1a`; authorized read-only GitHub API lookup returned the same remote `main` SHA. At inspection, both the standalone `CHIM-PrivateConversation` and collection `CHIM-Plugins` repositories had the existing prerelease tag/release `private_conversation-v0.1.7`. Both repositories returned 404 for the `private_conversation-v0.1.8` tag ref and release, so there was no 0.1.8 collision at that check. The hub repository reports `has_pages=false`; no tracked workflow, `CNAME`, site-generator config or HTML entrypoint was found. The publication route is the versioned GitHub releases plus the hub README and Markdown docs.

The local hub checkout's `origin` is `https://github.com/Francisco-boop-001/CHIM-Plugins.git`. The collection 0.1.7 annotated tag object was `fc9d08de68ef52ddf8501a3a5a0cd89005469544`; the standalone 0.1.7 annotated tag object was `2a99d557aaf1859688d964306eb8390aeeeac4e6`. Both remain historical prereleases. No tag, release, branch or remote was changed.

## PCV-only hub changes prepared

- `README.md` now points its PCV candidate row and three download links to canonical 0.1.8 tag URLs and adds the 0.1.8 release-note link. The Mind Poisoning row and assets are unchanged. The 0.1.7 troubleshooting paragraph remains labeled with the release that introduced those behaviors.
- `docs/deployment-migration.md` advances the current PCV row and one-time 0.1.4/0.1.5 transition text to 0.1.8, and explains 0.1.8's first-access state migration, fail-closed `EXDEV` behavior, PHP worker temporary-root namespace, and absence of installed PHP-FPM/CHIM proof. The immutable Mind Poisoning 0.1.14 / PCV 0.1.6 fixture is still identified as historical.
- `docs/development.md` changes only its current PCV candidate pointer from 0.1.6 to 0.1.8; Mind Poisoning remains 0.1.14 and its history is unchanged.
- `distribution/private_conversation-v0.1.8.md` is copied verbatim from the canonical PCV working tree. SHA-256 matches on both files: `4E329EFA57675CA87B1C70ED03105870CDDC5D3EED0595D4B93B865B21121E87`.

The 0.1.8 release links are intentionally staged and did not resolve at the remote-ref check above. The canonical 0.1.8 source tag and assets must be published and verified before advancing the mutable canonical manifest or publishing the bridge release.

## Subtree sync and preservation

The tracked `plugins/private_conversation/` directory is a normal Git tree, not a submodule. It is the embedded PCV source/integration snapshot, not part of the collection root Mind Poisoning package. The canonical PCV 0.1.8 worktree was still dirty and the tag absent during this review, so that subtree was not changed. Once the immutable canonical tag exists, sync the complete tagged source tree into `plugins/private_conversation/`, including its nested `.gitattributes`, manifest, source, documentation, tests and builder. Verify `git rev-parse <tag>^{tree}` equals `git rev-parse <hub-commit>:plugins/private_conversation`; that checks all tracked paths, modes and blob contents while preserving nested line-ending policy.

The scope is limited to the three root documentation files above and the new versioned PCV release note. The collection root `server/` and `server/manifest.json` remain Mind Poisoning 0.1.14. Protected outside paths include `plugins/private_conversation/` until tagged sync, `distribution/plugin_repository_entry.json`, `distribution/submission/mind-poisoning.json`, the Mind Poisoning release notes/runtime/docs, `docs/integration-api.md`, the immutable 0.1.14 task/evidence records, and `distribution/private_conversation-v0.1.7.md`. Historical PCV notes/tags/assets remain unchanged. No official CHIM catalog submission is included.

## Verification and limits

At baseline, the hub checkout was clean on `main` tracking `origin/main`; remote API `main` matched the checkout. After preparation, the hub diff is limited to `README.md`, `docs/deployment-migration.md`, `docs/development.md`, and new `distribution/private_conversation-v0.1.8.md`; `git diff --check` passes. The new release note hash matches the canonical source note exactly. No package was built, no release asset downloaded, and no installed CHIM, PHP-FPM, live provider/database or Skyrim behavior was tested. No files were copied into the embedded PCV subtree, and no commit, push, tag or publication was performed.
