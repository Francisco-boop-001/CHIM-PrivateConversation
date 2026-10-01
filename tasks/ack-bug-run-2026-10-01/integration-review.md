# Early-ACK integration review

## Verdict

I found no demonstrated hook/bootstrap or uncaught-exception defect in the reviewed PCV paths. The inactive ACK fast path remains bounded to parsing the request and one shared local-state read; it does not reach playthrough lookup, current NPC resolution, SQL, the optional Mind Poisoning loader, or evaluation.

There is a confirmed diagnostic limitation for early ACK recovery across requests. The initial `registration_missing` event is intentionally uncorrelated when the request has only a validated scope/receipt. If shutdown cannot reconcile yet and a later generated-output request consumes the saved receipt, the resulting reflection events belong to a different logger request ID. The saved receipt can match an exact utterance, but that ID is not carried into the initial event. This matches the explicit unmatched-ACK privacy assertion in `tests/reflection_hook_timing_check.php:139-141`; I classify it as an observability limit and a documentation-precision issue, not a product defect under the current privacy contract.

## Hook, shutdown and bootstrap trace

`server/prerequest.php:24-40` routes `_speech` to the reflection hook and returns before ordinary PCV routing starts. It verifies the included file is present and not a symlink. The `try` at lines 28-35 protects `require_once reflection.php`; the guarded call to `pcvReflectionEvaluateAck()` at lines 37-40 is outside that `try`.

The evaluated path has local failure boundaries for the operations that can fail in normal service: `pcv_reflection_active_solo_precheck()` catches `Throwable` around state path/lock/read (`server/reflection.php:121-154`); interaction-generation capture catches around MP/core helpers (`:156-182`); fresh identity/scope resolution catches (`:354-375`); the identity DB lookup catches query/bootstrap errors and closes the valid connection in `finally` (`server/state.php:143-194`); registry probing catches (`server/reflection.php:1318-1334`); receipt persistence catches (`server/reflection_receipt.php:225-289`); and the direct registered-record evaluator path has catches around optional API loading and store/evaluation (`server/reflection.php:75-91`). The queued shutdown closure also catches reconciliation failures (`:200-209`). I found no remaining real helper on this path that is known to throw outside a catch. The unwrapped call is still a defensive boundary worth recognizing, but source review did not establish a reachable failure that escapes it.

The delayed callback retains the original game request and validated receipt in a closure and calls reconciliation under a `Throwable` catch (`server/reflection.php:200-209`). A source-generation request can independently recover the same receipt from `pcv_reflection_register_with_store()` (`:648-650`) through `pcv_reflection_reconcile_registration()` (`:655-677`). This is what allows either arrival order to progress without rewriting globals or constructing a fake game request.

The prepost hook is request-scoped: it requires an active solo-reflection request and selected speaker (`server/prepostrequest.php:6-16`), disables only the normal relationship queue for that request, and restores its previous value at shutdown (`:18-30`). It checks the request mode again before registration (`:32-60`). It does not run for the `_speech` branch in `prerequest.php`.

`pcv_reflection_load_mind_poisoning()` checks for the compatible API already in memory and otherwise loads only the extension-relative Mind Poisoning reflection file, rejecting a symlink/missing file before require (`server/reflection.php:247-267`). Unmatched/out-of-scope ACKs return before this loader. A missing or incompatible API fails closed in direct evaluation; the delayed callback and source registration wrappers catch failures around their respective calls.

## Inactive-path work

After bounded `_speech` JSON/field parsing (`server/reflection_receipt.php:91-137`), the cheap active-solo precheck takes a shared lock and reads the shared state store (`server/reflection.php:121-154`). Missing, off, pair, pending, expired, malformed, or unreadable active state returns before the request identity/profile lookup, live scope resolution, SQL reader, optional MP bootstrap, or provider. A speaker mismatch also returns before interaction state and fresh scope checks (`:42-56`). Thus an unrelated/inactive `_speech` is not free, but its work is one bounded parse plus one local shared-state read. I found no call into presence polling or NPC catalog resolution on that fast path.

## Cross-request diagnostic limitation

The early ACK event is emitted from `pcvReflectionEvaluateAck()` with the `$scope` object, then reconciliation is queued (`server/reflection.php:95-118`, especially `:109-113`). `pcv_reflection_log()` sets correlation only when its argument validates as the full registration record; for a scope/receipt it clears correlation (`:281-303`). The emitted context contains only phase, route and actor ID (`:304-332`).

The logger request context is process-local and creates a new request ID lazily (`server/log.php:43-65`). If the ACK shutdown callback still sees no registration/native row, reconciliation returns `registration_missing` without a terminal event (`server/reflection.php:748-767`, `:793-795`). Later source registration can consume the receipt and log the effect with the full record, but that event has the source request's distinct request ID (`:648-650`, `:655-677`). There is no `linked_request_id` in the receipt or in that handoff. With several bounded receipts under the same scene/config, config, actor and time do not guarantee which early ACK line belongs to which later output/effect.

Counterevidence narrows this limitation: when the shutdown callback finds the matching record and completes evaluation in the ACK request, the entries share that request ID. A direct registered ACK and a registered-output event also carry validated event/utterance correlation. The missing link is specifically the accepted-early-ACK path that later reconciles from a separate output request.

The existing `tests/reflection_hook_timing_check.php:139-141` requires that an unmatched ACK utterance ID not appear in logs. The current logger allowlist can represent a validated utterance ID (`server/log.php:89-119`), but logging that ID here would change the accepted privacy boundary; I do not recommend doing so without an explicit policy change. Instead, the statement in `docs/logging-revision-2.md:43` that early `registration_missing` may be followed by a later result and users should “read the correlated reflection events together” should be qualified: that is possible for same-request or already-registered paths, while cross-request source recovery intentionally lacks a stable event link under the current unmatched-ID omission rule. No documentation or product edit was in my ownership for this audit.

## Evidence and limits

Source hashes inspected:

```text
server/prerequest.php         4653DCFEEA5B64CB17534DC3424D7D10DF1EF2D204816CE83C02FB8B87CF036A
server/prepostrequest.php     DA0DFCBEFFB047D2E1BDBE64F66DEA15E5B1B019FEAD4580600AABD7911908DB
server/reflection.php         7ADF7BFFABFA1DC85BB3134105233C828271A0A8DD8F5C40662503C9A8F9809B
server/reflection_receipt.php 5E651289635D013BFC83267D6E64E185859B01A0CAD2D1587433C7A53E4523B5
server/state.php              2387D8C39CFD1FC4FB6C5E3A94981C0A75E8C7617712CD310FB76F6F28C939E8
server/log.php                E890175B2A44856A22ED7345826410CB09587037271C5296FE2A44A9F9F8EB22
```

This is source-path analysis only. No new PHP fixture was added or run: the Windows shell has no `php` command, and `wsl -d DwemerAI4Skyrim3 -- php -v` was denied with `Wsl/Service/E_ACCESSDENIED`. I did not rerun the other owners’ isolated PHP/PostgreSQL checks. Their reported results demonstrate the specific fixtures they ran, not installed CHIM intake, live provider behavior, game audio/hearing, or all shutdown/runtime arrangements.
