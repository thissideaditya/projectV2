<?php
/**
 * ADA LAW Chambers — sitemap.php  (served as /sitemap.xml, see .htaccess)
 * ---------------------------------------------------------------
 * Lists every public page, plus every published Rules/Thoughts post
 * straight from the database, so new posts reach search engines
 * without anyone editing this file. To add a new public page to the
 * sitemap, add its filename to $pages below.
 *
 * If the database can't be reached, the static pages are still
 * listed (the sitemap never breaks because of the DB).
 * ---------------------------------------------------------------
 */

define('ADA_PAGE_RENDER', true); // no session / CORS headers, see api/config.php
require __DIR__ . '/api/config.php';

$base = 'https://www.adalawchambers.com';

// filename => [changefreq, priority]
$pages = [
    'index.html'           => ['weekly',  '1.0'],
    'about.html'           => ['monthly', '0.8'],
    'practice-areas.html'  => ['monthly', '0.9'],
    'team.html'            => ['monthly', '0.8'],
    'insights.html'        => ['weekly',  '0.7'],
    'rules.html'           => ['weekly',  '0.7'],
    'thoughts.html'        => ['weekly',  '0.7'],
    'articles.html'        => ['weekly',  '0.7'],
    'magazines.html'       => ['weekly',  '0.7'],
    'resources.html'       => ['monthly', '0.6'],
    'important-links.html' => ['monthly', '0.6'],
    'pro-bono.html'        => ['yearly',  '0.5'],
    'careers.html'         => ['monthly', '0.5'],
    'contact.html'         => ['yearly',  '0.7'],
    'privacy.html'         => ['yearly',  '0.2'],
];

$urls = [];
foreach ($pages as $file => [$freq, $priority]) {
    $path = __DIR__ . '/' . $file;
    if (!is_file($path)) continue;
    $urls[] = [
        'loc'        => $base . ($file === 'index.html' ? '/' : '/' . $file),
        'lastmod'    => date('Y-m-d', filemtime($path)),
        'changefreq' => $freq,
        'priority'   => $priority,
    ];
}

// Published posts. Connects directly rather than through db(), because
// db() answers a failed connection with a JSON error and exits — that
// would replace the whole sitemap instead of just leaving posts out.
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $stmt = $pdo->query("SELECT slug, created_at FROM posts WHERE status = 'published' ORDER BY created_at DESC");
    foreach ($stmt as $post) {
        $urls[] = [
            'loc'        => $base . '/post.php?slug=' . rawurlencode($post['slug']),
            'lastmod'    => date('Y-m-d', strtotime($post['created_at'])),
            'changefreq' => 'monthly',
            'priority'   => '0.6',
        ];
    }
} catch (Throwable $e) {
    // Leave posts out; the static pages above are still a valid sitemap.
}

header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1, 'UTF-8') . "</loc>\n";
    echo '    <lastmod>' . $u['lastmod'] . "</lastmod>\n";
    echo '    <changefreq>' . $u['changefreq'] . "</changefreq>\n";
    echo '    <priority>' . $u['priority'] . "</priority>\n";
    echo "  </url>\n";
}
echo "</urlset>\n";
