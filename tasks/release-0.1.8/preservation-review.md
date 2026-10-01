# Private Conversation 0.1.8 preservation review

## Result

The canonical release source is commit `533bc6339fe4c1d39a0d95157ca216c281164b89` (`Derive release package checks from the source manifest version`). All 52 files listed in `tasks/maintenance-2026-10-01/protected-before.json` still match their recorded SHA-256 values. The separate Mind Poisoning authoring checkout retains the same dirty/untracked paths recorded in the 0.1.7 preservation review. The historical PCV 0.1.7 tag refs and peeled targets match their previously recorded objects in both canonical and collection repositories.

No protected product file, Mind Poisoning authoring checkout, or release reference was changed. In the separately authorized hub-doc pass, `docs/development.md` was corrected from an obsolete PCV 0.1.6 migration pointer to the current direct 0.1.8 replacement; that did not touch the protected checkout or this hash inventory.

## Protected-file hashes

I parsed the captured inventory, checked each path exists, and recomputed its SHA-256:

```text
protected_count=52
protected_mismatch_count=0
protected_sha256=all_match
```

The hashes cover only the 52 listed Mind Poisoning source/test files and pinned PCV fixture files; they are not a byte inventory of every file in that checkout.

## Protected Mind Poisoning checkout

The checkout remains on `work/mind-poisoning` at `74ca8c97d30045822170477aad87824fb39b8222`. `git status --short --untracked-files=all` exited 0 and returned:

```text
 M plugins/private_conversation/tasks/logging-improvements-plan.md
?? critique.md
?? distribution/submission/discord-message.md
?? tasks/catalog-submission.md
?? tasks/drama-llama-desktop-night.png
?? tasks/drama-llama-mobile-day.png
```

This exact path set matches the current-set snapshot in `tasks/release-0.1.7/preservation-review.md`. That prior report also records that the original maintenance-time `git status` pathname list was not saved alongside the SHA inventory; therefore exact equality to that earlier unsaved porcelain list cannot be independently recomputed. No cleanup or checkout operation was performed.

## Historical 0.1.7 refs

The local refs and read-only GitHub API reads returned the same annotated tag objects and peeled commits as the prior release record:

```text
canonical: refs/tags/private_conversation-v0.1.7 tag 2a99d557aaf1859688d964306eb8390aeeeac4e6 peeled=d8f79c6059ac9184c5117c26a0514e18aae76902
collection: refs/tags/private_conversation-v0.1.7 tag fc9d08de68ef52ddf8501a3a5a0cd89005469544 peeled=98ed33b4169b4bc3e29835fdc4d710e83e6c2c1a
GitHub API objects: canonical tag 2a99d557aaf1859688d964306eb8390aeeeac4e6; collection tag fc9d08de68ef52ddf8501a3a5a0cd89005469544
```

These match `tasks/release-0.1.7/preservation-review.md`. No historical tag was moved, recreated or deleted.

## Scope and limits

This report is the sole artifact added by the preservation review. The separately authorized hub-doc pass made only the `docs/development.md` current-upgrade pointer correction noted above. No package build, new tag/release, commit, push, installation, live database/provider, or gameplay check was performed. The GitHub ref observation is a point-in-time read; the protected-file comparison is limited to the saved 52-path inventory.
