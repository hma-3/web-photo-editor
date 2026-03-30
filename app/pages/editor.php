<?php
$pageTitle = 'Editor';
require_login();
require __DIR__ . '/../blocks/header.php';

$overlayNames = [];
foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
    foreach (glob(OVERLAY_DIR . '/*.' . $ext) ?: [] as $path) {
        $overlayNames[] = basename($path);
    }
}
$overlayNames = array_values(array_unique($overlayNames));

$recentStmt = $pdo->prepare('
    SELECT id, final_path, created_at
    FROM images
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 3
');
$recentStmt->execute([(int)$_SESSION['user_id']]);
$recentImages = $recentStmt->fetchAll();
?>

<section class="page-head">
    <h1>Editor</h1>
    <p>Choose a sticker first. Then turn on the <strong>webcam</strong> or <strong>upload</strong> a picture — you will see the overlay on the preview. When it looks right, capture or pick your file; then hit <strong>Post</strong> to share or <strong>Cancel</strong> to discard.</p>
</section>

<section class="editor-page">
    <div class="card editor-page__main">
        <form id="editor-form" class="stack" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="overlay" id="overlay-input" required>

            <div class="editor-tabs" role="tablist" aria-label="Image source">
                <button type="button" class="editor-tab is-active" role="tab" id="tab-webcam"
                        aria-selected="true" aria-controls="panel-webcam" data-tab="webcam">
                    Webcam
                </button>
                <button type="button" class="editor-tab" role="tab" id="tab-upload"
                        aria-selected="false" aria-controls="panel-upload" data-tab="upload">
                    Upload file
                </button>
            </div>

            <div id="panel-webcam" class="editor-tab-panel" role="tabpanel" aria-labelledby="tab-webcam">
                <p class="muted small-print">On HTTP, some browsers only allow the camera on localhost. Use the Upload tab if the camera is unavailable.</p>
                <p class="muted small-print">The live preview shows your sticker on the camera. <strong>Capture snapshot</strong> saves that composite (same as what you will post).</p>
                <div class="editor-webcam__actions">
                    <button type="button" id="webcam-start" class="btn-secondary">Start camera</button>
                    <button type="button" id="webcam-stop" class="btn-secondary" disabled>Stop camera</button>
                    <button type="button" id="webcam-capture" class="btn-secondary" disabled>Capture snapshot</button>
                </div>
            </div>

            <div id="panel-upload" class="editor-tab-panel" role="tabpanel" aria-labelledby="tab-upload" hidden>
                <label>
                    Choose an image file
                    <input type="file" name="image" id="image-input" accept="image/jpeg,image/png,image/webp">
                </label>
            </div>

            <div class="editor-preview preview-wrap editor-webcam__preview" id="editor-preview-host">
                <div class="editor-webcam__composite" id="webcam-composite">
                    <video id="webcam-video" class="editor-webcam__video hidden" playsinline muted></video>
                    <canvas id="webcam-overlay-canvas" class="editor-webcam__overlay-canvas" hidden aria-hidden="true"></canvas>
                </div>
                <canvas id="webcam-canvas" class="hidden" aria-hidden="true"></canvas>
                <img id="preview-image" class="editor-preview__fallback hidden" alt="Preview">
            </div>

            <h3 class="editor-overlays__heading">Choose overlay</h3>
            <p class="muted small-print editor-overlays__hint">Change the sticker any time; we will update the saved image if you already captured or uploaded a photo.</p>

            <div class="editor-overlays__strip" role="region" aria-label="Overlay stickers">
                <div class="overlay-grid editor-overlays__grid">
                    <?php foreach ($overlayNames as $name): ?>
                        <button type="button" class="overlay-option" data-overlay="<?= h($name) ?>">
                            <img src="overlays/<?= h($name) ?>" alt="<?= h($name) ?>" loading="lazy">
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

        </form>

        <div class="editor-result hidden" id="result-section" aria-live="polite">
            <h3>Result</h3>
            <img id="result-image" alt="Created result" decoding="async">
            <div class="editor-result__actions hidden" id="result-actions">
                <button type="button" class="btn-secondary" id="discard-pending-btn">Cancel</button>
                <button type="button" id="publish-pending-btn">Post</button>
            </div>
        </div>
    </div>

    <aside class="card editor-page__sidebar" aria-label="Your recent images">
        <div class="editor-sidebar__header">
            <h2 class="editor-sidebar__title">Your recent images</h2>
            <p class="editor-sidebar__description muted small-print">Last 3 you posted to the gallery.</p>
        </div>

        <?php if ($recentImages === []): ?>
            <p class="muted sidebar-recent-empty">Nothing here yet. Post a creation to see thumbnails.</p>
        <?php else: ?>
            <div class="editor-sidebar__thumbs">
                <?php foreach ($recentImages as $img): ?>
                    <a class="sidebar-thumb-link" href="index.php?page=my_images">
                        <img src="<?= h($img['final_path']) ?>" alt="Your image" loading="lazy" class="editor-sidebar__thumb-image">
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <p class="sidebar-recent-more">
            <a href="index.php?page=my_images">All my images</a>
        </p>
    </aside>
</section>

<?php require __DIR__ . '/../blocks/footer.php'; ?>
