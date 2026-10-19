<?php
/**
 * ADA LAW Chambers — api/notify.php
 * ---------------------------------------------------------------
 * Emails every Contact / Careers submission to the Chambers, in
 * addition to saving it (admin panel) and the WhatsApp hand-off.
 *
 * Delivery goes through FormSubmit (formsubmit.co), a free
 * form-to-email service — no mail server or password needed. It is
 * called from this server, not from the visitor's browser, so:
 *   - the receiving address never appears in the page source,
 *   - only submissions that passed the spam check are emailed,
 *   - the visitor gets their reply first (the email is sent after).
 *
 * ONE-TIME ACTIVATION: the very first submission makes FormSubmit
 * send a confirmation email to NOTIFY_EMAIL. Click "Activate" in it;
 * from then on every submission arrives as an email. (That first
 * submission is still saved in the admin panel.)
 *
 * Optional: after activating, FormSubmit also emails a random alias
 * string — put it in place of the address below to keep the address
 * out of this file.
 *
 * Résumés are NOT sent to FormSubmit (they stay private on this
 * server); the email links to the admin panel to download them.
 * ---------------------------------------------------------------
 */

const NOTIFY_EMAIL = 'info@ada.org.in';
const FORMSUBMIT_AJAX_URL = 'https://formsubmit.co/ajax/';
const SITE_URL = 'https://www.adalawchambers.com';

/**
 * Sends one submission by email. $fields are shown as a table, in order
 * (label => value). $replyTo (the visitor's address) becomes the email's
 * Reply-To, so "Reply" in the inbox answers the visitor directly.
 * Never throws; failures are logged (see notify_log()).
 */
function send_form_email(string $subject, array $fields, string $replyTo, string $fromPage): bool
{
    $payload = $fields + [
        '_subject'  => $subject,
        '_template' => 'table',
        '_captcha'  => 'false',
    ];
    if (filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $payload['email'] = $replyTo;   // FormSubmit's Reply-To needs an "email" field
        $payload['_replyto'] = $replyTo;
    }

    $url = FORMSUBMIT_AJAX_URL . rawurlencode(NOTIFY_EMAIL);
    // FormSubmit sits behind Cloudflare, which may refuse requests with no
    // User-Agent (PHP's curl sends none by default), so name ourselves.
    $userAgent = 'Mozilla/5.0 (compatible; ADA-Law-Chambers-Website/1.0; +' . SITE_URL . ')';
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        // FormSubmit expects submissions to come from the website's own pages.
        'Origin: ' . SITE_URL,
        'Referer: ' . SITE_URL . '/' . ltrim($fromPage, '/'),
    ];
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $status = 0;
    $response = '';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => $userAgent,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($response === '' && curl_errno($ch)) $response = 'curl: ' . curl_error($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => [
            'method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $body, 'user_agent' => $userAgent,
            'timeout' => 15, 'ignore_errors' => true,
        ]]);
        $response = (string) @file_get_contents($url, false, $ctx);
        $respHeaders = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?: []) : ($http_response_header ?? []);
        if (isset($respHeaders[0]) && preg_match('~\s(\d{3})\s~', $respHeaders[0], $m)) $status = (int) $m[1];
    }

    $json = json_decode($response, true);
    $ok = $status >= 200 && $status < 300 && is_array($json) && filter_var($json['success'] ?? false, FILTER_VALIDATE_BOOLEAN);
    // Activation pending, rejected, network trouble...: keep a note for whoever checks.
    notify_log(($ok ? 'sent' : 'NOT SENT') . " | $subject | HTTP $status | " . (is_array($json) ? ($json['message'] ?? '') : substr($response, 0, 200)));
    return $ok;
}

/**
 * Appends one line to persistent-uploads/form-email.log (outside the
 * website). Holds the subject and FormSubmit's answer — not the
 * visitor's message — so delivery problems (e.g. "needs activation")
 * can be seen in File Manager.
 */
function notify_log(string $line): void
{
    $file = PERSIST_DIR . '/form-email.log';
    @file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Sends the JSON success response to the visitor right away, then keeps
 * running $after (the email) — so nobody waits on FormSubmit.
 */
function json_ok_then(array $data, int $status, callable $after): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    $out = json_encode(['ok' => true] + $data);
    header('Content-Length: ' . strlen($out));
    header('Connection: close');
    echo $out;
    ignore_user_abort(true);
    while (ob_get_level() > 0) @ob_end_flush();
    @flush();
    if (function_exists('litespeed_finish_request')) {
        litespeed_finish_request();   // Hostinger (LiteSpeed)
    } elseif (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();     // PHP-FPM
    }
    try {
        $after();
    } catch (Throwable $e) {
        notify_log('ERROR | ' . $e->getMessage());
    }
    exit;
}
