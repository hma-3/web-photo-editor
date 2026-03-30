<?php
$pageTitle = 'Verify account';
$token = trim($_GET['token'] ?? '');

if ($token !== '') {
    $hash = hash('sha256', $token);

    $stmt = $pdo->prepare('
        SELECT user_id
        FROM email_verification_tokens
        WHERE token_hash = ?
          AND expires_at >= NOW()
        LIMIT 1
    ');
    $stmt->execute([$hash]);
    $row = $stmt->fetch();

    if ($row) {
        $pdo->prepare('UPDATE users SET is_verified = 1 WHERE id = ?')->execute([(int)$row['user_id']]);
        $pdo->prepare('DELETE FROM email_verification_tokens WHERE token_hash = ?')->execute([$hash]);
        flash_set('success', 'You are verified — go ahead and log in.');
        redirect('index.php?page=login');
    }

    $message = 'This link is invalid or has expired. Ask for a new verification email or register again.';
} else {
    $message = 'No verification link was provided.';
}

require __DIR__ . '/../blocks/header.php';
?>

<section class="card">
    <h1>Verify account</h1>
    <p><?= h($message) ?></p>
    <p><a href="index.php?page=login">Go to login</a></p>
</section>

<?php require __DIR__ . '/../blocks/footer.php'; ?>
