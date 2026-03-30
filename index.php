<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$page = $_GET['page'] ?? 'gallery';
if (!is_string($page)) {
    $page = 'gallery';
}

$allowed = ['login', 'register', 'gallery', 'editor', 'my_images', 'verify', 'forgot', 'reset', 'settings'];
if (!in_array($page, $allowed, true)) {
    $page = 'gallery';
}

$pageFile = __DIR__ . '/app/pages/' . $page . '.php';

if (!is_file($pageFile)) {
    http_response_code(404);
    exit('That page does not exist.');
}

require $pageFile;
