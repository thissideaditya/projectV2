<?php
/**
 * ADA LAW Chambers — api/contact.php
 * ---------------------------------------------------------------
 * POST (JSON body): { name, email, phone, message }
 * Stores the message in contact_messages (admin panel → Messages) and
 * emails it to the Chambers (see api/notify.php).
 * ---------------------------------------------------------------
 */

require __DIR__ . '/config.php';
require __DIR__ . '/notify.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error(405, 'Method not allowed.');
}

$body = json_body();

// Bots get the normal success reply, and nothing is stored.
if (looks_like_spam($body)) {
    json_ok(['message' => 'Thank you — your message has been received.'], 201);
}

// At most 5 messages per visitor per 10 minutes.
rate_limit('contact:' . client_ip(), 5, 600, 'Too many messages sent. Please try again in a few minutes, or call us.');

// Cleaned (no hidden control characters) and length-checked to fit the database.
$name = clean_input($body['name'] ?? '', 190);
$email = clean_input($body['email'] ?? '', 190);
$phone = clean_input($body['phone'] ?? '', 40);
$message = clean_input($body['message'] ?? '', 5000, true);
if ($name === null || $email === null || $phone === null) {
    json_error(400, 'One of the fields is too long.');
}
if ($message === null) {
    json_error(400, 'Your message is too long (max 5000 characters).');
}

if (!$name || !$email || !$message) {
    json_error(400, 'Name, email, and message are required.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error(400, 'Please provide a valid email address.');
}

$stmt = db()->prepare(
    'INSERT INTO contact_messages (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)'
);
// The form no longer has a Subject field; the column stays for older messages.
$stmt->execute([$name, $email, $phone, null, $message]);

// Reply to the visitor first, then email the enquiry to the Chambers.
json_ok_then(['message' => 'Thank you — your message has been received.'], 201, function () use ($name, $email, $phone, $message) {
    send_form_email('Website enquiry — ' . $name, [
        'Name'    => $name,
        'Phone'   => $phone ?: '—',
        'Message' => $message,
        'Sent from' => SITE_URL . '/contact.html',
    ], $email, 'contact.html');
});
