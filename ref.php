<?php
/**
 * ref.php — Referral tracking endpoint
 * Redirects to main page with referral cookie
 * 
 * URL: /ref.php?code=ABC123  or  /ref/ABC123 (via .htaccess rewrite)
 */

require_once __DIR__ . '/config/database.php';

$code = $_GET['code'] ?? null;

if (!$code) {
    // Try to extract from REQUEST_URI
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match('#/ref/([a-zA-Z0-9]+)#', $uri, $m)) {
        $code = $m[1];
    }
}

if ($code) {
    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare("UPDATE referrals SET clicks = clicks + 1 WHERE code = :code");
        $stmt->execute([':code' => $code]);

        // Set referral cookie (30 days)
        setcookie('ref', $code, [
            'expires' => time() + 86400 * 30,
            'path' => '/',
            'domain' => 'masterhacks.ru',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } catch (Throwable $e) {
        // Silent fail — still redirect
    }
}

// Redirect to main page
header('Location: /');
exit;
