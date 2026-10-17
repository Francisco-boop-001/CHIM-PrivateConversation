# Hub publication: Private Conversation 0.1.21 BETA

Verified 2026-10-17. This records the PCV documentation/evidence mirror published to the historical `CHIM-Plugins` hub; it is not a full copy of the canonical plugin repository.

## Revisions and scope

- Canonical source: annotated tag `private_conversation-v0.1.21`, tag object `e056638a9a083c91634da7619e469645cf2d09bc`, peeled commit `f1e1b4b410bca981afb1f7e95de7ea84f9dd2981`, tree `30ea1280bcd8beecfee5bb2ad16c99e21a96da0c`.
- Hub commit: `291fac478ccb29021f963dcfa1ff715663fb1bdd`, parent `84dbe4f5c022764372ff23fe65a6b38be229ddf0`, tree `dfdce7511c2c460716b4dc51e2cc8b4a5b1cc648`. It was pushed non-force to hub `main` after confirming the remote still pointed at the parent.
- The commit contains exactly 17 approved paths: the 14 files changed in the canonical 0.1.21 source commit under `plugins/private_conversation/`, plus PCV-specific spans in the shared hub `README.md`, `docs/deployment-migration.md`, and `docs/development.md`.
- Thirteen of the 14 mirrored source files match canonical tag blobs byte-for-byte. The copied `plugins/private_conversation/tasks/todo.md` has only two intentional URL adaptations: both receipt references point to their canonical 0.1.21-tag locations because those historical files are absent from this partial hub mirror. No historical task tree was copied. All 14 mirrored paths retain their canonical file modes.
- The three shared Markdown files change only in their approved PCV spans. Text outside those spans, including Mind Poisoning and Sworn & Scorned sections/claims, is unchanged. The PCV README stanza itself was rewritten for the current BETA release and is not a whole-file preservation claim.

## Verification and limits

- The staged path set was exactly 17; cached whitespace checks passed. Every tracked path outside that allowlist retained its prior blob and mode. Hub-root `server/`, `scripts/`, and `tests/` stayed unchanged; the PCV mirror's 20 non-manifest server files remained byte-identical to the prior hub tree.
- After the push, anonymous GitHub REST responses identified public hub commit `291fac478ccb29021f963dcfa1ff715663fb1bdd`, its expected parent and tree, and a complete (not truncated) recursive tree. All 17 public path blob IDs and modes matched the reviewed local commit.
- This is a partial PCV documentation/evidence mirror, not proof that the entire PCV subtree equals the canonical repository. It publishes documentation and package metadata only. No CHIM core, Mind Poisoning, SHARMAT, server installation, game-client sync, or gameplay changes were made or tested by this hub update.
