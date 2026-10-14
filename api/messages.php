<?php
/**
 * ADA LAW Chambers — api/messages.php
 * ---------------------------------------------------------------
 * GET (admin only) — list every Contact Us submission, newest first.
 * ---------------------------------------------------------------
 */

require __DIR__ . '/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error(405, 'Method not allowed.');
}

$stmt = db()->query('SELECT * FROM contact_messages ORDER BY created_at DESC');
json_ok(['messages' => $stmt->fetchAll()]);
