"""Live smoke test: baseline chat, one pair turn, one full-reply solo reflection, one duplicate-ACK failure case.

Run inside the test distro after env.sh up and installing PCV + Mind Poisoning (docs/live-server-testing.md).
Counts AI work from the logs and stops before exceeding PCV_SMOKE_BUDGET (default 12 calls).
"""
import json, os, re, sys, time
sys.path.insert(0, os.path.dirname(__file__))
import pcvsim as s

A, B, SUBJECT = "Lidia Sobieska", "Aela the Huntress", "Bruce Wayne"
A_ID, B_ID = "5124", "2892"
NAMES = [A, B, SUBJECT]
BUDGET = int(os.environ.get("PCV_SMOKE_BUDGET", "12"))
# Mind Poisoning writes its request records through CHIM's Logger into log/chim.log (not Apache's error log).
ERROR_LOG = "/var/www/html/HerikaServer/log/chim.log"
WORKER_LOG = "/var/www/html/HerikaServer/log/relationship_worker.log"
START = {"error": os.path.getsize(ERROR_LOG), "worker": os.path.getsize(WORKER_LOG) if os.path.exists(WORKER_LOG) else 0}
turns = 0


def tail(path, offset):
    with open(path, "rb") as handle:
        handle.seek(offset)
        return handle.read().decode("utf-8", "replace")


def ai_calls():
    mp_model, mp_results = 0, []
    for line in tail(ERROR_LOG, START["error"]).splitlines():
        brace = line.find('{"schema_version"')
        if brace < 0 or '"plugin":"mind_poisoning"' not in line or '"event":"request_finished"' not in line:
            continue
        try:
            record = json.loads(line[brace:].replace("\\n", ""))
        except ValueError:
            continue
        mp_results.append((record.get("source_kind") or "speech_ack", record.get("outcome"), record.get("reason"), record.get("model_outcome")))
        if record.get("model_outcome") not in (None, "not_called"):  # e.g. "validated": the model was called
            mp_model += 1
    worker = sum(int(n) for n in re.findall(r"processed (\d+)", tail(WORKER_LOG, START["worker"]))) if os.path.exists(WORKER_LOG) else 0
    return {"dialogue": turns, "mp_model": mp_model, "relationship": worker,
            "total": turns + mp_model + worker, "mp_results": mp_results}


def guard(planned):
    used = ai_calls()["total"]
    if used + planned > BUDGET:
        sys.exit(f"BUDGET STOP: {used} used, {planned} planned, budget {BUDGET}")


def say(listener, text):
    global turns
    guard(2)
    turns += 1
    (res, _) = s.comm("inputtext", f"{s.PLAYER}: {text}", s.snapshot(NAMES, listener=listener), profile=listener)
    lines = []
    for line in res[2].splitlines():
        if "|ScriptQueue|" not in line or line.startswith("Player|"):
            continue
        speaker, _, payload = line.split("|", 2)
        fields = payload.split("/")
        lines.append({"speaker": speaker, "subtitle": fields[0].strip(), "listener": fields[2], "utt": fields[7]})
    print(f"  reply: {len(lines)} lines in {res[3]}s", flush=True)
    for line in lines:
        print(f"    {line['speaker']} -> {line['listener']}: {line['subtitle'][:90]}", flush=True)
    return lines


def ack(line, listener):
    (res, _) = s.comm("_speech", json.dumps({"speaker": line["speaker"], "listener": listener,
                                             "speech": line["subtitle"], "utterance_id": line["utt"]}))
    return res[0]


def fresh_presence():
    s.heartbeat(NAMES)
    time.sleep(9.5)
    s.heartbeat(NAMES)
    time.sleep(1.2)


def arm(form):
    summary = s.page_summary(s.page()[2])
    code, _, body, _ = s.page("POST", dict(form, csrf=summary["csrf"]))
    result = s.page_summary(body)
    print(f"  arm: {result['badge']} | {result['notice']}", flush=True)


print("== presence", flush=True)
fresh_presence()

print("== 1 baseline chat (no scene)", flush=True)
say(B, f"Aela, have you seen {SUBJECT} around Solitude?")

print("== 2 pair turn: Lidia tells Aela about the subject; ACK each line to Aela", flush=True)
s.heartbeat(NAMES); time.sleep(1.2)
arm({"action": "arm", "actor_a": A_ID, "actor_b": B_ID, "bystander_mode": "exclude", "exclude_player": "1"})
pair = say(B, f"Lidia tells Aela the Huntress that {SUBJECT} cannot be trusted and explains why. Aela weighs the claim.")
for line in pair:
    print(f"  ack {line['utt']} -> HTTP {ack(line, line['listener'])}", flush=True)
time.sleep(2)

print("== 3 solo full reply: subject named early; ACK every line to the player", flush=True)
fresh_presence()
arm({"action": "arm", "actor_a": A_ID, "scene_mode": "solo", "bystander_mode": "exclude"})
solo = say(A, f"Lidia thinks aloud about {SUBJECT}: she names him in her first sentence, "
              "then reflects for three more sentences on whether she misjudged him.")
s.heartbeat(NAMES); time.sleep(1.2)
for line in solo:
    print(f"  ack {line['utt']} -> HTTP {ack(line, s.PLAYER)}", flush=True)
time.sleep(2)

print("== 4 failure case: duplicate final ACK must not evaluate again", flush=True)
if solo:
    print(f"  duplicate ack -> HTTP {ack(solo[-1], s.PLAYER)}", flush=True)

summary = s.page_summary(s.page()[2])
s.page("POST", {"csrf": summary["csrf"], "action": "end"})
calls = ai_calls()
print("== AI work", json.dumps({k: v for k, v in calls.items() if k != "mp_results"}), flush=True)
for result in calls["mp_results"]:
    print("  MP:", result, flush=True)
