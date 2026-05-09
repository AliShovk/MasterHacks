<?php
require_once __DIR__ . '/../config/database.php';

$pdo = getDatabaseConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($id > 0) {
        if ($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE videos SET status='approved', published_at=NOW(), updated_at=NOW() WHERE id=:id");
            $stmt->execute([':id' => $id]);
        } elseif ($action === 'reject') {
            $stmt = $pdo->prepare("UPDATE videos SET status='rejected', updated_at=NOW() WHERE id=:id");
            $stmt->execute([':id' => $id]);
        }
    }

    header('Location: moderation.php');
    exit;
}

$stmt = $pdo->query("SELECT v.*, a.username, a.first_name FROM videos v LEFT JOIN authors a ON a.telegram_id=v.telegram_id WHERE v.status='pending' ORDER BY v.created_at DESC LIMIT 200");
$pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

?><!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Модерация — MasterHacks</title>
    <style>
        body{font-family:Arial,sans-serif;background:#0f0f0f;color:#fff;margin:0;padding:20px}
        h1{margin:0 0 16px}
        .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px}
        .card{background:#1a1a1a;border:1px solid rgba(255,255,255,.08);border-radius:12px;overflow:hidden}
        .media{background:#000;aspect-ratio:9/16;display:flex;align-items:center;justify-content:center}
        video,img{max-width:100%;max-height:100%}
        .media video,.media img{width:100%;height:100%;object-fit:cover;display:block}
        .media-btn{width:100%;height:100%;padding:0;margin:0;border:0;background:transparent;cursor:pointer;display:flex;align-items:center;justify-content:center}
        .meta{padding:12px}
        .row{display:flex;gap:8px;flex-wrap:wrap;align-items:center;justify-content:space-between}
        .small{opacity:.75;font-size:12px}
        .btn{border:0;border-radius:10px;padding:10px 12px;cursor:pointer;font-weight:700}
        .approve{background:#2ecc71;color:#05210f}
        .reject{background:#e74c3c;color:#fff}
        .id{font-family:monospace;opacity:.9}
        form{margin:0}
        .preview-overlay{position:fixed;inset:0;background:rgba(0,0,0,.85);display:none;align-items:center;justify-content:center;padding:20px;z-index:9999}
        .preview-box{max-width:min(920px,95vw);width:100%}
        .preview-inner{background:#000;border-radius:14px;overflow:hidden;border:1px solid rgba(255,255,255,.12)}
        .preview-inner video,.preview-inner img{width:100%;max-height:75vh;height:auto;display:block;object-fit:contain}
        .preview-close{position:fixed;top:14px;right:14px;width:42px;height:42px;border:0;border-radius:12px;background:rgba(255,255,255,.12);color:#fff;font-size:26px;line-height:42px;cursor:pointer;z-index:10000}
        .preview-nav{position:fixed;top:50%;transform:translateY(-50%);width:42px;height:42px;border:0;border-radius:12px;background:rgba(255,255,255,.12);color:#fff;font-size:22px;line-height:42px;cursor:pointer;z-index:10000}
        .preview-prev{left:14px}
        .preview-next{right:66px}
        .thumbs{margin-top:12px;display:flex;gap:10px;overflow-x:auto;padding:10px;background:rgba(255,255,255,.06);border-radius:12px;border:1px solid rgba(255,255,255,.08)}
        .thumb{flex:0 0 auto;width:72px;height:120px;border:1px solid rgba(255,255,255,.14);border-radius:10px;background:#000;overflow:hidden;padding:0;cursor:pointer}
        .thumb.active{border-color:#fc7b07;box-shadow:0 0 0 2px rgba(252,123,7,.25)}
        .thumb video,.thumb img{width:100%;height:100%;object-fit:cover;display:block}
    </style>
</head>
<body>
    <h1>Модерация (pending)</h1>
    <div class="small" style="margin-bottom:16px;">Показываются только записи со статусом <b>pending</b>. После approve они появятся в <code>/index_db.php</code>.</div>

    <?php if (!$pending): ?>
        <div class="small">Нет материалов на модерации.</div>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($pending as $i => $v): ?>
                <div class="card">
                    <div class="media">
                        <?php $isVideo = (($v['file_type'] ?? '') === 'video') || (isset($v['filename']) && preg_match('/\.(mp4|webm|mov|m4v)$/i', (string)$v['filename'])); ?>
                        <?php if ($isVideo): ?>
                            <button class="media-btn" type="button" onclick="openPreview(<?= (int)$i ?>)">
                                <video src="../media/<?= htmlspecialchars($v['filename']) ?>" muted playsinline preload="metadata"></video>
                            </button>
                        <?php else: ?>
                            <button class="media-btn" type="button" onclick="openPreview(<?= (int)$i ?>)">
                                <img src="../media/<?= htmlspecialchars($v['filename']) ?>" alt="">
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="meta">
                        <div class="row">
                            <div class="id">#<?= (int)$v['id'] ?></div>
                            <div class="small">
                                <?= htmlspecialchars(($v['username'] ? '@'.$v['username'] : ($v['first_name'] ?? '')) ?: 'Автор') ?>
                            </div>
                        </div>
                        <div class="small" style="margin:8px 0 12px;"><?= htmlspecialchars($v['created_at'] ?? '') ?></div>
                        <div class="row">
                            <form method="post">
                                <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
                                <input type="hidden" name="action" value="approve">
                                <button class="btn approve" type="submit">APPROVE</button>
                            </form>
                            <form method="post">
                                <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
                                <input type="hidden" name="action" value="reject">
                                <button class="btn reject" type="submit">REJECT</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="preview-overlay" id="previewOverlay" onclick="closePreview(event)">
        <button class="preview-close" type="button" onclick="closePreview(event)">×</button>
        <button class="preview-nav preview-prev" type="button" onclick="prevPreview(event)">‹</button>
        <button class="preview-nav preview-next" type="button" onclick="nextPreview(event)">›</button>
        <div class="preview-box" onclick="stopPreviewClick(event)">
            <div class="preview-inner" id="previewInner"></div>
            <div class="thumbs" id="previewThumbs"></div>
        </div>
    </div>

    <script>
        const PREVIEW_ITEMS = [
            <?php foreach ($pending as $v): ?>
                {
                    type: <?= json_encode(((($v['file_type'] ?? '') === 'video') || (isset($v['filename']) && preg_match('/\.(mp4|webm|mov|m4v)$/i', (string)$v['filename']))) ? 'video' : 'image') ?>,
                    src: <?= json_encode('../media/' . (string)($v['filename'] ?? '')) ?>,
                    id: <?= (int)($v['id'] ?? 0) ?>
                },
            <?php endforeach; ?>
        ];

        let currentPreviewIndex = -1;
        let thumbsBuilt = false;

        function stopPreviewClick(e) {
            if (!e) return;
            e.preventDefault();
            e.stopPropagation();
        }

        function buildThumbs() {
            if (thumbsBuilt) return;
            const thumbs = document.getElementById('previewThumbs');
            if (!thumbs) return;
            thumbs.innerHTML = '';

            PREVIEW_ITEMS.forEach((it, idx) => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'thumb';
                b.onclick = (e) => {
                    stopPreviewClick(e);
                    openPreview(idx);
                };

                if (it.type === 'video') {
                    const v = document.createElement('video');
                    v.src = it.src;
                    v.muted = true;
                    v.playsInline = true;
                    v.preload = 'metadata';
                    b.appendChild(v);
                } else {
                    const img = document.createElement('img');
                    img.src = it.src;
                    img.alt = '';
                    b.appendChild(img);
                }

                thumbs.appendChild(b);
            });
            thumbsBuilt = true;
        }

        function setActiveThumb(idx) {
            const thumbs = document.getElementById('previewThumbs');
            if (!thumbs) return;
            const nodes = thumbs.querySelectorAll('.thumb');
            nodes.forEach((n, i) => {
                if (i === idx) n.classList.add('active');
                else n.classList.remove('active');
            });
            const active = nodes[idx];
            if (active && typeof active.scrollIntoView === 'function') {
                active.scrollIntoView({ block: 'nearest', inline: 'center' });
            }
        }

        function renderPreview() {
            const overlay = document.getElementById('previewOverlay');
            const inner = document.getElementById('previewInner');
            if (!overlay || !inner) return;

            const it = PREVIEW_ITEMS[currentPreviewIndex];
            if (!it) return;

            inner.innerHTML = '';
            if (it.type === 'video') {
                const v = document.createElement('video');
                v.src = it.src;
                v.controls = true;
                v.playsInline = true;
                v.autoplay = true;
                v.preload = 'auto';
                inner.appendChild(v);
            } else {
                const img = document.createElement('img');
                img.src = it.src;
                img.alt = '';
                inner.appendChild(img);
            }

            buildThumbs();
            setActiveThumb(currentPreviewIndex);
            overlay.style.display = 'flex';
        }

        function openPreview(index) {
            const i = Number(index);
            if (!Number.isFinite(i) || i < 0 || i >= PREVIEW_ITEMS.length) return;
            currentPreviewIndex = i;
            renderPreview();
        }

        function prevPreview(e) {
            stopPreviewClick(e);
            if (PREVIEW_ITEMS.length === 0) return;
            if (currentPreviewIndex <= 0) currentPreviewIndex = PREVIEW_ITEMS.length - 1;
            else currentPreviewIndex -= 1;
            renderPreview();
        }

        function nextPreview(e) {
            stopPreviewClick(e);
            if (PREVIEW_ITEMS.length === 0) return;
            if (currentPreviewIndex >= PREVIEW_ITEMS.length - 1) currentPreviewIndex = 0;
            else currentPreviewIndex += 1;
            renderPreview();
        }

        function closePreview(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            const overlay = document.getElementById('previewOverlay');
            const inner = document.getElementById('previewInner');
            if (inner) inner.innerHTML = '';
            if (overlay) overlay.style.display = 'none';
            currentPreviewIndex = -1;
        }

        document.addEventListener('keydown', (e) => {
            const overlay = document.getElementById('previewOverlay');
            if (!overlay || overlay.style.display !== 'flex') return;
            if (e.key === 'Escape') closePreview(e);
            if (e.key === 'ArrowLeft') prevPreview(e);
            if (e.key === 'ArrowRight') nextPreview(e);
        });
    </script>
</body>
</html>
