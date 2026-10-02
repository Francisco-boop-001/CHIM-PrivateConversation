"""Replay a real crowded infonpc_close report (from the copy's history) twice, then read eligibility."""
import os, subprocess, sys, time
sys.path.insert(0, os.path.dirname(__file__))
import pcvsim as s

q = ("select data from public.eventlog where type='infonpc_close' "
     "and data ilike '%Lidia Sobieska%' "
     "and array_length(regexp_split_to_array(trim(data),'/'),1) > 60 order by rowid desc limit 1")
env = dict(os.environ, PGPASSWORD=open('/home/dwemer/.pgpass').read().split(':')[4].strip())
report = subprocess.run(["psql", "-h", "localhost", "-U", "dwemer", "-d", "dwemer", "-At", "-c", q],
                        capture_output=True, text=True, env=env).stdout.strip()
tokens = report.split("/")
print(f"real report: {len(tokens)} tokens, {len(report)} bytes, player listed {sum(t.strip()=='Hawke' for t in tokens)}x", flush=True)
if not report:
    sys.exit("no crowded report found")

names = sorted({t.strip() for t in tokens if t.strip() and t.strip() != "Hawke"})
for attempt in range(2):
    (res, ts) = s.comm("infonpc_close", report)
    s.activity(["Lidia Sobieska", "Aela the Huntress", "Bruce Wayne"], ts)
    print("heartbeat", attempt + 1, res[0], flush=True)
    time.sleep(9.5 if attempt == 0 else 1.3)
summary = s.page_summary(s.page()[2])
print("picker:", summary["picker"][:90], flush=True)
print("eligible:", summary["options"], flush=True)
