<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/database.php';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

$pdo = new PDO($DB_DSN, $DB_USER, $DB_PASSWORD, $options);

if (!empty($_SESSION['user_id'])) {
    $stmt = $pdo->prepare('SELECT 1 FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        unset($_SESSION['user_id'], $_SESSION['username']);
    }
}

define('APP_ROOT', dirname(__DIR__));
define('UPLOAD_DIR', APP_ROOT . '/uploads');
define('OVERLAY_DIR', APP_ROOT . '/overlays');

if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0775, true);
}
if (!is_dir(OVERLAY_DIR)) {
    mkdir(OVERLAY_DIR, 0775, true);
}

function is_safe_pending_upload_path(string $relative): bool
{
    $relative = str_replace('\\', '/', $relative);

    return (bool)preg_match('#^uploads/[a-zA-Z0-9_.-]+$#', $relative);
}

function clear_pending_composite(): void
{
    if (empty($_SESSION['pending_composite']) || !is_array($_SESSION['pending_composite'])) {
        unset($_SESSION['pending_composite']);

        return;
    }

    $p = $_SESSION['pending_composite'];
    foreach (['original_path', 'final_path'] as $key) {
        if (empty($p[$key]) || !is_string($p[$key])) {
            continue;
        }
        $rel = str_replace('\\', '/', $p[$key]);
        if (!is_safe_pending_upload_path($rel)) {
            continue;
        }
        $full = APP_ROOT . '/' . $rel;
        if (!is_file($full)) {
            continue;
        }
        $realUpload = realpath(UPLOAD_DIR);
        $realFile = realpath($full);
        if ($realUpload && $realFile && str_starts_with($realFile, $realUpload)) {
            @unlink($full);
        }
    }

    unset($_SESSION['pending_composite']);
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function current_user(PDO $pdo): ?array
{
    static $cached = null;

    if ($cached !== null) {
        return $cached;
    }

    if (!is_logged_in()) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id, username, email, is_verified, notify_comments, created_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $cached = $stmt->fetch() ?: null;

    return $cached;
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('index.php?page=login');
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Your session may have expired. Go back, refresh the page, and try again.');
    }
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function app_web_root_path(): string
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    if (str_contains($script, '/api/')) {
        $root = dirname(dirname($script));
    } else {
        $root = dirname($script);
    }
    $root = str_replace('\\', '/', $root);
    if ($root === '/' || $root === '.' || $root === '') {
        return '';
    }
    return rtrim($root, '/');
}

function app_url(string $path): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $root = app_web_root_path();
    $path = ltrim(str_replace('\\', '/', $path), '/');
    $base = $scheme . '://' . $host;
    if ($root !== '') {
        $base .= $root;
    }
    return $base . '/' . $path;
}

function is_local_host_request(): bool
{
    $raw = $_SERVER['HTTP_HOST'] ?? '';
    if ($raw === '') {
        return false;
    }
    if (str_starts_with($raw, '[')) {
        $end = strpos($raw, ']');
        $host = $end !== false ? substr($raw, 1, $end - 1) : $raw;
    } else {
        $host = explode(':', $raw, 2)[0];
    }

    $host = strtolower($host);

    if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
        return true;
    }

    if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    return false;
}
