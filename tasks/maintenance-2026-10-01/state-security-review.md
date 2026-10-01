# HTTP state protection review

## Change

Added an extension-root `server/.htaccess` rule that returns 403 for requests whose mapped path contains a `state` directory. It uses Apache 2.4's `<If>` and `Require all denied` with `REQUEST_FILENAME`, so it covers the mounted extension path, mixed-case paths, and state descendants without `mod_rewrite`. The manifest and assets remain outside that path.

The package builder now includes `.htaccess` in its explicit server payload allowlist. The schema-4 manifest still declares `mutable_paths: ["state"]`; runtime state is neither moved nor packaged. The minimal `.gitignore` excludes generated `dist/` and Python `__pycache__/` output.

## Consumer and upgrade evidence

Read-only inspection of installed HerikaServer commit `cf5030f15781637498be86debe26fcf102f5690d` found schema-4 validation in `lib/plugin_package_manager.php` and activation through `activateServerComponent()`. Before swapping the extension tree, the importer copies each declared mutable path from the old tree into the staged replacement. The new extension root is then moved into place. This preserves `state/` while replacing the extension-root `.htaccess` from the package on updates; putting the rule inside `state/` would couple its delivery to mutable-state preservation.

The importer accepts ordinary dot-prefixed file names such as `.htaccess`; its path checks reject unsafe path segments (`.` and `..`), not this filename. The installed Apache is 2.4.65 with `authz_core_module` loaded. The isolated configuration and inspected web-root directory rule use `AllowOverride All`.

## Verification

- **RED:** Before adding `server/.htaccess`, the isolated fixture returned 200 for the synthetic `state/state.json` while manifest, CSS, and JavaScript also returned 200. The fixture exited 1 on the exposed state file.
- **HTTP GREEN:** `wsl.exe -d DwemerAI4Skyrim3 -- python3 -u /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/http_state_protection_check.py /usr/sbin/apache2 /usr/lib/apache2/modules` exited 0. A separate Apache process used an ephemeral loopback port and temporary config, logs, document root, and synthetic files. Manifest/CSS/JavaScript returned 200; the state directory, JSON, lock, temporary file, registry, mixed-case path, and percent-encoded-character path returned 403. Apache returned 404 for an encoded slash. No fixture request used live state data.
- **Package:** `python tests/package_check.py` exited 0: 4 tests passed. Checks cover exact `.htaccess` bytes in the DWPkg and extracted repository tar; the MO2 member is byte-identical to the verified DWPkg. Existing membership, checksum, CRC, deterministic-build, and schema-4 mutable-state assertions passed.
- **Whitespace:** `git diff --check -- scripts/build-package.py tests/package_check.py` exited 0.

## Limits

The HTTP result proves the rule under the isolated Apache configuration, not a live plugin installation or in-game behavior. Deployments must use Apache 2.4 with `mod_authz_core` and permit the extension's `.htaccess` file (`AllowOverride All` was the observed setup). Apache with overrides disabled, another HTTP server, or a different authorization configuration needs an equivalent server-level denial; this package does not claim protection there. Public-resource checks use synthetic static files and do not execute the PHP UI.
