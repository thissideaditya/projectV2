<?php
/**
 * ADA LAW Chambers — api/auth.php
 * ---------------------------------------------------------------
 * POST ?action=login   { email, password }  -> checks the password, then
 *                                             emails a one-time code (OTP)
 *                                             to OTP_EMAIL
 * POST ?action=verify_otp { code }          -> checks the code, sets the session
 * POST ?action=resend_otp                   -> emails a fresh code
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

// ---------------------------------------------------------------
// Email OTP — second sign-in step. After the password is accepted, a
// 6-digit code is emailed to OTP_EMAIL and must be entered within
// 10 minutes (5 tries per code; a new code at most once a minute).
// Only a hash of the code is kept, in the visitor's server-side session.
// ---------------------------------------------------------------
const OTP_EMAIL = 'adadesk@gmail.com';
const OTP_LENGTH = 6;
const OTP_TTL = 10 * 60;
const OTP_MAX_TRIES = 5;
const OTP_RESEND_GAP = 60;
const OTP_MAX_SENDS = 5;

/** Makes a new code, stores its hash in the session and emails it. */
function otp_issue(): bool
{
    $code = str_pad((string) random_int(0, 10 ** OTP_LENGTH - 1), OTP_LENGTH, '0', STR_PAD_LEFT);
    $_SESSION['otp'] = [
        'hash' => hash('sha256', $code),
        'expires' => time() + OTP_TTL,
        'tries' => 0,
        'sent_at' => time(),
        'sends' => ($_SESSION['otp']['sends'] ?? 0) + 1,
    ];
    return otp_send_email($code, (string) ($_SESSION['otp_pending']['email'] ?? ''));
}

/**
 * Emails the code. Uses the server's own mail() first (Hostinger
 * supports it); if that fails, falls back to FormSubmit (api/notify.php),
 * which needs a one-time "Activate" click for OTP_EMAIL.
 */
function otp_send_email(string $code, string $adminEmail): bool
{
    $minutes = (int) (OTP_TTL / 60);
    $when = date('d M Y, H:i');
    $ip = client_ip();
    $subject = "ADA Law Chambers admin sign-in code: $code";
    $text = "Your admin panel sign-in code is:\n\n    $code\n\n"
        . "It expires in $minutes minutes and can be used once.\n\n"
        . "Account: $adminEmail\nTime: $when\nIP address: $ip\n\n"
        . "If you did not just try to sign in, someone knows the admin password — change it from the Account page.\n";

    $host = preg_replace('~^www\.~', '', strtolower(preg_replace('~:\d+$~', '', (string) ($_SERVER['HTTP_HOST'] ?? 'adalawchambers.com'))));
    if (!preg_match('~^[a-z0-9.-]+$~', $host)) $host = 'adalawchambers.com';
    $headers = implode("\r\n", [
        "From: ADA Law Chambers <no-reply@$host>",
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Auto-Response-Suppress: All',
    ]);
    if (function_exists('mail') && @mail(OTP_EMAIL, $subject, $text, $headers, "-fno-reply@$host")) {
        return true;
    }

    require_once __DIR__ . '/notify.php';
    return send_form_email($subject, [
        'Sign-in code' => $code,
        'Expires in' => "$minutes minutes",
        'Account' => $adminEmail,
        'Time' => $when,
        'IP address' => $ip,
    ], '', 'admin/index.html', OTP_EMAIL);
}

/** "ad****@gmail.com" — shown on the login page so the admin knows where to look. */
function otp_masked_email(): string
{
    [$user, $domain] = explode('@', OTP_EMAIL, 2);
    return substr($user, 0, 2) . str_repeat('*', max(1, strlen($user) - 2)) . '@' . $domain;
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

    // Password is right — the second step is the emailed code. Nothing is
    // signed in until verify_otp succeeds.
    session_regenerate_id(true);
    $_SESSION['otp_pending'] = ['admin_id' => $admin['id'], 'email' => $admin['email']];
    unset($_SESSION['otp']);
    if (!otp_issue()) {
        unset($_SESSION['otp_pending'], $_SESSION['otp']);
        json_error(500, 'Could not send the verification code email. Please try again in a minute.');
    }
    json_ok(['otp_required' => true, 'sent_to' => otp_masked_email()]);
}

if ($action === 'resend_otp' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['otp_pending'])) {
        json_error(400, 'Your sign-in has expired. Please enter your email and password again.');
    }
    $wait = (int) ($_SESSION['otp']['sent_at'] ?? 0) + OTP_RESEND_GAP - time();
    if ($wait > 0) {
        json_error(429, "Please wait $wait second" . ($wait === 1 ? '' : 's') . ' before asking for a new code.');
    }
    if (($_SESSION['otp']['sends'] ?? 0) >= OTP_MAX_SENDS) {
        unset($_SESSION['otp_pending'], $_SESSION['otp']);
        json_error(429, 'Too many codes requested. Please sign in again.');
    }
    if (!otp_issue()) {
        json_error(500, 'Could not send the verification code email. Please try again in a minute.');
    }
    json_ok(['otp_required' => true, 'sent_to' => otp_masked_email()]);
}

if ($action === 'verify_otp' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pending = $_SESSION['otp_pending'] ?? null;
    $otp = $_SESSION['otp'] ?? null;
    if (!$pending || !$otp) {
        json_error(400, 'Your sign-in has expired. Please enter your email and password again.');
    }
    if (time() > $otp['expires']) {
        unset($_SESSION['otp_pending'], $_SESSION['otp']);
        json_error(400, 'The code has expired. Please sign in again.');
    }

    $body = json_body();
    $code = preg_replace('/\D/', '', is_string($body['code'] ?? null) ? $body['code'] : '');

    if (strlen($code) !== OTP_LENGTH || !hash_equals($otp['hash'], hash('sha256', $code))) {
        login_record_failure('ip:' . client_ip());
        $_SESSION['otp']['tries'] = ($otp['tries'] ?? 0) + 1;
        if ($_SESSION['otp']['tries'] >= OTP_MAX_TRIES) {
            unset($_SESSION['otp_pending'], $_SESSION['otp']);
            json_error(401, 'Too many wrong codes. Please sign in again.');
        }
        $left = OTP_MAX_TRIES - $_SESSION['otp']['tries'];
        json_error(401, "That code is not correct. $left attempt" . ($left === 1 ? '' : 's') . ' left.');
    }

    unset($_SESSION['otp_pending'], $_SESSION['otp']);

    // New session id on sign-in, so a session id from before login can't be reused.
    session_regenerate_id(true);

    $_SESSION['admin_id'] = $pending['admin_id'];
    $_SESSION['admin_email'] = $pending['email'];
    $_SESSION['signed_in_at'] = time();
    $_SESSION['last_seen'] = time();

    json_ok(['user' => ['email' => $pending['email']]]);
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
