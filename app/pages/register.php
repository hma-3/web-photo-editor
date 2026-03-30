<?php
$pageTitle = 'Register';

if (is_logged_in()) {
    redirect('index.php?page=gallery');
}

require __DIR__ . '/../blocks/header.php';
?>

<section class="card auth-card">
    <h1 class="auth-card__title">Register</h1>

    <form method="post" action="api/register.php" class="stack">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

        <label class="form-label">
            Username <span class="required">*</span>
            <input type="text" name="username" minlength="3" maxlength="50" required>
        </label>

        <label class="form-label">
            Email <span class="required">*</span>
            <input type="email" name="email" maxlength="255" required>
        </label>

        <label class="form-label">
            Password <span class="required">*</span>
            <input type="password" name="password" minlength="8" required>
        </label>

        <button type="submit">Create account</button>
    </form>

    <p class="muted">
        Already have an account? <a href="index.php?page=login">Login</a>
    </p>
</section>

<?php require __DIR__ . '/../blocks/footer.php'; ?>
