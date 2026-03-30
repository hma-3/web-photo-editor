<?php
require_once __DIR__ . '/../app/bootstrap.php';

require_login();
csrf_check();

$imageId = (int)($_POST['image_id'] ?? 0);
if ($imageId <= 0) {
    flash_set('error', 'That image could not be found.');
    redirect('../index.php?page=my_images');
}

$stmt = $pdo->prepare('SELECT id, user_id, original_path, final_path FROM images WHERE id = ? LIMIT 1');
$stmt->execute([$imageId]);
$image = $stmt->fetch();

if (!$image || (int)$image['user_id'] !== (int)$_SESSION['user_id']) {
    flash_set('error', 'You can only delete your own images.');
    redirect('../index.php?page=my_images');
}

$originalFs = APP_ROOT . '/' . $image['original_path'];
$finalFs = APP_ROOT . '/' . $image['final_path'];

if (is_file($originalFs)) {
    @unlink($originalFs);
}
if (is_file($finalFs)) {
    @unlink($finalFs);
}

$pdo->prepare('DELETE FROM images WHERE id = ?')->execute([$imageId]);

flash_set('success', 'That image has been removed.');
redirect('../index.php?page=my_images');
