<?php
$pageTitle = 'PhotoBooth';
$user = current_user($pdo);
$flash = flash_get();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?></title>
    <meta name="csrf-token" content="<?= h(csrf_token()) ?>">
    <link rel="stylesheet" href="assets/css/styles.css">
    <script defer src="assets/js/app.js"></script>
</head>

<body>
<header class="site-header">
    <a class="site-header__brand" href="index.php">PhotoBooth</a>

    <nav class="site-nav">
        <a href="index.php?page=gallery">Gallery</a>
        <?php if ($user): ?>
            <a href="index.php?page=editor">Editor</a>
            <div class="site-nav__account" data-nav-account>
                <button
                    type="button"
                    class="site-nav__account-toggle"
                    id="nav-account-btn"
                    aria-expanded="false"
                    aria-haspopup="true"
                    aria-controls="nav-account-menu"
                >
                    <span class="site-nav__account-label"><?= h($user['username']) ?></span>
                </button>
                <div class="site-nav__account-menu" id="nav-account-menu" hidden>
                    <a href="index.php?page=my_images">My images</a>
                    <a href="index.php?page=settings">Settings</a>
                    <a href="api/logout.php" class="site-nav__account-logout">Logout</a>
                </div>
            </div>
        <?php else: ?>
            <a href="index.php?page=login">Login</a>
            <a href="index.php?page=register">Register</a>
        <?php endif; ?>
    </nav>
</header>

<?php if ($flash): ?>
    <div class="flash <?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
<?php endif; ?>

<main class="site-main">
