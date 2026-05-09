<?php
/**
 * Получение комментариев к посту
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=30'); // Кэширование на 30 секунд

// Только GET запросы
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Валидация post_id
if (!isset($_GET['post_id']) || !is_numeric($_GET['post_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid post ID']);
    exit;
}

$post_id = intval($_GET['post_id']);

if ($post_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Post ID must be positive']);
    exit;
}

if (!file_exists(__DIR__ . '/config/database.php')) {
    echo json_encode([]);
    exit;
}

require_once __DIR__ . '/config/database.php';

try {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare(
        'SELECT id, parent_id, author_name, text, created_at '
        . 'FROM comments '
        . 'WHERE video_id = :vid '
        . 'ORDER BY id ASC'
    );
    $stmt->execute([':vid' => $post_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $safe = array_map(function($row) {
        return [
            'id' => isset($row['id']) ? (int)$row['id'] : 0,
            'parent_id' => isset($row['parent_id']) ? (int)$row['parent_id'] : null,
            'author' => isset($row['author_name']) ? htmlspecialchars((string)$row['author_name'], ENT_QUOTES, 'UTF-8') : 'Аноним',
            'text' => isset($row['text']) ? (string)$row['text'] : '',
            'date' => isset($row['created_at']) ? htmlspecialchars((string)$row['created_at'], ENT_QUOTES, 'UTF-8') : ''
        ];
    }, $rows);

    echo json_encode($safe, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode([]);
}
?>
