# Routing and immediate END review - 2026-10-01

## Result

Unstamped Standard events no longer inherit the stored private scene. `context_pre.php` returns when preprocessing has not stamped a valid request scope, and `pcvPairRoutedRequest()` no longer admits the fallback `generated_event` route. Ordinary private input, supported pair rechat/continuation and solo reflection still pass through their existing request-specific guards.

END now clears both active and pending state in the existing `pcv_stage()` path while it holds the store's exclusive lock. The existing CSRF and current-playthrough checks remain in front of that call. ARM uses the current nearby and CHIM-AI-active eligibility map and remains staged for the next eligible ordinary input. END does not require a fresh presence or catalog result to authorize the disabled state transition.

## Affected call paths and failure boundaries

- Ordinary input enters `preprocessing.php`, which resolves current identity and fresh eligibility, validates the selected speakers, and stamps the request scope. Player-excluded directions still become `instruction` only after exact player-prefix validation, then carry the selected audience without the player or bystander presence. Missing, stale, or unavailable eligibility still fails closed for this managed request.
- Pair `rechat` retains its selected-speaker clamp. Pair continuation remains admitted only through `pair_continuation` when the player is included. The generation hook still rejects a stamped request whose selected speaker is outside the pair. Solo reflection remains limited to its armed ordinary Standard input; it does not gain a rechat or generated-event route. These paths were left in place and remain covered by the scope fixture.
- A Standard event without a valid stamped scope now returns before reading persistent state, catalog rows, or cached presence. It keeps its ordinary profile, action settings, prompt context, audience, and speaker. This change does not make unrelated CHIM events private-scene requests or apply PCV restrictions to them. `context.php` and the custom JSON hook already return when there is no active request scope.
- END is still a same-origin POST validated by the existing session CSRF token and a current playthrough identity. The page may read presence to render the picker, but `pcv_form_can_stage()` permits a disabled config without a fresh presence result or even a current NPC catalog. `pcv_stage()` then rechecks the shared store under its existing exclusive lock, preserves wrong-key invalidation behavior, clears `active` and `pending`, and atomically writes the result. An invalid key leaves stored bytes unchanged; an unavailable/corrupt store returns unavailable rather than claiming success. ARM remains a pending write.
- Immediate END does not cancel an exchange or audio already delivered or in progress. The UI retains that limitation and points to CHIM's Stop All Dialogue control. The current reflection ACK path freshly reads scope before any opinion effect; after END that read returns off, so the existing scope match rejects an older solo ACK. The ACK owner's isolated registry check covers this cross-task interaction.
- The bounded parser review used the parent-identified installed core revision `cf5030f15781637498be86debe26fcf102f5690d`, read-only. `main.php` strips an instruction prefix using `preg_replace('/^[^:]+:\s*/', '', ...)` at line 1102. A player name containing `:` would therefore leave the remainder of the name in the instruction text. `pcvPrepareScopedInput()` now rejects that excluded-player rewrite with `invalid_input_prefix` and leaves the request unchanged; it does not change core or relabel the sender.

## Verification

All PHP checks used PHP 8.2.29 in WSL distro `DwemerAI4Skyrim3`. State and logger fixtures use isolated temporary directories.

| Check | RED evidence | Final result |
| --- | --- | --- |
| `tests/scope_check.php` | Before removing the fallback, the new unrelated-instruction regression exited 1 because the unstamped instruction inherited the active solo scene. After the colon-name regression was added, it independently exited 1 with `An excluded-player name containing a colon was rewritten ambiguously instead of rejected unchanged.` | Exit 0. Unrelated internal, stale-presence and narrator Standard events retain ordinary context with no private-state/presence lookup. Managed input, rechat, pair continuation, outsider speaker, solo, audience and action-schema assertions pass. |
| `tests/state_check.php` | Exit 1: `END clears active and pending state without another input status mismatch`; the old implementation left a disabled change pending. | Exit 0. Active plus pending state clears immediately, both JSON slots are null under the same key, invalid-key END leaves the file unchanged, and a later eligible input cannot revive the ended state. Existing state identity, expiry, corruption and log checks pass. |
| `tests/ui_check.php` | Exit 1 because the rendered control still said `End on next input`. | Exit 0. END is labeled immediately, retains CSRF and current-identity gates, is permitted when fresh eligibility/catalog evidence is unavailable, and preserves the in-flight dialogue/audio limitation. |
| `php -l` | Not applicable. | Exit 0 for `server/context_pre.php`, `server/scope.php`, `server/state.php`, `server/index.php`, `tests/scope_check.php`, `tests/state_check.php` and `tests/ui_check.php`. The final `server/index.php` lint was repeated after its END presence-read guard changed. |
| `git diff --check` | Not applicable. | Exit 0 for the owned source and test files. |

The commands below were run before and after the fixes; the table records the RED exit and failure text, then the final GREEN exit:

```text
wsl.exe -d DwemerAI4Skyrim3 -- sh -lc 'cd /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev && php tests/scope_check.php'
wsl.exe -d DwemerAI4Skyrim3 -- sh -lc 'cd /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev && php tests/state_check.php'
wsl.exe -d DwemerAI4Skyrim3 -- sh -lc 'cd /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev && php tests/ui_check.php'
```

The state test's logger-contention fixture pre-created `events.lock` with the process umask permissions and kept that invalid file after unlocking. The logger correctly rejected it, so the suite could not read an event file and exited before later assertions. The test now chmods only its isolated lock fixture to `0600` before exercising contention; product logger code was not changed. With that fixture correction, the complete state check exits 0.

## Limits

These are source and isolated PHP-fixture results. No live CHIM database, game, provider, or playback path was run. No live HTTP POST was dispatched; the END gate was checked through the existing form/state helpers and the routed source was inspected. UI may attempt a presence read for the rendered picker, but that result does not gate END. The report does not claim cancellation of in-flight generation/audio or a general privacy barrier. A disabled END record persisted by an older version remains readable and can still be applied by the legacy next-input transition until the user submits the new immediate END control.
