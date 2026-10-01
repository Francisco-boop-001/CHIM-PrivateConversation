# Private Conversation 0.1.8 — PRE-ALPHA candidate

This candidate follows published 0.1.7 and keeps the existing CHIM compatibility reference and Mind Poisoning reflection API v1 contract. Scene direction does not require Mind Poisoning; optional solo opinion effects require compatible Mind Poisoning 0.1.14. The candidate remains PRE-ALPHA.

## Changes

- **Unclaimed registration lease:** a distinct output may replace an unclaimed registration after 60 seconds from its original registration time. Duplicate registration does not renew that time, and a late ACK for a superseded output cannot claim its replacement. The existing 600-second registry lifetime and claimed-effect protection remain; an exact direct ACK remains eligible until a newer output replaces its record.
- **Private runtime-state migration:** default state now lives under a validated per-install private temporary root, separate from `PCV_LOG_DIR`; the operating system may clean this temporary state. On first state resolution, not installation, a valid legacy extension-local state directory is moved whole with atomic `rename()` when the target is absent and both paths share a filesystem. Corrupt, unknown, unsafe or conflicting data is refused without merge, partial copy or reset. `EXDEV` is a fail-closed migration error: the legacy source stays in place, with no copy fallback.
- **Logger contention handling:** native nonblocking lock acquisition retries within one cumulative 100 ms per-request wait budget. On exhaustion, request health stays degraded and PCV attempts to send each already-sanitized bounded JSONL event line to PHP's configured `error_log`. This is best effort, is not shown by the Logs reader, and does not bound total I/O or promise durable/no-loss logging.
- **END evidence:** `state.scope_ended` records a successful END state change. It does not cancel an in-flight request or audio already delivered.

## Limits and upgrade conditions

Before updating, quiesce old PCV requests and keep HTTP access restricted through first startup. Migration runs on first state resolution. Until it succeeds, legacy extension-local state may remain HTTP-readable where server overrides are ignored; the package is not an independent access boundary.

The private state path depends on the effective PHP temporary root/namespace, worker UID and canonical extension path. Different HTTP and CLI temp namespaces (for example, a systemd `PrivateTmp` boundary) can resolve different roots even when UID and path match. If migration fails with `EXDEV`, leave the source and access boundary intact. Any manual recovery must use the HTTP worker's actual temp root/namespace, UID and install path and remain on one filesystem. A matching UID in a CLI shell alone is not enough. Do not merge, copy or reset live state.

Focused source and isolated fixtures do not establish installed CHIM behavior, PHP-FPM integration, live database/provider behavior, native playback or Skyrim gameplay acceptance. Mind Poisoning API v1 is unchanged. PCV adds no pair-correlation API and cannot guarantee model compliance, secrecy from other CHIM routes, or cancellation of work already committed or audio already delivered.

## Installation

Replace the old enabled PCV package; do not stack versions. This is CHIM-only server data and needs no ESP/ESL or Papyrus companion. Use the version-specific 0.1.8 candidate assets and verify the installed version and repository identity. See the [0.1.8 installation guide](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.8/README.md) for asset formats and update steps.
