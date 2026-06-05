#!/usr/bin/env python3
"""
AI Video Pipeline v1: cellauto visuals → text overlay → synth music → 12-15s (pending)
Полностью AI-генерированный контент. Никакого внешнего контента.
"""

import subprocess, os, random, hashlib, time, textwrap
from datetime import datetime

MEDIA_DIR = "/var/www/masterhacks.ru/media"
TEMP_DIR = "/tmp/masterhacks_ai"
os.makedirs(TEMP_DIR, exist_ok=True)
os.makedirs(MEDIA_DIR, exist_ok=True)

W, H = 720, 1280  # вертикальный 9:16
FPS = 24

HACKS = [
    "Налей уксус в бачок унитаза на ночь — утром налёт снимется сам.",
    "Сложи футболки в рулон — в шкафу поместится в 2 раза больше.",
    "Заморозь лимонный сок в формочках — всегда свежий под рукой.",
    "Приклей магнит к шуруповёрту — саморезы не будут падать.",
    "Положи лавровый лист в муку — жучки не заведутся.",
    "Протри зеркало пеной для бритья — не запотеет на неделю.",
    "Надень резинку на банку краски — вытирай кисть об неё.",
    "Положи телефон в пустую чашку — динамик в 2 раза громче.",
    "Смажь петли дверей подсолнечным маслом — скрип исчезнет.",
    "Кипяти воду с лимонной кислотой — чайник как новый за 5 минут.",
    "Храни батарейки в холодильнике — на 30% дольше служат.",
    "Разогрей пиццу на сковороде под крышкой — хрустящая корочка.",
    "Заточи ножницы порезав фольгу — острые как новые за минуту.",
    "Насыпь соду в кроссовки на ночь — запах уйдёт полностью.",
    "Используй прищепку как держатель гвоздя — пальцы целы.",
    "Капни масло чайного дерева в стирку — полотенца без запаха.",
    "Заклей скотчем сверло при сверлении плитки — не соскользнёт.",
    "Вари макароны в меньшем объёме воды — соус кремовый.",
    "Храни зелень в стакане с водой как букет — свежая 2 недели.",
    "Заморозь вино в формочках для льда — в соус вместо бульона.",
    "Налей перекись в уши на 5 минут — пробки растворятся без боли.",
    "Прокипяти пригоревшую кастрюлю с содой — отмоется без усилий.",
    "Смажь молнию свечкой — заедать перестанет навсегда.",
    "Положи губку в микроволновку на минуту — бактерии погибнут.",
    "Сверни наушники восьмёркой — не запутаются в кармане.",
    "Положи мокрую газету в холодильник — запахи уйдут за 2 часа.",
    "Включи фен на холодный воздух — пыль с клавиатуры сдует.",
    "Закрась царапины на мебели грецким орехом — цвет идеально.",
    "Заморозь воду в пакете с застёжкой — многоразовый лёд готов.",
    "Привяжи ключи к винному поплавку — не утонут в воде.",
]

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

CELLAUTO_RULES = [30, 54, 60, 90, 110, 126, 150, 184]
COLOR_OVERLAYS = [
    "0x16213e@0.55",  # тёмно-синий
    "0x1e272e@0.55",  # почти чёрный
    "0x2c3e50@0.55",  # синий
    "0x2d3436@0.55",  # тёмно-серый
    "0x1a1a2e@0.50",  # очень тёмный синий
]


def run(cmd, timeout=90):
    try:
        r = subprocess.run(cmd, shell=True, capture_output=True, text=True, timeout=timeout)
        return r.returncode, r.stderr[-300:]
    except subprocess.TimeoutExpired:
        return -1, "timeout"


def create_background(output_path):
    """Абстрактный фон: cellauto + цветовой фильтр"""
    dur = VIDEO_DURATION
    rule = random.choice(CELLAUTO_RULES)
    seed = random.randint(1, 9999)
    color = random.choice(COLOR_OVERLAYS)
    fill = round(random.uniform(0.25, 0.4), 2)

    cmd = (
        f'ffmpeg -y -f lavfi '
        f'-i "cellauto=s={W}x{H}:rate={FPS}:seed={seed}:rule={rule}:random_fill_ratio={fill},'
        f'drawbox=x=0:y=0:w={W}:h={H}:color={color}:t=fill,'
        f'hue=h=0:s=1.5" '
        f'-c:v libx264 -preset ultrafast -crf 28 '
        f'-pix_fmt yuv420p -t {dur} '
        f'"{output_path}" 2>&1'
    )
    rc, err = run(cmd, timeout=60)
    return rc == 0 and os.path.exists(output_path) and os.path.getsize(output_path) > 5000


def create_music(output_path):
    """Синтез фоновой музыки: слоёные синусоиды"""
    dur = VIDEO_DURATION + 1
    freq = random.choice([130.8, 146.8, 164.8, 196, 220, 261.6, 293.7])

    cmd = (
        f'ffmpeg -y '
        f'-f lavfi -i "sine=frequency={freq}:duration={dur}:sample_rate=44100,volume=0.08" '
        f'-f lavfi -i "sine=frequency={freq*1.5}:duration={dur}:sample_rate=44100,volume=0.05" '
        f'-f lavfi -i "sine=frequency={freq*2}:duration={dur}:sample_rate=44100,volume=0.03" '
        f'-filter_complex '
        f'"[0:a][1:a][2:a]amix=inputs=3:duration=first:dropout_transition=2,'
        f'afade=t=in:d=1.5,afade=t=out:st={dur-2}:d=2,volume=0.6" '
        f'-ac 1 -b:a 64k "{output_path}" 2>&1'
    )
    rc, _ = run(cmd, timeout=30)
    return rc == 0 and os.path.exists(output_path)


def add_text(input_vid, text, output_path):
    """Наложение текста по центру с тенью и полупрозрачным фоном"""
    chars_per_line = 26
    lines = textwrap.wrap(text, width=chars_per_line, break_long_words=False)
    if len(lines) > 4:
        lines = lines[:4]
        lines[-1] = lines[-1][:chars_per_line-1] + "…"

    # Собираем фильтры для каждой строки
    y_center = H // 2 - (len(lines) - 1) * 30
    filters = []

    for i, line in enumerate(lines):
        y = y_center + i * 52
        escaped = line.replace("'", "\\'").replace(":", "\\:").replace(",", "\\,")
        # Text with shadow + semi-transparent background box
        f = (
            f"drawtext=text='{escaped}':"
            f"fontsize=34:fontcolor=white:"
            f"fontfile=/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf:"
            f"x=(w-text_w)/2:y={y}:"
            f"shadowcolor=black@0.8:shadowx=3:shadowy=3:"
            f"box=1:boxcolor=black@0.35:boxborderw=12"
        )
        filters.append(f)

    vf_chain = ",".join(filters)

    cmd = (
        f'ffmpeg -y -i "{input_vid}" '
        f'-vf "{vf_chain}" '
        f'-c:v libx264 -preset fast -crf 23 '
        f'-pix_fmt yuv420p -an '
        f'"{output_path}" 2>&1'
    )
    rc, _ = run(cmd, timeout=60)
    return rc == 0 and os.path.exists(output_path)


def mix_video_audio(video_path, audio_path, output_path):
    cmd = (
        f'ffmpeg -y '
        f'-i "{video_path}" -i "{audio_path}" '
        f'-c:v copy -c:a aac -b:a 96k -shortest '
        f'"{output_path}" 2>&1'
    )
    rc, _ = run(cmd, timeout=30)
    return rc == 0 and os.path.exists(output_path)


def publish(filename, title, description, duration):
    safe_t = title.replace("'", "\\'")
    safe_d = description.replace("'", "\\'")
    fhash = hashlib.md5(filename.encode()).hexdigest()

    sql = (
        f"INSERT INTO videos (telegram_id, file_hash, filename, title, description, duration, status, created_at) "
        f"VALUES (5405885462, '{fhash}', '{filename}', '{safe_t}', '{safe_d}', {duration}, 'pending', NOW());"
    )

    with open("/tmp/insert_vid.sql", "w") as f:
        f.write(sql + "\n")
    rc, _ = run("mysql -u root masterhacks < /tmp/insert_vid.sql 2>&1")
    return rc == 0


def main():
    global VIDEO_DURATION
    VIDEO_DURATION = random.randint(12, 15)

    print("=" * 50)
    print(f"[{datetime.now().strftime('%H:%M:%S')}] AI Video Pipeline v1")
    print(f"Длительность: {VIDEO_DURATION}s")
    print("=" * 50)

    hack = random.choice(HACKS)
    title = random.choice(TITLES)
    print(f"\n{hack}")

    # 1. Background
    print("[1/4] Генерация фона...")
    bg_file = os.path.join(TEMP_DIR, f"bg_{int(time.time())}.mp4")
    if not create_background(bg_file):
        print("  ✗ Фон не создался")
        return
    print("  ✓")

    # 2. Music
    print("[2/4] Синтез музыки...")
    music_file = os.path.join(TEMP_DIR, f"music_{int(time.time())}.m4a")
    if not create_music(music_file):
        print("  ✗ Музыка не создалась")
        return
    print("  ✓")

    # 3. Text
    print("[3/4] Наложение текста...")
    text_file = os.path.join(TEMP_DIR, f"text_{int(time.time())}.mp4")
    if not add_text(bg_file, hack, text_file):
        print("  ✗ Текст не наложился")
        return
    print("  ✓")

    # 4. Mix
    output_name = f"ai_{int(time.time())}_{random.randint(1000,9999)}.mp4"
    output_path = os.path.join(MEDIA_DIR, output_name)
    print(f"[4/4] Сборка → {output_name}")
    if not mix_video_audio(text_file, music_file, output_path):
        print("  ✗ Сборка не удалась")
        return

    # Get actual duration
    dur_cmd = f'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "{output_path}"'
    d = subprocess.run(dur_cmd, shell=True, capture_output=True, text=True).stdout.strip()
    try:
        final_dur = int(float(d))
    except:
        final_dur = VIDEO_DURATION

    # Publish
    desc = f"{hack} #лайфхак #советы #masterhacks"
    if publish(output_name, title, desc, final_dur):
        size_kb = os.path.getsize(output_path) / 1024
        print(f"\n  ✅ «{title}» — {final_dur}s, {size_kb:.0f}KB — на модерации")
    else:
        os.remove(output_path)
        print("  ✗ Публикация не удалась")

    # Cleanup
    for f in [bg_file, music_file, text_file]:
        if os.path.exists(f):
            os.remove(f)

    print(f"\n{'=' * 50}")
    print("Готово!")
    print(f"{'=' * 50}")


if __name__ == '__main__':
    main()
