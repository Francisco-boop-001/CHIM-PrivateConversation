# Private Conversation 0.1.7 — PRE-ALPHA candidate

Private Conversation directs selected NPC scenes through CHIM's ordinary Standard-mode path. This candidate keeps the existing CHIM compatibility reference and Mind Poisoning reflection API v1 contract. Scene direction does not require Mind Poisoning; optional solo opinion effects require compatible Mind Poisoning 0.1.14.

## Changes

- **Narrower scene routing:** an unrelated ordinary Standard event no longer receives private-scene identity, catalog or presence treatment merely because scene settings are stored. Managed scene guards and continuation checks remain in place.
- **Immediate END:** END clears active and pending scene settings without a fresh actor report or another input. An already-running generation or queued audio may still finish; this does not cancel delivered or in-flight work.
- **HTTP state protection:** the package includes an extension-root `.htaccess` denying HTTP reads of runtime state, locks and temporary files. The rule was verified with Apache 2.4, `mod_authz_core` and effective `AllowOverride All`. Other web servers and Apache configurations need an equivalent rule; this is not proof of the installed server's routing.
- **Cheaper unmatched-ACK diagnostics:** a validated negative state hint skips unnecessary identity and scope work for missing, unavailable, disabled, pair, pending-only or expired state. A positive hint never authorizes an effect; fresh scope, identity, registration and ACK checks still apply.
- **Bounded early-ACK recovery:** when a valid solo `_speech` acknowledgement arrives before output registration, PCV retains only bounded receipt metadata and reconciles it against the exact registration and a unique matching native `public.speech` row. A direct ACK with an existing registration keeps its direct path. No dialogue is stored in the receipt.
- **Fresh receipt admission:** duplicate reuse, pruning and writes recheck current active solo state and interaction epoch under the shared receipt lock. The original receipt timestamp is retained and checked at claim; a duplicate does not renew its age. This prevents a delayed stale admission from replacing a fresh receipt.
- **Clearer diagnostics:** routing completion remains distinct from later ACK reconciliation and reflection outcomes. Protected Logs reads and JSONL exports remain sanitized and manual; they do not poll or recover lost records.

Pending receipts are limited to **eight entries / 8 KiB total** and **45 seconds** from the original server receipt time. The separate effect registration/claim can remain active for up to **600 seconds**. Expired receipts are not refreshed by duplicate input; a later ACK is a new capture. An unresolved claim can keep another registration busy, including across END/rearm. Fresh checks prevent later stale effects but cannot undo a committed result.

## Limits

Only the final complete native output line is registered. A matching ACK proves a line attempt, not audio playback or hearing. A process failure before output registration or either reconciliation probe, an ACK that never reaches PCV, or a native row that stays invisible through both probes (for example, while an ambient transaction is uncommitted) can still lose recovery. There is no daemon or automatic retry for uncertain provider or commit results. Legacy version-1 registrations retain their historical direct-ACK path; the source/ACK generation check applies to version-2 registrations and recovered receipts. PCV does not add a pair-gossip correlation API or change Mind Poisoning's API-v1 behavior.

The focused source and isolated fixtures are not an installed CHIM, live database/provider, native playback or Skyrim gameplay test. The rules do not guarantee privacy from other CHIM routes, model compliance or cancellation of already-started work. PRE-ALPHA remains an accurate maturity label; the llama has not been appointed release engineer.

## Installation

Replace any older enabled PCV package; do not stack versions. This is CHIM-only server data and needs no ESP/ESL or Papyrus companion. Verify the downloaded asset checksum and the installed version/repository identity. See the [0.1.7 installation guide](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.7/README.md) for asset formats and update steps.
