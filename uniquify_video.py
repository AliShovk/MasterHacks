#!/usr/bin/env python3
"""Deep Uniquifier — usage: python3 uniquify_video.py <filename> [duration]"""

import subprocess, os, sys, random

MEDIA_DIR = "/var/www/masterhacks.ru/media"

def run(cmd, timeout=180):
    try:
        r = subprocess.run(cmd, shell=True, capture_output=True, text=True, timeout=timeout)
        return r.returncode, r.stderr[-300:]
    except:
        return -1, "error"

def deep_uniquify(input_path, output_path):
    # Get input duration
    d = subprocess.run(f'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "{input_path}"', shell=True, capture_output=True, text=True).stdout.strip()
    try: duration = float(d)
    except: duration = 30

    # Mirror 50%
    vf = ["hflip"] if random.random() > 0.5 else []
    
    # Speed +-4-8%
    speed = round(random.uniform(0.93, 0.97) if random.random() > 0.5 else random.uniform(1.03, 1.07), 3)
    vf.append(f"setpts={1/speed:.4f}*PTS")
    af = [f"atempo={speed:.4f}"]

    # Color
    vf.append(f"hue=h={round(random.uniform(-5,5),1)}:s={round(random.uniform(0.92,1.08),2)}:b={round(random.uniform(-0.03,0.03),2)}")
    vf.append(f"eq=contrast={round(random.uniform(0.96,1.06),2)}:gamma={round(random.uniform(0.97,1.03),2)}")

    # Zoom
    z = round(random.uniform(1.02, 1.05), 3)
    vf.append(f"scale=iw*{z}:ih*{z},crop=iw/{z}:ih/{z}")

    # Rotate
    r = round(random.uniform(-1.5, 1.5), 2)
    vf.append(f"rotate={r}*PI/180:fillcolor=black,crop=iw-4:ih-4")

    # Noise
    vf.append(f"noise=alls={random.randint(2,5)}:allf=t+u")

    # Audio pitch +-3-6%
    pitch = round(random.uniform(0.94, 0.97) if random.random() > 0.5 else random.uniform(1.03, 1.06), 3)
    af.append(f"rubberband=pitch={pitch:.4f}")
    af.append("bass=g=2:f=80:w=0.3")

    # Sharpen
    vf.append("unsharp=3:3:0.5:3:3:0.0")

    vf_s = ",".join(vf)
    af_s = ",".join(af)

    cmd = (
        f'ffmpeg -y -i "{input_path}" -vf "{vf_s}" -af "{af_s}" '
        f'-c:v libx264 -preset medium -crf {random.randint(26,29)} '
        f'-c:a aac -b:a {random.choice([96,112,128])}k '
        f'-movflags +faststart -map_metadata -1 '
        f'"{output_path}" 2>&1'
    )
    rc, err = run(cmd, timeout=180)

    if rc == 0 and os.path.exists(output_path) and os.path.getsize(output_path) > 5000:
        d2 = subprocess.run(f'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "{output_path}"', shell=True, capture_output=True, text=True).stdout.strip()
        try: new_dur = int(float(d2))
        except: new_dur = int(duration)
        return True, new_dur
    return False, 0

def main():
    if len(sys.argv) < 2:
        print("Usage: uniquify_video.py <filename>")
        sys.exit(1)

    filename = sys.argv[1]
    input_path = os.path.join(MEDIA_DIR, filename)

    if not os.path.exists(input_path):
        print(f"ERROR: file not found: {input_path}")
        sys.exit(1)

    print(f"Processing: {filename} ({os.path.getsize(input_path)/1024:.0f} KB)")

    tmp = os.path.join(MEDIA_DIR, f"uniq_{random.randint(1000,9999)}.mp4")
    success, new_dur = deep_uniquify(input_path, tmp)

    if not success:
        print("ERROR: uniquify failed")
        sys.exit(1)

    os.rename(tmp, input_path)
    print(f"SUCCESS: {os.path.getsize(input_path)/1024:.0f} KB, {new_dur}s")

if __name__ == '__main__':
    main()
