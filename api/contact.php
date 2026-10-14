<?php
/**
 * ADA LAW Chambers — api/contact.php
 * ---------------------------------------------------------------
 * POST (JSON body): { name, email, phone, subject, message }
 * Stores the message in contact_messages. View submissions any time
 * via Hostinger's phpMyAdmin (Databases > phpMyAdmin > contact_messages).
 * ---------------------------------------------------------------
 */

require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error(405, 'Method not allowed.');
}

$body = json_body();

$name = trim($body['name'] ?? '');
$email = trim($body['email'] ?? '');
$phone = trim($body['phone'] ?? '');
$subject = trim($body['subject'] ?? '');
$message = trim($body['message'] ?? '');

if (!$name || !$email || !$message) {
    json_error(400, 'Name, email, and message are required.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error(400, 'Please provide a valid email address.');
}

$stmt = db()->prepare(
    'INSERT INTO contact_messages (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)'
);
$stmt->execute([$name, $email, $phone, $subject, $message]);

json_ok(['message' => 'Thank you — your message has been received.'], 201);
