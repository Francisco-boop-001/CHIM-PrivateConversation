# Group mode (0.1.11) clone verification — 2026-10-03 ~11:37–11:45Z

Test package `dist/test-0.1.11/private_conversation-0.1.11.dwpkg`, sha256
`bfb885db5f94f814cc575d582633ff30f0b9925050b2062646a18ef14c47d021`, on `DwemerAI4Skyrim3-test`
(the gaming distro was stopped). The scene used Lidia Sobieska (5124), Aela the Huntress (2892) and Bruce Wayne
(2905). 16 AI calls in total.

| Case | Result |
| --- | --- |
| 16 named opener | **Pass**. "What does Bruce think of the Bannered Mare's mead?" opened with Bruce (member C, not A); `opener_source: named`; he addressed Lidia. |
| 17 group rechats | **Pass**. Only members spoke (Lidia, Bruce) and every listener was another member; `active_agents` were clamped to the three. |
| 18 member missing at start | **Pass**. Armed with all three nearby; Bruce left before the direction; `state.scope_activated` `member_count: 2, dropped_count: 1`. |
| 19 member leaves | **Pass**. Bruce absent beyond grace and wide: `state.scope_members_dropped` `left_scene` (`member_count: 2`); the scene continued (4 lines). |
| Witness lists | **Pass**. Every scene line's `eventlog.people` was exactly `|Lidia Sobieska|Aela the Huntress|Bruce Wayne|` (no player, no bystanders), which is what Mind Poisoning will use for overheard gossip. |

Simulator corrections found during the run, none of them plugin faults:
- the first case armed after only one (baseline) heartbeat, so the startup wait is now 22 s;
- case 18 first removed Bruce before ARM, which the page correctly refuses because it only offers nearby NPCs; it now
  removes him between ARM and the direction;
- the input's routing snapshot used the full name list instead of the current heartbeat roster;
- rechats now use a fresh `chain_id` per prompt, so CHIM's rechat budget is not shared across prompts.

Cases 16 and 18 were rerun after these fixes. The figures above come from the reruns plus the first run's 17 and 19.
