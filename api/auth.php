<?php
/**
 * ADA LAW Chambers — api/auth.php
 * ---------------------------------------------------------------
 * POST ?action=login   { email, password }  -> logs in, sets a session
 * POST ?action=logout                       -> logs out
 * GET  ?action=check                        -> returns current session, if any
 * ---------------------------------------------------------------
 */

require __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_body();
    $email = trim($body['email'] ?? '');
    $password = $body['password'] ?? '';

    if (!$email || !$password) {
        json_error(400, 'Email and password are required.');
    }

    $stmt = db()->prepare('SELECT id, email, password_hash FROM admins WHERE email = ?');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        json_error(401, 'Invalid email or password.');
    }

    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_email'] = $admin['email'];

    json_ok(['user' => ['email' => $admin['email']]]);
}

if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    json_ok();
}

if ($action === 'check') {
    if (is_admin()) {
        json_ok(['user' => ['email' => $_SESSION['admin_email']]]);
    }
    json_ok(['user' => null]);
}

json_error(400, 'Unknown action.');
