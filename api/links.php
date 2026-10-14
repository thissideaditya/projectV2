<?php
/**
 * ADA LAW Chambers — api/links.php
 * ---------------------------------------------------------------
 * PUBLIC (no login required):
 *   GET                          -> every link, ordered for display
 *
 * ADMIN ONLY (must be logged in — see api/auth.php):
 *   POST                         -> create a link (JSON body)
 *   PUT    ?id=5                 -> update a link (JSON body)
 *   DELETE ?id=5                 -> delete a link
 * ---------------------------------------------------------------
 */

require __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = db();

try {

if ($method === 'GET') {
    $stmt = $pdo->query('SELECT * FROM important_links ORDER BY display_order ASC, created_at DESC');
    json_ok(['links' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    require_admin();
    $body = json_body();

    $title = trim($body['title'] ?? '');
    $url = trim($body['url'] ?? '');
    if (!$title || !$url) {
        json_error(400, 'title and url are required.');
    }
    if (!preg_match('~^https?://~i', $url)) {
        $url = 'https://' . $url;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO important_links (title, url, description, display_order) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([
        $title,
        $url,
        $body['description'] ?? '',
        (int) ($body['display_order'] ?? 0),
    ]);

    $id = $pdo->lastInsertId();
    $stmt = $pdo->prepare('SELECT * FROM important_links WHERE id = ?');
    $stmt->execute([$id]);
    json_ok(['link' => $stmt->fetch()], 201);
}

if ($method === 'PUT') {
    require_admin();
    $id = $_GET['id'] ?? null;
    if (!$id) json_error(400, 'Missing ?id=.');

    $body = json_body();
    $fields = [];
    $values = [];

    foreach (['title', 'url', 'description', 'display_order'] as $col) {
        if (array_key_exists($col, $body)) {
            $fields[] = "$col = ?";
            $values[] = $col === 'display_order' ? (int) $body[$col] : $body[$col];
        }
    }
    if (!$fields) json_error(400, 'No fields to update.');

    $values[] = $id;
    $stmt = $pdo->prepare('UPDATE important_links SET ' . implode(', ', $fields) . ' WHERE id = ?');
    $stmt->execute($values);

    $stmt = $pdo->prepare('SELECT * FROM important_links WHERE id = ?');
    $stmt->execute([$id]);
    json_ok(['link' => $stmt->fetch()]);
}

if ($method === 'DELETE') {
    require_admin();
    $id = $_GET['id'] ?? null;
    if (!$id) json_error(400, 'Missing ?id=.');

    $stmt = $pdo->prepare('DELETE FROM important_links WHERE id = ?');
    $stmt->execute([$id]);
    json_ok();
}

json_error(405, 'Method not allowed.');

} catch (PDOException $e) {
    // Common cause: the important_links table hasn't been created yet
    // in this database — see api/schema.sql for the CREATE TABLE block.
    json_error(500, 'Database error in links.php: ' . $e->getMessage());
}
