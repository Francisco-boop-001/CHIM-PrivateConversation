"""Live Standard run: every scene type and opinion path, with failure cases (docs/live-server-testing.md).

Heartbeats run in the background every ~9.5 s like the game (paused only for the stale-presence case).
Stops before PCV_STANDARD_BUDGET AI calls (default 60). Writes /tmp/pcv_standard_results.json.
"""
import base64, json, os, re, subprocess, sys, threading, time, urllib.request
sys.path.insert(0, os.path.dirname(__file__))
import pcvsim as s

A, B, C = "Lidia Sobieska", "Aela the Huntress", "Bruce Wayne"
IDS = {A: 5124, B: 2892, C: 2905}
NAMES = [A, B, C]
BUDGET = int(os.environ.get("PCV_STANDARD_BUDGET", "60"))
CHIM_LOG = "/var/www/html/HerikaServer/log/chim.log"
WORKER_LOG = "/var/www/html/HerikaServer/log/relationship_worker.log"
START = {"chim": os.path.getsize(CHIM_LOG), "worker": os.path.getsize(WORKER_LOG) if os.path.exists(WORKER_LOG) else 0}
PG = ["psql", "-h", "localhost", "-U", "dwemer", "-d", "dwemer", "-At", "-c"]
PG_ENV = dict(os.environ, PGPASSWORD=open("/home/dwemer/.pgpass").read().split(":")[4].strip())
turns = 0
results = []
heartbeat_on = threading.Event()
heartbeat_on.set()
# Real-client behaviours seen in game (0.1.9): partners leave the close range mid-scene while CHIM's
# wider "beings in range" report (infonpc) may still list them.
HEARTBEAT_NAMES = list(NAMES)
wide_on = threading.Event()


# ---------- background heartbeat (no cookie jar, safe to run concurrently) ----------
def raw_get(url):
    with urllib.request.urlopen(url, timeout=30) as response:
        response.read()


def raw_post_json(url, body):
    request = urllib.request.Request(url, data=json.dumps(body).encode(), headers={"Content-Type": "application/json"})
    with urllib.request.urlopen(request, timeout=30) as response:
        response.read()


def heartbeat_once():
    ts, gamets = s.clock()
    roster = list(HEARTBEAT_NAMES)
    packet = f"infonpc_close|{ts}|{gamets}|{'/'.join(roster)}//{s.PLAYER}"
    raw_get(f"{s.BASE}/comm.php?DATA={base64.b64encode(packet.encode()).decode()}")
    raw_post_json(f"{s.BASE}/gamedata.php", {"type": "activity_status_bulk", "statuses": [
        {"actor_name": n, "timestamp": ts + 50_000 * (i + 1), "gamets": gamets, "current_action": "idle"}
        for i, n in enumerate(roster)]})
    if wide_on.is_set():  # same shape as the client: "(beings in range:Name,Name,...,)"
        wide = f"infonpc|{ts}|{gamets}|(beings in range:{','.join(NAMES)},)"
        raw_get(f"{s.BASE}/comm.php?DATA={base64.b64encode(wide.encode()).decode()}")


def heartbeat_loop():
    while True:
        if heartbeat_on.is_set():
            try:
                heartbeat_once()
            except Exception as error:  # keep the loop alive; a missed beat is part of real life too
                print(f"  (heartbeat error: {error})", flush=True)
        time.sleep(9.5)


# ---------- accounting ----------
def tail(path, offset):
    with open(path, "rb") as handle:
        handle.seek(offset)
        return handle.read().decode("utf-8", "replace")


def mp_records(offset):
    records = []
    for line in tail(CHIM_LOG, offset).splitlines():
        start = line.find('{"schema_version"')
        if start < 0 or '"plugin":"mind_poisoning"' not in line or '"event":"request_finished"' not in line:
            continue
        try:
            records.append(json.loads(line[start:].replace("\\n", "")))
        except ValueError:
            pass
    return records


def ai_calls():
    mp_model = sum(1 for r in mp_records(START["chim"]) if r.get("model_outcome") not in (None, "not_called"))
    worker = sum(int(n) for n in re.findall(r"processed (\d+)", tail(WORKER_LOG, START["worker"]))) if os.path.exists(WORKER_LOG) else 0
    return {"dialogue": turns, "mp_model": mp_model, "relationship": worker, "total": turns + mp_model + worker}


def guard(planned=3):
    used = ai_calls()["total"]
    if used + planned > BUDGET:
        raise SystemExit(f"BUDGET STOP: {used} used + {planned} planned > {BUDGET}")


def max_event_id():
    return int(subprocess.run(PG + ["select coalesce(max(rowid),0) from public.eventlog"], capture_output=True,
                              text=True, env=PG_ENV).stdout.strip() or 0)


def opinion_changes(since_event_id):
    changes = []
    for name, npc_id in IDS.items():
        raw = subprocess.run(PG + [f"select plugin_extended_data::text from public.core_npc_master where id={npc_id}"],
                             capture_output=True, text=True, env=PG_ENV).stdout.strip()
        try:
            events = json.loads(raw).get("mind_poisoning", {}).get("events", []) if raw else []
        except ValueError:
            events = []
        for event in events:
            if int(event.get("event_id", 0)) > since_event_id:
                for judgment in event.get("judgments", []):
                    subject = judgment.get("subject", "")
                    subject_name = next((n for n, i in IDS.items() if subject == f"npc:{i}"), subject)
                    changes.append(f"{name} on {subject_name}: {judgment.get('delta'):+d} ({(judgment.get('reason') or '')[:70]})")
    return changes


def pcv_events(since_iso):
    out = subprocess.run(["su", "-s", "/bin/sh", "www-data", "-c",
                          "cd /var/www/html/HerikaServer/ext/private_conversation && php diagnostics.php --limit 200 --jsonl"],
                         capture_output=True, text=True).stdout
    events = []
    for line in out.splitlines():
        try:
            entry = json.loads(line)
        except ValueError:
            continue
        if entry.get("timestamp", "") >= since_iso and not entry.get("event", "").startswith(("ui.", "presence.")):
            events.append(f"{entry['event']}:{entry.get('reason') or entry.get('outcome')}")
    return events


# ---------- client actions ----------
def say(listener, text, kind="inputtext"):
    global turns
    guard()
    turns += 1
    (res, _) = s.comm(kind, f"{s.PLAYER}: {text}", s.snapshot(list(HEARTBEAT_NAMES), listener=listener), profile=listener)
    return parse_lines(res)


def rechat(previous_speaker, target, last_line, agents=None, chain_id=None):
    """One CHIM rechat turn. A fresh chain_id per scene prompt mirrors the game (a shared id shares CHIM's budget)."""
    global turns
    guard()
    turns += 1
    payload = json.dumps({"speaker": previous_speaker, "listener_hint": target, "rechat_target_hint": target,
                          "origin_line": last_line, "rechat_depth": 1, "chain_id": chain_id or f"pcv-{time.time_ns()}",
                          "active_agents": agents or [A, B]})
    (res, _) = s.comm("rechat", payload, profile=target)
    return parse_lines(res)


def parse_lines(res):
    lines = []
    for line in res[2].splitlines():
        if "|ScriptQueue|" not in line or line.startswith("Player|"):
            continue
        speaker, _, payload = line.split("|", 2)
        fields = payload.split("/")
        if len(fields) >= 8:
            lines.append({"speaker": speaker, "subtitle": fields[0].strip(), "listener": fields[2], "utt": fields[7]})
    if res[0] != 200:
        lines.append({"speaker": "HTTP", "subtitle": f"{res[0]} {res[2][:120]}", "listener": "", "utt": ""})
    return lines


def ack(line, listener):
    s.comm("_speech", json.dumps({"speaker": line["speaker"], "listener": listener,
                                  "speech": line["subtitle"], "utterance_id": line["utt"]}))


def ack_all(lines, listener=None):
    for line in lines:
        if line["utt"]:
            ack(line, listener or line["listener"])
    time.sleep(1.5)


def arm(**form):
    summary = s.page_summary(s.page()[2])
    _, _, body, _ = s.page("POST", dict(form, csrf=summary["csrf"]))
    return s.page_summary(body)["badge"]


def end_scene():
    summary = s.page_summary(s.page()[2])
    s.page("POST", {"csrf": summary["csrf"], "action": "end"})


def arm_pair(exclude_player=True, bystanders="exclude"):
    form = {"action": "arm", "actor_a": str(IDS[A]), "actor_b": str(IDS[B]), "bystander_mode": bystanders}
    if exclude_player:
        form["exclude_player"] = "1"
    return arm(**form)


def arm_group(ids, opener="auto", exclude_player=True):
    """0.1.11 group form: NPC A and B plus optional C and D and the opener picker."""
    keys = ["actor_a", "actor_b", "actor_c", "actor_d"]
    form = {"action": "arm", "bystander_mode": "exclude", "opener": str(opener)}
    for index, key in enumerate(keys):
        form[key] = str(ids[index]) if index < len(ids) else ""
    if exclude_player:
        form["exclude_player"] = "1"
    return arm(**form)


def arm_solo():
    return arm(action="arm", actor_a=str(IDS[A]), scene_mode="solo", bystander_mode="exclude")


def run_case(name, expectation, body):
    since_iso = time.strftime("%Y-%m-%dT%H:%M:%S", time.gmtime())
    since_event = max_event_id()
    print(f"\n== {name}\n   expect: {expectation}", flush=True)
    note = ""
    try:
        note = body() or ""
    except SystemExit as stop:
        results.append({"case": name, "stopped": str(stop)})
        raise
    time.sleep(2)
    record = {"case": name, "expect": expectation, "note": note, "pcv": pcv_events(since_iso),
              "opinions": opinion_changes(since_event), "calls_so_far": ai_calls()}
    results.append(record)
    print(f"   note: {note}\n   pcv: {record['pcv']}\n   opinions: {record['opinions']}\n   calls: {record['calls_so_far']}", flush=True)


def show(lines):
    return " | ".join(f"{l['speaker'].split()[0]}->{(l['listener'] or '?').split()[0]}: {l['subtitle'][:60]}" for l in lines[:4]) \
        + (f" … (+{len(lines) - 4} lines)" if len(lines) > 4 else "")


# ---------- cases ----------
def c_baseline():
    end_scene()
    lines = say(B, f"Aela, I heard {C} stole a horse from the Solitude stables.")
    return f"{len(lines)} lines; " + show(lines)


def c_pair():
    print("   arm:", arm_pair(), flush=True)
    opening = say(B, f"Lidia tells Aela the Huntress that {C} cannot be trusted. Aela weighs the claim.")
    ack_all(opening)
    first = rechat(A, B, opening[-1]["subtitle"] if opening else "")
    ack_all(first)
    second = rechat(B, A, first[-1]["subtitle"] if first else "")
    ack_all(second)
    speakers = {l["speaker"] for l in opening + first + second}
    return f"opening {len(opening)} / rechat {len(first)} / rechat {len(second)} lines; speakers={sorted(speakers)}; " + show(first)


def c_pair_with_player():
    print("   arm:", arm_pair(exclude_player=False), flush=True)
    one = say(A, f"Lidia, I think {C} is a fraud who lies to everyone.")
    ack_all(one)
    two = say(B, f"Aela, what do you make of {C}?")
    ack_all(two)
    return f"{len(one)}+{len(two)} lines; listeners={sorted({l['listener'] for l in one + two})}; " + show(one)


def c_silent_bystanders():
    print("   arm:", arm_pair(bystanders="silent"), flush=True)
    lines = say(B, "Lidia and Aela the Huntress quietly discuss the weather in Solitude.")
    ack_all(lines)
    return f"{len(lines)} lines; speakers={sorted({l['speaker'] for l in lines})} (Bruce must not speak)"


def solo(direction, listener=None, ack_lines=True):
    print("   arm:", arm_solo(), flush=True)
    lines = say(A, direction)
    if ack_lines:
        ack_all(lines, listener or s.PLAYER)
    return lines


def c_solo_positive():
    lines = solo(f"Lidia thinks aloud about Aela the Huntress and decides Aela has earned her trust.")
    return f"{len(lines)} lines; " + show(lines)


def c_solo_negative():
    lines = solo(f"Lidia thinks aloud about {C} and concludes he is a liar who cannot be trusted.")
    return f"{len(lines)} lines; " + show(lines)


def c_solo_neutral():
    lines = solo(f"Lidia thinks aloud about {C} with mixed feelings and reaches no conclusion.")
    return f"{len(lines)} lines; " + show(lines)


def c_solo_short_name():
    lines = solo("Lidia thinks aloud about Aela, calling her only by her first name, Aela, and praises her loyalty.")
    return f"{len(lines)} lines; " + show(lines)


def c_solo_first_line_only():
    lines = solo(f"Lidia names {C} only in her first sentence, then keeps reflecting on him using only 'he' and 'him'.")
    named = [i for i, l in enumerate(lines) if "Bruce" in l["subtitle"]]
    return f"{len(lines)} lines; name in lines {named}; " + show(lines)


def c_fail_abort():
    lines = solo(f"Lidia thinks aloud about {C} and decides he is dishonest.", ack_lines=False)
    if lines:
        ack(lines[0], s.PLAYER)
        s.comm("_speech_abort", json.dumps({"utterance_ids": [l["utt"] for l in lines[1:]]}))
        time.sleep(1)
        ack(lines[-1], s.PLAYER)  # a late/forged final ACK after the abort must not authorize an effect
    return f"{len(lines)} lines; aborted {max(0, len(lines) - 1)}; final ACK sent after abort"


def c_fail_duplicate():
    lines = solo(f"Lidia thinks aloud about {C} and decides he owes her an apology.")
    if lines:
        ack(lines[-1], s.PLAYER)  # duplicate final ACK
    return f"{len(lines)} lines; duplicate final ACK sent"


def c_fail_stale():
    print("   arm:", arm_solo(), flush=True)
    lines = say(A, f"Lidia thinks aloud about {C} and decides he is a coward.")
    heartbeat_on.clear()
    time.sleep(50)
    ack_all(lines, s.PLAYER)
    heartbeat_on.set()
    heartbeat_once()
    return f"{len(lines)} lines; ACKs sent after 50 s without heartbeats"


def c_fail_end_during():
    print("   arm:", arm_solo(), flush=True)
    lines = say(A, f"Lidia thinks aloud about {C} and decides he is arrogant.")
    end_scene()
    ack_all(lines, s.PLAYER)
    return f"{len(lines)} lines; END before ACKs"


def c_repeat():
    first = solo(f"Lidia thinks aloud about {C} and concludes he is a liar who cannot be trusted.")
    second = solo(f"Lidia thinks aloud about {C} and concludes he is a liar who cannot be trusted.")
    return f"{len(first)}+{len(second)} lines (same claim twice)"


def c_player_gossip():
    end_scene()
    one = say(A, f"Lidia, {C} insulted your husband in front of the Jarl.")
    ack_all(one)
    two = say(B, f"Aela, {C} saved a child from a burning house in Dragonsbridge.")
    ack_all(two)
    return f"{len(one)}+{len(two)} lines"


def c_end_then_full_reply():
    print("   arm:", arm_solo(), flush=True)
    abandoned = say(A, f"Lidia thinks aloud about {C} and decides he is vain.")
    end_scene()  # abandons the registration; the next scope must not be blocked by it
    gossip = say(A, f"Lidia, {C} mocked your husband's armour at the Bannered Mare.")  # new basis for Lidia
    ack_all(gossip)
    lines = solo(f"Lidia names {C} only in her first sentence, then keeps reflecting on him using only 'he' and 'him'.")
    named = [i for i, l in enumerate(lines) if "Bruce" in l["subtitle"]]
    return f"abandoned {len(abandoned)} lines; gossip {len(gossip)}; reflection {len(lines)} lines, name in lines {named}"


def c_partner_leaves():
    """0.1.10: a partner out of close range keeps the scene through grace, then the wide report; then refused."""
    print("   arm:", arm_pair(), flush=True)
    opening = say(B, f"Lidia tells Aela the Huntress that {C} cannot be trusted. Aela weighs the claim.")
    ack_all(opening)
    try:
        HEARTBEAT_NAMES.remove(B)
        time.sleep(20)                                   # Aela out of close range, inside the 60 s grace
        one = rechat(A, B, opening[-1]["subtitle"] if opening else "")
        ack_all(one)
        time.sleep(45)
        wide_on.set()
        time.sleep(12)                                   # grace expired; the wide report still lists her
        two = rechat(B, A, one[-1]["subtitle"] if one else "")
        ack_all(two)
        wide_on.clear()
        time.sleep(60)                                   # gone from close, grace and wide
        three = rechat(A, B, two[-1]["subtitle"] if two else "")
    finally:
        wide_on.clear()
        if B not in HEARTBEAT_NAMES:
            HEARTBEAT_NAMES.append(B)
    return f"grace={len(one)} wide={len(two)} absent={len(three)} lines (expect >0, >0, 0 with presence_check wide_absent)"


def c_gap_then_rechat():
    """0.1.10: the first close report after a long gap is a baseline; an active scene still counts its names."""
    print("   arm:", arm_pair(), flush=True)
    opening = say(B, f"Lidia tells Aela the Huntress something unflattering about {C}.")
    ack_all(opening)
    heartbeat_on.clear()
    try:
        time.sleep(70)
        heartbeat_once()
        time.sleep(1)
        turn = rechat(A, B, opening[-1]["subtitle"] if opening else "")
    finally:
        heartbeat_on.set()
    return f"rechat after gap: {len(turn)} lines (expect >0)"


def c_early_ack():
    """0.1.10: an ACK for an early line while the reply is still generating logs reflection.ack_pending."""
    print("   arm:", arm_solo(), flush=True)
    start_id = max_event_id()
    result = {}
    worker = threading.Thread(target=lambda: result.update(
        lines=say(A, f"Lidia thinks aloud at length about {C}: what he said, what he offered, and what she answered.")))
    worker.start()
    acked = None
    while worker.is_alive() and acked is None:
        row = subprocess.run(PG + [
            "select utterance_id||'|'||data from public.eventlog where type='chat' and data like 'Lidia Sobieska:%' "
            f"and utterance_id is not null and rowid > {start_id} order by rowid limit 1"],
            capture_output=True, text=True, env=PG_ENV).stdout.strip()
        if row:
            utt, data = row.split("|", 1)
            text = data.split(":", 1)[1].rsplit("(talking to", 1)[0].strip()
            ack({"speaker": A, "subtitle": text, "utt": utt}, s.PLAYER)
            acked = utt
        time.sleep(0.5)
    worker.join()
    ack_all(result.get("lines", []), s.PLAYER)
    return f"early ACK for {acked} sent while generating; expect reflection.ack_pending reply_in_progress, then normal evaluation"


def c_solo_subject_present():
    """0.1.10: with the subject in the audience, solo lines should not address him ('you')."""
    lines = solo(f"Lidia thinks aloud about {C}, who is standing right there in the snow.")
    addressed = [l["subtitle"] for l in lines if re.search(r"\b(you|your|yer|ya)\b", l["subtitle"], re.I)]
    return f"{len(lines)} lines; lines with 'you': {len(addressed)} -> {addressed[:2]}"


TRIO_IDS = [IDS[A], IDS[B], IDS[C]]
TRIO = [A, B, C]


def c_group_named_opener():
    """0.1.11: the member named in the direction opens (Bruce is C, not A)."""
    end_scene()
    print("   arm:", arm_group(TRIO_IDS), flush=True)
    lines = say(A, f"What does {C.split()[0]} think of the Bannered Mare's mead?")
    ack_all(lines)
    return f"{len(lines)} lines; first speaker={lines[0]['speaker'] if lines else '-'} (expect {C}); listeners={sorted({l['listener'] for l in lines})}"


def c_group_rechats():
    """0.1.11: rechats stay inside the three members; the addressed member answers."""
    print("   arm:", arm_group(TRIO_IDS), flush=True)
    opening = say(A, "Lidia, Aela and Bruce argue about who should lead the next hunt.")
    ack_all(opening)
    chain = f"pcv-group-{time.time_ns()}"
    turns_seen = list(opening)
    last = opening[-1] if opening else None
    for _ in range(2):
        if not last:
            break
        nxt = rechat(last["speaker"], last["listener"], last["subtitle"], agents=TRIO, chain_id=chain)
        ack_all(nxt)
        turns_seen += nxt
        last = nxt[-1] if nxt else None
    speakers = sorted({l["speaker"] for l in turns_seen})
    listeners = sorted({l["listener"] for l in turns_seen})
    return f"speakers={speakers} listeners={listeners} (expect members only)"


def c_group_member_missing_at_start():
    """0.1.11: armed with all three nearby; Bruce walks off before the direction, so activation starts with two.
    (The page only offers NPCs nearby at ARM time, so absence between ARM and activation is the real case.)"""
    end_scene()
    print("   arm:", arm_group(TRIO_IDS), flush=True)
    HEARTBEAT_NAMES.remove(C)
    try:
        time.sleep(12)                       # at least one close report without Bruce before the direction
        lines = say(A, "Lidia and Aela discuss the weather.")
        ack_all(lines)
    finally:
        HEARTBEAT_NAMES.append(C)
    return f"{len(lines)} lines; expect state.scope_activated member_count=2 dropped_count=1 (Bruce)"


def c_group_member_leaves():
    """0.1.11: a member gone beyond grace and wide is dropped; the scene continues with two."""
    print("   arm:", arm_group(TRIO_IDS), flush=True)
    opening = say(A, "Lidia asks Aela and Bruce about the Companions.")
    ack_all(opening)
    try:
        HEARTBEAT_NAMES.remove(C)
        time.sleep(70)
        turn = rechat(opening[-1]["speaker"] if opening else A, B, opening[-1]["subtitle"] if opening else "", agents=TRIO)
    finally:
        HEARTBEAT_NAMES.append(C)
    return f"rechat after Bruce left: {len(turn)} lines; expect state.scope_members_dropped left_scene and the scene continuing"


def arm_free():
    """0.1.12 free scene: no pickers; the nearest six eligible NPCs are chosen at activation."""
    return arm(action="arm", scene_mode="free", bystander_mode="exclude")


def say_target(target, text):
    """Ordinary input with an optional direct target (target_mode direct) or none (automatic)."""
    global turns
    guard()
    turns += 1
    (res, _) = s.comm("inputtext", f"{s.PLAYER}: {text}", s.snapshot(list(HEARTBEAT_NAMES), listener=target or ""),
                      profile=target or A)
    return parse_lines(res)


def crowd(n):
    """n extra catalog NPCs with a profile and a unique name (the crowd for free scenes)."""
    rows = subprocess.run(PG + [
        "select npc_name from public.core_npc_master where profile_id is not null "
        "and npc_name ~ '^[A-Za-z][A-Za-z '' -]{2,40}$' group by npc_name having count(*) = 1 order by min(id) limit 60"],
        capture_output=True, text=True, env=PG_ENV).stdout.split("\n")
    taken = {x.lower() for x in NAMES + [s.PLAYER]}
    return [r.strip() for r in rows if r.strip() and r.strip().lower() not in taken][:n]


def witness_sets(since_event_id):
    """Distinct eventlog.people values of the chat lines written since since_event_id."""
    out = subprocess.run(PG + [f"select distinct people from public.eventlog where rowid > {since_event_id} "
                               "and type = 'chat' and people is not null"], capture_output=True, text=True, env=PG_ENV).stdout
    return [line for line in out.splitlines() if line.strip()]


EXTRAS = []


def with_crowd(body):
    """Run body with A, B, C plus six extras nearby (nine in all; snapshot distances grow with list order)."""
    if not EXTRAS:
        EXTRAS.extend(crowd(6))
    HEARTBEAT_NAMES[:] = NAMES + EXTRAS
    try:
        time.sleep(12)                       # one close report with the crowd before ARM
        return body()
    finally:
        HEARTBEAT_NAMES[:] = list(NAMES)


def c_free_crowd():
    def body():
        end_scene()
        print("   crowd:", EXTRAS, "\n   arm:", arm_free(), flush=True)
        start = max_event_id()
        opening = say_target("", "Everyone around the fire trades rumours about the road to Solitude.")
        ack_all(opening)
        members = NAMES + EXTRAS[:3]
        nxt = []
        if opening:
            last = opening[-1]
            nxt = rechat(last["speaker"], last["listener"], last["subtitle"], agents=members)
            ack_all(nxt)
        speakers = sorted({l["speaker"] for l in opening + nxt})
        outsiders = sorted({l["speaker"] for l in opening + nxt} - set(members) - {"HTTP"})
        return (f"opening {len(opening)} / rechat {len(nxt)}; first={opening[0]['speaker'] if opening else '-'} (expect {A}, nearest); "
                f"speakers={speakers}; outsiders={outsiders} (expect none); witness={witness_sets(start)[:2]}")
    return with_crowd(body)


def c_free_target_opener():
    def body():
        end_scene()
        print("   arm:", arm_free(), flush=True)
        lines = say_target(C, "Someone by the fire should say what they think of the Jarl.")
        ack_all(lines)
        return f"{len(lines)} lines; first={lines[0]['speaker'] if lines else '-'} (expect {C}, opener_source target)"
    return with_crowd(body)


def c_free_named_opener():
    def body():
        end_scene()
        print("   arm:", arm_free(), flush=True)
        lines = say_target(C, f"{B.split()[0]} tells the others about her last hunt.")
        ack_all(lines)
        return f"{len(lines)} lines; first={lines[0]['speaker'] if lines else '-'} (expect {B}, opener_source named over target)"
    return with_crowd(body)


def c_free_too_few():
    end_scene()
    print("   arm:", arm_free(), flush=True)          # armed with three nearby
    HEARTBEAT_NAMES[:] = [A]
    try:
        time.sleep(12)                       # only Lidia remains close before the direction
        lines = say_target("", "Lidia muses about the weather.")
    finally:
        HEARTBEAT_NAMES[:] = list(NAMES)
    return f"{len(lines)} lines; expect state.scope_skipped scene_not_eligible (free scene stays pending)"


CASES = [
    ("1 baseline chat + player gossip (no scene)", "normal reply to Hawke; PCV scope_off; MP may judge the claim", c_baseline),
    ("2 pair: opening + 2 rechats", "only Lidia/Aela speak, to each other; MP updates listener opinions of Bruce", c_pair),
    ("3 pair with player included", "player speech kept; replies may address Hawke", c_pair_with_player),
    ("4 pair, silent bystanders", "Bruce never speaks", c_silent_bystanders),
    ("5a solo positive (Aela)", "full reply registered; MP evaluates Lidia's opinion of Aela", c_solo_positive),
    ("5b solo negative (Bruce)", "Lidia's opinion of Bruce goes down", c_solo_negative),
    ("5c solo neutral (Bruce)", "zero change recorded", c_solo_neutral),
    ("6 solo short name 'Aela'", "MP resolves 'Aela' to Aela the Huntress", c_solo_short_name),
    ("7 solo subject only in first line", "full reply lets MP see the name", c_solo_first_line_only),
    ("8a failure: abort mid-reply", "no effect; aborted source rejected", c_fail_abort),
    ("8b failure: duplicate final ACK", "one evaluation at most (claim_taken on the duplicate)", c_fail_duplicate),
    ("8c failure: stale presence", "no effect; scope_changed logged", c_fail_stale),
    ("8d failure: END during reply", "no effect after END", c_fail_end_during),
    ("9 same claim twice", "no accumulation from unchanged evidence", c_repeat),
    ("10 player gossip (no scene)", "MP judges player claims about Bruce for Lidia and Aela", c_player_gossip),
    ("11 END, new basis, then full-reply solo", "next scope registers (not busy); MP sees name from line 1",
     c_end_then_full_reply),
    ("12 partner leaves mid-scene", "rechat kept by grace, then by wide report; refused when absent from all",
     c_partner_leaves),
    ("13 heartbeat gap then rechat", "baseline report after the gap still counts the pair; rechat prepared",
     c_gap_then_rechat),
    ("14 early ACK during reply", "reflection.ack_pending reply_in_progress, then normal evaluation", c_early_ack),
    ("15 solo with subject present", "lines refer to the subject in the third person (compliance is model-dependent)",
     c_solo_subject_present),
    ("16 group named opener", "Bruce (named, member C) speaks first", c_group_named_opener),
    ("17 group rechats", "only members speak; listeners are other members", c_group_rechats),
    ("18 group member missing at start", "starts with two; Bruce dropped (not_eligible_at_start)", c_group_member_missing_at_start),
    ("19 group member leaves", "Bruce dropped mid-scene (left_scene); scene continues", c_group_member_leaves),
    ("20 free scene in a crowd", "member_count=6 (nearest six), Lidia opens (nearest); only members speak", c_free_crowd),
    ("21 free target opener", "Bruce (the player's target) opens; opener_source target", c_free_target_opener),
    ("22 free named opener", "Aela (named) opens even though Bruce is targeted", c_free_named_opener),
    ("23 free too few nearby", "scene_not_eligible; the free scene stays pending", c_free_too_few),
]
# Optional case-number prefixes select a subset, e.g. `standard.py 11`; `--budget=N` caps AI calls.
_args = [a for a in sys.argv[1:] if not a.startswith("--budget=")]
for _a in sys.argv[1:]:
    if _a.startswith("--budget="):
        BUDGET = int(_a.split("=", 1)[1])
if _args:
    CASES = [case for case in CASES if case[0].split()[0] in _args]

if __name__ == "__main__":
    threading.Thread(target=heartbeat_loop, daemon=True).start()
    time.sleep(22)  # after a long gap the first heartbeat is only a baseline; arm after the second
    try:
        for name, expectation, body in CASES:
            run_case(name, expectation, body)
    except SystemExit as stop:
        print(f"\n{stop}", flush=True)
    finally:
        end_scene()
        heartbeat_on.clear()
        summary = {"calls": ai_calls(), "cases": results}
        json.dump(summary, open("/tmp/pcv_standard_results.json", "w"), indent=1)
        print("\n== TOTAL AI CALLS", json.dumps(summary["calls"]), flush=True)
