"""Install a .dwpkg through CHIM's package API, the way the client sync does. Test distro only."""
import hashlib, json, os, sys, time, urllib.request

TEST_DISTRO = os.environ.get("PCV_TEST_DISTRO", "DwemerAI4Skyrim3-test")
if os.environ.get("WSL_DISTRO_NAME") != TEST_DISTRO:
    sys.exit(f"REFUSING: not running inside {TEST_DISTRO}")
# Usage: install_pkg.py PATH.dwpkg NAME VERSION FULL_SHA256  (copy the hash from the build's SHA256SUMS.txt)

BASE = "http://127.0.0.1:8081/HerikaServer/ui/api/plugin_packages.php"
path, name, version, expected_sha = sys.argv[1:5]
data = open(path, "rb").read()
digest = hashlib.sha256(data).hexdigest()
if digest != expected_sha:
    sys.exit(f"hash mismatch {digest}")

def call(method, query, body=None, raw=False):
    req = urllib.request.Request(f"{BASE}?{query}", data=body, method=method)
    if body is not None and not raw:
        req.add_header("Content-Type", "application/json")
    try:
        with urllib.request.urlopen(req, timeout=120) as r:
            return r.status, json.loads(r.read())
    except urllib.error.HTTPError as e:
        return e.code, json.loads(e.read() or b"{}")

print("probe:", call("POST", "action=probe", json.dumps({"name": name, "version": version}).encode()))
CHUNK = 1024 * 1024
chunks = [data[i:i + CHUNK] for i in range(0, len(data), CHUNK)]
status, start = call("POST", "action=start-upload", json.dumps({
    "name": name, "version": version, "archive_name": f"{version}.dwpkg",
    "size": len(data), "total_chunks": len(chunks)}).encode())
print("start:", status, start, flush=True)
upload_id = (start.get("upload") or {}).get("upload_id")
if not upload_id:
    sys.exit("no upload id")
last = None
for i, c in enumerate(chunks):
    last = call("POST", f"action=upload-chunk&upload_id={upload_id}&index={i}", c, raw=True)
    if last[0] >= 400:
        sys.exit(f"chunk {i} failed: {last}")
print("final:", last[0], json.dumps(last[1])[:1500], flush=True)
pk = call("GET", "action=packages")[1]
print("installed:", json.dumps([{k: p.get(k) for k in ("name", "version", "status", "installed_version")} for p in (pk.get("packages") or [])]), flush=True)
