<?php
/**
 * ADA LAW Chambers — api/setup-admin.php
 * ---------------------------------------------------------------
 * Run this ONCE, from your browser, to create your first admin
 * login (or reset one) — then DELETE this file. It exists because
 * passwords must be hashed by PHP, not pasted into phpMyAdmin as
 * plain text.
 *
 * HOW TO USE
 * 1. Set SETUP_SECRET in api/config.php to your own random string.
 * 2. Visit, in your browser:
 *      https://yourdomain.com/api/setup-admin.php
 *        ?secret=YOUR_SETUP_SECRET
 *        &email=admin@adalawChambers.com
 *        &password=ChooseAStrongPassword123
 * 3. You should see {"ok":true,...}. Your admin login now works at
 *    /admin/.
 * 4. Delete (or rename) this file. Leaving it live is a security
 *    risk — anyone who guesses your SETUP_SECRET could create or
 *    overwrite an admin login.
 * ---------------------------------------------------------------
 */

require __DIR__ . '/config.php';

$secret   = $_GET['secret'] ?? '';
$email    = trim($_GET['email'] ?? '');
$password = $_GET['password'] ?? '';

if (!hash_equals(SETUP_SECRET, $secret)) {
    json_error(403, 'Incorrect setup secret.');
}
if ($secret === 'change-this-to-a-long-random-string') {
    json_error(403, 'Set your own SETUP_SECRET in api/config.php first — do not use the default.');
}
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error(400, 'Provide a valid ?email= parameter.');
}
if (strlen($password) < 8) {
    json_error(400, 'Provide a ?password= of at least 8 characters.');
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$pdo = db();

$stmt = $pdo->prepare('SELECT id FROM admins WHERE email = ?');
$stmt->execute([$email]);
$existing = $stmt->fetch();

if ($existing) {
    $stmt = $pdo->prepare('UPDATE admins SET password_hash = ? WHERE email = ?');
    $stmt->execute([$hash, $email]);
    json_ok(['message' => "Password updated for $email. Now delete api/setup-admin.php."]);
} else {
    $stmt = $pdo->prepare('INSERT INTO admins (email, password_hash) VALUES (?, ?)');
    $stmt->execute([$email, $hash]);
    json_ok(['message' => "Admin account created for $email. Now delete api/setup-admin.php."]);
}
