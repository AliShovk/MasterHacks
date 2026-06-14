#!/usr/bin/env python3
"""
Prepare Video Pipeline: AI title + hashtags + hard uniquify
Called from bot right after video upload.
Usage: python3 prepare_video.py <filename>
"""

import subprocess, os, sys, random, re

MEDIA_DIR = "/var/www/masterhacks.ru/media"
SCRIPT_DIR = "/var/www/masterhacks.ru"

TITLES = [
    "Ты точно этого не знал",
    "Секрет, который скрывают",
    "Попробуй этот трюк сейчас",
    "Гениально и просто",
    "Лайфхак на все случаи",
    "Мало кто догадывается",
    "Работает безотказно",
    "То, чему не учат в школе",
    "Хитрость профи",
    "Сделай так же — результат instantly",
    "Результат за секунду",
    "Скрытая функция твоего телефона",
    "Проверено — работает",
    "Совет бывалых",
    "Быстро и легко",
    "Мгновенный эффект",
    "Проще некуда",
    "Секрет мастеров",
    "Находка дня",
    "Бери и делай",
    "Срочно попробуй это",
    "Никто не говорит об этом",
    "Знание, которое изменит всё",
    "Это должен знать каждый",
    "Скрытая правда о...",
    "Трюк за 5 секунд",
    "Редкое знание",
    "Система работает идеально",
    "То, о чём молчат",
    "Сделай это прямо сейчас",
    "Лучшее решение проблемы",
    "Невероятный результат",
    "Вещь, которую ты упускал",
    "Об этом молчат мастера",
]

TAG_POOLS = {
    "main": ["#masterhacks", "#лайфхак"],
    "tech": ["#техника", "#гаджеты", "#секрет", "#фишка", "#смартфон"],
    "home": ["#дом", "#ремонт", "#порядок", "#чистота", "#экономия"],
    "kitchen": ["#кухня", "#готовка", "#еда", "#рецепты"],
    "health": ["#здоровье", "#красота", "#уход", "#спорт"],
    "auto": ["#авто", "#ремонт", "#вождение"],
    "work": ["#работа", "#продуктивность", "#карьера"],
    "money": ["#деньги", "#экономия", "#бюджет"],
}

def run(cmd, timeout=300):
    try:
        r = subprocess.run(cmd, shell=True, capture_output=True, text=True, timeout=timeout)
        return r.returncode, r.stderr[-500:] if r.stderr else ""
    except subprocess.TimeoutExpired:
        return -1, "timeout"

def db_query(sql):
    r = subprocess.run(
        ["mysql", "-u", "root", "masterhacks", "-N", "-e", sql],
        capture_output=True, text=True
    )
    return r.stdout.strip()

def db_exec(sql):
    subprocess.run(
        ["mysql", "-u", "root", "masterhacks", "-e", sql],
        capture_output=True
    )

def generate_title():
    return random.choice(TITLES)

def generate_tags():
    tags = ["#masterhacks", "#лайфхак"]
    categories = random.sample(list(TAG_POOLS.keys()), k=random.randint(2, 3))
    for cat in categories:
        if cat == "main":
            continue
        tag = random.choice(TAG_POOLS[cat])
        if tag not in tags:
            tags.append(tag)
    seen = set()
    result = []
    for t in tags:
        if t not in seen:
            seen.add(t)
            result.append(t)
    return " ".join(result[:6])

def main():
    if len(sys.argv) < 2:
        print("Usage: prepare_video.py <filename>")
        sys.exit(1)

    filename = sys.argv[1]
    input_path = os.path.join(MEDIA_DIR, filename)

    if not os.path.exists(input_path):
        print(f"ERROR: file not found: {input_path}")
        sys.exit(1)

    print(f"[prepare] {filename} ({os.path.getsize(input_path)/1024:.0f} KB)")

    title = generate_title()
    print(f"[1/3] Title: {title}")

    tags = generate_tags()
    print(f"[2/3] Tags: {tags}")

    safe_title = title.replace("'", "\'")
    safe_tags = tags.replace("'", "\'")
    safe_fn = filename.replace("'", "\'")
    
    db_exec(f"UPDATE videos SET title='{safe_title}', tags='{safe_tags}' WHERE filename='{safe_fn}'")

    print(f"[3/3] Uniquifying (hard mode)...")
    uniquify_script = os.path.join(SCRIPT_DIR, "uniquify_video.py")
    rc, err = run(f"python3 {uniquify_script} --hard {filename}", timeout=300)

    if rc == 0:
        import hashlib
        with open(input_path, "rb") as f:
            new_hash = hashlib.md5(f.read()).hexdigest()
        db_exec(f"UPDATE videos SET file_hash='{new_hash}' WHERE filename='{safe_fn}'")
        print(f"[prepare] DONE: title={title}, tags={tags}, hash={new_hash}")
    else:
        print(f"[prepare] WARNING: uniquify failed (rc={rc}), but title+tags saved")
        print(f"  stderr: {err[:200]}")

if __name__ == "__main__":
    main()
