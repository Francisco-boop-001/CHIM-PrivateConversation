# Free mode verification: 0.1.12, 2026-10-03

The disposable clone `DwemerAI4Skyrim3-test` (E:) ran with a simulated client while the gaming distro was stopped. The test package was `dist/test-0.1.12/private_conversation-0.1.12.dwpkg` (sha256 `f935d8ee1d93ec7e92d9d6f5b09c48267c3133859a011a8c77cc88cdb8b449fe`), installed through CHIM's package API.

**Crowd of nine, nearest first:** Lidia Sobieska, Aela the Huntress, Bruce Wayne, Brittanya, Sigrid, Elenor, Kara, Adrianne Avenicci, Marianne Avenicci.

## Results

| Case | Expected | Observed |
| --- | --- | --- |
| 20 free scene in a crowd | Nearest six become the members; nearest opens; members only | `state.scope_activated` with `member_count: 6`, `free_scene: true`. `request_prepared` with `opener_source: nearest` (Lidia). Witness list `|Lidia|Aela|Bruce|Brittanya|Sigrid|Elenor|`, so the three farthest were excluded. No outsider spoke. |
| 20 (rerun) | Addressed member answers the rechat | Opening 6 lines (Lidia), then a rechat prepared and answered by Aela (3 lines). Speakers were Lidia and Aela only, with the same six witnesses. |
| 21 free target opener | The player's direct target opens | Bruce opened, `opener_source: target`. |
| 22 free named opener | A named member beats the target | Aela opened while Bruce was targeted, `opener_source: named`. |
| 23 free too few | Stays pending | `state.scope_skipped scene_not_eligible`, and the request finished as `scene_not_eligible`. |

In the first case 20 run, the rechat finished without speech. CHIM's own log shows `Rechat: pre-roll determined 0 rounds — terminating` before any PCV hook ran. This is CHIM's random rechat pre-roll, invisible to plugins. The rerun then exercised the rechat path.

**AI calls:** 11 in total.
- First run: 8 (5 dialogue, 1 Mind Poisoning, 2 relationship).
- Rerun: 3 (2 dialogue, 1 relationship).

## Offline

- All 40 default PHP fixtures pass, including the new `free_config`, `free_activation`, `free_opener` and `free_ui` checks.
- All 11 browser-refresh cases pass.
- `package_check` passes.

## Not covered

- An in-game check with the real client.
- Real in-game distances. The simulator's distances follow list order.
