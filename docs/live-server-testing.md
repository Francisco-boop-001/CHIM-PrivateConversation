# Live-server testing on a disposable CHIM clone

This is the procedure used on 2026-10-02 to test PCV against a real CHIM/HerikaServer and a real
player database without touching the gaming server. A simulated game client sends the same HTTP
requests the Skyrim plugin sends. Use it before asking anyone to test in game.

**What this proves.** Install through CHIM's real package installer, real hook order, real database
writes, real prompts and model output, state/presence handling, logging and uninstall.
**What it does not prove.** The exact client payloads (they are built from the client source and the
parsers), audio, timing of real playback and in-game feel. Record results as *live-server, simulated
client* evidence — never as gameplay evidence.

## 1. Safety rules (non-negotiable)

- The gaming server distro (`DwemerAI4Skyrim3`, disk `D:\DwemerAI4Skyrim3\ext4.vhdx`) is **never** started,
  read or written during a test. In WSL even a read starts the distro, so run no command against it.
  After testing, its `ext4.vhdx` LastWriteTime must be unchanged.
- All WSL2 distros share one network address. Never run the clone while the gaming server runs: whichever
  starts second loses ports 8081/5432, or the game silently talks to the clone.
- Every helper script refuses to run unless `WSL_DISTRO_NAME` is the test distro (`PCV_TEST_DISTRO`,
  default `DwemerAI4Skyrim3-test`).
- Do not run `/etc/start_env` in the clone: it starts TTS servers, may auto-update and opens a dashboard.
- Keep the export tar (`H:\DwemerAI4Skyrim3-<date>.tar`) as the backup of the gaming server.
- This applies to **every** tool and agent on the machine, including other AI sessions. On 2026-10-02 another
  agent ran `wsl.exe -d DwemerAI4Skyrim3 -- php …/tests/*.php` to borrow the server's PHP, which booted the gaming
  distro repeatedly (07:58–10:26) and wrote to its disk. Use the test distro for any PHP run.
- Check after a session: `(Get-Item D:\DwemerAI4Skyrim3\ext4.vhdx).LastWriteTime` must be unchanged. If it moved,
  `Get-WinEvent -LogName Microsoft-Windows-VHDMP-Operational` hourly I/O summaries show when that disk was active.

## 2. Create the clone (one time)

```powershell
wsl --export DwemerAI4Skyrim3 H:\DwemerAI4Skyrim3-<date>.tar     # stops the gaming distro; ~136 GB
New-Item -ItemType Directory -Path "E:\WSL\DwemerAI4Skyrim3-test" -Force
wsl --import DwemerAI4Skyrim3-test E:\WSL\DwemerAI4Skyrim3-test H:\DwemerAI4Skyrim3-<date>.tar
```

Check free space first: the export target needs the full tar size, and the import target the same again.
`wsl --import` needs the parent folder to exist. Remove the clone with `wsl --unregister DwemerAI4Skyrim3-test`.

## 3. Start and stop the clone

```powershell
.\scripts\live-test\sim.ps1 -Script env.sh up        # postgres + apache only; disables background workers
.\scripts\live-test\sim.ps1 -Script env.sh down
wsl --terminate DwemerAI4Skyrim3-test               # always finish with this
wsl -l -v                                           # gaming distro must still read Stopped
```

`env.sh up` renames `service/start.sh` to `start.sh.disabled-for-pcv-test`, so CHIM cannot auto-start its
background processor (backgroundlife, dynamicprofile, middleterm, snqe… all make their own LLM calls).
`env.sh restore-workers` undoes it. The `relationship_system` worker can still run on its own; it
processed about one item per pair turn during testing (one cheap call each).

## 4. Cost control

- LLM calls happen only on generated dialogue. Heartbeats, page actions, installs and log reads cost nothing.
- Connectors are in the database, not `conf/conf.php` (which is empty): `public.core_llm_connector`,
  `public.core_profiles` (per-profile primary/secondary), `public.general_settings` (`RELLLM_CONNECTOR`,
  used by Mind Poisoning). On 2026-10-02: profile 1 = DeepSeek V4.1 Flash (id 54), MP = Gemma 4 31B (id 50).
- TTS is local (XTTS 8020 / OmniVoice 8021) and not started, so audio fails quickly at no cost.
- The Narrator has no connector in this database: a line routed to the Narrator produces no output and no call.
- Typical spend for the full checklist: about 7 dialogue calls plus a few relationship-worker calls.

## 5. PHP fixture tests with the clone's PHP 8.2

Windows has no PHP; use the clone (reads the repo through `/mnt/k/...`, fixtures use temp dirs only):

```powershell
.\scripts\live-test\sim.ps1 -Script run-php-tests.sh tests/state_check.php tests/scope_check.php
```

Not run by default: `reflection_ack_database_check.php` and `reflection_ack_shutdown_check.php` need a guarded
disposable PostgreSQL target (`PCV_ACK_TEST_*`). `ui_preview.php` renders a preview, not a test. (`page_check.php`
failed through 0.1.8 because it still expected the older END wording; fixed in 0.1.9.)

Cases 12–15 (added for 0.1.10) reproduce real-client behaviour the first in-game session exposed: a partner leaving
the close range mid-scene while the wider `infonpc` report still lists them (12), a heartbeat gap whose next report is
only a baseline (13), an ACK for an early line while the reply is still generating (14), and a solo reflection with its
subject standing nearby (15).

For a whole live matrix with an AI-call budget, use `scripts/live-test/standard.py` (all cases) or
`standard.py 11` (selected case numbers); it stops before `PCV_STANDARD_BUDGET` calls (default 60).

## 6. Install through CHIM's package installer

```powershell
.\scripts\live-test\sim.ps1 -Script install_pkg.py /mnt/k/.../private_conversation-X.Y.Z.dwpkg private_conversation X.Y.Z <full sha256>
```

The helper verifies the hash, then uses `ui/api/plugin_packages.php` (probe → start-upload → 1 MiB chunks);
installation is synchronous and the last chunk returns the finished job. Facts observed:
- A same-version upload is accepted and replaces the payload: never reuse a version for a different build.
- Plugin Manager **Delete** (`POST ui/server_plugins.php delete_plugin=private_conversation`) removes
  `ext/private_conversation` but the package ledger still reports the version as installed/current.
- Mind Poisoning must be installed the same way (its own dwpkg) for opinion paths.

## 7. The client protocol (what the simulator sends)

- **Requests:** `GET /HerikaServer/comm.php?DATA=<base64("type|ts|gamets|data[|field4]")>[&profile=<md5(npc name)>]`.
  The raw base64 is not URL-encoded (core reads the raw query string). `&profile=` selects the responding NPC
  exactly as the client does; without it an input goes to The Narrator.
- **Clock:** the client `ts` runs at roughly 1e9 units per second (same clock as activity timestamps). The
  simulator uses `1.9e15 + elapsed ns`, above every historical `eventlog.ts` (core aborts a request if a later
  `user_input` already exists). `gamets` is any increasing value above the history.
- **Presence heartbeat:** `infonpc_close` with data `Name/Name//Player` (real reports contain empty tokens,
  repeated generic NPCs and `(busy)`-style suffixes). Then `POST /HerikaServer/gamedata.php` with
  `{"type":"activity_status_bulk","statuses":[{"actor_name":…,"timestamp":ts+δ,…}]}` — activity is reported
  just *after* the heartbeat, as in live data. Heartbeats arrive every ~10 s in game: send two about 10 s
  apart (the first is only a baseline) and never leave a gap over 45 s before a check.
- **Input routing snapshot (field 4):** base64 JSON `{"source":"plugin_player_routing_v2","speech_mode":"standard",
  "execution_mode":"STANDARD","target_mode":"direct","listener":…,"audience_radius_units":…,"present_actors":[{form_id,name,distance,managed,creature}]}`.
  PCV uses it to validate eligibility at the moment a scene activates.
- **Speech acknowledgement:** `_speech` with JSON `{"speaker","listener","speech","utterance_id"}`. Per the client
  source (`tasks/implementation-2026-09-30/native-reference/SpeakManager.cpp` ~3262-3593): `speech` is the
  **subtitle** (not the phonetic field), and for a line whose listener is not an actor — such as the solo
  sentinel `explicit_disable_rechat` — the client reports the **player's in-game name** as listener.
- **Output lines:** `Speaker|ScriptQueue|subtitle/mood/listener/expression/phonetic/1/rechatTarget/utterance_id`.
  CHIM splits a reply into several lines (~125 characters each); only the last line's ID is PCV's registered one.
- **Plugin page:** `ext/private_conversation/index.php` with a cookie jar; ARM/END forms need the CSRF token.

## 8. Test data in this database (2026-10-02)

- Player `Hawke`; no active playthrough profile, so PCV uses its shared-server identity.
- Usable NPCs (profile 1): Lidia Sobieska `5124`, Aela the Huntress `2892`, Bruce Wayne `2905`.
  `Lydia` (5102) does not store activity updates. Nazeem is not in the catalog.
- The catalog contains NPC rows `Hawke` (5071) and `Dragonborn` (5072): relevant to Mind Poisoning's
  listener checks.

## 9. Checklist run (docs/operator-acceptance.md) and what to look for

| Step | Simulator commands | Expect in PCV logs (`sim.ps1 logs 20`) |
|---|---|---|
| 0 Eligibility | `heartbeat A B`, wait 10 s, `heartbeat A B`, `page` | `state.presence_observed` available; page lists A and B |
| 1 Baseline | `input "<NPC>" "text" A B` | `routing.request_skipped scope_off`; normal reply addressed to the player |
| 2 Pair | `arm pair <A_ID> <B_ID> exclude`, `input "<B>" "direction" A B` | `scope_activated`, `route: scene_direction`; A speaks, listener B, no `Player|` echo |
| 3 Solo | fresh heartbeats, `arm solo <A_ID> exclude`, `input "<A>" "direction" A B`, `ack A <player> "<final subtitle>" <final utt>` | `reflection.output_registered`, then an ACK/evaluation event (`scripts/live-test/solo_retest.py` automates this) |
| 5 End | `end`, then a normal input | `state.scope_ended`; next input `scope_off` |
| 6 Uninstall | Plugin Manager delete, then a heartbeat | no new PCV log lines |
| Crowd | `scripts/live-test/crowd_live.py` (replays a real 60+ token report) | page lists catalog NPCs |

**Smoke test:** `.\scripts\live-test\sim.ps1 -Script smoke.py` runs baseline chat, one pair turn with ACKs, one
solo reflection with ACKs and a duplicate-ACK failure case, and stops before `PCV_SMOKE_BUDGET` AI calls (default
12). It counts dialogue turns, Mind Poisoning model calls and relationship-worker items. First run: 8 calls.

Mind Poisoning writes its records through CHIM's `Logger` to `/var/www/html/HerikaServer/log/chim.log` (not
Apache's error log); its opinion ledger is `core_npc_master.plugin_extended_data->'mind_poisoning'` of the listener
(pair) or the reflecting NPC (solo).

Useful database checks (inside the clone, `psql -h localhost -U dwemer -d dwemer`, password from
`/home/dwemer/.pgpass`): `eventlog` rows with `ts > 1900000000000000` (type, data, people,
delivery_state, utterance_id) and `speech` rows by `utterance_id`. CHIM's own log is
`/var/log/apache2/error.log`. PCV logs are read with `diagnostics.php` as `www-data` (the simulator's `logs`).

## 10. Traps found the hard way

- An eligibility read in the same second as a heartbeat used to drop every NPC (fixed in 0.1.9).
- A heartbeat after a gap over 45 s is only a new baseline: eligibility is unknown until the next one.
- A refused ARM leaves the previous scene active, so the next "solo" direction runs as the old pair.
- Forgetting END turns ordinary player lines into scene direction.
- Directions are stored as `The Narrator: …` instruction rows; solo lines are stored as
  `(talking to explicit_disable_rechat)` and list the sentinel among `people`.
- Mind Poisoning only evaluates when its subject and listener checks pass; read its reasons in the
  PCV `reflection.evaluation_result` event (`source_reason`).
