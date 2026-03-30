<?php
require_once __DIR__ . '/../app/bootstrap.php';

if (!is_logged_in()) {
    json_response(['success' => false, 'error' => 'Please log in again and retry.'], 401);
}

$token = (string)($_POST['csrf_token'] ?? '');
if ($token === '' || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    json_response(
        ['success' => false, 'error' => 'Your session may have expired. Refresh the page and try again.'],
        403
    );
}

clear_pending_composite();

$allowedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

if (!isset($_FILES['original'], $_POST['overlay'])) {
    json_response(['success' => false, 'error' => 'Something was missing from the request. Refresh and try again.']);
}

$overlayName = basename((string)$_POST['overlay']);
$overlayPath = OVERLAY_DIR . '/' . $overlayName;

if (!is_file($overlayPath)) {
    json_response(['success' => false, 'error' => 'That sticker could not be found.']);
}

$origErr = (int)($_FILES['original']['error'] ?? UPLOAD_ERR_NO_FILE);
if ($origErr !== UPLOAD_ERR_OK) {
    $msg = match ($origErr) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Upload too large for server limits. Use a smaller photo or try again.',
        UPLOAD_ERR_PARTIAL => 'Upload was interrupted.',
        UPLOAD_ERR_NO_FILE => 'No file was received.',
        default => 'Upload failed — please try again.',
    };
    json_response(['success' => false, 'error' => $msg]);
}

$origInfo = @getimagesize($_FILES['original']['tmp_name']);
if ($origInfo === false || !in_array($origInfo[2], $allowedTypes, true)) {
    json_response(['success' => false, 'error' => 'The original photo must be JPEG, PNG, or WebP.']);
}

$origExt = image_type_to_extension($origInfo[2], false) ?: 'png';
$sourceName = uniqid('source_', true) . '.' . $origExt;
$finalName = uniqid('final_', true) . '.png';
$sourceFs = UPLOAD_DIR . '/' . $sourceName;
$finalFs = UPLOAD_DIR . '/' . $finalName;

if (!move_uploaded_file($_FILES['original']['tmp_name'], $sourceFs)) {
    json_response(['success' => false, 'error' => 'We could not save your original photo.']);
}

$baseData = file_get_contents($sourceFs);
$base = $baseData !== false ? imagecreatefromstring($baseData) : false;
if (!$base) {
    @unlink($sourceFs);
    json_response(['success' => false, 'error' => 'That original photo is not a usable image.']);
}

$overlayInfo = @getimagesize($overlayPath);
if ($overlayInfo === false || !in_array($overlayInfo[2], $allowedTypes, true)) {
    @unlink($sourceFs);
    json_response(['success' => false, 'error' => 'The sticker file on the server is not a valid image.']);
}

$overlayData = file_get_contents($overlayPath);
$overlay = $overlayData !== false ? imagecreatefromstring($overlayData) : false;
if (!$overlay) {
    @unlink($sourceFs);
    json_response(['success' => false, 'error' => 'We could not load that sticker. Try another one.']);
}

$baseWidth = imagesx($base);
$baseHeight = imagesy($base);

$overlayResized = imagecreatetruecolor($baseWidth, $baseHeight);
if (!$overlayResized) {
    @unlink($sourceFs);
    json_response(['success' => false, 'error' => 'Server could not prepare the sticker layer.']);
}

imagealphablending($overlayResized, false);
imagesavealpha($overlayResized, true);

if (!imagecopyresampled(
    $overlayResized,
    $overlay,
    0,
    0,
    0,
    0,
    $baseWidth,
    $baseHeight,
    imagesx($overlay),
    imagesy($overlay)
)) {
    @unlink($sourceFs);
    json_response(['success' => false, 'error' => 'Server could not resize the sticker image.']);
}

imagealphablending($base, true);
imagesavealpha($base, true);
if (!imagecopy($base, $overlayResized, 0, 0, 0, 0, $baseWidth, $baseHeight)) {
    @unlink($sourceFs);
    json_response(['success' => false, 'error' => 'Server could not combine image layers.']);
}

if (!imagepng($base, $finalFs)) {
    @unlink($sourceFs);
    @unlink($finalFs);
    json_response(['success' => false, 'error' => 'We could not save the finished image.']);
}

$_SESSION['pending_composite'] = [
    'original_path' => 'uploads/' . $sourceName,
    'overlay_path' => 'overlays/' . $overlayName,
    'final_path' => 'uploads/' . $finalName,
];

json_response([
    'success' => true,
    'final_path' => 'uploads/' . $finalName,
]);
