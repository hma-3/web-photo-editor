<?php
require_once __DIR__ . '/../app/bootstrap.php';

require_login();
csrf_check();

clear_pending_composite();

$allowedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

if (!isset($_FILES['image'], $_POST['overlay'])) {
    json_response(['success' => false, 'error' => 'Please choose an image and an overlay.']);
}

if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    json_response(['success' => false, 'error' => 'The upload did not finish. Try a smaller file or check your connection.']);
}

$overlayName = basename((string)$_POST['overlay']);
$overlayPath = OVERLAY_DIR . '/' . $overlayName;

if (!is_file($overlayPath)) {
    json_response(['success' => false, 'error' => 'That sticker could not be found.']);
}

$imageInfo = @getimagesize($_FILES['image']['tmp_name']);
if ($imageInfo === false || !in_array($imageInfo[2], $allowedTypes, true)) {
    json_response(['success' => false, 'error' => 'Please use a JPEG, PNG, or WebP image.']);
}

$ext = image_type_to_extension($imageInfo[2], false) ?: 'png';
$sourceName = uniqid('source_', true) . '.' . $ext;
$sourceFs = UPLOAD_DIR . '/' . $sourceName;

if (!move_uploaded_file($_FILES['image']['tmp_name'], $sourceFs)) {
    json_response(['success' => false, 'error' => 'We could not save your file. Try again.']);
}

$baseData = file_get_contents($sourceFs);
$base = imagecreatefromstring($baseData);

if (!$base) {
    @unlink($sourceFs);
    json_response(['success' => false, 'error' => 'That file is not a usable image.']);
}

$overlayInfo = @getimagesize($overlayPath);
if ($overlayInfo === false || !in_array($overlayInfo[2], $allowedTypes, true)) {
    @unlink($sourceFs);
    json_response(['success' => false, 'error' => 'The sticker file on the server is not a valid image.']);
}

$overlay = imagecreatefromstring((string)file_get_contents($overlayPath));
if (!$overlay) {
    @unlink($sourceFs);
    json_response(['success' => false, 'error' => 'We could not load that sticker. Try another one.']);
}

$baseWidth = imagesx($base);
$baseHeight = imagesy($base);

$overlayResized = imagecreatetruecolor($baseWidth, $baseHeight);
imagealphablending($overlayResized, false);
imagesavealpha($overlayResized, true);

imagecopyresampled(
    $overlayResized,
    $overlay,
    0, 0, 0, 0,
    $baseWidth,
    $baseHeight,
    imagesx($overlay),
    imagesy($overlay)
);

imagealphablending($base, true);
imagesavealpha($base, true);
imagecopy($base, $overlayResized, 0, 0, 0, 0, $baseWidth, $baseHeight);

$finalName = uniqid('final_', true) . '.png';
$finalFs = UPLOAD_DIR . '/' . $finalName;

imagepng($base, $finalFs);

$_SESSION['pending_composite'] = [
    'original_path' => 'uploads/' . $sourceName,
    'overlay_path' => 'overlays/' . $overlayName,
    'final_path' => 'uploads/' . $finalName,
];

json_response([
    'success' => true,
    'final_path' => 'uploads/' . $finalName,
]);
