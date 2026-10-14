<?php
/**
 * ADA LAW Chambers — api/articles.php
 * ---------------------------------------------------------------
 * Articles section — downloadable Articles/PPTs/Presentations,
 * backed by MySQL. Mirrors api/posts.php's pattern.
 *
 * PUBLIC (no login required):
 *   GET                                -> every published article
 *   GET  ?slug=some-article-slug       -> one published article
 *
 * ADMIN ONLY (must be logged in — see api/auth.php):
 *   GET    ?all=1                      -> every article, any status
 *   GET    ?id=5&all=1                 -> one article, any status
 *   POST                               -> create an article (JSON body)
 *   PUT    ?id=5                       -> update an article (JSON body)
 *   DELETE ?id=5                       -> delete an article
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
    $all = isset($_GET['all']);

    if ($all) {
        require_admin();

        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare('SELECT * FROM articles WHERE id = ?');
            $stmt->execute([$_GET['id']]);
            $article = $stmt->fetch();
            json_ok(['article' => $article ?: null]);
        }

        $stmt = $pdo->query('SELECT * FROM articles ORDER BY created_at DESC');
        json_ok(['articles' => $stmt->fetchAll()]);
    }

    if (!empty($_GET['slug'])) {
        $stmt = $pdo->prepare("SELECT * FROM articles WHERE slug = ? AND status = 'published'");
        $stmt->execute([$_GET['slug']]);
        $article = $stmt->fetch();
        json_ok(['article' => $article ?: null]);
    }

    $stmt = $pdo->query("SELECT * FROM articles WHERE status = 'published' ORDER BY created_at DESC");
    json_ok(['articles' => $stmt->fetchAll()]);
}

// ---------------------------------------------------------------
// POST (create)
// ---------------------------------------------------------------
if ($method === 'POST') {
    require_admin();
    $body = json_body();

    $title = trim($body['title'] ?? '');
    $fileUrl = trim($body['file_url'] ?? '');
    $fileType = trim($body['file_type'] ?? '');
    if (!$title || !$fileUrl || !$fileType) {
        json_error(400, 'title, file_url and file_type are required.');
    }

    $slug = slugify($body['slug'] ?? $title);
    $base = $slug;
    $n = 2;
    $check = $pdo->prepare('SELECT id FROM articles WHERE slug = ?');
    while (true) {
        $check->execute([$slug]);
        if (!$check->fetch()) break;
        $slug = $base . '-' . $n;
        $n++;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO articles (title, slug, description, file_url, file_type, cover_image, status)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $title,
        $slug,
        $body['description'] ?? '',
        $fileUrl,
        $fileType,
        $body['cover_image'] ?? '',
        in_array($body['status'] ?? '', ['draft', 'published'], true) ? $body['status'] : 'draft',
    ]);

    $id = $pdo->lastInsertId();
    $stmt = $pdo->prepare('SELECT * FROM articles WHERE id = ?');
    $stmt->execute([$id]);
    json_ok(['article' => $stmt->fetch()], 201);
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

    foreach (['title', 'description', 'file_url', 'file_type', 'cover_image', 'status'] as $col) {
        if (array_key_exists($col, $body)) {
            $fields[] = "$col = ?";
            $values[] = $body[$col];
        }
    }
    if (array_key_exists('title', $body) && !array_key_exists('slug', $body)) {
        $fields[] = 'slug = ?';
        $values[] = slugify($body['title']);
    } elseif (array_key_exists('slug', $body)) {
        $fields[] = 'slug = ?';
        $values[] = slugify($body['slug']);
    }

    if (!$fields) json_error(400, 'No fields to update.');

    $values[] = $id;
    $stmt = $pdo->prepare('UPDATE articles SET ' . implode(', ', $fields) . ' WHERE id = ?');
    $stmt->execute($values);

    $stmt = $pdo->prepare('SELECT * FROM articles WHERE id = ?');
    $stmt->execute([$id]);
    json_ok(['article' => $stmt->fetch()]);
}

// ---------------------------------------------------------------
// DELETE
// ---------------------------------------------------------------
if ($method === 'DELETE') {
    require_admin();
    $id = $_GET['id'] ?? null;
    if (!$id) json_error(400, 'Missing ?id=.');

    $stmt = $pdo->prepare('DELETE FROM articles WHERE id = ?');
    $stmt->execute([$id]);
    json_ok();
}

json_error(405, 'Method not allowed.');

} catch (PDOException $e) {
    // Common cause: the articles table hasn't been created yet in this
    // database — see api/schema.sql for the CREATE TABLE block.
    json_error(500, 'Database error in articles.php: ' . $e->getMessage());
}
