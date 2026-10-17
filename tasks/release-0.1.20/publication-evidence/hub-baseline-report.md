# Private Conversation 0.1.20 hub baseline

Captured 2026-10-16T23:33:19.060740+00:00 from the isolated hub checkout. This is a Git-object baseline only; it does not claim raw filesystem bytes or a live-host result.

## Current base

- Hub HEAD and local origin/main: 301dce06b7e0559180a38449aaba7ba4551ff4a4; tree 9012b1ecae0d4d07af9627a225bb611303ba4532.
- Branch/status: main, clean; HEAD matches origin/main.
- Expected parent-verified origin/main: 301dce06b7e0559180a38449aaba7ba4551ff4a4; local ref matches that anchor.
- Current plugins/private_conversation subtree object: dc76ae7ac1551622767135b47487b6b6118aaa43.
- Pre-.19 hub base: 3feee95f9754b05902da8b77efc7d8454f4fb754; tree 9c8873f77dd2917f1f9c5d198dfd85fb50a234ef.

No fetch was performed. Any fresh remote-main advancement must be rechecked before the hub is changed or published.

## Allowed shared-document paths

The prior .19 hub report names these three paths as the proposed PCV documentation allowlist:
- README.md — mode 100644, blob 347a39ea91f90c8957f1657184fe5354eaaf1708.
- docs/deployment-migration.md — mode 100644, blob ed9b8aa441efed252bc26a7992c2aa0fa086b77b.
- docs/development.md — mode 100644, blob 500f9368a38ac227692cb2769d6ed414c5eb88ba.

## Protected Git-object baseline

origin/main has 498 tracked leaf entries: 256 in the PCV subtree and 242 outside it. Of the outside entries, the three shared documents above are separated from 239 protected paths. The exact path/mode/object-type/blob records for all 242 outside entries are in hub-baseline.json.

Compared with the pre-.19 base, all 239 protected paths match by path, mode, object type, and blob ID: PASS (0 mismatches). This includes the hub-root Mind Poisoning source and all other non-PCV content. This is not a raw-byte or runtime claim.

## Release-source anchor and gate

Root supplied committed PCV source 85e9dd08c8bacbb6f1553e36b0d1f4c2caacf45e (parent b7030f259bf25101303f56c03090a060290992a5) and tree fd1a2ff48cfa65dbb5c4500a7fb364975e48fdcd (expected fd1a2ff48cfa65dbb5c4500a7fb364975e48fdcd; tree matches). Its commit changes 84 paths, matching the supplied 84-path gate. The intended release tag is private_conversation-v0.1.20; this report does not modify the hub, stage files, or authorize publication.

Before any hub update, revalidate the remote main tip, require the intended PCV release source/tag and exact path allowlist, and repeat the protected path/mode/blob comparison against this baseline. Keep Mind Poisoning changes protected.
