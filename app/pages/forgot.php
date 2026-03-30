<?php
$pageTitle = 'Forgot password';

if (is_logged_in()) {
    redirect('index.php?page=gallery');
}

require __DIR__ . '/../blocks/header.php';
?>

<section class="card auth-card">
    <h1 class="auth-card__title">Forgot password</h1>
    <p class="muted">Enter your account email. If it exists, we will send reset instructions.</p>

    <form method="post" action="api/forgot_password.php" class="stack">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

        <label class="form-label">
            Email
            <input type="email" name="email" maxlength="255" required autocomplete="email">
        </label>

        <button type="submit">Send reset link</button>
    </form>

    <p class="muted">
        <a href="index.php?page=login">Back to login</a>
    </p>
</section>

<?php require __DIR__ . '/../blocks/footer.php'; ?>
