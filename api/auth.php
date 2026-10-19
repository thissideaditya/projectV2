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

// ---------------------------------------------------------------
// Login rate limiting — stops password guessing. Failed attempts are
// counted per visitor (5 per 15 minutes) and per account (20 per 15
// minutes, so rotating addresses doesn't help either). Counts live in
// small files in PERSIST_DIR (outside the website) and reset on success.
// ---------------------------------------------------------------
const LOGIN_WINDOW = 15 * 60;
const LOGIN_MAX_PER_VISITOR = 5;
const LOGIN_MAX_PER_ACCOUNT = 20;

function login_client_ip(): string
{
    // Behind Hostinger's CDN the visitor's address is the first X-Forwarded-For entry.
    $fwd = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')[0]);
    return filter_var($fwd, FILTER_VALIDATE_IP) ? $fwd : ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

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
    $email = trim($body['email'] ?? '');
    $password = $body['password'] ?? '';

    if (!$email || !$password) {
        json_error(400, 'Email and password are required.');
    }

    $ipKey = 'ip:' . login_client_ip();
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
    // New session id on sign-in, so a session id from before login can't be reused.
    session_regenerate_id(true);

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
