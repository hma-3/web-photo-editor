<?php
require_once __DIR__ . '/../app/bootstrap.php';

require_login();
csrf_check();

if (empty($_SESSION['pending_composite']) || !is_array($_SESSION['pending_composite'])) {
    json_response(['success' => false, 'error' => 'Nothing to post yet — capture or upload a photo first.']);
}

$p = $_SESSION['pending_composite'];
$orig = $p['original_path'] ?? '';
$final = $p['final_path'] ?? '';
$ov = $p['overlay_path'] ?? '';

if (!is_string($orig) || !is_string($final) || !is_string($ov)) {
    json_response(['success' => false, 'error' => 'Something got out of sync. Try creating the image again.']);
}

$orig = str_replace('\\', '/', $orig);
$final = str_replace('\\', '/', $final);

if (!is_safe_pending_upload_path($orig) || !is_safe_pending_upload_path($final)) {
    json_response(['success' => false, 'error' => 'We could not find the saved files. Try again from the editor.']);
}

$overlayName = basename($ov);
if ($overlayName === '' || $overlayName === '.' || $overlayName === '..') {
    json_response(['success' => false, 'error' => 'That overlay is not valid. Pick another sticker.']);
}

$overlayFs = OVERLAY_DIR . '/' . $overlayName;
if (!is_file($overlayFs)) {
    json_response(['success' => false, 'error' => 'That sticker file is missing on the server.']);
}

$origFs = APP_ROOT . '/' . $orig;
$finalFs = APP_ROOT . '/' . $final;
if (!is_file($origFs) || !is_file($finalFs)) {
    json_response(['success' => false, 'error' => 'The image files are gone. Create your photo again in the editor.']);
}

$pdo->prepare('
    INSERT INTO images (user_id, original_path, overlay_path, final_path)
    VALUES (?, ?, ?, ?)
')->execute([
    (int)$_SESSION['user_id'],
    $orig,
    'overlays/' . $overlayName,
    $final,
]);

unset($_SESSION['pending_composite']);

json_response(['success' => true]);
