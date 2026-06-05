#!/usr/bin/env python3
"""
Trend Pipeline v3: YouTube Shorts → Download → Strip voice → Add music → Uniquify → Publish (pending)
"""

import subprocess, os, json, random, hashlib, time
from datetime import datetime

MEDIA_DIR = "/var/www/masterhacks.ru/media"
TEMP_DIR = "/tmp/masterhacks_trends"
MUSIC_DIR = "/var/www/masterhacks.ru/assets/music"
os.makedirs(TEMP_DIR, exist_ok=True)
os.makedirs(MEDIA_DIR, exist_ok=True)
os.makedirs(MUSIC_DIR, exist_ok=True)

SEARCH_QUERIES = [
    "ytsearch8:#shorts lifehack",
    "ytsearch8:#shorts diy",
    "ytsearch8:#shorts tricks",
    "ytsearch8:#shorts howto",
    "ytsearch8:#shorts tips",
    "ytsearch8:#shorts satisfying",
    "ytsearch8:#shorts craft",
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


def generate_music(duration_sec, output_path):
    """
    Generate royalty-free ambient background music using ffmpeg.
    Layered sine waves at different frequencies for a pleasant, generic sound.
    """
    dur = max(duration_sec, 5)
    # Base: warm pad (low freq sine + harmonics), subtle rhythm
    # Multiple sine waves at musical intervals, mixed at low volume
    cmd = (
        f'ffmpeg -y -f lavfi -i '
        f'"sine=frequency=220:duration={dur}:sample_rate=44100,'
        f'volume=0.08" '
        f'-f lavfi -i '
        f'"sine=frequency=330:duration={dur}:sample_rate=44100,'
        f'volume=0.06" '
        f'-f lavfi -i '
        f'"sine=frequency=440:duration={dur}:sample_rate=44100,'
        f'volume=0.04" '
        f'-filter_complex "[0:a][1:a][2:a]amix=inputs=3:duration=first:dropout_transition=2,'
        f'afade=t=in:d=1,afade=t=out:st={dur-2}:d=2,'
        f'volume=0.5" '
        f'-ac 1 -b:a 64k "{output_path}" 2>&1'
    )
    _, _, code = run(cmd, timeout=30)
    return code == 0 and os.path.exists(output_path) and os.path.getsize(output_path) > 1000


def scan_youtube_shorts():
    all_videos = []
    for query in SEARCH_QUERIES:
        out, _, _ = run(f'yt-dlp --flat-playlist --dump-json --playlist-end 8 "{query}" 2>/dev/null', timeout=30)
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


def uniquify(input_path, output_path, music_path):
    """Visual transforms + mix with background music, no original audio"""
    transforms = ["mirror", "speed", "color", "zoom"]
    chosen = random.sample(transforms, k=random.randint(2, 3))
    vf = []
    speed_factor = 1.0

    if "mirror" in chosen:
        vf.append("hflip")
    if "speed" in chosen:
        speed_factor = round(random.uniform(0.93, 1.07), 2)
        vf.append(f"setpts={1/speed_factor:.4f}*PTS")
    if "color" in chosen:
        vf.append(f"hue=h={round(random.uniform(-5,5),1)}:s={round(random.uniform(0.95,1.05),2)}")
    if "zoom" in chosen:
        z = round(random.uniform(1.02, 1.05), 2)
        vf.append(f"scale=iw*{z}:ih*{z},crop=iw/{z}:ih/{z}")

    vf_s = ",".join(vf) if vf else "null"

    # Get video duration for music timing
    dur_out, _, _ = run(f'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "{input_path}"')
    try:
        vid_dur = float(dur_out.strip())
    except:
        vid_dur = 60

    # Adjust speed factor: if speed changed, video duration changes
    adjusted_dur = vid_dur / speed_factor

    # Mix: video (no audio) + generated music
    cmd = (
        f'ffmpeg -y '
        f'-i "{input_path}" '
        f'-i "{music_path}" '
        f'-filter_complex '
        f'"[0:v]{vf_s}[v];'
        f'[1:a]atrim=0:{adjusted_dur:.2f},afade=t=out:st={adjusted_dur-2}:d=2[a]" '
        f'-map "[v]" -map "[a]" '
        f'-c:v libx264 -preset fast -crf 23 '
        f'-c:a aac -b:a 96k '
        f'-shortest '
        f'"{output_path}" 2>&1'
    )
    _, _, code = run(cmd, timeout=120)
    return code == 0 and os.path.exists(output_path) and os.path.getsize(output_path) > 10000


def publish(filename, title, duration):
    """Insert video with status='pending' for moderation"""
    desc = f"{title}. Полезный лайфхак на каждый день. #лайфхак #советы #masterhacks"
    safe_t = title.replace("'", "\\'")
    safe_d = desc.replace("'", "\\'")
    fhash = hashlib.md5(filename.encode()).hexdigest()

    sql = f"INSERT INTO videos (telegram_id, file_hash, filename, title, description, duration, status, created_at) VALUES (5405885462, '{fhash}', '{filename}', '{safe_t}', '{safe_d}', {duration}, 'pending', NOW());"

    with open("/tmp/insert_vid.sql", "w") as f:
        f.write(sql + "\n")

    _, err, code = run("mysql -u root masterhacks < /tmp/insert_vid.sql 2>&1")
    return code == 0


def main():
    print("=" * 50)
    print(f"[{datetime.now().strftime('%H:%M:%S')}] Trend Pipeline v3 (music + pending)")
    print("=" * 50)

    print("\n[1/5] Сканирую YouTube Shorts...")
    videos = scan_youtube_shorts()
    print(f"  Найдено: {len(videos)}")
    for v in videos:
        print(f"  [{v['duration']}s] {v['title'][:55]}")

    if not videos:
        print("  Пусто. Выход.")
        return

    processed = 0
    for v in videos:
        print(f"\n[2/5] Скачиваю: {v['title'][:50]}...")
        raw = download_video(v['url'], v['id'])
        if not raw:
            print("  [!] Скачивание не удалось")
            continue

        # Generate background music
        music_file = os.path.join(TEMP_DIR, f"bgm_{v['id']}.m4a")
        print(f"[3/5] Генерирую фоновую музыку ({v['duration']}s)...")
        if not generate_music(v['duration'] + 5, music_file):
            print("  [!] Не удалось создать музыку")
            os.remove(raw)
            continue

        unique_name = f"trend_{int(time.time())}_{random.randint(1000,9999)}.mp4"
        unique_path = os.path.join(MEDIA_DIR, unique_name)

        print(f"[4/5] Уникализация + музыка...")
        if not uniquify(raw, unique_path, music_file):
            print("  [!] Не удалось")
            os.remove(raw)
            os.remove(music_file)
            continue

        out, _, _ = run(f'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "{unique_path}"')
        try:
            final_dur = int(float(out.strip()))
        except:
            final_dur = v['duration']

        print(f"[5/5] Публикация (pending): {unique_name} ({final_dur}s)")
        title = random.choice(TITLES)
        if publish(unique_name, title, final_dur):
            print(f"  ✓ «{title}» → на модерации")
            processed += 1
        else:
            os.remove(unique_path)

        os.remove(raw)
        os.remove(music_file)

    print(f"\n{'=' * 50}")
    print(f"Готово! На модерации: {processed}")
    print(f"{'=' * 50}")


if __name__ == '__main__':
    main()
