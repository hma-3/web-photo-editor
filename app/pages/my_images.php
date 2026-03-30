<?php
$pageTitle = 'My images';
require_login();
require __DIR__ . '/../blocks/header.php';

$stmt = $pdo->prepare('
    SELECT id, final_path, created_at
    FROM images
    WHERE user_id = ?
    ORDER BY created_at DESC
');
$stmt->execute([(int)$_SESSION['user_id']]);
$images = $stmt->fetchAll();
?>

<section class="page-head">
    <h1>My images</h1>
    <p class="muted">Photos you have shared. Remove anything you do not want in the gallery anymore.</p>
    <p><a href="index.php?page=editor">Back to editor</a></p>
</section>

<?php if ($images === []): ?>
    <section class="card">
        <p class="muted">You have not posted anything yet.</p>
        <p><a href="index.php?page=editor">Open the editor</a></p>
    </section>
<?php else: ?>
    <section class="my-images-page__grid">
        <?php foreach ($images as $img): ?>
            <article class="card my-images-page__card">
                <img class="my-images-page__image" src="<?= h($img['final_path']) ?>" alt="Your image">
                <p class="muted my-images-page__meta"><?= h($img['created_at']) ?></p>

                <form method="post" action="api/delete.php" class="my-images-page__delete-form">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="image_id" value="<?= (int)$img['id'] ?>">
                    <button type="submit" class="btn-danger">Delete</button>
                </form>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/../blocks/footer.php'; ?>
