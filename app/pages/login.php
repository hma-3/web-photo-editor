<?php
$pageTitle = 'Login';

if (is_logged_in()) {
    redirect('index.php?page=gallery');
}

require __DIR__ . '/../blocks/header.php';
?>

<section class="card auth-card">
    <h1 class="auth-card__title">Login</h1>

    <form method="post" action="api/login.php" class="stack">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

        <label class="form-label">
            Username or email
            <input type="text" name="identifier" required>
        </label>

        <label class="form-label">
            Password
            <input type="password" name="password" required>
        </label>

        <button type="submit">Login</button>
    </form>

    <p class="muted">
        <a href="index.php?page=forgot">Forgot password?</a>
    </p>

    <p class="muted">
        No account yet? <a href="index.php?page=register">Register</a>
    </p>
</section>

<?php require __DIR__ . '/../blocks/footer.php'; ?>
