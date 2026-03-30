<?php
require_once __DIR__ . '/../app/bootstrap.php';

require_login();
csrf_check();

$imageId = (int)($_POST['image_id'] ?? 0);
if ($imageId <= 0) {
    json_response(['success' => false, 'error' => 'That image could not be found.']);
}

$check = $pdo->prepare('SELECT id FROM likes WHERE user_id = ? AND image_id = ? LIMIT 1');
$check->execute([(int)$_SESSION['user_id'], $imageId]);
$existing = $check->fetch();

if ($existing) {
    $pdo->prepare('DELETE FROM likes WHERE id = ?')->execute([(int)$existing['id']]);
    $liked = false;
} else {
    $pdo->prepare('INSERT INTO likes (user_id, image_id) VALUES (?, ?)')->execute([(int)$_SESSION['user_id'], $imageId]);
    $liked = true;
}

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM likes WHERE image_id = ?');
$countStmt->execute([$imageId]);
$count = (int)$countStmt->fetchColumn();

json_response([
    'success' => true,
    'liked' => $liked,
    'count' => $count,
]);
