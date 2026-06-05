#!/usr/bin/env python3
"""
Trend Pipeline v2.1: YouTube Shorts → Download → Strip Audio → Uniquify → Publish
"""

import subprocess, os, json, random, hashlib, time
from datetime import datetime

MEDIA_DIR = "/var/www/masterhacks.ru/media"
TEMP_DIR = "/tmp/masterhacks_trends"
os.makedirs(TEMP_DIR, exist_ok=True)
os.makedirs(MEDIA_DIR, exist_ok=True)

SEARCH_QUERIES = [
    "ytsearch10:#shorts lifehack",
    "ytsearch10:#shorts diy",
    "ytsearch10:#shorts tricks",
    "ytsearch10:#shorts howto",
    "ytsearch10:#shorts tips",
    "ytsearch10:#shorts satisfying",
    "ytsearch10:#shorts craft",
    "ytsearch10:#shorts cooking",
]

MAX_VIDEOS = 3
MIN_DURATION = 10
MAX_DURATION = 90

TITLES = [
    "Ты точно этого не знал", "Секрет, который скрывают",
    "Попробуй этот трюк", "Гениально и просто",
    "Лайфхак на все случаи", "Мало кто догадывается",
    "Работает безотказно", "То, чему не учат",
    "Хитрость профи", "Сделай так же",
    "Результат за секунду", "Скрытая функция",
    "Проверено — работает", "Совет бывалых",
    "Быстро и легко", "Мгновенный эффект",
    "Проще некуда", "Секрет мастеров",
    "Находка дня", "Бери и делай",
]


def run(cmd, timeout=120):
    try:
        r = subprocess.run(cmd, shell=True, capture_output=True, text=True, timeout=timeout)
        return r.stdout.strip(), r.stderr.strip(), r.returncode
    except subprocess.TimeoutExpired:
        return "", "timeout", -1


def has_speech(filepath):
    """Check if audio track contains speech (silence detection)"""
    # Detect silence — if > 80% is silence, probably no speech
    out, _, _ = run(
        f'ffmpeg -i "{filepath}" -af "silencedetect=noise=-30dB:d=0.5" -f null - 2>&1'
    )
    silence_count = out.count("silence_start")
    # If many silence segments, likely has speech between them
    if silence_count >= 2:
        return True
    # Also check if audio stream exists at all
    out2, _, _ = run(f'ffprobe -v error -select_streams a -show_entries stream=codec_type -of csv=p=0 "{filepath}"')
    return bool(out2.strip())


def strip_audio(input_path, output_path):
    """Remove audio track, keep video only"""
    cmd = f'ffmpeg -y -i "{input_path}" -an -c:v copy "{output_path}" 2>&1'
    _, err, code = run(cmd, timeout=30)
    if code == 0 and os.path.exists(output_path) and os.path.getsize(output_path) > 10000:
        return True
    # Fallback: re-encode without audio
    cmd2 = f'ffmpeg -y -i "{input_path}" -an -c:v libx264 -preset fast -crf 23 "{output_path}" 2>&1'
    _, err2, code2 = run(cmd2, timeout=60)
    return code2 == 0 and os.path.exists(output_path) and os.path.getsize(output_path) > 10000


def scan_youtube_shorts():
    all_videos = []
    for query in SEARCH_QUERIES:
        out, _, _ = run(f'yt-dlp --flat-playlist --dump-json --playlist-end 10 "{query}" 2>/dev/null', timeout=30)
        for line in out.split('\n'):
            if not line.startswith('{'):
                continue
            try:
                d = json.loads(line)
                dur = d.get('duration', 0) or 0
                if MIN_DURATION <= dur <= MAX_DURATION:
                    all_videos.append({
                        'url': d.get('webpage_url') or d.get('url', ''),
                        'id': d.get('id', ''),
                        'title': (d.get('title') or '')[:100],
                        'duration': int(dur),
                        'views': d.get('view_count', 0) or 0,
                    })
            except:
                pass

    seen = set()
    unique = []
    for v in sorted(all_videos, key=lambda x: x['views'], reverse=True):
        if v['id'] not in seen and v['url']:
            seen.add(v['id'])
            unique.append(v)
    return unique[:MAX_VIDEOS]


def download_video(url, vid_id):
    outpath = os.path.join(TEMP_DIR, f"{vid_id}.mp4")
    cmd = f'yt-dlp -f "best[ext=mp4][height<=1080]" -o "{outpath}" --no-warnings "{url}" 2>&1'
    _, _, code = run(cmd, timeout=60)
    if os.path.exists(outpath) and os.path.getsize(outpath) > 10000:
        return outpath
    return None


def uniquify(input_path, output_path):
    """Visual transforms only (no audio)"""
    transforms = ["mirror", "speed", "color", "zoom"]
    chosen = random.sample(transforms, k=random.randint(2, 3))

    vf = []

    if "mirror" in chosen:
        vf.append("hflip")
    if "speed" in chosen:
        f = round(random.uniform(0.93, 1.07), 2)
        vf.append(f"setpts={1/f:.4f}*PTS")
    if "color" in chosen:
        vf.append(f"hue=h={round(random.uniform(-5,5),1)}:s={round(random.uniform(0.95,1.05),2)}")
    if "zoom" in chosen:
        z = round(random.uniform(1.02, 1.05), 2)
        vf.append(f"scale=iw*{z}:ih*{z},crop=iw/{z}:ih/{z}")

    vf_s = ",".join(vf)

    cmd = f'ffmpeg -y -i "{input_path}" -vf "{vf_s}" -an -c:v libx264 -preset fast -crf 23 "{output_path}" 2>&1'
    _, _, code = run(cmd, timeout=120)
    return code == 0 and os.path.exists(output_path) and os.path.getsize(output_path) > 10000


def publish(filename, title, duration):
    desc = f"{title}. Полезный лайфхак на каждый день. #лайфхак #советы #masterhacks"
    safe_t = title.replace("'", "\\'")
    safe_d = desc.replace("'", "\\'")
    fhash = hashlib.md5(filename.encode()).hexdigest()

    sql = f"INSERT INTO videos (telegram_id, file_hash, filename, title, description, duration, status, created_at) VALUES (5405885462, '{fhash}', '{filename}', '{safe_t}', '{safe_d}', {duration}, 'approved', NOW());"

    with open("/tmp/insert_vid.sql", "w") as f:
        f.write(sql + "\n")

    _, err, code = run("mysql -u root masterhacks < /tmp/insert_vid.sql 2>&1")
    return code == 0


def main():
    print("=" * 50)
    print(f"[{datetime.now().strftime('%H:%M:%S')}] Trend Pipeline v2.1 (no audio)")
    print("=" * 50)

    print("\n[1/4] Сканирую YouTube Shorts...")
    videos = scan_youtube_shorts()
    print(f"  Найдено: {len(videos)}")
    for v in videos:
        print(f"  [{v['duration']}s] {v['title'][:55]}")

    if not videos:
        print("  Пусто. Выход.")
        return

    processed = 0
    for v in videos:
        print(f"\n[2/4] Скачиваю: {v['title'][:50]}...")
        raw = download_video(v['url'], v['id'])
        if not raw:
            print("  [!] Скачивание не удалось")
            continue

        # Check for speech
        speech = has_speech(raw)
        print(f"  Аудио: {'есть речь' if speech else 'без речи / без звука'}")

        # Strip audio regardless — we want wordless videos
        muted = os.path.join(TEMP_DIR, f"{v['id']}_muted.mp4")
        if not strip_audio(raw, muted):
            print("  [!] Не удалось убрать звук")
            os.remove(raw)
            continue

        unique_name = f"trend_{int(time.time())}_{random.randint(1000,9999)}.mp4"
        unique_path = os.path.join(MEDIA_DIR, unique_name)

        print(f"[3/4] Уникализация (без звука)...")
        if not uniquify(muted, unique_path):
            print("  [!] Не удалось")
            os.remove(raw)
            os.remove(muted)
            continue

        out, _, _ = run(f'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "{unique_path}"')
        try:
            final_dur = int(float(out.strip()))
        except:
            final_dur = v['duration']

        print(f"[4/4] Публикация: {unique_name} ({final_dur}s)")
        title = random.choice(TITLES)
        if publish(unique_name, title, final_dur):
            print(f"  ✓ «{title}»")
            processed += 1
        else:
            os.remove(unique_path)

        os.remove(raw)
        os.remove(muted)

    print(f"\n{'=' * 50}")
    print(f"Готово! Опубликовано: {processed}")
    print(f"{'=' * 50}")


if __name__ == '__main__':
    main()
