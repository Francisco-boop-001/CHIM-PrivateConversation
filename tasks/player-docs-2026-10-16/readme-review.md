# Reader-first README and release-body review

Prepared 2026-10-16 for lead review. This is a documentation-only follow-up after the immutable 0.1.20 tag and public assets.

## Scope

- Reorganized the canonical README around the player’s first scene: quickstart, mode setup, capability map, examples and expected outcomes, ending, troubleshooting, installation, and current ALPHA limits.
- Opened with the player use case: two NPCs trade gossip without the Dragonborn joining, or one NPC thinks aloud to no one; the single author joke is self-deprecating and does not mock users.
- Preserved the World of Drama-Llama identity and existing art; operating details include the server-side/no-game-companion boundary, the old 0.1.4/0.1.5 manifest update limitation, duplicate recovery, state/access safeguards, logging, ownership boundaries, compatibility references, and historical release links.
- Added an eavesdropping example while stating that exclusion from the current audience is not proof of physical earshot. Clarified that Scene card works in every mode while Turn length is disabled in Solo.
- Restored the optional Mind Poisoning repository link and API behavior: reflection API v1 supports evaluation; optional reply API v2 enables full-reply handling with v1 fallback. The README now describes the Pair path (A speaks to B about C) and Solo subject boundaries. Mind Poisoning remains optional for PCV scenes.
- Prepared a proposed full replacement body for the existing 0.1.20 GitHub release. Its player-use-case intro, links, three-step installed quickstart, and End now reminder precede the original technical release-note body. The only technical-body edit is changing its relative README install link to an absolute GitHub README URL so the link works on the release page. The draft has not been applied to GitHub.
- No product source, package asset, version, tag, release flag, CHIM core, Mind Poisoning, or hub file was changed by this documentation follow-up.

## Verification

- Focused static Python checks passed for UTF-8 without BOM, one terminal newline, consistent line endings, no trailing whitespace, local links and heading anchors, README identity/safety wording, and release-body structure.
- README: 21,729 bytes, SHA-256 `e8cead536efe5adc62fa70a96bcf7e7d8ee2c5b8a2a27019ef78e9ba132adf4d`; LF endings and two balanced fenced code blocks.
- Proposed release body: 6,355 bytes, SHA-256 `f43a584592cd504eff14af95c07ea4a7e7f2aeb489007fc571440f9fd6be4b5f`; LF endings. Its technical body matches `distribution/private_conversation-v0.1.20.md` after normalizing only the final relative README link to the absolute GitHub URL.
- Scoped `git diff --check` exited 0 for README, tasks/todo.md, tasks/lessons.md, and the corrected publication receipt. Git reported its expected CRLF-to-LF normalization warning for `tasks/todo.md`; the file has consistent CRLF and no trailing whitespace.
- No PHP, WSL, full CHIM, database, live install, provider, or gameplay checks were run for this docs-only follow-up. Existing 0.1.20 runtime/package/publication evidence remains in the release task receipts; it is not new verification from this pass.

## Captured-output whitespace exception

Default `git diff --check` reports an extra blank line at EOF in exactly these five raw stdout captures: `tasks/release-0.1.20/publication-evidence/clean-build/compare-SHA256SUMS.txt.stdout.txt`, `compare-chim-plugin.tar.gz.stdout.txt`, `compare-private_conversation-0.1.20-mo2.zip.stdout.txt`, `compare-private_conversation-0.1.20.dwpkg.stdout.txt`, and `compare-private_conversation.tar.gz.stdout.txt`. The blank line is part of the captured command output; the clean-build manifest records the FC comparison as no differences (exit 0). Preserve these receipts byte-for-byte. The scoped whitespace check excludes only this five-file set; the remaining staged paths pass the default check.
