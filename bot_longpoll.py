#!/usr/bin/env python3
import urllib.request, json, os, time, subprocess

# Read token
tok = ""
with open("/var/www/masterhacks.ru/.env") as f:
    for line in f:
        if "TELEGRAM_BOT_TOKEN" in line:
            tok = line.strip().split("=*** 1)[1].strip().strip('"').strip("'")
            break

API = f"https://api.telegram.org/bot{tok}"
OFFSET = 0
print(f"Bot started. Token: {tok[:12]}...")

def api(method, data=None):
    url = f"{API}/{method}"
    d = urllib.parse.urlencode(data).encode() if data else None
    req = urllib.request.Request(url, data=d) if d else urllib.request.Request(url)
    try:
        with urllib.request.urlopen(req, timeout=30) as r:
            return json.loads(r.read())
    except Exception as e:
        print(f"API err: {e}")
        return None

def reply(chat, text):
    return api("sendMessage", {"chat_id": chat, "text": text, "parse_mode": "HTML"})

def handle(msg):
    chat = msg["chat"]["id"]
    uid = msg["from"]["id"]
    text = msg.get("text", "")
    if text in ["/start", "/start upload", "/start add"]:
        reply(chat, "\ud83d\udcf9 <b>\u041f\u0440\u0438\u0432\u0435\u0442!</b> \ud83d\ude80\n\n\u041f\u0440\u043e\u0441\u0442\u043e \u0437\u0430\u0433\u0440\u0443\u0437\u0438 \u0441\u0432\u043e\u0451 \u0432\u0438\u0434\u0435\u043e \u0432 \u0431\u043e\u0442\u0430 \u0438\u043b\u0438 \u043f\u043e\u0434\u0435\u043b\u0438\u0441\u044c \u0438\u043c \u2014 \u0438 \u043e\u043d\u043e \u043f\u043e\u044f\u0432\u0438\u0442\u0441\u044f \u043d\u0430 MasterHacks!\n\n\ud83d\udcdd \u041d\u0430\u0437\u0432\u0430\u043d\u0438\u0435: \u043e\u0442\u043f\u0440\u0430\u0432\u044c \u0442\u0435\u043a\u0441\u0442 \u043f\u043e\u0441\u043b\u0435 \u0437\u0430\u0433\u0440\u0443\u0437\u043a\u0438\n\ud83d\udd10 \u0412\u0445\u043e\u0434: /login\n\ud83d\udce2 \u0420\u0430\u0441\u0441\u044b\u043b\u043a\u0430: /subscribe")
    elif text in ["/login", "/start login"]:
        reply(chat, "\ud83d\udd10 \u0412\u0445\u043e\u0434 \u043d\u0430 \u0441\u0430\u0439\u0442: https://masterhacks.ru/auth.php")
    elif text == "/subscribe":
        subprocess.run(["mysql", "-u", "root", "masterhacks", "-e", f"INSERT INTO broadcast_subscribers (telegram_id) VALUES ({uid}) ON DUPLICATE KEY UPDATE subscribed_at=NOW()"])
        reply(chat, "\u2705 \u0422\u044b \u043f\u043e\u0434\u043f\u0438\u0441\u0430\u043b\u0441\u044f \u043d\u0430 \u0435\u0436\u0435\u0434\u043d\u0435\u0432\u043d\u0443\u044e \u0440\u0430\u0441\u0441\u044b\u043b\u043a\u0443! \u041a\u0430\u0436\u0434\u044b\u0439 \u0434\u0435\u043d\u044c \u0432 12:00.")
    elif text == "/unsubscribe":
        subprocess.run(["mysql", "-u", "root", "masterhacks", "-e", f"DELETE FROM broadcast_subscribers WHERE telegram_id={uid}"])
        reply(chat, "\ud83d\udc4b \u0422\u044b \u043e\u0442\u043f\u0438\u0441\u0430\u043b\u0441\u044f.")
    else:
        reply(chat, "\ud83d\udcf9 \u041e\u0442\u043f\u0440\u0430\u0432\u044c \u043c\u043d\u0435 \u0432\u0438\u0434\u0435\u043e \u0438\u043b\u0438 \u0444\u043e\u0442\u043e \u2014 \u0438 \u043e\u043d\u043e \u043f\u043e\u044f\u0432\u0438\u0442\u0441\u044f \u043d\u0430 MasterHacks! \ud83d\ude80\n\n/subscribe \u2014 \u043f\u043e\u0434\u043f\u0438\u0441\u043a\u0430 \u043d\u0430 \u0440\u0430\u0441\u0441\u044b\u043b\u043a\u0443")

print("Polling...")
while True:
    try:
        resp = api("getUpdates", {"offset": OFFSET, "timeout": 30})
        if resp and resp.get("ok") and resp["result"]:
            for upd in resp["result"]:
                OFFSET = upd["update_id"] + 1
                if "message" in upd and "text" in upd["message"]:
                    nm = upd["message"]["from"].get("first_name", "?")
                    tx = upd["message"]["text"]
                    print(f"[{nm}] {tx}")
                    handle(upd["message"])
    except KeyboardInterrupt:
        break
    except Exception as e:
        print(f"Err: {e}")
        time.sleep(5)
