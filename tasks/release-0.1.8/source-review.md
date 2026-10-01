# Release 0.1.8 source and staging review

## Finding

The current 24-path index is a focused release candidate: accepted runtime and test changes, user-facing release metadata, and linked review evidence. I found no unexpected product or builder payload in that set. Add this report as the 25th explicit path if it is to be retained with the release evidence; do not stage by wildcard.

Snapshot: branch `fix/scene-scope-and-diagnostics`, `HEAD` and `origin/main` both `82b0b84c34f317213a2b81df801ef3af4871ada4`. The `private_conversation-v0.1.8` tag is absent at this check. The manifest says version `0.1.8`, schema 2, development candidate, and uses a version-substituted release-asset URL. The release note and operator/README material describe a candidate and its validation limits; they do not claim gameplay, live database, or provider proof. Metadata owner reported the final metadata checks passed. A clean-source export/package gate and any release publication are still separate steps.

## Verified payload

Current SHA-256 values match the accepted implementation/test reviews:

| Path | SHA-256 |
| --- | --- |
| `scripts/build-package.py` | `053860A61E388EC2B4F062C6B98C1E387EABBC3E8DEE73BCAA60F7C710558D0F` |
| `server/log.php` | `76D7BE47ED7104AF391012B5C5DC491C700A95F7626E51A62DC0E29867BB9F7F` |
| `server/reflection.php` | `A2BC64BE75E377BCA078F45C6C3907D1F39EAB42344FA38F3AAE3ECC9FE5FC17` |
| `server/scope.php` | `F60519E72C7281E2BDAB81D78C5C873EC264EBEA3EFFEAC6D97B791428AED3C0` |
| `server/state.php` | `CE6E9E660E7F491AE51AD000CB86AFEB60E132AB894A7D9DC1D02B81E4E11F6B` |
| `tests/http_state_protection_check.py` | `C6A4A632829B9E19086664772161AD996FFC25770CE3FEE2BE117025C90A8007` |
| `tests/log_check.php` | `DAD6D2D36D8B114200DF68F8CCDB66C441F84F6F6B77A1A76F3F54A82D203D13` |
| `tests/reflection_registry_check.php` | `CD363311EE835DB53856DA0395AE67FD12DF938068F0081777CB7438973814E4` |
| `tests/scope_check.php` | `7401E86C2C14959E48650D1A04FC02DFE8BE0353B0CB9C0E4664C1E048822DFD` |
| `tests/state_check.php` | `55399327175037DFC75405AA5462D6619E528724EC737B1CD6466D79AC10084F` |

The package builder is unchanged from `HEAD`. The cached diff has no whitespace errors. This review did not rerun behavior or package suites; see the linked final and component reviews for their recorded gates.

## Explicit staging allowlist

The reviewed cached index contains exactly these 24 paths:

```text
README.md
distribution/private_conversation-v0.1.8.md
docs/logging-revision-2.md
docs/operator-acceptance.md
server/log.php
server/manifest.json
server/reflection.php
server/scope.php
server/state.php
tasks/critique-followup-2026-10-01/ack-review.md
tasks/critique-followup-2026-10-01/final-review.md
tasks/critique-followup-2026-10-01/log-review.md
tasks/critique-followup-2026-10-01/operator-review.md
tasks/critique-followup-2026-10-01/plan.md
tasks/critique-followup-2026-10-01/proposed.patch
tasks/critique-followup-2026-10-01/skill-review.md
tasks/critique-followup-2026-10-01/storage-review.md
tasks/release-0.1.8/plan.md
tasks/todo.md
tests/http_state_protection_check.py
tests/log_check.php
tests/reflection_registry_check.php
tests/scope_check.php
tests/state_check.php
```

Add only `tasks/release-0.1.8/source-review.md` if preserving this provenance note. The critique `proposed.patch` is included because the review evidence references it. Do not add absent reports by assumption; any later release-specific report needs separate review and an explicit path.

Keep out the untracked `before/` trees, `source-before.json`, `final-scope-check.json`, protected snapshots, stale/earlier task plans, temporary database or Apache copies, `dist/`, and unrelated work. No `.gitignore`, `.gitattributes`, `.htaccess`, builder, package-check, or Mind Poisoning files belong to this candidate set. The independent hub/preservation check remains its own release gate.

## Package-check follow-up

The release gate later exposed that `tests/package_check.py` still pinned generated DWPkg/MO2 names and the manifest version to 0.1.7. It now reads `server/manifest.json` once as its independent expected-version source and uses that value for archive names, the manifest assertion, and the MO2 member path. Add `tests/package_check.py` as an explicit release staging path; this supersedes the earlier sentence above excluding package-check files.

The updated test SHA-256 is `2871C93C555123636EB170C040D2E13B41BD386BBDC8F52DDB0964F866792146`. Python AST parsing, a scan confirming no remaining literal `0.1.7` expectation in the test, and `git diff --check -- tests/package_check.py` passed. I did not rerun the package gate; the packager will run it from the next clean source commit.

## Evidence referenced

The accepted behavioral and source findings are in `tasks/critique-followup-2026-10-01/final-review.md` and its ACK, storage, logger, and operator subreviews. The only fresh checks for this staging audit were `git diff --cached --name-only`, `git diff --cached --check`, the listed SHA-256 calculations, and the branch/HEAD/origin/tag queries. No install, publication, live database, provider, or gameplay test was performed here.
