<?php
$pageTitle = 'Reset password';
$token = trim($_GET['token'] ?? '');

if (is_logged_in()) {
    redirect('index.php?page=gallery');
}

if ($token === '') {
    flash_set('error', 'That reset link is incomplete. Request a new one.');
    redirect('index.php?page=forgot');
}

require __DIR__ . '/../blocks/header.php';
?>

<section class="card auth-card">
    <h1 class="auth-card__title">Choose a new password</h1>

    <form method="post" action="api/reset_password.php" class="stack">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="token" value="<?= h($token) ?>">

        <label class="form-label">
            New password
            <input type="password" name="password" minlength="8" required autocomplete="new-password">
        </label>

        <label class="form-label">
            Confirm new password
            <input type="password" name="password_confirm" minlength="8" required autocomplete="new-password">
        </label>

        <button type="submit">Update password</button>
    </form>
</section>

<?php require __DIR__ . '/../blocks/footer.php'; ?>
