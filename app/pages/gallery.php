<?php
require_once __DIR__ . '/../services/gallery_data.php';
$pageTitle = 'Gallery';
$isLoggedIn = is_logged_in();
$viewerId = $isLoggedIn ? (int)$_SESSION['user_id'] : 0;

$sortLabels = [
    'newest' => 'Newest first',
    'oldest' => 'Oldest first',
    'likes_desc' => 'Most likes',
    'likes_asc' => 'Fewest likes',
    'comments_desc' => 'Most comments',
    'comments_asc' => 'Fewest comments',
];

[
    'sortKey' => $sortKey,
    'filterUserId' => $filterUserId,
    'memberCount' => $memberCount,
    'siteImageCount' => $siteImageCount,
    'galleryAuthors' => $galleryAuthors,
    'authorNameById' => $authorNameById,
    'popularImages' => $popularImages,
    'galleryPage' => $galleryPage,
    'total' => $total,
    'totalPages' => $totalPages,
    'images' => $images,
    'commentsByImage' => $commentsByImage,
] = load_gallery_page_data($pdo, $_GET, $viewerId);

$galleryQuery = static function (array $extra = []) use ($sortKey, $filterUserId): string {
    $q = array_merge(['page' => 'gallery', 'sort' => $sortKey], $extra);
    if ($filterUserId > 0) {
        $q['user'] = $filterUserId;
    }
    return 'index.php?' . http_build_query($q);
};

require __DIR__ . '/../blocks/header.php';
?>

<section class="page-head">
    <h1>Gallery</h1>
    <p>Browse what everyone has posted. Narrow it down by author or change the sort order.</p>
</section>

<div class="gallery-page">
    <div class="gallery-page__main">
        <form class="card gallery-filters" method="get" action="index.php">
            <input type="hidden" name="page" value="gallery">

            <div class="gallery-filters__row">
                <label class="gallery-filters__field">
                    <span class="gallery-filters__label">Author</span>
                    <select name="user" aria-label="Filter by author" onchange="this.form.submit()">
                        <option value="0"<?= $filterUserId === 0 ? ' selected' : '' ?>>All users</option>
                        <?php foreach ($galleryAuthors as $author): ?>
                            <option
                                value="<?= (int)$author['id'] ?>"
                                <?= $filterUserId === (int)$author['id'] ? ' selected' : '' ?>
                            ><?= h($author['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="gallery-filters__field">
                    <span class="gallery-filters__label">Sort by</span>
                    <select name="sort" aria-label="Sort gallery" onchange="this.form.submit()">
                        <?php foreach ($sortLabels as $key => $label): ?>
                            <option value="<?= h($key) ?>"<?= $sortKey === $key ? ' selected' : '' ?>>
                                <?= h($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <?php if ($total > 0): ?>
                <p class="muted gallery-filters__meta">
                    Showing <?= (int)$total ?> image<?= $total === 1 ? '' : 's' ?>
                    <?php if ($filterUserId > 0): ?>
                        <?php $filterName = $authorNameById[$filterUserId] ?? ''; ?>
                        by <?= h($filterName !== '' ? $filterName : 'this user') ?>
                    <?php endif; ?>
                    · page <?= (int)$galleryPage ?> of <?= (int)$totalPages ?>
                </p>
            <?php endif; ?>
        </form>

        <section class="gallery-list">
            <?php if ($images === []): ?>
                <p class="muted gallery-list__empty">Nothing matches those filters — try widening your search.</p>
            <?php endif; ?>
            <?php foreach ($images as $image): ?>
                <?php
                $postedTs = strtotime((string)$image['created_at']);
                $postedTime = $postedTs !== false ? date('H:i', $postedTs) : (string)$image['created_at'];
                ?>
                <article class="card gallery-card">
                    <img class="gallery-card__image" src="<?= h($image['final_path']) ?>" alt="Created image">

                    <div class="gallery-card__meta">
                        <strong><?= h($image['username']) ?></strong>
                        <span><?= h($postedTime) ?></span>
                    </div>

                    <div class="gallery-card__actions">
                        <?php if ($isLoggedIn): ?>
                            <button
                                type="button"
                                class="like-btn"
                                data-image-id="<?= (int)$image['id'] ?>"
                            >
                                ❤️ <span id="like-count-<?= (int)$image['id'] ?>"><?= (int)$image['like_count'] ?></span>
                            </button>
                        <?php else: ?>
                            <a href="index.php?page=login">Login to like</a>
                        <?php endif; ?>
                    </div>

                    <div class="gallery-comments">
                        <h3>Comments (<?= (int)$image['comment_count'] ?>)</h3>

                        <?php
                        $comments = $commentsByImage[(int)$image['id']] ?? [];
                        $commentPreview = 3;
                        $commentTotal = count($comments);
                        $commentExtraId = 'comments-extra-' . (int)$image['id'];
                        ?>

                        <?php foreach (array_slice($comments, 0, $commentPreview) as $comment): ?>
                            <p class="gallery-comments__item">
                                <strong><?= h($comment['username']) ?>:</strong>
                                <?= h($comment['content']) ?>
                            </p>
                        <?php endforeach; ?>

                        <?php if ($commentTotal > $commentPreview): ?>
                            <div class="gallery-comments__extra" id="<?= h($commentExtraId) ?>" hidden>
                                <?php foreach (array_slice($comments, $commentPreview) as $comment): ?>
                                    <p class="gallery-comments__item">
                                        <strong><?= h($comment['username']) ?>:</strong>
                                        <?= h($comment['content']) ?>
                                    </p>
                                <?php endforeach; ?>
                            </div>
                            <button
                                type="button"
                                class="gallery-comments__toggle"
                                data-comment-toggle
                                aria-expanded="false"
                                aria-controls="<?= h($commentExtraId) ?>"
                            >
                                <span class="gallery-comments__toggle-more">Show all <?= $commentTotal ?> comments</span>
                                <span class="gallery-comments__toggle-less">Show fewer</span>
                            </button>
                        <?php endif; ?>

                        <?php if ($isLoggedIn): ?>
                            <form class="comment-form" data-image-id="<?= (int)$image['id'] ?>">
                                <input type="hidden" name="image_id" value="<?= (int)$image['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                <textarea name="content" rows="2" maxlength="500" placeholder="Write a comment..." required></textarea>
                                <button type="submit">Comment</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <?php if ($totalPages > 1): ?>
            <nav class="pagination" aria-label="Gallery pages">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a
                        class="<?= $i === $galleryPage ? 'active' : '' ?>"
                        href="<?= h($galleryQuery(['pg' => $i])) ?>"
                    ><?= $i ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    </div>

    <aside class="card gallery-page__sidebar" aria-label="Gallery sidebar">
        <div class="gallery-page__sidebar-header">
            <h2 class="sidebar-recent-heading">Most liked</h2>
            <p class="muted small-print gallery-sidebar__popular-hint">Top creations by likes (site-wide).</p>
        </div>

        <?php if ($popularImages === []): ?>
            <p class="muted sidebar-recent-empty">No images yet.</p>
        <?php else: ?>
            <div class="gallery-sidebar__popular-list">
                <?php foreach ($popularImages as $pop): ?>
                    <div class="gallery-sidebar__popular-row">
                        <div class="sidebar-thumb-link gallery-sidebar__popular-thumb" aria-hidden="true">
                            <img src="<?= h($pop['final_path']) ?>" alt="" loading="lazy" class="gallery-sidebar__popular-thumb-image">
                        </div>
                        <div class="gallery-sidebar__popular-meta">
                            <span class="gallery-sidebar__popular-likes">❤️ <?= (int)$pop['like_count'] ?></span>
                            <span class="muted gallery-sidebar__popular-author"><?= h($pop['username']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="gallery-sidebar__stats">
            <h2 class="sidebar-recent-heading">Community</h2>
            <ul class="gallery-sidebar__stats-list muted">
                <li><strong class="gallery-sidebar__stats-value"><?= (int)$siteImageCount ?></strong> images</li>
                <li><strong class="gallery-sidebar__stats-value"><?= $memberCount ?></strong> members</li>
            </ul>
        </div>

        <div class="sidebar-recent-more gallery-sidebar__cta">
            <?php if ($isLoggedIn): ?>
                <a href="index.php?page=editor">Create your own →</a>
            <?php else: ?>
                <a href="index.php?page=register">Sign up to create →</a>
            <?php endif; ?>
        </div>
    </aside>
</div>

<?php require __DIR__ . '/../blocks/footer.php'; ?>
