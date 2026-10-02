"""Live solo-reflection retest on the test copy: one reflection, one ACK for its final line."""
import os, re, subprocess, sys, time
sys.path.insert(0, os.path.dirname(__file__))
import pcvsim as s

NAMES = ["Lidia Sobieska", "Aela the Huntress"]

def safe_pause():
    time.sleep(1.2)

print("heartbeat:", s.heartbeat(NAMES), flush=True)
time.sleep(9.5)
print("heartbeat:", s.heartbeat(NAMES), flush=True)
safe_pause()
form = s.page_summary(s.page()[2])
code, _, body, _ = s.page("POST", {"csrf": form["csrf"], "action": "arm", "actor_a": "5124",
                                   "scene_mode": "solo", "bystander_mode": "exclude"})
armed = s.page_summary(body)
print("arm:", code, armed["badge"], "|", armed["notice"], flush=True)

direction = os.environ.get("PCV_DIRECTION", "Lidia thinks aloud about Nazeem and whether she misjudged him.")
(res, _) = s.comm("inputtext", f"{s.PLAYER}: {direction}",
                  s.snapshot(NAMES, listener="Lidia Sobieska"), profile="Lidia Sobieska")
code, _, out, secs = res
lines = [l for l in out.splitlines() if "|ScriptQueue|" in l and not l.startswith("Player|")]
print(f"direction: HTTP {code} in {secs}s, {len(lines)} lines", flush=True)
for l in lines:
    print("  ", l[:220], flush=True)
last = lines[-1]
fields = last.split("|", 2)[2].split("/")
subtitle, listener_field, utt = fields[0], fields[2], fields[7]
print(f"final line: listener_field={listener_field} utt={utt}", flush=True)

print("heartbeat:", s.heartbeat(NAMES), flush=True)
safe_pause()
ack_listener = sys.argv[1] if len(sys.argv) > 1 else s.PLAYER
print(f"ack listener: {ack_listener}", flush=True)
(ack, _) = s.comm("_speech", __import__("json").dumps({"speaker": "Lidia Sobieska", "listener": ack_listener,
                                                        "speech": subtitle.strip(), "utterance_id": utt}))
print(f"ack: HTTP {ack[0]} in {ack[3]}s", flush=True)
