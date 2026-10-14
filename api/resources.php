<?php
/**
 * ADA LAW Chambers — api/resources.php
 * ---------------------------------------------------------------
 * PUBLIC (no login required):
 *   GET                          -> every resource, grouped for display
 *
 * ADMIN ONLY (must be logged in — see api/auth.php):
 *   POST                         -> create a resource (JSON body)
 *   PUT    ?id=5                 -> update a resource (JSON body)
 *   DELETE ?id=5                 -> delete a resource
 *
 * Fields: category (required), subcategory (optional), title, url,
 * description (optional), display_order.
 * ---------------------------------------------------------------
 */

require __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = db();

try {

if ($method === 'GET') {
    $stmt = $pdo->query('SELECT * FROM resources ORDER BY category ASC, subcategory ASC, display_order ASC, created_at DESC');
    json_ok(['resources' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    require_admin();
    $body = json_body();

    $category = trim($body['category'] ?? '');
    $title = trim($body['title'] ?? '');
    $url = trim($body['url'] ?? '');
    if (!$category || !$title || !$url) {
        json_error(400, 'category, title and url are required.');
    }
    if (!preg_match('~^https?://~i', $url)) {
        $url = 'https://' . $url;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO resources (category, subcategory, title, url, description, display_order) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $category,
        trim($body['subcategory'] ?? '') ?: null,
        $title,
        $url,
        $body['description'] ?? '',
        (int) ($body['display_order'] ?? 0),
    ]);

    $id = $pdo->lastInsertId();
    $stmt = $pdo->prepare('SELECT * FROM resources WHERE id = ?');
    $stmt->execute([$id]);
    json_ok(['resource' => $stmt->fetch()], 201);
}

if ($method === 'PUT') {
    require_admin();
    $id = $_GET['id'] ?? null;
    if (!$id) json_error(400, 'Missing ?id=.');

    $body = json_body();
    $fields = [];
    $values = [];

    foreach (['category', 'subcategory', 'title', 'url', 'description', 'display_order'] as $col) {
        if (array_key_exists($col, $body)) {
            $fields[] = "$col = ?";
            if ($col === 'display_order') {
                $values[] = (int) $body[$col];
            } elseif ($col === 'subcategory') {
                $values[] = trim((string) $body[$col]) ?: null;
            } else {
                $values[] = $body[$col];
            }
        }
    }
    if (!$fields) json_error(400, 'No fields to update.');

    $values[] = $id;
    $stmt = $pdo->prepare('UPDATE resources SET ' . implode(', ', $fields) . ' WHERE id = ?');
    $stmt->execute($values);

    $stmt = $pdo->prepare('SELECT * FROM resources WHERE id = ?');
    $stmt->execute([$id]);
    json_ok(['resource' => $stmt->fetch()]);
}

if ($method === 'DELETE') {
    require_admin();
    $id = $_GET['id'] ?? null;
    if (!$id) json_error(400, 'Missing ?id=.');

    $stmt = $pdo->prepare('DELETE FROM resources WHERE id = ?');
    $stmt->execute([$id]);
    json_ok();
}

json_error(405, 'Method not allowed.');

} catch (PDOException $e) {
    // Common cause: the resources table hasn't been created yet in this
    // database — see api/schema.sql for the CREATE TABLE block.
    json_error(500, 'Database error in resources.php: ' . $e->getMessage());
}
