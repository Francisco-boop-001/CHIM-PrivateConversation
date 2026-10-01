# Critique review and operator-checklist handoff

## Judgment

The critique is right about the evidence gap: fixtures and source inspection do not establish that the client emits the expected listener/ACK, that audio behaves as intended, or that a full scene works in Skyrim. A short, repeatable test on an isolated server with its own database and client is the useful next gate. The attached suggestion to back up and test restore is also sound; without a restore drill, rollback must remain **unverified**.

The critique's smaller-feature and maintainability suggestions are valid questions for a separate design review, not changes silently folded into this acceptance task. This checklist does not introduce or promise a separate opinion-effects switch. State location and HTTP protection are also separate deployment questions; this gameplay checklist does not certify either one on an installed host.

The 60-second check is deliberately observational. Development source distinguishes unclaimed-registration supersession from the broader registry freshness/claim lifetime. The README snapshot inspected for this review still described a 600-second busy period for a fresh unresolved registration; that wording is being reconciled by the documentation owner. `reflection.output_registered` carries a new registration/correlation, but no UI field directly exposes the private lease status. Do not claim supersession unless diagnostics establish the prior registration was unclaimed and the new registration was accepted; otherwise mark that subcase **NOT RUN**. Do not create a synthetic failure or infer a lease transition from speech alone.

The checklist is a development follow-up, not a test result, release note, or claim that later working-tree fixes are present in the published 0.1.7 tag. It does not authorize live installation, database cleanup, or code simplification.

## Sources checked and limits

- Attached critique: `L:\Proyectos\Snake\codex_home_test\attachments\0c9d46be-1819-4170-aa6d-63a0503fd3b1\Pasted text.txt`.
- README working-tree snapshot checked on 2026-10-01: [direct scene and END behavior](../../README.md#direct-your-first-scene), [solo reflection and Mind Poisoning](../../README.md#solo-reflection-and-mind-poisoning), and [limits and verification](../../README.md#limits-and-verification). Each anchor matched its heading. UI text checked in [`server/index.php`](../../server/index.php): **Solo reflection**, **Reflecting NPC**, **Exclude the player**, **Other NPCs**, **Exclude from this conversation**, **Present but silent**, **Arm or update on next input**, **End now**, and **Operational logs**; the checklist uses these labels.
- Source timing cross-check: `server/state.php` defines 15-minute pending, 60-minute active and 45-second presence windows; `server/reflection.php` defines a separate 60-second registered-output supersession threshold. This is source inspection of the development tree, not a live timing measurement.
- Supported removal boundary: the CHIM plugin release checklist cites the Plugin Manager Delete handler at core revision `cf5030f15781637498be86debe26fcf102f5690d`, `ui/server_plugins.php`, and distinguishes extension-file removal from database/migration cleanup. This task did not execute that handler or modify an installed server.
- No checklist item has been run. There is no installed-client, actual audio, provider, live database, restore, uninstall, or gameplay result in this review. The operator procedure intentionally avoids direct production commands and manual ledger/table edits.
