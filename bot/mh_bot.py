import urllib.request, urllib.parse, json, time, subprocess, binascii, os, hashlib

tok = open("/tmp/bot_token.txt").read().strip()
API = "https://api.telegram.org/bot" + tok
MEDIA_DIR = "/var/www/masterhacks.ru/media"
CACHE_FILE = "/var/www/masterhacks.ru/data/cache/posts_cache.json"
OFFSET = 0

def api(method, data=None):
    url = API + "/" + method
    try:
        d = urllib.parse.urlencode(data).encode() if data else None
        req = urllib.request.Request(url, data=d) if d else urllib.request.Request(url)
        with urllib.request.urlopen(req, timeout=30) as r:
            return json.loads(r.read())
    except:
        return None

def reply(chat, text):
    api("sendMessage", {"chat_id": chat, "text": text, "parse_mode": "HTML"})

def download_tg_file(file_id):
    """Download a file from Telegram servers. Returns (file_content, ext) or (None, None)."""
    resp = api("getFile", {"file_id": file_id})
    if not resp or not resp.get("ok"):
        return None, None
    file_path = resp["result"]["file_path"]
    ext = os.path.splitext(file_path)[1].lstrip(".").lower()
    download_url = f"https://api.telegram.org/file/bot{tok}/{file_path}"
    try:
        with urllib.request.urlopen(download_url, timeout=120) as r:
            content = r.read()
        return content, ext
    except Exception as e:
        print(f"Download error: {e}")
        return None, None

def save_and_record(chat_id, uid, content, ext, file_type):
    """Save file to media/ and insert into videos table. Returns filename or None."""
    file_hash = hashlib.md5(content).hexdigest()
    filename = f"{file_hash}.{ext}"
    filepath = os.path.join(MEDIA_DIR, filename)

    # Check DB for duplicate
    r = subprocess.run(
        ["mysql", "-u", "root", "masterhacks", "-N", "-e",
         f"SELECT id FROM videos WHERE file_hash='{file_hash}'"],
        capture_output=True, text=True
    )
    if r.stdout.strip():
        reply(chat_id, "⚠️ Этот файл уже есть в ленте (дубликат)")
        return None

    # Save file
    with open(filepath, "wb") as f:
        f.write(content)

    # Insert into videos
    subprocess.run(
        ["mysql", "-u", "root", "masterhacks", "-e",
         f"INSERT INTO videos (telegram_id, file_hash, filename, file_type, status) "
         f"VALUES ({uid}, '{file_hash}', '{filename}', '{file_type}', 'pending')"]
    )

    # Clear cache
    if os.path.exists(CACHE_FILE):
        os.unlink(CACHE_FILE)

    return filename

while True:
    try:
        resp = api("getUpdates", {"offset": OFFSET, "timeout": 30})
        if resp and resp.get("ok") and resp["result"]:
            for upd in resp["result"]:
                OFFSET = upd["update_id"] + 1
                m = upd.get("message", {})
                if not m:
                    continue
                chat = m["chat"]["id"]
                uid = m["from"]["id"]

                # --- TEXT COMMANDS ---
                if "text" in m:
                    text = m["text"]
                    print(f"[TEXT] {text}")
                    if text.startswith("/start login") or text == "/login":
                        t = binascii.hexlify(os.urandom(16)).decode()
                        subprocess.run(["mysql", "-u", "root", "masterhacks", "-e",
                            f"INSERT INTO user_sessions (telegram_id,token,expires_at) "
                            f"VALUES ({uid}, '{t}', DATE_ADD(NOW(),INTERVAL 1 HOUR)) "
                            f"ON DUPLICATE KEY UPDATE token='{t}', expires_at=DATE_ADD(NOW(),INTERVAL 1 HOUR)"])
                        api("sendMessage", {
                            "chat_id": chat, "text": "🔐 <b>Вход на сайт</b>", "parse_mode": "HTML",
                            "reply_markup": json.dumps({"inline_keyboard": [[
                                {"text": "🟢 ВОЙТИ", "url": f"https://masterhacks.ru/auth.php?token={t}"}
                            ]]})
                        })
                    elif text.startswith("/start"):
                        reply(chat, "📹 Привет! Загрузи видео! 🚀")
                    elif text == "/subscribe":
                        subprocess.run(["mysql", "-u", "root", "masterhacks", "-e",
                            f"INSERT INTO broadcast_subscribers (telegram_id) VALUES ({uid}) "
                            f"ON DUPLICATE KEY UPDATE subscribed_at=NOW()"])
                        reply(chat, "✅ Ты подписался на ежедневную рассылку!")
                    elif text == "/unsubscribe":
                        subprocess.run(["mysql", "-u", "root", "masterhacks", "-e",
                            f"DELETE FROM broadcast_subscribers WHERE telegram_id={uid}"])
                        reply(chat, "👋 Ты отписался от рассылки.")
                    else:
                        # Try using text as title for last uploaded video
                        r = subprocess.run(
                            ["mysql", "-u", "root", "masterhacks", "-e",
                             f"UPDATE videos SET title='{text.replace(chr(39), chr(39)+chr(39))}' "
                             f"WHERE telegram_id={uid} AND (title IS NULL OR title='') "
                             f"ORDER BY id DESC LIMIT 1"],
                            capture_output=True, text=True
                        )
                        if "matched" in r.stderr.lower() or "Rows matched" in r.stderr:
                            reply(chat, f"✅ Название обновлено: <b>{text}</b> 🎉")
                        else:
                            reply(chat, "📹 Отправь мне видео или фото — и оно появится на MasterHacks! 🚀")

                # --- VIDEO ---
                elif "video" in m:
                    print(f"[VIDEO] from {uid}")
                    reply(chat, "🎥 Видео получено! Сохраняю...")
                    content, ext = download_tg_file(m["video"]["file_id"])
                    if content:
                        fname = save_and_record(chat, uid, content, ext or "mp4", "video")
                        if fname:
                            subprocess.Popen(["python3", "/var/www/masterhacks.ru/prepare_video.py", fname],
                                           stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
                            reply(chat, "✅ Видео загружено! Отправь мне <b>название</b> — просто напиши текст сюда ✍️")
                    else:
                        reply(chat, "❌ Ошибка загрузки видео. Попробуй ещё раз.")

                # --- PHOTO ---
                elif "photo" in m:
                    print(f"[PHOTO] from {uid}")
                    reply(chat, "📸 Фото получено! Сохраняю...")
                    # Take the largest photo (last in array)
                    content, ext = download_tg_file(m["photo"][-1]["file_id"])
                    if content:
                        fname = save_and_record(chat, uid, content, ext or "jpg", "image")
                        if fname:
                            subprocess.Popen(["python3", "/var/www/masterhacks.ru/prepare_video.py", fname],
                                           stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
                            reply(chat, "✅ Фото загружено! Отправь мне <b>название</b> ✍️")
                    else:
                        reply(chat, "❌ Ошибка загрузки фото. Попробуй ещё раз.")

                # --- DOCUMENT (could be video) ---
                elif "document" in m:
                    doc = m["document"]
                    mime = doc.get("mime_type", "")
                    print(f"[DOCUMENT] from {uid}, mime={mime}")
                    if mime.startswith("video/") or mime.startswith("image/"):
                        ftype = "video" if mime.startswith("video/") else "image"
                        reply(chat, f"📎 {'Видео' if ftype == 'video' else 'Фото'} получено! Сохраняю...")
                        content, ext = download_tg_file(doc["file_id"])
                        if content:
                            fname = save_and_record(chat, uid, content, ext or ("mp4" if ftype == "video" else "jpg"), ftype)
                            if fname:
                                subprocess.Popen(["python3", "/var/www/masterhacks.ru/prepare_video.py", fname],
                                               stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
                                reply(chat, f"✅ Загружено! Отправь <b>название</b> ✍️")
                        else:
                            reply(chat, "❌ Ошибка загрузки файла.")
                    else:
                        reply(chat, "⚠️ Бот принимает только видео и фото.")

    except Exception as e:
        print("E:", e)
        time.sleep(5)
