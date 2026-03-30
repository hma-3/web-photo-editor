<?php
require_once __DIR__ . '/../app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../index.php?page=forgot');
}

csrf_check();

$email = trim($_POST['email'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash_set('error', 'That does not look like a valid email address.');
    redirect('../index.php?page=forgot');
}

$stmt = $pdo->prepare('SELECT id, username FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);

    $ins = $pdo->prepare('
        INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
        ON DUPLICATE KEY UPDATE
            token_hash = VALUES(token_hash),
            expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR)
    ');
    $ins->execute([(int)$user['id'], $tokenHash]);

    $resetLink = app_url('index.php?page=reset&token=' . urlencode($token));
    $mailBody = "Hi {$user['username']},\n\nWe received a request to reset your password. Open this link:\n{$resetLink}\n\nIt expires in 24 hours. If you did not ask for this, you can ignore this email.\n";
    $mailSent = mail($email, 'Password reset', $mailBody);

    if (is_local_host_request()) {
        flash_set(
            'success',
            'Here is your reset link (local dev): ' . $resetLink
        );
        redirect('../index.php?page=login');
    }

    if (!$mailSent) {
        flash_set(
            'success',
            'We could not send email. Reset your password with this link: ' . $resetLink
        );
        redirect('../index.php?page=login');
    }

    flash_set('success', 'If that email is on file, you will get a reset link shortly.');
    redirect('../index.php?page=login');
}

flash_set('success', 'If we have an account for that address, you will get reset instructions shortly.');
redirect('../index.php?page=login');
