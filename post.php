<?php
/**
 * ADA LAW Chambers — post.php
 * ---------------------------------------------------------------
 * Same visible page as the old post.html, but the <title> and the
 * social-share tags (og:title, og:image, etc.) are now filled in
 * SERVER-SIDE, before any JavaScript runs.
 *
 * Why this file needed to exist: link-preview bots (WhatsApp,
 * Facebook, LinkedIn, iMessage, ...) only read the raw HTML a server
 * sends back — they do not run JavaScript. The old post.html always
 * sent the same generic "Article — ADA Law Chambers" title and no
 * image, no matter which post you shared, because the real title/
 * image were only ever filled in afterwards by posts-render.js in
 * the visitor's browser. This file fixes that by looking the post
 * up in the database and writing the correct tags directly into the
 * page before anything is sent to the browser (or the bot).
 *
 * The post itself, the shared header/footer and the breadcrumbs are
 * also written in on the server (see inc/site.php), so search engines
 * read the full article without running JavaScript. posts-render.js
 * sees the data-ssr marker and leaves it alone; if the database was
 * unreachable here, it loads the post in the browser as before.
 * ---------------------------------------------------------------
 */

require __DIR__ . '/inc/site.php';

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

$post = null;
if ($slug !== '') {
    // site_query() returns null instead of failing if the DB is unreachable;
    // the generic fallback tags below are used then.
    $rows = site_query("SELECT * FROM posts WHERE slug = ? AND status = 'published' LIMIT 1", [$slug]);
    $post = $rows ? $rows[0] : null;
    // The database answered but has no such post: a real 404 for search engines.
    if ($rows !== null && !$post) {
        http_response_code(404);
    }
}

// ---- Work out what to put in the tags -------------------------------
$siteName = 'ADA Law Chambers';
$baseUrl  = 'https://www.adalawchambers.com'; // the live domain — also used for the canonical URL

if ($post) {
    $pageTitle = $post['title'] . ' — ' . $siteName;
    $ogTitle   = $post['title'];
    $ogDesc    = $post['excerpt'] !== '' ? $post['excerpt'] : 'Read this article from ' . $siteName . '.';

    $cover = $post['cover_image'];
    if ($cover && preg_match('~^https?://~i', $cover)) {
        $ogImage = $cover; // already a full URL
    } elseif ($cover) {
        $ogImage = $baseUrl . '/' . ltrim($cover, '/');
    } else {
        $ogImage = $baseUrl . '/assets/images/og-default.jpg';
    }
} else {
    $pageTitle = 'Article — ' . $siteName;
    $ogTitle   = $pageTitle;
    $ogDesc    = 'Rules, Thoughts and Insights from ' . $siteName . '.';
    $ogImage   = $baseUrl . '/assets/images/og-default.jpg';
}

$ogUrl = $baseUrl . '/post.php' . ($slug !== '' ? '?slug=' . urlencode($slug) : '');

function h($str) {
    return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
}

// Breadcrumbs: Home › Insights › Rules|Thoughts › this post.
$crumbs = null;
if ($post) {
    $section = $post['category'] === 'rule' ? ['Rules', 'rules.html'] : ['Thoughts', 'thoughts.html'];
    $crumbs = [['Insights', 'insights.html'], $section, [$post['title'], null]];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo h($pageTitle); ?></title>
<meta name="description" content="<?php echo h($ogDesc); ?>">
<?php if ($post): ?>
<link rel="canonical" href="<?php echo h($ogUrl); ?>">
<?php else: ?>
<!-- No published post matches this slug: keep this generic page out of search results. -->
<meta name="robots" content="noindex, follow">
<?php endif; ?>

<meta property="og:type" content="article">
<meta property="og:site_name" content="<?php echo h($siteName); ?>">
<meta property="og:title" content="<?php echo h($ogTitle); ?>">
<meta property="og:description" content="<?php echo h($ogDesc); ?>">
<meta property="og:image" content="<?php echo h($ogImage); ?>">
<meta property="og:url" content="<?php echo h($ogUrl); ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo h($ogTitle); ?>">
<meta name="twitter:description" content="<?php echo h($ogDesc); ?>">
<meta name="twitter:image" content="<?php echo h($ogImage); ?>">

<link rel="icon" href="assets/images/favicon-32.png" type="image/png" sizes="32x32">
<link rel="apple-touch-icon" href="assets/images/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Source+Serif+4:opsz,wght@8..60,400;8..60,500;8..60,600&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500&family=Libertinus+Serif:ital,wght@0,400;0,600;0,700;1,400;1,600;1,700&family=Merriweather:wght@400;700&family=Lora:wght@400;600;700&family=PT+Serif:wght@400;700&family=Libre+Baskerville:wght@400;700&family=Roboto:wght@400;500;700&family=Open+Sans:wght@400;600;700&family=Lato:wght@400;700&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">

<link rel="stylesheet" href="css/style.css">
<?php if ($crumbs) echo site_breadcrumb_jsonld($crumbs, $ogUrl) . "\n"; ?>
</head>
<body>

<div id="site-disclaimer-slot"></div>

<?php echo site_partial('header'); ?>

<section class="section" style="padding-top:3rem;">
  <div class="container">
    <div style="max-width:760px;margin:0 auto 1.5rem;">
      <?php if ($crumbs) echo site_breadcrumb_html($crumbs); ?>
      <a href="insights.html" class="btn btn--sm btn--outline-dark">&larr; Back to Insights</a>
    </div>
<?php if ($post): ?>
    <div id="article-mount" data-ssr>
      <header class="article-header">
        <span class="eyebrow"><?php echo $post['category'] === 'rule' ? 'Rules' : 'Thoughts'; ?> | <?php echo site_date($post['created_at']); ?></span>
        <h1><?php echo h($post['title']); ?></h1>
        <div style="margin-top:1rem;"><?php echo site_share_button($ogUrl, $post['title']); ?></div>
      </header>
      <img src="<?php echo h($post['cover_image']); ?>" alt="" style="border-radius:6px;margin-bottom:2.5rem;max-height:420px;object-fit:cover;width:100%;">
      <?php // Admin-authored HTML from the rich-text editor, shown as-is (same as posts-render.js). ?>
      <div class="article-body"><?php echo $post['content']; ?></div>
    </div>
<?php else: ?>
    <div id="article-mount">
      <div class="empty-state">Loading&hellip;</div>
    </div>
<?php endif; ?>
  </div>
</section>

<?php echo site_partial('footer'); ?>

<script src="js/partials.js"></script>
<script src="js/main.js"></script>
<script src="js/api-config.js"></script>
<script src="js/hostinger-client.js"></script>
<script src="js/share.js?v=1"></script>
<script src="js/posts-render.js?v=6"></script>
</body>
</html>
