<?php
require_once __DIR__ . '/../app/bootstrap.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../index.php?page=settings');
}

csrf_check();

$currentPassword = (string)($_POST['current_password'] ?? '');
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$newPassword = (string)($_POST['new_password'] ?? '');
$newPasswordConfirm = (string)($_POST['new_password_confirm'] ?? '');
$notifyComments = isset($_POST['notify_comments']) ? 1 : 0;

$userId = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT password_hash, username, email FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
    flash_set('error', 'The current password you entered is wrong.');
    redirect('../index.php?page=settings');
}

if (mb_strlen($username) < 3 || mb_strlen($username) > 50) {
    flash_set('error', 'Your username should be between 3 and 50 characters.');
    redirect('../index.php?page=settings');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash_set('error', 'That does not look like a valid email address.');
    redirect('../index.php?page=settings');
}

$dup = $pdo->prepare('SELECT id FROM users WHERE (username = ? OR email = ?) AND id <> ? LIMIT 1');
$dup->execute([$username, $email, $userId]);
if ($dup->fetch()) {
    flash_set('error', 'Someone else is already using that username or email.');
    redirect('../index.php?page=settings');
}

$passwordHash = $user['password_hash'];

if ($newPassword !== '' || $newPasswordConfirm !== '') {
    if ($newPassword !== $newPasswordConfirm) {
        flash_set('error', 'The new passwords you typed do not match.');
        redirect('../index.php?page=settings');
    }
    if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,}$/', $newPassword)) {
        flash_set('error', 'New password: at least 8 characters with both a letter and a number.');
        redirect('../index.php?page=settings');
    }
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
}

$pdo->prepare('
    UPDATE users
    SET username = ?, email = ?, password_hash = ?, notify_comments = ?
    WHERE id = ?
')->execute([$username, $email, $passwordHash, $notifyComments, $userId]);

$_SESSION['username'] = $username;

flash_set('success', 'All set — your changes are saved.');
redirect('../index.php?page=settings');
