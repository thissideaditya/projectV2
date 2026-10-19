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

/** Post rows go out with their HTML made safe (see sanitize_post_html in config.php). */
function clean_post($post)
{
    if (is_array($post) && array_key_exists('content', $post)) {
        $post['content'] = sanitize_post_html($post['content']);
    }
    return $post;
}
$pdo = db();

// ---------------------------------------------------------------
// GET
// ---------------------------------------------------------------
if ($method === 'GET') {
    $all = isset($_GET['all']);

    if ($all) {
        require_admin();

        if (isset($_GET['id'])) {
            $id = query_id();
            $stmt = $pdo->prepare('SELECT * FROM posts WHERE id = ?');
            $stmt->execute([$id]);
            $post = $stmt->fetch();
            json_ok(['post' => $post ? clean_post($post) : null]);
        }

        if (query_str('category') !== '') {
            $stmt = $pdo->prepare('SELECT * FROM posts WHERE category = ? ORDER BY created_at DESC');
            $stmt->execute([query_str('category')]);
        } else {
            $stmt = $pdo->query('SELECT * FROM posts ORDER BY created_at DESC');
        }
        json_ok(['posts' => array_map('clean_post', $stmt->fetchAll())]);
    }

    if (query_str('slug') !== '') {
        $stmt = $pdo->prepare("SELECT * FROM posts WHERE slug = ? AND status = 'published'");
        $stmt->execute([query_str('slug')]);
        $post = $stmt->fetch();
        json_ok(['post' => $post ? clean_post($post) : null]);
    }

    if (query_str('category') !== '') {
        $stmt = $pdo->prepare("SELECT * FROM posts WHERE category = ? AND status = 'published' ORDER BY created_at DESC");
        $stmt->execute([query_str('category')]);
        json_ok(['posts' => array_map('clean_post', $stmt->fetchAll())]);
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
        sanitize_post_html($body['content'] ?? ''),
        $body['cover_image'] ?? '',
        in_array($body['status'] ?? '', ['draft', 'published'], true) ? $body['status'] : 'draft',
    ]);

    $id = $pdo->lastInsertId();
    $stmt = $pdo->prepare('SELECT * FROM posts WHERE id = ?');
    $stmt->execute([$id]);
    json_ok(['post' => clean_post($stmt->fetch())], 201);
}

// ---------------------------------------------------------------
// PUT (update)
// ---------------------------------------------------------------
if ($method === 'PUT') {
    require_admin();
    $id = query_id();

    $body = json_body();
    $fields = [];
    $values = [];

    foreach (['title', 'category', 'excerpt', 'content', 'cover_image', 'status'] as $col) {
        if (array_key_exists($col, $body)) {
            $fields[] = "$col = ?";
            $values[] = $col === 'content' ? sanitize_post_html($body[$col]) : $body[$col];
        }
    }
    // The slug is the post's address (post.php?slug=...). It is set once, when
    // the post is created, and NOT changed when the title is edited — links
    // already shared or indexed by Google must keep working.

    if (!$fields) json_error(400, 'No fields to update.');

    $stmt = $pdo->prepare('SELECT cover_image FROM posts WHERE id = ?');
    $stmt->execute([$id]);
    $old = $stmt->fetch();

    $values[] = $id;
    $stmt = $pdo->prepare('UPDATE posts SET ' . implode(', ', $fields) . ' WHERE id = ?');
    $stmt->execute($values);

    // A replaced cover picture is removed from the server.
    if ($old && array_key_exists('cover_image', $body) && $body['cover_image'] !== $old['cover_image']) {
        delete_unreferenced_uploads($pdo, [$old['cover_image']]);
    }

    $stmt = $pdo->prepare('SELECT * FROM posts WHERE id = ?');
    $stmt->execute([$id]);
    json_ok(['post' => clean_post($stmt->fetch())]);
}

// ---------------------------------------------------------------
// DELETE
// ---------------------------------------------------------------
if ($method === 'DELETE') {
    require_admin();
    $id = query_id();

    $stmt = $pdo->prepare('SELECT cover_image FROM posts WHERE id = ?');
    $stmt->execute([$id]);
    $old = $stmt->fetch();

    $stmt = $pdo->prepare('DELETE FROM posts WHERE id = ?');
    $stmt->execute([$id]);

    // Its uploaded picture goes too, so it isn't downloadable after deletion.
    if ($old) delete_unreferenced_uploads($pdo, [$old['cover_image']]);
    json_ok();
}

json_error(405, 'Method not allowed.');
