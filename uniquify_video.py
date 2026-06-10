#!/usr/bin/env python3
"""Deep Uniquifier — usage: python3 uniquify_video.py [--hard] <filename>"""

import subprocess, os, sys, random

MEDIA_DIR = "/var/www/masterhacks.ru/media"

def run(cmd, timeout=180):
    try:
        r = subprocess.run(cmd, shell=True, capture_output=True, text=True, timeout=timeout)
        return r.returncode, r.stderr[-500:] if r.stderr else ""
    except subprocess.TimeoutExpired:
        return -1, "timeout"
    except Exception as e:
        return -1, str(e)

def deep_uniquify(input_path, output_path, hard=False):
    d = subprocess.run(f'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "{input_path}"', shell=True, capture_output=True, text=True).stdout.strip()
    try: duration = float(d)
    except: duration = 30

    vf = []
    af = []

    if random.random() > 0.5:
        vf.append("hflip")
    
    if hard:
        speed = round(random.uniform(0.85, 0.92) if random.random() > 0.5 else random.uniform(1.08, 1.15), 3)
    else:
        speed = round(random.uniform(0.93, 0.97) if random.random() > 0.5 else random.uniform(1.03, 1.07), 3)
    vf.append(f"setpts={1/speed:.4f}*PTS")
    af.append(f"atempo={speed:.4f}")

    hue_shift = round(random.uniform(-10, 10), 1) if hard else round(random.uniform(-5, 5), 1)
    sat_range = (0.85, 1.15) if hard else (0.92, 1.08)
    bright_range = (-0.06, 0.06) if hard else (-0.03, 0.03)
    vf.append(f"hue=h={hue_shift}:s={round(random.uniform(*sat_range),2)}:b={round(random.uniform(*bright_range),2)}")
    
    contrast_range = (0.90, 1.12) if hard else (0.96, 1.06)
    gamma_range = (0.94, 1.06) if hard else (0.97, 1.03)
    vf.append(f"eq=contrast={round(random.uniform(*contrast_range),2)}:gamma={round(random.uniform(*gamma_range),2)}")
    
    if hard:
        vf.append(f"eq=brightness={round(random.uniform(-0.08, 0.08),2)}")

    z = round(random.uniform(1.03, 1.07), 3) if hard else round(random.uniform(1.02, 1.05), 3)
    vf.append(f"scale=iw*{z}:ih*{z},crop=iw/{z}:ih/{z}")

    r = round(random.uniform(-2.5, 2.5), 2) if hard else round(random.uniform(-1.5, 1.5), 2)
    crop_amt = 8 if hard else 4
    vf.append(f"rotate={r}*PI/180:fillcolor=black,crop=iw-{crop_amt}:ih-{crop_amt}")

    noise_strength = random.randint(4, 8) if hard else random.randint(2, 5)
    vf.append(f"noise=alls={noise_strength}:allf=t+u")

    pitch_down = (0.90, 0.96) if hard else (0.94, 0.97)
    pitch_up = (1.04, 1.10) if hard else (1.03, 1.06)
    pitch = round(random.uniform(*pitch_down) if random.random() > 0.5 else random.uniform(*pitch_up), 3)
    af.append(f"rubberband=pitch={pitch:.4f}")
    af.append("bass=g=2:f=80:w=0.3")
    
    if hard:
        af.append("treble=g=3:f=3000:w=0.3")

    vf.append("unsharp=5:5:0.8:5:5:0.0" if hard else "unsharp=3:3:0.5:3:3:0.0")

    if hard:
        vf.append(f"chromashift=crh={random.randint(1,3)}:crv={random.randint(1,3)}")
        vf.append("vignette=PI/4")
        cp = random.randint(2, 4)
        vf.append(f"crop=iw-{cp*2}:ih-{cp*2}")
        fade_in = round(random.uniform(0.3, 0.7), 1)
        fade_out = round(random.uniform(0.3, 0.7), 1)
        vf.append(f"fade=t=in:st=0:d={fade_in}")
        vf.append(f"fade=t=out:st={duration-fade_out-0.1}:d={fade_out}")
        vf.append("curves=all=0/0 0.5/0.55 1/1")
        vf.append("noise=alls=6:allf=t+u")

    vf_s = ",".join(vf)
    af_s = ",".join(af)

    crf = random.randint(28, 33) if hard else random.randint(26, 29)
    bitrate = random.choice([80, 96]) if hard else random.choice([96, 112, 128])

    cmd = (
        f'ffmpeg -y -i "{input_path}" -vf "{vf_s}" -af "{af_s}" '
        f'-c:v libx264 -preset medium -crf {crf} '
        f'-c:a aac -b:a {bitrate}k '
        f'-movflags +faststart -map_metadata -1 '
        f'"{output_path}" 2>&1'
    )
    rc, err = run(cmd, timeout=300 if hard else 180)

    if rc == 0 and os.path.exists(output_path) and os.path.getsize(output_path) > 5000:
        d2 = subprocess.run(f'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "{output_path}"', shell=True, capture_output=True, text=True).stdout.strip()
        try: new_dur = int(float(d2))
        except: new_dur = int(duration)
        return True, new_dur
    return False, 0

def main():
    args = sys.argv[1:]
    hard = False
    if "--hard" in args:
        hard = True
        args.remove("--hard")
    
    if len(args) < 1:
        print("Usage: uniquify_video.py [--hard] <filename>")
        sys.exit(1)

    filename = args[0]
    input_path = os.path.join(MEDIA_DIR, filename)

    if not os.path.exists(input_path):
        print(f"ERROR: file not found: {input_path}")
        sys.exit(1)

    mode = "HARD" if hard else "NORMAL"
    print(f"Processing [{mode}]: {filename} ({os.path.getsize(input_path)/1024:.0f} KB)")

    tmp = os.path.join(MEDIA_DIR, f"uniq_{random.randint(1000,9999)}.mp4")
    success, new_dur = deep_uniquify(input_path, tmp, hard=hard)

    if not success:
        print("ERROR: uniquify failed")
        sys.exit(1)

    os.rename(tmp, input_path)
    print(f"SUCCESS: {os.path.getsize(input_path)/1024:.0f} KB, {new_dur}s")

if __name__ == '__main__':
    main()
