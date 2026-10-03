# Live in-game issues — PCV 0.1.9 (opened 2026-10-03)

Source: first in-game session after publishing 0.1.9, read through the plugin's own web logs
(GET/export only; nothing run inside the gaming distro).

## 1. Solo reflection produced no speech (`request_unobserved`)

- 09:25:24Z solo staged for Lidia Sobieska (5124); 09:27:33Z `inputtext` activated the scope;
  09:27:35Z `routing.request_prepared` (route `solo_reflection`, speaker 5124), then
  `routing.request_finished` outcome `unobserved`, reason `request_unobserved`, phase `shutdown`.
- About 2 s from input to shutdown: too fast for a model reply. No `reflection.output_registered`.
- 09:28:20Z a follow-up `rechat` was blocked correctly (`solo_rechat_unsupported`).
- The same pattern appeared once on the test clone (Standard run, case 2, second rechat: 0 lines).
- Cause is downstream of PCV context preparation: CHIM core or the AI provider ended the
  request without output. Not yet diagnosed; CHIM's own log is needed.

To take care of:
- Find the cause: provider error/credits/rate limit, CHIM dropping the reply, or a hook failure
  after `request_prepared`. Needs CHIM's log for that request (user decides how to obtain it).
- PCV should make this visible: today the player sees silence and the page still says
  "Active". Consider recording the last request's outcome on the plugin page (e.g. "last
  reflection produced no speech") and logging what PCV can observe at shutdown (output length,
  CHIM error marker) so the cause is identifiable from PCV logs alone.

## 2. Logs page refused from the player's browser (`ui.diagnostics_rejected access_denied`)

- 09:28:48Z `operation: logs_read`, `source: browser`, rejected `access_denied`.
- The same page read from 127.0.0.1 on the gaming PC worked.
- Likely the browser reached the plugin through a non-local address; `pcv_ui_logs_access_allowed`
  only admits requests it treats as local. Check which address the Plugin Manager link uses
  and whether the rule is too strict for a normal single-PC setup, and explain the refusal on
  the page instead of failing silently.

## Retry (09:34Z): full chain worked

- 09:34:45Z prepared; 09:35:09Z ACK for an early line arrived while the reply was still generating
  (`reflection.ack_skipped registration_missing`); 09:35:19Z `reflection.output_registered` and
  `postrequest_observed` (request took ~34 s); 09:35:33Z Mind Poisoning model valid (2.5 s),
  persistence committed, **zero change**.
- So issue 1 is intermittent: a working request takes ~34 s, the failed one ended in ~2 s.

## 3. Real-client timing: early-line ACKs log as `registration_missing`

- The real client plays and ACKs lines while CHIM is still generating; registration happens only at
  postrequest, after the whole reply. ACKs for earlier lines therefore arrive before any
  registration and log `reflection.ack_skipped registration_missing`, which reads like a failure.
- The simulated client ACKed only after the full response, so the live-server runs never showed
  this. Harmless today (the final-line ACK still evaluates), but noisy and misleading.
- To take care of: classify pre-registration ACKs of the in-flight solo request as a quiet
  "pending" outcome (or rely on early-ACK receipts) instead of `registration_missing`; extend the
  simulator to ACK lines before the response completes.

## Evidence source added 09:50Z

User granted read-only access to the live distro ("you can LOOK at, but never mess with").
Read with `wsl -d DwemerAI4Skyrim3 -u root -- bash scratchpad/live_look*.sh` (SELECTs on
`public.eventlog`, `public.speech`, `core_npc_master`; grep/tail of `log/chim.log` and the Apache
error log). Nothing was written. Live dialogue connector: ID 68, `openaijson/glm-5.3-flash`.

## 4. Pair rechat dropped after a long first reply (`scene_not_eligible`) — fix first

- 09:44:37Z pair armed (A Lidia 5124, B Bruce 2905, player excluded); Lidia spoke 4 lines
  (09:44:51–09:45:24). 09:45:29Z the rechat for Bruce's turn was skipped: `state.scope_skipped`
  / `routing.request_skipped` `scene_not_eligible`. Bruce never answered.
- Same pattern at 09:30:59Z (rechat skipped, presence `presence_baseline`).
- Likely cause: during long speech the real client sends fewer `infonpc_close` heartbeats, so
  the presence evidence ages past 45 s (or the first report after the gap is only a baseline).
  The rechat then fails the eligibility check. The simulator sent heartbeats every 9.5 s
  regardless of speech, so the live-server runs never saw this.
- **Cause found (09:58–10:02Z rerun):** the heartbeat-gap guess was wrong. Heartbeats kept
  arriving every ~9 s during speech. Instead, the `infonpc_close` reports genuinely omitted the
  partner. At 09:45:22 and 09:45:31 Bruce was absent, and the rechat at 09:45:29 fell between
  them. In the rerun Bruce was absent 09:58:28–09:59:59 and Lidia intermittently; the user
  confirmed "I followed Lidia. But Bruce did move farther away". Bruce was back from 10:00:08,
  the rechat at 10:01:27 was accepted (`rechat_clamped`), and Bruce answered with 5 lines.
- So the reports are accurate: NPCs wander during scenes, and PCV requires both to be in the
  close report at the moment of each turn. This is a design question, not a client bug.
- Options for the user:
  - (a) Grace period: an actor present at activation stays valid for N seconds (e.g. 60) of
    absence during an active scene.
  - (b) During an active scene, accept presence from CHIM's wider `infonpc` "beings in range" list
    (it still listed Bruce).
  - (c) Keep it strict.
  Recommended: (a) or (b), keeping the fail-closed rule for activating new scenes. Extend the
  simulator to drop the partner from heartbeats mid-scene.

## 5. Solo reflection addresses an NPC who is physically present

- Solo lines at 09:35 and 09:42 were spoken *to* Bruce ("Bruce. You. You're s'till here.",
  "Jus' stay. Don't go."), the subject of the reflection, who stood nearby. They did not address
  the player. The client logged the listener as `Hawke` (`public.speech`), the known solo-listener
  behaviour.
- Bystanders were excluded from the scoped audience, but the direction itself named Bruce and he
  remains in history and memories.
- To take care of: consider strengthening the solo guidance ("think aloud; do not address anyone
  present, including the person you are thinking about") and test whether the model complies.

## 6. Pair always opens with A (user expectation)

- 09:46Z direction "What does bruce reply to Lidia?" in the Lidia(A)+Bruce(B) pair produced Lidia
  again (5 lines). Working as designed, since A opens every scene input, but surprising in play.
- To take care of: say it on the plugin page next to A/B ("A speaks first on every input; B
  answers through rechat"), and possibly offer "swap A/B". Depends on issue 4 for B ever to answer.

## Observations outside PCV (for the user, not PCV bugs)

- Voice: XTTS `http://127.0.0.1:8020/tts_to_audio` returns HTTP 500 for Lidia's voice
  (09:29:53Z, 09:34:52Z, 09:41:49Z, 09:44:44Z, 09:47:20Z); CHIM falls back to `femalenord`.
- Mind Poisoning: 09:35 reply registered in full (3 lines: events 1008488, 1008498, 1008502),
  verdict zero change on Bruce. 09:42 reflection `evaluation_skipped` (probably
  `reflection-basis-duplicate`; no new information about Bruce since 09:35). Lidia → Bruce is +7
  on the live server.
- CHIM relationship worker: `[REL-ASYNC] ABANDONED after 3 retries: NPC Bruce Wayne ...
  NPC(s) not found` (09:28:21Z), for Bruce → Solitude Guard (a generic NPC without a profile).
- Issue 1 (09:27 silent attempt): no Lidia row exists in `eventlog` for that request, and
  `chim.log` shows nothing at 09:27:33Z except an inventory update. Still unexplained; the Apache
  error log has no timestamps, so the next silent case should be read right after it happens.

## Session 10:09–10:14Z (both NPCs kept close by the user)

- 10:09:45Z direction → Lidia 9 lines; 10:11:28Z rechat (`rechat_clamped`) → Bruce 7 lines;
  10:12:34Z rechat (`rechat_clamped`) → Lidia 9 lines. Both NPCs were in every close report
  except one (10:11:32Z, Lidia absent). The pair works when both stay in range.
- **Issue 1, partly explained:** 10:14:26Z rechat ended as PCV `request_unobserved`. CHIM's log
  shows a deliberate stop: `Rechat: pre-roll budget exhausted (2/2) — terminating` /
  `[RECHAT_COUNT] exhausted speaker=Bruce Wayne ... used=2 budget=2`. So `request_unobserved`
  covers CHIM's intentional terminations as well as failures. The 09:27Z case was an
  `inputtext`, not a rechat, and stays unexplained.
  - To take care of: distinguish "CHIM ended the request without output by design" from "failed"
    where PCV can observe it, or at least document that `request_unobserved` after a rechat
    budget is normal.
  - Lead, not a finding: CHIM's `[LLM_RANDOMIZER]` switches each NPC between model slots
    (50% per request). A misbehaving slot could explain occasional silent replies. Next silent
    case: read the randomizer lines and the connector used at that moment.
- **Issue 4 variant:** 10:06:41Z rechat refused, `scene_not_eligible` with `presence_baseline`.
  It was the first close report after a gap of about 4 minutes (likely the user in a menu). The
  first report after a >45 s gap is only a baseline, so a rechat arriving at that moment is
  refused. Any fix for 4 should cover this too.
- Opinions now: Lidia → Bruce 14, Bruce → Lidia 14 (Lidia → Bruce was 7 at 09:35Z). I have not
  checked which system made these changes.

Status: open. Priority: 4, then 1 (needs a fresh occurrence), 3, 2, 5, 6.
