<?php
$pageTitle = 'Account settings';
require_login();

$userRow = current_user($pdo);
if (!$userRow) {
    flash_set('error', 'Please sign in again to continue.');
    redirect('index.php?page=login');
}

require __DIR__ . '/../blocks/header.php';
?>

<section class="card auth-card settings-card">
    <h1 class="settings-card__title">Account settings</h1>
    <p class="muted">Update your profile. Your current password is required to save changes.</p>

    <form method="post" action="api/settings.php" class="stack">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

        <label class="form-label">
            Username <span class="required">*</span>
            <input type="text" name="username" minlength="3" maxlength="50" autocomplete="username" required
                   value="<?= h($userRow['username']) ?>">
        </label>

        <label class="form-label">
            Email <span class="required">*</span>
            <input type="email" name="email" maxlength="255" autocomplete="email" required
                   value="<?= h($userRow['email']) ?>">
        </label>

        <label class="form-label checkbox-row">
            <input type="checkbox" name="notify_comments" value="1"
                <?= (int)$userRow['notify_comments'] === 1 ? 'checked' : '' ?>>
            <span>Email me when someone comments on my images</span>
        </label>

        <label class="form-label">
            New password
            <input type="password" name="new_password" minlength="8" autocomplete="new-password"
                   placeholder="Leave blank to keep current password">
        </label>

        <label class="form-label">
            Confirm new password
            <input type="password" name="new_password_confirm" minlength="8" autocomplete="new-password">
        </label>

        <hr class="form-sep">

        <label class="form-label">
            <span>Current password <span class="required">*</span></span>
            <input type="password" name="current_password" required autocomplete="current-password">
        </label>

        <button type="submit">Save changes</button>
    </form>
</section>

<?php require __DIR__ . '/../blocks/footer.php'; ?>
