<?php
require_once __DIR__ . '/../app/bootstrap.php';

require_login();
csrf_check();

$imageId = (int)($_POST['image_id'] ?? 0);
$content = trim((string)($_POST['content'] ?? ''));

if ($imageId <= 0 || $content === '' || mb_strlen($content) > 500) {
    json_response(['success' => false, 'error' => 'Please write something (up to 500 characters).']);
}

$imageStmt = $pdo->prepare('
    SELECT i.user_id AS author_id, u.email AS author_email, u.notify_comments
    FROM images i
    INNER JOIN users u ON u.id = i.user_id
    WHERE i.id = ?
    LIMIT 1
');
$imageStmt->execute([$imageId]);
$image = $imageStmt->fetch();

if (!$image) {
    json_response(['success' => false, 'error' => 'That image is no longer here.']);
}

$pdo->prepare('INSERT INTO comments (user_id, image_id, content) VALUES (?, ?, ?)')
    ->execute([(int)$_SESSION['user_id'], $imageId, $content]);

if ((int)$image['notify_comments'] === 1) {
    $viewerStmt = $pdo->prepare('SELECT email, username FROM users WHERE id = ? LIMIT 1');
    $viewerStmt->execute([(int)$_SESSION['user_id']]);
    $viewer = $viewerStmt->fetch();

    if ($viewer && $viewer['email'] !== $image['author_email']) {
        $subject = 'Someone commented on your photo';
        $body = "Hi,\n\n{$viewer['username']} left a comment on one of your images:\n\n\"{$content}\"\n";
        @mail($image['author_email'], $subject, $body);
    }
}

json_response(['success' => true]);
