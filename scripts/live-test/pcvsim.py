"""Minimal CHIM client simulator for PCV live-server tests (see docs/live-server-testing.md).

Runs only inside the disposable test distro. Commands:
  heartbeat NAME...                 infonpc_close + activity_status_bulk (send twice, ~10 s apart)
  page | arm pair A_ID B_ID [exclude|silent] [include_player] | arm solo A_ID [exclude|silent] | end
  input LISTENER|- "text" NAME...   ordinary Standard input with a routing snapshot and &profile=
  ack SPEAKER LISTENER "speech" UTTERANCE_ID
  raw TYPE DATA | logs [N]
"""
import base64, json, os, re, subprocess, sys, time, urllib.parse, urllib.request
from http import cookiejar as cookielib

TEST_DISTRO = os.environ.get("PCV_TEST_DISTRO", "DwemerAI4Skyrim3-test")
if os.environ.get("WSL_DISTRO_NAME") != TEST_DISTRO:
    sys.exit(f"REFUSING: not running inside {TEST_DISTRO}")

BASE = "http://127.0.0.1:8081/HerikaServer"
STATE = "/tmp/pcvsim.json"  # persisted synthetic clock start
PLAYER = os.environ.get("PCV_SIM_PLAYER", "Hawke")  # must equal public.core_player player_name
FORM = {"Lydia": 0xA2C94, "Aela the Huntress": 0x1A696}  # any stable ints; only used by the snapshot

def _state():
    try:
        return json.load(open(STATE))
    except Exception:
        s = {"start_ns": time.time_ns(), "cookies": {}}
        json.dump(s, open(STATE, "w"))
        return s

def clock():
    s = _state()
    elapsed = time.time_ns() - s["start_ns"]
    return 1_900_000_000_000_000 + elapsed, 292_500_000 + elapsed // 50_000_000

def _cookie_jar():
    jar = cookielib.LWPCookieJar("/tmp/pcvsim.cookies")
    if os.path.exists("/tmp/pcvsim.cookies"):
        jar.load(ignore_discard=True)
    return jar

def http(method, url, body=None, headers=None, timeout=180):
    jar = _cookie_jar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
    req = urllib.request.Request(url, data=body, method=method, headers=headers or {})
    t0 = time.time()
    try:
        with opener.open(req, timeout=timeout) as r:
            code, hdrs, data = r.status, dict(r.headers), r.read()
    except urllib.error.HTTPError as e:
        code, hdrs, data = e.code, dict(e.headers), e.read()
    jar.save(ignore_discard=True)
    return code, hdrs, data.decode("utf-8", "replace"), round(time.time() - t0, 2)

def comm(kind, data, field4=None, profile=None):
    import hashlib
    ts, gamets = clock()
    packet = f"{kind}|{ts}|{gamets}|{data}" + (f"|{field4}" if field4 is not None else "")
    b64 = base64.b64encode(packet.encode()).decode()
    extra = f"&profile={hashlib.md5(profile.encode()).hexdigest()}" if profile else ""
    return http("GET", f"{BASE}/comm.php?DATA={b64}{extra}"), ts

def snapshot(names, radius=1200, listener=""):
    payload = {
        "source": "plugin_player_routing_v2", "speech_mode": "standard", "execution_mode": "STANDARD",
        "target_mode": "direct" if listener else "automatic", "listener": listener, "audience_radius_units": radius,
        "present_actors": [{"form_id": FORM.get(n, 0x10000 + i), "name": n, "distance": 150.0 + 40 * i,
                            "managed": True, "creature": False} for i, n in enumerate(names)],
    }
    return base64.b64encode(json.dumps(payload).encode()).decode()

def activity(names, ts):
    _, gamets = clock()
    body = json.dumps({"type": "activity_status_bulk", "statuses": [
        {"actor_name": n, "timestamp": ts + 50_000 * (i + 1), "gamets": gamets, "current_action": "idle"}
        for i, n in enumerate(names)]}).encode()
    return http("POST", f"{BASE}/gamedata.php", body, {"Content-Type": "application/json"})

def heartbeat(names):
    (res, ts) = comm("infonpc_close", "/".join(names) + "//" + PLAYER)
    act = activity(names, ts)
    return res[0], act[0]

def page(method="GET", form=None, query=""):
    url = f"{BASE}/ext/private_conversation/index.php{query}"
    body = None
    headers = {}
    if form is not None:
        body = urllib.parse.urlencode(form).encode()
        headers["Content-Type"] = "application/x-www-form-urlencoded"
    return http(method, url, body, headers)

def page_summary(html):
    csrf = re.search(r'name="csrf" value="([a-f0-9]{64})"', html)
    badge = re.search(r'class="status-badge[^"]*"[^>]*>([^<]+)<', html)
    picker = re.search(r'id="picker-status"[^>]*>([^<]+)<', html)
    notice = re.search(r'<p role="status">([^<]+)</p>', html)
    options = re.findall(r'<option value="(\d+)"[^>]*>([^<]+)</option>', html)
    return {"csrf": csrf.group(1) if csrf else None, "badge": badge.group(1) if badge else None,
            "picker": picker.group(1) if picker else None, "notice": notice.group(1) if notice else None,
            "options": sorted(set(options))[:12]}

def show(code, hdrs, body, secs, limit=1500):
    print(f"HTTP {code} in {secs}s; X-CUSTOM-CLOSE={hdrs.get('X-CUSTOM-CLOSE') or hdrs.get('x-custom-close')}")
    print(body[:limit])

if __name__ == "__main__":
    import urllib.parse
    cmd, args = sys.argv[1], sys.argv[2:]
    if cmd == "heartbeat":
        print("infonpc_close / activity:", heartbeat(args))
    elif cmd == "page":
        code, hdrs, body, secs = page()
        print("HTTP", code, secs, json.dumps(page_summary(body), indent=1))
    elif cmd == "arm":  # arm pair|solo A_ID [B_ID] exclude|silent [include_player]
        s = page_summary(page()[2])
        mode, a = args[0], args[1]
        form = {"csrf": s["csrf"], "action": "arm", "actor_a": a, "bystander_mode": "exclude"}
        if mode == "solo":
            form["scene_mode"] = "solo"
            form["bystander_mode"] = args[2] if len(args) > 2 else "exclude"
        else:
            form["actor_b"] = args[2]
            form["bystander_mode"] = args[3] if len(args) > 3 else "exclude"
            if not (len(args) > 4 and args[4] == "include_player"):
                form["exclude_player"] = "1"
        code, hdrs, body, secs = page("POST", form)
        print("HTTP", code, secs, json.dumps(page_summary(body), indent=1))
    elif cmd == "end":
        s = page_summary(page()[2])
        code, hdrs, body, secs = page("POST", {"csrf": s["csrf"], "action": "end"})
        print("HTTP", code, secs, json.dumps(page_summary(body), indent=1))
    elif cmd == "input":  # input LISTENER|- "text" name1 name2 ...
        listener, text, names = args[0], args[1], args[2:]
        target = None if listener == "-" else listener
        (res, ts) = comm("inputtext", f"{PLAYER}: {text}", snapshot(names, listener=target or ""), profile=target)
        show(*res)
    elif cmd == "raw":  # raw TYPE DATA
        (res, ts) = comm(args[0], args[1])
        show(*res)
    elif cmd == "ack":  # ack speaker listener speech utterance_id
        speaker, listener, speech, utt = args
        (res, ts) = comm("_speech", json.dumps({"speaker": speaker, "listener": listener, "speech": speech,
                                                "utterance_id": utt}))
        show(*res, limit=400)
    elif cmd == "logs":
        n = args[0] if args else "40"
        out = subprocess.run(["su", "-s", "/bin/sh", "www-data", "-c",
                              f"cd /var/www/html/HerikaServer/ext/private_conversation && php diagnostics.php --limit {n} --jsonl"],
                             capture_output=True, text=True)
        for line in out.stdout.splitlines():
            try:
                e = json.loads(line)
            except Exception:
                print(line); continue
            ctx = {k: v for k, v in (e.get("context") or {}).items() if k not in ("correlation",)}
            print(e.get("timestamp", "")[11:23], e.get("event"), e.get("outcome"), e.get("reason") or "", json.dumps(ctx)[:160])
        if out.stderr.strip():
            print("stderr:", out.stderr.strip()[:400])
    else:
        sys.exit("unknown command")
