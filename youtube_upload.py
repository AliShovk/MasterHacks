#!/usr/bin/env python3
"""
youtube_upload.py — Cross-post MasterHacks videos to YouTube
Usage: python3 youtube_upload.py [--video-id N] [--limit N] [--dry-run]
"""

import sys, json, time, argparse, urllib.request, urllib.parse, urllib.error, base64
from pathlib import Path

# ── OAuth credentials (from .env, never commit to git) ──
def _load_env():
    """Load variables from .env file into os.environ."""
    import os
    env_file = Path(__file__).parent / '.env'
    if env_file.exists():
        with open(env_file) as f:
            for line in f:
                line = line.strip()
                if line and not line.startswith('#') and '=' in line:
                    key, _, val = line.partition('=')
                    if key.strip() not in os.environ:
                        os.environ[key.strip()] = val.strip().strip('"').strip("'")
_load_env()

import os
CLIENT_ID     = os.getenv('YT_CLIENT_ID', '')
CLIENT_SECRET = os.getenv('YT_CLIENT_SECRET', '')
REFRESH_TOKEN = os.getenv('YT_REFRESH_TOKEN', '')

SITE_URL      = 'https://masterhacks.ru'
MEDIA_URL     = f'{SITE_URL}/media/'
TAGS          = ['MasterHacks', 'лайфхак', 'совет', 'DIY']
CATEGORY_ID   = '26'  # Howto & Style
PRIVACY       = 'public'

TOKEN_FILE    = '/tmp/yt_access_token.json'

# ── Database (via mysql CLI) ──
DB_USER = os.getenv('DB_USER', 'app_user')
DB_PASS = os.getenv('DB_PASS', '')
DB_NAME = os.getenv('DB_NAME', 'masterhacks')

def db_query(sql):
    """Run SQL and return output rows."""
    import subprocess
    cmd = ['mysql', '-u', DB_USER, f'-p{DB_PASS}', DB_NAME, '-N', '-e', sql]
    p = subprocess.run(cmd, capture_output=True, text=True, timeout=10)
    return p.stdout.strip().split('\n') if p.stdout.strip() else []

def db_execute(sql):
    """Run SQL without capturing output."""
    import subprocess
    cmd = ['mysql', '-u', DB_USER, f'-p{DB_PASS}', DB_NAME, '-e', sql]
    subprocess.run(cmd, capture_output=True, timeout=10)

# ── Token refresh ──
def get_access_token():
    """Get fresh access token from Google OAuth."""
    # Try cached token first
    if os.path.exists(TOKEN_FILE):
        try:
            with open(TOKEN_FILE) as f:
                cached = json.load(f)
            if cached.get('expires_at', 0) > time.time() + 60:
                return cached['access_token']
        except Exception:
            pass

    data = urllib.parse.urlencode({
        'client_id': CLIENT_ID,
        'client_secret': CLIENT_SECRET,
        'refresh_token': REFRESH_TOKEN,
        'grant_type': 'refresh_token'
    }).encode()

    req = urllib.request.Request('https://oauth2.googleapis.com/token', data=data)
    with urllib.request.urlopen(req, timeout=15) as resp:
        tok = json.loads(resp.read())

    access_token = tok['access_token']
    expires_in = tok.get('expires_in', 3600)

    with open(TOKEN_FILE, 'w') as f:
        json.dump({'access_token': access_token, 'expires_at': time.time() + expires_in}, f)

    return access_token


def upload_video(video_id, title, filename, description='', access_token=None):
    """Upload a single video to YouTube. Returns YouTube URL or None on failure."""
    if access_token is None:
        access_token = get_access_token()

    video_url = MEDIA_URL + filename

    # 1. Download video
    print(f"  Downloading: {video_url}")
    try:
        with urllib.request.urlopen(video_url, timeout=60) as resp:
            video_data = resp.read()
    except Exception as e:
        print(f"  ERROR downloading: {e}")
        return None

    file_size = len(video_data)
    print(f"  Size: {file_size/1024/1024:.1f} MB")

    # 2. Build metadata
    youtube_title = title if title else 'MasterHacks — полезный совет'
    # Truncate to YouTube limit (100 chars)
    if len(youtube_title) > 100:
        youtube_title = youtube_title[:97] + '...'

    youtube_desc = f"{title}\n\n"
    if description:
        youtube_desc += f"{description}\n\n"
    youtube_desc += f"🔗 Сайт: {SITE_URL}\n#MasterHacks #лайфхак"

    snippet = {
        'snippet': {
            'title': youtube_title,
            'description': youtube_desc,
            'tags': TAGS,
            'categoryId': CATEGORY_ID
        },
        'status': {
            'privacyStatus': PRIVACY,
            'selfDeclaredMadeForKids': False
        }
    }

    # 3. Create resumable upload session
    metadata = json.dumps(snippet).encode()
    req = urllib.request.Request(
        'https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status',
        data=metadata,
        method='POST'
    )
    req.add_header('Authorization', f'Bearer {access_token}')
    req.add_header('Content-Type', 'application/json; charset=UTF-8')
    req.add_header('X-Upload-Content-Length', str(file_size))
    req.add_header('X-Upload-Content-Type', 'video/mp4')

    try:
        with urllib.request.urlopen(req, timeout=15) as resp:
            upload_url = resp.headers.get('Location')
        if not upload_url:
            print(f"  ERROR: No upload URL in response")
            return None
    except urllib.error.HTTPError as e:
        print(f"  ERROR creating session: {e.code} {e.reason}")
        print(f"  Body: {e.read().decode()[:300]}")
        return None

    # 4. Upload video binary
    print(f"  Uploading to YouTube...")
    req2 = urllib.request.Request(upload_url, data=video_data, method='PUT')
    req2.add_header('Content-Type', 'video/mp4')

    try:
        with urllib.request.urlopen(req2, timeout=300) as resp:
            result = json.loads(resp.read())
    except urllib.error.HTTPError as e:
        print(f"  ERROR uploading: {e.code} {e.reason}")
        print(f"  Body: {e.read().decode()[:300]}")
        return None

    yt_id = result.get('id')
    if yt_id:
        youtube_url = f'https://youtube.com/watch?v={yt_id}'
        print(f"  ✓ Published: {youtube_url}")
        return youtube_url
    else:
        print(f"  ERROR: No video ID in response: {json.dumps(result)[:200]}")
        return None


def get_videos_to_post(limit=5, video_id=None):
    """Get videos that haven't been posted to YouTube yet."""
    if video_id:
        sql = f"SELECT id, title, filename, description FROM videos WHERE id={int(video_id)}"
    else:
        sql = (
            "SELECT id, title, filename, description FROM videos "
            "WHERE status='approved' AND file_type='video' "
            "AND youtube_posted = 0 "
            "ORDER BY views DESC LIMIT {}".format(int(limit))
        )
    rows = db_query(sql)
    videos = []
    for row in rows:
        if '\t' in row:
            parts = row.split('\t')
            videos.append({
                'id': int(parts[0]),
                'title': parts[1] if len(parts) > 1 and parts[1] != 'NULL' else '',
                'filename': parts[2] if len(parts) > 2 else '',
                'description': parts[3] if len(parts) > 3 and parts[3] != 'NULL' else ''
            })
    return videos


def mark_posted(video_id, youtube_url):
    """Update DB with YouTube URL and mark as posted."""
    safe_url = youtube_url.replace("'", "\\'")
    db_execute(f"UPDATE videos SET youtube_url='{safe_url}', youtube_posted=1 WHERE id={int(video_id)}")


def main():
    parser = argparse.ArgumentParser(description='Cross-post MasterHacks videos to YouTube')
    parser.add_argument('--video-id', type=int, help='Upload specific video by ID')
    parser.add_argument('--limit', type=int, default=5, help='Max videos to upload (default: 5)')
    parser.add_argument('--dry-run', action='store_true', help='Show which videos would be posted without uploading')
    args = parser.parse_args()

    videos = get_videos_to_post(limit=args.limit, video_id=args.video_id)
    print(f"Found {len(videos)} videos to post\n")

    if args.dry_run:
        for v in videos:
            print(f"  [{v['id']}] {v['title']}")
        return

    token = get_access_token()
    print(f"Access token obtained (length: {len(token)})\n")

    success = 0
    for v in videos:
        print(f"[{v['id']}] {v['title']}")
        yt_url = upload_video(v['id'], v['title'], v['filename'], v['description'], token)
        if yt_url:
            mark_posted(v['id'], yt_url)
            success += 1
        print()

    print(f"Done: {success}/{len(videos)} uploaded successfully")


if __name__ == '__main__':
    main()
