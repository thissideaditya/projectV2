<?php
/**
 * ADA LAW Chambers — api/magazines.php
 * ---------------------------------------------------------------
 * Magazines section — uploaded magazine issues (PDF + optional
 * cover image), backed by MySQL. Mirrors api/articles.php's pattern.
 *
 * PUBLIC (no login required):
 *   GET                                -> every published magazine, newest first
 *   GET  ?limit=3                      -> the 3 newest published magazines (Home page)
 *
 * ADMIN ONLY (must be logged in — see api/auth.php):
 *   GET    ?all=1                      -> every magazine, any status
 *   GET    ?id=5&all=1                 -> one magazine, any status
 *   POST                               -> create a magazine (JSON body)
 *   PUT    ?id=5                       -> update a magazine (JSON body)
 *   DELETE ?id=5                       -> delete a magazine
 * ---------------------------------------------------------------
 */

require __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = db();

try {

// ---------------------------------------------------------------
// GET
// ---------------------------------------------------------------
if ($method === 'GET') {
    if (isset($_GET['all'])) {
        require_admin();

        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare('SELECT * FROM magazines WHERE id = ?');
            $stmt->execute([$_GET['id']]);
            $magazine = $stmt->fetch();
            json_ok(['magazine' => $magazine ?: null]);
        }

        $stmt = $pdo->query('SELECT * FROM magazines ORDER BY created_at DESC, id DESC');
        json_ok(['magazines' => $stmt->fetchAll()]);
    }

    $sql = "SELECT * FROM magazines WHERE status = 'published' ORDER BY created_at DESC, id DESC";
    $limit = (int) ($_GET['limit'] ?? 0);
    if ($limit > 0) {
        $sql .= ' LIMIT ' . min($limit, 50);
    }
    $stmt = $pdo->query($sql);
    json_ok(['magazines' => $stmt->fetchAll()]);
}

// ---------------------------------------------------------------
// POST (create)
// ---------------------------------------------------------------
if ($method === 'POST') {
    require_admin();
    $body = json_body();

    $title = trim($body['title'] ?? '');
    $fileUrl = trim($body['file_url'] ?? '');
    if (!$title || !$fileUrl) {
        json_error(400, 'title and file_url are required.');
    }

    $slug = slugify($body['slug'] ?? $title);
    $base = $slug;
    $n = 2;
    $check = $pdo->prepare('SELECT id FROM magazines WHERE slug = ?');
    while (true) {
        $check->execute([$slug]);
        if (!$check->fetch()) break;
        $slug = $base . '-' . $n;
        $n++;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO magazines (title, slug, issue, description, file_url, cover_image, status)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $title,
        $slug,
        trim($body['issue'] ?? '') ?: null,
        $body['description'] ?? '',
        $fileUrl,
        $body['cover_image'] ?? '',
        in_array($body['status'] ?? '', ['draft', 'published'], true) ? $body['status'] : 'draft',
    ]);

    $id = $pdo->lastInsertId();
    $stmt = $pdo->prepare('SELECT * FROM magazines WHERE id = ?');
    $stmt->execute([$id]);
    json_ok(['magazine' => $stmt->fetch()], 201);
}

// ---------------------------------------------------------------
// PUT (update)
// ---------------------------------------------------------------
if ($method === 'PUT') {
    require_admin();
    $id = $_GET['id'] ?? null;
    if (!$id) json_error(400, 'Missing ?id=.');

    $body = json_body();
    $fields = [];
    $values = [];

    foreach (['title', 'issue', 'description', 'file_url', 'cover_image', 'status'] as $col) {
        if (array_key_exists($col, $body)) {
            $fields[] = "$col = ?";
            $values[] = $col === 'issue' ? (trim((string) $body[$col]) ?: null) : $body[$col];
        }
    }
    // Slugs aren't used in any public URL yet, so they're left as created
    // (re-slugifying on rename could collide with another magazine's slug).

    if (!$fields) json_error(400, 'No fields to update.');

    $stmt = $pdo->prepare('SELECT file_url, cover_image FROM magazines WHERE id = ?');
    $stmt->execute([$id]);
    $old = $stmt->fetch();

    $values[] = $id;
    $stmt = $pdo->prepare('UPDATE magazines SET ' . implode(', ', $fields) . ' WHERE id = ?');
    $stmt->execute($values);

    // A replaced PDF / cover is removed from the server.
    if ($old) {
        $replaced = [];
        foreach (['file_url', 'cover_image'] as $col) {
            if (array_key_exists($col, $body) && $body[$col] !== $old[$col]) $replaced[] = $old[$col];
        }
        delete_unreferenced_uploads($pdo, $replaced);
    }

    $stmt = $pdo->prepare('SELECT * FROM magazines WHERE id = ?');
    $stmt->execute([$id]);
    json_ok(['magazine' => $stmt->fetch()]);
}

// ---------------------------------------------------------------
// DELETE
// ---------------------------------------------------------------
if ($method === 'DELETE') {
    require_admin();
    $id = $_GET['id'] ?? null;
    if (!$id) json_error(400, 'Missing ?id=.');

    $stmt = $pdo->prepare('SELECT file_url, cover_image FROM magazines WHERE id = ?');
    $stmt->execute([$id]);
    $old = $stmt->fetch();

    $stmt = $pdo->prepare('DELETE FROM magazines WHERE id = ?');
    $stmt->execute([$id]);

    // Its PDF and cover go too, so they aren't downloadable after deletion.
    if ($old) delete_unreferenced_uploads($pdo, [$old['file_url'], $old['cover_image']]);
    json_ok();
}

json_error(405, 'Method not allowed.');

} catch (PDOException $e) {
    // Common cause: the magazines table hasn't been created yet in this
    // database — see api/schema.sql for the CREATE TABLE block.
    json_error(500, 'Database error in magazines.php: ' . $e->getMessage());
}
