# Private Conversation 0.1.7 hub metadata review

## Scope

Prepared the isolated `CHIM-Plugins` hub README, migration guide and 0.1.7 bridge release-note copy after canonical PCV source commit `d8f79c6059ac9184c5117c26a0514e18aae76902`. Only the assigned hub documents and this review note changed. The `plugins/private_conversation/` snapshot belongs to the package owner and was not edited.

## PCV-only hub updates

- The root README's PCV candidate row and three quick-install links now target version 0.1.7 in the canonical standalone repository. The Mind Poisoning row, assets and 0.1.14 links are unchanged.
- The debugging section now identifies PCV 0.1.7 and briefly describes bounded early-ACK recovery: eight entries / 8 KiB, 45 seconds from original receipt, exact output registration plus a unique native speech row, and a separate 600-second claim. It states that ACK is not playback proof and recovery can still be lost.
- The migration index now lists PCV 0.1.7. The 0.1.4/0.1.5 literal old-hub URLs remain historical and require the documented one-time 0.1.6 migration. That standalone 0.1.6 channel reads the dedicated repository's `main` manifest and uses a substituted release URL; it can reach 0.1.7 only after the standalone release and `main` manifest are advanced. The 0.1.7 collection bridge does not make the old literal URLs self-updating.
- The migration guide distinguishes the immutable Mind Poisoning 0.1.14 tag's PCV 0.1.6 fixture from PCV 0.1.7 standalone source and the separately synchronized current collection-main snapshot. It does not retag or rebuild MP 0.1.14.
- The hub release-note file is a byte-for-byte copy of the canonical 0.1.7 notes. Its SHA-256 is `3FCD93923B575F43CD67BBF4F7E0A29CE1F551EF05CDD94586F6874779A199EB`; commit/source/hub bytes match. The note's guide URL is absolute and tag-specific.

## Publication and preservation boundaries

The route review records GitHub Pages `has_pages=false` and a 404 for the not-yet-created `private_conversation-v0.1.7` tag; this update is the repository README, migration Markdown and planned versioned bridge release path, not a Pages deployment. No official CHIM catalog entry is submitted or approved. Root `server/` Mind Poisoning runtime/manifest, MP documentation and release links remain untouched. The bridge uses version-specific assets and does not replace the standalone canonical PCV source or alter the immutable MP 0.1.14 tag.

## Verification

Static check: exit 0 with five PASS lines for PCV candidate/install URLs, the 0.1.6 updater route and 0.1.4/0.1.5 transition, the historical MP 0.1.14 / PCV 0.1.6 fixture distinction and unchanged MP rows/links, source-commit note-byte equality, and the Pages-disabled publication route. Path-limited `git diff --check` exited 0; new Markdown whitespace checks passed. No broad tests or package build were run for this documentation slice. No commit, push, tag, Pages deployment, catalog submission or live environment change was performed.
