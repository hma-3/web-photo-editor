<?php
require_once __DIR__ . '/../app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../index.php?page=register');
}

csrf_check();

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = (string)($_POST['password'] ?? '');

if (mb_strlen($username) < 3 || mb_strlen($username) > 50) {
    flash_set('error', 'Pick a username between 3 and 50 characters.');
    redirect('../index.php?page=register');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash_set('error', 'That does not look like a valid email address.');
    redirect('../index.php?page=register');
}

if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,}$/', $password)) {
    flash_set('error', 'Use at least 8 characters with both a letter and a number.');
    redirect('../index.php?page=register');
}

$stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
$stmt->execute([$username, $email]);

if ($stmt->fetch()) {
    flash_set('error', 'That username or email is already taken.');
    redirect('../index.php?page=register');
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
    $stmt->execute([$username, $email, $passwordHash]);

    $userId = (int)$pdo->lastInsertId();

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = (new DateTimeImmutable('+1 day'))->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare('
        INSERT INTO email_verification_tokens (user_id, token_hash, expires_at)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), expires_at = VALUES(expires_at)
    ');
    $stmt->execute([$userId, $tokenHash, $expiresAt]);

    $pdo->commit();

    $verifyLink = app_url('index.php?page=verify&token=' . urlencode($token));

    $mailBody = "Hi {$username},\n\nThanks for signing up. Open this link to verify your account:\n{$verifyLink}\n\nIt expires in 24 hours.";
    $mailSent = mail($email, 'Verify your account', $mailBody);

    if (is_local_host_request()) {
        flash_set(
            'success',
            'Account created. On localhost or LAN, email usually does not arrive — open this link on this device to verify: ' . $verifyLink
        );
    } elseif (!$mailSent) {
        flash_set(
            'success',
            'Account created, but we could not send email. Verify with this link: ' . $verifyLink
        );
    } else {
        flash_set('success', 'Almost there — check your inbox and click the verification link.');
    }

    redirect('../index.php?page=login');
} catch (Throwable $e) {
    $pdo->rollBack();
    flash_set('error', 'Something went wrong while creating your account. Please try again.');
    redirect('../index.php?page=register');
}
