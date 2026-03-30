<?php
require_once __DIR__ . '/../app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../index.php?page=forgot');
}

csrf_check();

$token = trim($_POST['token'] ?? '');
$password = (string)($_POST['password'] ?? '');
$passwordConfirm = (string)($_POST['password_confirm'] ?? '');

if ($token === '') {
    flash_set('error', 'That reset link is not valid.');
    redirect('../index.php?page=forgot');
}

if ($password !== $passwordConfirm) {
    flash_set('error', 'The two passwords you entered do not match.');
    redirect('../index.php?page=reset&token=' . urlencode($token));
}

if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,}$/', $password)) {
    flash_set('error', 'Use at least 8 characters with both a letter and a number.');
    redirect('../index.php?page=reset&token=' . urlencode($token));
}

$hash = hash('sha256', $token);
$stmt = $pdo->prepare('
    SELECT user_id
    FROM password_reset_tokens
    WHERE token_hash = ?
      AND expires_at >= NOW()
    LIMIT 1
');
$stmt->execute([$hash]);
$row = $stmt->fetch();

if (!$row) {
    flash_set('error', 'This link is invalid or has expired. Request a new one from the forgot-password page.');
    redirect('../index.php?page=forgot');
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$passwordHash, (int)$row['user_id']]);
$pdo->prepare('DELETE FROM password_reset_tokens WHERE token_hash = ?')->execute([$hash]);

flash_set('success', 'Password updated. You can log in with your new password.');
redirect('../index.php?page=login');
