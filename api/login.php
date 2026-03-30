<?php
require_once __DIR__ . '/../app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../index.php?page=login');
}

csrf_check();

$identifier = trim($_POST['identifier'] ?? '');
$password = (string)($_POST['password'] ?? '');

$stmt = $pdo->prepare('
    SELECT id, username, email, password_hash, is_verified
    FROM users
    WHERE username = ? OR email = ?
    LIMIT 1
');
$stmt->execute([$identifier, $identifier]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    flash_set('error', 'That username or password does not match our records.');
    redirect('../index.php?page=login');
}

if (!(int)$user['is_verified']) {
    flash_set('error', 'Please verify your email before logging in.');
    redirect('../index.php?page=login');
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

flash_set('success', 'Welcome back!');
redirect('../index.php?page=gallery');
