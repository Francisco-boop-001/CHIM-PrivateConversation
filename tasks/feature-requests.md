# Feature requests

## From CHIM Discord (DemodiX [CHIM]), 2026-10-03, after testing 0.1.9

Verdict from the tester: "As proof of concept it worked pretty fine."

1. **Checklist of NPCs instead of A/B.** Choose the included NPCs; the direction decides who
   addresses whom.
   - Also resolves live issue 6 (A always opens).
   - Changes the clamp from "A and B" to "the checked set". CHIM rechat already carries
     `active_agents`, which PCV currently narrows to the pair.
   - Unverified: how CHIM chooses the next speaker from a larger `active_agents` set. Test on
     the E: clone first.
   - Open design point: who opens. Proposal: optional "opener" picker, defaulting to the first
     checked NPC.
2. **Three-person scenes feel wonky** (Stalleo plus his Treva's Watch bodyguards).
   - Depends on live issue 4: every participant must be in the close presence report at every
     turn, so the odds of a refused turn grow with group size. Fix 4 first (grace period or wider
     range during an active scene).
   - Also bounded by CHIM's rechat budget (2 per chain in the user's config).
3. **"Free mode" that only excludes the player.**
   - Same mechanism as 1, with the set being "all eligible nearby". Cheap once 1 exists, but
     noisier in crowded places.

Mind Poisoning: group listeners should be judged like today's B (listener owns the opinion).
Ask the MP agent to confirm nothing changes on its side.

Proposed order: live issue 4 → group mode (2–4 NPCs, optional opener; Pair becomes "two
checked") → free mode. Solo unchanged. Each step: failing test first, then a clone run with a
simulator that also drops participants from heartbeats.
