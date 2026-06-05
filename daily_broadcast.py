#!/usr/bin/env python3
import subprocess, os, json, urllib.request, urllib.parse

TOKEN = ""
with open("/var/www/masterhacks.ru/.env") as f:
    for line in f:
        if line.startswith("TELEGRAM_BOT_TOKEN="):
            TOKEN = line.strip().split("=", 1)[1].strip().strip('"').strip("'")
            break

API = f"https://api.telegram.org/bot{TOKEN}"
SITE = "https://www.masterhacks.ru"

def sql(q):
    r = subprocess.run(["mysql", "-u", "root", "masterhacks", "-N", "-e", q], capture_output=True, text=True)
    return r.stdout.strip()

def get_subs():
    ids = []
    for line in sql("SELECT telegram_id FROM broadcast_subscribers").split("\n"):
        if line.strip().isdigit():
            ids.append(int(line.strip()))
    return ids

def get_videos(n=3):
    vids = []
    q = f"SELECT id, title, likes, views FROM videos WHERE status='approved' AND published_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY likes DESC, published_at DESC LIMIT {n}"
    for line in sql(q).split("\n"):
        p = line.split("\t")
        if len(p) >= 4:
            vids.append({"id": p[0], "title": p[1], "likes": p[2], "views": p[3]})
    if not vids:
        q2 = f"SELECT id, title, likes, views FROM videos WHERE status='approved' ORDER BY published_at DESC LIMIT {n}"
        for line in sql(q2).split("\n"):
            p = line.split("\t")
            if len(p) >= 4:
                vids.append({"id": p[0], "title": p[1], "likes": p[2], "views": p[3]})
    return vids

def send(uid, text):
    data = urllib.parse.urlencode({"chat_id": uid, "text": text, "parse_mode": "HTML", "disable_web_page_preview": False}).encode()
    try:
        req = urllib.request.Request(f"{API}/sendMessage", data=data)
        with urllib.request.urlopen(req, timeout=10) as r:
            return json.loads(r.read()).get("ok", False)
    except Exception as e:
        print(f"  FAIL {uid}: {e}")
        return False

subs = get_subs()
print(f"Subscribers: {len(subs)}")
if not subs:
    exit()

vids = get_videos(3)
print(f"Videos: {len(vids)}")
if not vids:
    exit()

msg = "\U0001f525 <b>\u041b\u0443\u0447\u0448\u0435\u0435 \u0437\u0430 \u0441\u0435\u0433\u043e\u0434\u043d\u044f</b>\n\n"
for v in vids:
    msg += f"\U0001f3ac <a href=\"{SITE}/view.php?id={v['id']}\">{v['title'][:60]}</a>\n"
    msg += f"\U0001f441 {v['views']}  \u2764\ufe0f {v['likes']}\n\n"
msg += f"\U0001f517 <a href=\"{SITE}\">\u0421\u043c\u043e\u0442\u0440\u0435\u0442\u044c \u0432\u0441\u0435 \u0432\u0438\u0434\u0435\u043e</a>"

ok = fail = 0
for uid in subs:
    if send(uid, msg):
        ok += 1
    else:
        fail += 1
print(f"Sent: {ok}, Failed: {fail}")
