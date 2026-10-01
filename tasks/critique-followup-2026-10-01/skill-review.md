# CHIM skill addition proposal

## Recommendation

Add three short paragraphs to the existing platform and async references, via [`proposed.patch`](proposed.patch). These cover narrow gaps rather than repeating the skill's existing general guidance about hook stages, transaction revalidation, stale jobs and deployment access control. The lead approved this scoped update, including the preference for supported pre-delivery registration and out-of-webroot storage for confidential mutable files.

## Source support

- **Output/ACK ordering:** pinned-core inspection records `main.php:2919-2943` flushing the response before loading `prepostrequest.php`, and `_speech` processing/termination before later hooks in [`ack-handoff-investigation-2026-10-01/delivery-evidence.md`](../ack-handoff-investigation-2026-10-01/delivery-evidence.md), especially its request-order table. The current platform reference already warns that `_speech` exits before later hooks and that hook maps are not guarantees; the addition makes the concrete flush/ACK race actionable without promising recovery.
- **Separate lifetimes and locked pruning:** [`ack-bug-fix-2026-10-01/core-review.md`](../ack-bug-fix-2026-10-01/core-review.md) records a pending receipt that expired between lookup and claim, the requirement to compare the stored `created_at` at claim, and receipt admission revalidation under the same exclusive lock before reading, pruning or writing. It distinguishes the claimed provider's protection window from pending receipt TTL. The skill already says to re-read state under a transaction/lock; the proposed text clarifies that evidence freshness, unclaimed leases and claimed uncertainty are separate policies and that pruning itself needs current locked context.
- **Effective `.htaccess`:** [`maintenance-2026-10-01/final-review.md`](../maintenance-2026-10-01/final-review.md) records Apache 2.4.65 isolated HTTP checks with effective `AllowOverride All`, 200 for intended public assets and 403 for protected state paths; it explicitly limits the claim to that configuration. The proposed text converts that deployment-specific result into a reusable verification rule, not a claim that every deployment is protected.

## Applied change and verification

The approved additions were applied only to `references/platform.md` and `references/async-and-atomicity.md`; no skill entrypoint or other reference changed. Source support remains bounded: the PCV evidence includes isolated tests and source inspection, not installed CHIM or gameplay proof.

| Reference | Before SHA-256 | After SHA-256 |
| --- | --- | --- |
| `references/platform.md` | `408243603F52C543A2A10BBCF458A1EDA337E3A2889B008E60B3EDC10E2ED7DA` | `A2D77DA698BFA6990463218F27BF3334314404F13AB00777ABDA5E0BC2478B88` |
| `references/async-and-atomicity.md` | `47D191774C5A78ABDD1CF216897465D9BC9F87E5FA5F40E8027F2BA036359511` | `6ADF50788AFB6F25C80D5D06465714025C70FF9B48B5CD2E4510B55A8D3C4C79` |

The scoped diff check removes only the three approved paragraphs in memory and re-hashes each reconstructed file; both hashes must exactly match their recorded pre-edit values. Trailing-whitespace checks passed. No pressure-test scaffold was added; verification was a bounded source/document cross-check.
