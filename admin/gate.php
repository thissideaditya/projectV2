<?php
/**
 * ADA LAW Chambers — admin/gate.php
 * ---------------------------------------------------------------
 * Serves an admin page (admin/<page>.html) only to a signed-in admin;
 * everyone else is sent to the login page. Reached through the rewrite
 * rule in admin/.htaccess, so addresses stay /admin/dashboard.html etc.
 * ---------------------------------------------------------------
 */

require __DIR__ . '/../api/config.php'; // starts (and expires) the admin session

$page = $_GET['page'] ?? '';
if (!is_string($page) || !preg_match('~^[a-z0-9-]+\.html$~', $page) || $page === 'index.html' || !is_file(__DIR__ . '/' . $page)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Not found.');
}

if (!is_admin()) {
    header('Cache-Control: no-store');
    header('Location: index.html', true, 302);
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
readfile(__DIR__ . '/' . $page);
