<?php
/**
 * ADA LAW Chambers — api/applications.php
 * ---------------------------------------------------------------
 * GET (admin only) — list every Careers application (internship and
 * associate), newest first. Use api/download-resume.php?id= to
 * download a specific application's attached resume.
 * ---------------------------------------------------------------
 */

require __DIR__ . '/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error(405, 'Method not allowed.');
}

$stmt = db()->query('SELECT * FROM career_applications ORDER BY created_at DESC');
json_ok(['applications' => $stmt->fetchAll()]);
