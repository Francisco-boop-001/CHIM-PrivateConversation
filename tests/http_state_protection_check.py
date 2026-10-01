from __future__ import annotations

import argparse
import os
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
STATE_FILES = (
    "state/state.json",
    "state/state.lock",
    "state/.state-temp",
    "state/registry.json",
    "State/case.json",
)
STATE_PATHS = (
    "state/",
    *STATE_FILES,
    "st%61te/state.json",
    "state%2fstate.json",
)


def apache_quote(path: Path) -> str:
    return '"' + str(path).replace("\\", "/").replace('"', '\\"') + '"'


def main() -> None:
    parser = argparse.ArgumentParser(description="Verify state denial with an isolated Apache process.")
    parser.add_argument("apache", type=Path, help="Apache executable, for example /usr/sbin/apache2")
    parser.add_argument("modules", type=Path, help="Apache module directory")
    args = parser.parse_args()
    if not args.apache.is_file() or not args.modules.is_dir():
        parser.error("Apache executable or module directory does not exist")

    with tempfile.TemporaryDirectory(prefix="pcv-http-") as temp_name:
        temp = Path(temp_name)
        webroot = temp / "www"
        extension = webroot / BASE.lstrip("/")
        (extension / "assets").mkdir(parents=True)
        (extension / "state").mkdir()
        (extension / "manifest.json").write_text('{"name":"fixture"}\n', encoding="utf-8")
        (extension / "assets/style.css").write_text("body { color: black; }\n", encoding="utf-8")
        (extension / "assets/ui-refresh.js").write_text("void 0;\n", encoding="utf-8")
        for relative in STATE_FILES:
            path = extension / relative
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text("private fixture data\n", encoding="utf-8")

        source_htaccess = ROOT / "server" / ".htaccess"
        if source_htaccess.is_file():
            shutil.copyfile(source_htaccess, extension / ".htaccess")

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
                    "    AllowOverride All",
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

    print("PASS: isolated Apache serves manifest/assets and denies state paths (403/404)")


if __name__ == "__main__":
    main()
