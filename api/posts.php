<?php
/**
 * ADA LAW Chambers — api/posts.php
 * ---------------------------------------------------------------
 * Rules & Thoughts posts, backed by MySQL.
 *
 * PUBLIC (no login required):
 *   GET  ?category=rule|thought        -> published posts in that category
 *   GET  ?slug=some-post-slug          -> one published post
 *
 * ADMIN ONLY (must be logged in — see api/auth.php):
 *   GET    ?all=1[&category=rule]      -> every post, any status
 *   GET    ?id=5&all=1                 -> one post, any status
 *   POST                               -> create a post (JSON body)
 *   PUT    ?id=5                       -> update a post (JSON body)
 *   DELETE ?id=5                       -> delete a post
 * ---------------------------------------------------------------
 */

require __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = db();

// ---------------------------------------------------------------
// GET
// ---------------------------------------------------------------
if ($method === 'GET') {
    $all = isset($_GET['all']);

    if ($all) {
        require_admin();

        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare('SELECT * FROM posts WHERE id = ?');
            $stmt->execute([$_GET['id']]);
            $post = $stmt->fetch();
            json_ok(['post' => $post ?: null]);
        }

        if (!empty($_GET['category'])) {
            $stmt = $pdo->prepare('SELECT * FROM posts WHERE category = ? ORDER BY created_at DESC');
            $stmt->execute([$_GET['category']]);
        } else {
            $stmt = $pdo->query('SELECT * FROM posts ORDER BY created_at DESC');
        }
        json_ok(['posts' => $stmt->fetchAll()]);
    }

    if (!empty($_GET['slug'])) {
        $stmt = $pdo->prepare("SELECT * FROM posts WHERE slug = ? AND status = 'published'");
        $stmt->execute([$_GET['slug']]);
        $post = $stmt->fetch();
        json_ok(['post' => $post ?: null]);
    }

    if (!empty($_GET['category'])) {
        $stmt = $pdo->prepare("SELECT * FROM posts WHERE category = ? AND status = 'published' ORDER BY created_at DESC");
        $stmt->execute([$_GET['category']]);
        json_ok(['posts' => $stmt->fetchAll()]);
    }

    json_error(400, 'Provide ?category= or ?slug= (or ?all=1 as an admin).');
}

// ---------------------------------------------------------------
// POST (create)
// ---------------------------------------------------------------
if ($method === 'POST') {
    require_admin();
    $body = json_body();

    $title = trim($body['title'] ?? '');
    $category = $body['category'] ?? '';
    if (!$title || !in_array($category, ['rule', 'thought'], true)) {
        json_error(400, 'title and a valid category are required.');
    }

    $slug = slugify($body['slug'] ?? $title);
    // Ensure uniqueness by appending -2, -3, ... if needed
    $base = $slug;
    $n = 2;
    $check = $pdo->prepare('SELECT id FROM posts WHERE slug = ?');
    while (true) {
        $check->execute([$slug]);
        if (!$check->fetch()) break;
        $slug = $base . '-' . $n;
        $n++;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO posts (category, title, slug, excerpt, content, cover_image, status)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $category,
        $title,
        $slug,
        $body['excerpt'] ?? '',
        $body['content'] ?? '',
        $body['cover_image'] ?? '',
        in_array($body['status'] ?? '', ['draft', 'published'], true) ? $body['status'] : 'draft',
    ]);

    $id = $pdo->lastInsertId();
    $stmt = $pdo->prepare('SELECT * FROM posts WHERE id = ?');
    $stmt->execute([$id]);
    json_ok(['post' => $stmt->fetch()], 201);
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

    foreach (['title', 'category', 'excerpt', 'content', 'cover_image', 'status'] as $col) {
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
    $stmt = $pdo->prepare('UPDATE posts SET ' . implode(', ', $fields) . ' WHERE id = ?');
    $stmt->execute($values);

    $stmt = $pdo->prepare('SELECT * FROM posts WHERE id = ?');
    $stmt->execute([$id]);
    json_ok(['post' => $stmt->fetch()]);
}

// ---------------------------------------------------------------
// DELETE
// ---------------------------------------------------------------
if ($method === 'DELETE') {
    require_admin();
    $id = $_GET['id'] ?? null;
    if (!$id) json_error(400, 'Missing ?id=.');

    $stmt = $pdo->prepare('DELETE FROM posts WHERE id = ?');
    $stmt->execute([$id]);
    json_ok();
}

json_error(405, 'Method not allowed.');
