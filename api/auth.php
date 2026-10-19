<?php
/**
 * ADA LAW Chambers — api/auth.php
 * ---------------------------------------------------------------
 * POST ?action=login   { email, password }  -> logs in, sets a session
 * POST ?action=logout                       -> logs out
 * GET  ?action=check                        -> returns current session, if any
 * POST ?action=change_password { current_password, new_password }  (signed in)
 * ---------------------------------------------------------------
 */

require __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';

// ---------------------------------------------------------------
// Login rate limiting — stops password guessing. Failed attempts are
// counted per visitor (5 per 15 minutes) and per account (20 per 15
// minutes, so rotating addresses doesn't help either). Counts live in
// small files in PERSIST_DIR (outside the website) and reset on success.
// ---------------------------------------------------------------
const LOGIN_WINDOW = 15 * 60;
const LOGIN_MAX_PER_VISITOR = 5;
const LOGIN_MAX_PER_ACCOUNT = 20;

function login_bucket_file(string $key): string
{
    $dir = PERSIST_DIR . '/login-attempts';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    return $dir . '/' . hash('sha256', $key) . '.json';
}

/** Recent failure timestamps for a key (older than the window are dropped). */
function login_failures(string $key): array
{
    $data = json_decode((string) @file_get_contents(login_bucket_file($key)), true);
    $cutoff = time() - LOGIN_WINDOW;
    return array_values(array_filter(is_array($data) ? $data : [], function ($t) use ($cutoff) { return $t > $cutoff; }));
}

function login_record_failure(string $key): void
{
    $fails = login_failures($key);
    $fails[] = time();
    @file_put_contents(login_bucket_file($key), json_encode($fails), LOCK_EX);
}

function login_clear(string $key): void
{
    @unlink(login_bucket_file($key));
}

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_body();
    $email = is_string($body['email'] ?? null) ? trim($body['email']) : '';
    $password = is_string($body['password'] ?? null) ? $body['password'] : '';

    if (!$email || !$password) {
        json_error(400, 'Email and password are required.');
    }

    $ipKey = 'ip:' . client_ip();
    $accountKey = 'acct:' . strtolower($email);
    $ipFails = login_failures($ipKey);
    $accountFails = login_failures($accountKey);
    if (count($ipFails) >= LOGIN_MAX_PER_VISITOR || count($accountFails) >= LOGIN_MAX_PER_ACCOUNT) {
        $oldest = min(count($ipFails) >= LOGIN_MAX_PER_VISITOR ? $ipFails : $accountFails);
        $minutes = max(1, (int) ceil(($oldest + LOGIN_WINDOW - time()) / 60));
        json_error(429, "Too many failed sign-in attempts. Please try again in $minutes minute" . ($minutes === 1 ? '' : 's') . '.');
    }

    $stmt = db()->prepare('SELECT id, email, password_hash FROM admins WHERE email = ?');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        login_record_failure($ipKey);
        login_record_failure($accountKey);
        json_error(401, 'Invalid email or password.');
    }

    login_clear($ipKey);
    login_clear($accountKey);

    // Hashes made with older settings are upgraded to the current default.
    if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
        $up = db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
        $up->execute([password_hash($password, PASSWORD_DEFAULT), $admin['id']]);
    }

    // New session id on sign-in, so a session id from before login can't be reused.
    session_regenerate_id(true);

    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_email'] = $admin['email'];
    $_SESSION['signed_in_at'] = time();
    $_SESSION['last_seen'] = time();

    json_ok(['user' => ['email' => $admin['email']]]);
}

if ($action === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    }
    session_destroy();
    json_ok();
}

// Change the signed-in admin's password. Needs the current password (so a
// left-open session alone can't lock the owner out) and a new one of at
// least 12 characters.
if ($action === 'change_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();
    $body = json_body();
    $current = is_string($body['current_password'] ?? null) ? $body['current_password'] : '';
    $new = is_string($body['new_password'] ?? null) ? $body['new_password'] : '';

    $key = 'pw:' . $_SESSION['admin_id'];
    if (count(login_failures($key)) >= LOGIN_MAX_PER_VISITOR) {
        json_error(429, 'Too many wrong current passwords. Please try again in 15 minutes.');
    }

    $stmt = db()->prepare('SELECT id, password_hash FROM admins WHERE id = ?');
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch();
    if (!$admin || !password_verify($current, $admin['password_hash'])) {
        login_record_failure($key);
        json_error(400, 'Your current password is not correct.');
    }
    $len = function_exists('mb_strlen') ? mb_strlen($new, 'UTF-8') : strlen($new);
    if ($len < 12) {
        json_error(400, 'The new password must be at least 12 characters long.');
    }
    if ($len > 200) {
        json_error(400, 'The new password is too long (max 200 characters).');
    }
    if (password_verify($new, $admin['password_hash'])) {
        json_error(400, 'The new password must be different from the current one.');
    }

    $up = db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
    $up->execute([password_hash($new, PASSWORD_DEFAULT), $admin['id']]);
    login_clear($key);
    session_regenerate_id(true);
    json_ok(['message' => 'Password changed.']);
}

if ($action === 'check') {
    if (is_admin()) {
        json_ok(['user' => ['email' => $_SESSION['admin_email']]]);
    }
    json_ok(['user' => null]);
}

json_error(400, 'Unknown action.');
