# PCV 0.1.7 publication route review

## Result

The existing user-facing hub is the `CHIM-Plugins` GitHub repository: its root `README.md` is the plugin index/landing page, `docs/deployment-migration.md` is the migration guide, and `distribution/private_conversation-v<version>.md` holds release notes. The repository also retains versioned bridge releases. I found no tracked GitHub Pages workflow or static-site configuration in the reviewed `main` tree. The lead's authorized read-only GitHub check confirmed `has_pages=false`; there is no Pages deployment to update. “Publish” here means versioned bridge release assets plus the reviewed README/docs update.

The review used the isolated clean collection checkout `K:\ActorwrightExchange\projects\CHIM-Plugins-release-0.1.7`, at `origin/main` / `74ca8c97d30045822170477aad87824fb39b8222`; the lead's read-only GitHub query confirms the same `main` head. The separate dirty `CHIM-MindPoisoning` authoring checkout was not changed. The PCV 0.1.6 release record confirms the previously used ordering: verify and publish versioned assets before advancing mutable manifest branches, then compare public downloads against the tagged assets.

## Current route and minimal PCV-only hub delta

The canonical PCV release source is the standalone `CHIM-PrivateConversation` repository. The collection's `plugins/private_conversation/` is a pinned source/integration snapshot, not the MP runtime package. The collection root `server/` and `server/manifest.json` are Mind Poisoning 0.1.14 and should remain unchanged for this PCV-only release.

After the canonical PCV 0.1.7 source tag and assets are stable, the minimal hub update is:

| Hub path | PCV-only change |
| --- | --- |
| `plugins/private_conversation/` | Sync the complete clean tagged PCV 0.1.7 source tree, including its PCV manifest, docs, tests and builder, and compare it with the standalone clean export under the same `.gitattributes` policy. Keep it out of the MP runtime allowlist. |
| `README.md` | Advance only the Private Conversation row and quick-install links from 0.1.6 to 0.1.7. Update statements about the current PCV candidate; retain 0.1.6 references that describe historical releases or migration. Leave the Mind Poisoning row and links alone. |
| `docs/deployment-migration.md` | Advance the PCV candidate/release row, document that 0.1.6's dedicated-repository manifest can update through the standalone channel, and preserve the one-time migration warning for 0.1.4/0.1.5 literal old-hub URLs. Do not say a 0.1.7 bridge release makes those old URLs self-updating. |
| `distribution/private_conversation-v0.1.7.md` | Add version-specific release notes and installation/evidence limits. Preserve the 0.1.6 note as the record of that release. |
| Collection release `private_conversation-v0.1.7` | Publish a bridge release whose versioned assets and checksums are byte-identical to the verified standalone 0.1.7 assets. Preserve prior release tags/assets. |

The current row/link locations are `README.md:14,25`, the migration index is `docs/deployment-migration.md:10`, and the older-PCV transition is documented at `docs/deployment-migration.md:22-24`. `plugins/private_conversation/server/manifest.json` currently identifies PCV 0.1.6 and the dedicated repository; the collection root `server/manifest.json` identifies MP 0.1.14. `distribution/plugin_repository_entry.json` and `distribution/submission/mind-poisoning.json` contain only the Mind Poisoning catalog draft. The official CHIM catalog is a separate route, not this README/migration hub; the current docs say no catalog submission was made. No PCV catalog-row change is needed for the repository bridge release.

## Preserve historical source identities

The immutable MP 0.1.14 tag contains the matched PCV 0.1.6 test snapshot. Keep that tag, its root MP payload, and the associated evidence unchanged. The new PCV bridge tag can carry a newer `plugins/private_conversation/` snapshot while retaining the same MP 0.1.14 root source/version; update current hub documentation so it distinguishes that newer main/bridge snapshot from the older snapshot pinned inside the immutable MP tag. Do not retag or rebuild MP 0.1.14 as part of PCV 0.1.7.

The current PCV 0.1.6 manifest in the hub snapshot points to `Francisco-boop-001/CHIM-PrivateConversation`, reads its `main/server/manifest.json`, and uses a version-substituted release URL. That is the continuing standalone update route for 0.1.6 and newer packages. By contrast, the migration guide documents that 0.1.4 and 0.1.5 use literal version-specific old-hub URLs; preserve their historical tags/assets and keep the manual/explicit-channel migration instruction.

## Required release chronology

1. Prepare immutable canonical PCV and collection bridge tags from clean source, with the hub commit limited to the PCV snapshot and hub docs. Verify package members, manifests, checksums, archive equality and the full PCV snapshot equality. Keep `.gitattributes`/line-ending rules in the standalone and embedded trees consistent.
2. Upload draft assets for both releases and download/compare them against the clean-tag outputs; publish the verified standalone assets and then the byte-identical `CHIM-Plugins` `private_conversation-v0.1.7` bridge assets.
3. Only after both versioned releases are published and their assets verified, advance the mutable standalone `main` manifest and collection `main` PCV snapshot/README/migration links to the reviewed commits. Leave the collection root MP manifest and the MP 0.1.14 payload/version untouched.

This follows the prior PCV 0.1.6 record in `tasks/release-v0.1.6-evidence.md` and the collection record `tasks/release-v0.1.14-pcv-v0.1.6.md`: the dedicated and migration-hub assets were byte-compared, published, and only then were their mutable manifests advanced. The bridge is a familiar versioned download/migration-hub route; it does not replace the canonical standalone source or official CHIM catalog submission.

## Review limits and follow-up

The isolated collection tree has no tracked `.github/workflows`, `CNAME`, site-generator config, Pages config, or HTML site entrypoint; its tracked presentation is README and Markdown docs. My local GitHub CLI could not read its configuration and `git ls-remote` could not connect, so I could not independently refresh those public refs. The lead subsequently resolved that limitation with authorized read-only GitHub queries: `repos/Francisco-boop-001/CHIM-Plugins/pages` reports `has_pages=false`; `main` is `74ca8c97d30045822170477aad87824fb39b8222`; the annotated `private_conversation-v0.1.6` tag object exists as `a1be0ff6d7e30ab9551d45e34ee586fc69f4f1f5`; and `private_conversation-v0.1.7` currently returns 404. The local isolated clone omitted the existing 0.1.6 tag; that was a fetch-state gap, not evidence that the public tag is absent. Preserve 0.1.6 and create a new 0.1.7 tag/release only after release checks.
