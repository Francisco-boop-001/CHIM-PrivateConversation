from __future__ import annotations

import argparse
import os
import pwd
import re
import shutil
import socket
import subprocess
import tempfile
import time
from pathlib import Path
from urllib.error import HTTPError, URLError
from urllib.request import ProxyHandler, build_opener


ROOT = Path(__file__).resolve().parents[1]
BASE = "/HerikaServer/ext/private_conversation"
PUBLIC_PATHS = ("manifest.json", "assets/style.css", "assets/ui-refresh.js")
STATE_PATHS = (
    "state/",
    "state/state.json",
    "state/state.lock",
    "state/.state-ABC123",
    "state/reflection.json",
    "state/reflection_receipts.json",
    "state/presence.json",
    "state/background_presence.json",
    "State/case.json",
    "st%61te/state.json",
    "state%2fstate.json",
)


def apache_quote(path: Path) -> str:
    return '"' + str(path).replace("\\", "/").replace('"', '\\"') + '"'


def main() -> None:
    parser = argparse.ArgumentParser(description="Verify state denial with an isolated Apache process.")
    parser.add_argument("apache", type=Path, help="Apache executable, for example /usr/sbin/apache2")
    parser.add_argument("modules", type=Path, help="Apache module directory")
    parser.add_argument("--php", type=Path, default=Path("/usr/bin/php"), help="PHP CLI executable")
    args = parser.parse_args()
    if not args.apache.is_file() or not args.modules.is_dir() or not args.php.is_file():
        parser.error("Apache, module directory, or PHP executable does not exist")

    private_state: Path | None = None
    with tempfile.TemporaryDirectory(prefix="pcv-http-") as temp_name:
        temp = Path(temp_name)
        webroot = temp / "www"
        extension = webroot / BASE.lstrip("/")
        shutil.copytree(ROOT / "server", extension)
        (extension / "state").mkdir()
        state_bytes = ('{"version":1,"key":"' + 'a' * 64 + '","active":null,"pending":null}\n').encode()
        (extension / "state/state.json").write_bytes(state_bytes)
        (extension / "state/state.lock").write_text("", encoding="utf-8")
        (extension / "state/.state-ABC123").write_text("interrupted staging fixture\n", encoding="utf-8")

        for path in (temp, webroot, *webroot.rglob("*")):
            os.chmod(path, 0o755 if path.is_dir() else 0o644)

        with socket.socket() as listener:
            listener.bind(("127.0.0.1", 0))
            port = listener.getsockname()[1]

        modules = args.modules.resolve()
        config = temp / "httpd.conf"
        config.write_text(
            "\n".join(
                (
                    f"ServerRoot {apache_quote(temp)}",
                    f"PidFile {apache_quote(temp / 'httpd.pid')}",
                    f"ErrorLog {apache_quote(temp / 'error.log')}",
                    f"Listen 127.0.0.1:{port}",
                    "ServerName 127.0.0.1",
                    f"LoadModule mpm_prefork_module {apache_quote(modules / 'mod_mpm_prefork.so')}",
                    f"LoadModule authz_core_module {apache_quote(modules / 'mod_authz_core.so')}",
                    f"LoadModule mime_module {apache_quote(modules / 'mod_mime.so')}",
                    f"TypesConfig {apache_quote(Path('/etc/mime.types'))}",
                    f"DocumentRoot {apache_quote(webroot)}",
                    f"<Directory {apache_quote(webroot)}>",
                    "    AllowOverride None",
                    "    Options None",
                    "    Require all granted",
                    "</Directory>",
                    *(('User www-data', 'Group www-data') if os.geteuid() == 0 else ()),
                    "",
                )
            ),
            encoding="utf-8",
        )

        check = subprocess.run(
            [str(args.apache), "-t", "-f", str(config), "-d", str(temp)],
            capture_output=True,
            text=True,
            check=False,
        )
        if check.returncode:
            raise RuntimeError(f"Apache config check failed:\n{check.stdout}{check.stderr}")

        process = subprocess.Popen(
            [str(args.apache), "-f", str(config), "-d", str(temp), "-DFOREGROUND"],
            stdout=subprocess.DEVNULL,
            stderr=subprocess.PIPE,
            text=True,
            start_new_session=True,
        )
        try:
            opener = build_opener(ProxyHandler({}))

            def status(path: str) -> int:
                try:
                    with opener.open(f"http://127.0.0.1:{port}{path}", timeout=2) as response:
                        return response.status
                except HTTPError as error:
                    return error.code

            for _ in range(50):
                if process.poll() is not None:
                    raise RuntimeError(f"Isolated Apache exited before readiness ({process.returncode})")
                try:
                    if status(f"{BASE}/manifest.json") == 200:
                        break
                except URLError:
                    time.sleep(0.1)
            else:
                raise RuntimeError("Isolated Apache did not become ready")

            before = status(f"{BASE}/state/state.json")
            print(f"RED: HTTP {before} {BASE}/state/state.json with AllowOverride None", flush=True)
            if before != 200:
                raise AssertionError("The pre-migration fixture did not reproduce the web-root exposure")

            php_command = [str(args.php)]
            expected_uid = os.getuid()
            if os.geteuid() == 0:
                service = pwd.getpwnam("www-data")
                runuser = shutil.which("runuser")
                if runuser is None:
                    raise RuntimeError("runuser is required to match PHP CLI and Apache worker identities")
                expected_uid = service.pw_uid
                for path in (extension, *extension.rglob("*")):
                    os.chown(path, service.pw_uid, service.pw_gid)
                php_command = [runuser, "-u", "www-data", "--", str(args.php)]
            migration = subprocess.run(
                [
                    *php_command,
                    "-r",
                    "require " + repr(str(extension / "state.php")) + "; "
                    + "echo pcv_state_directory(null);",
                ],
                capture_output=True,
                text=True,
                check=False,
            )
            if migration.returncode:
                raise RuntimeError(f"PHP migration fixture failed:\n{migration.stdout}{migration.stderr}")
            private_state = Path(migration.stdout.strip()).resolve()
            private_root = Path(tempfile.gettempdir()).resolve()
            if (private_state.name != "state" or private_state.parent.parent != private_root
                    or not re.fullmatch(r"private-conversation-" + str(expected_uid) + r"-[a-f0-9]{16}", private_state.parent.name)
                    or webroot.resolve() == private_state or webroot.resolve() in private_state.parents):
                raise AssertionError("The migrated state path did not resolve to the isolated private temp root")
            if (
                (extension / "state").exists()
                or (private_state / "state.json").read_bytes() != state_bytes
                or (private_state / ".state-ABC123").read_text(encoding="utf-8") != "interrupted staging fixture\n"
            ):
                raise AssertionError("The PHP resolver did not move and preserve the legacy state directory")

            for relative in PUBLIC_PATHS:
                actual = status(f"{BASE}/{relative}")
                print(f"HTTP {actual} {BASE}/{relative}", flush=True)
                if actual != 200:
                    raise AssertionError(f"Public resource {relative} returned HTTP {actual}")
            for relative in STATE_PATHS:
                actual = status(f"{BASE}/{relative}")
                print(f"HTTP {actual} {BASE}/{relative}", flush=True)
                if actual not in (403, 404):
                    raise AssertionError(f"Private path {relative} returned HTTP {actual}")
        finally:
            process.terminate()
            try:
                process.wait(timeout=5)
            except subprocess.TimeoutExpired:
                process.kill()
                process.wait()
            if process.returncode not in (0, -15):
                error_log = (temp / "error.log").read_text(encoding="utf-8", errors="replace")
                stderr = process.stderr.read() if process.stderr is not None else ""
                if error_log or stderr:
                    raise RuntimeError(f"Apache exited {process.returncode}:\n{error_log}{stderr}")
            if private_state is not None and private_state.exists():
                private_root = Path(tempfile.gettempdir()).resolve()
                if (private_state.parent.parent == private_root
                        and re.fullmatch(r"private-conversation-" + str(expected_uid) + r"-[a-f0-9]{16}", private_state.parent.name)):
                    shutil.rmtree(private_state)
                    private_state.parent.rmdir()

    print("PASS: with AllowOverride None, the pre-migration state was HTTP-readable; PHP moved it outside DocumentRoot, public resources stayed available, and legacy state URLs returned 403/404")


if __name__ == "__main__":
    main()
