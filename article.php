<?php
/**
 * ADA LAW Chambers — article.php
 * ---------------------------------------------------------------
 * One page per article: article.php?slug=<slug>
 *
 * Why this file exists: link-preview bots (WhatsApp, Facebook,
 * LinkedIn, ...) can't take a picture out of a PDF/PPT, so sharing
 * the file itself showed no thumbnail. This page writes the
 * article's cover image into the og:image tag on the server (same
 * approach as magazine.php), and the Share button shares this page.
 *
 * Visitors see the cover, title and description, with buttons to
 * open or download the file.
 * ---------------------------------------------------------------
 */

require __DIR__ . '/inc/site.php';

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

$art = null;
if ($slug !== '') {
    // site_query() returns null instead of failing if the DB is unreachable.
    $rows = site_query("SELECT * FROM articles WHERE slug = ? AND status = 'published' LIMIT 1", [$slug]);
    $art = $rows ? $rows[0] : null;
    if ($rows !== null && !$art) {
        http_response_code(404);
    }
}

$siteName = 'ADA Law Chambers';
$baseUrl  = SITE_BASE_URL;

function h($str) {
    return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
}

function article_abs_url(string $url, string $baseUrl): string {
    return preg_match('~^https?://~i', $url) ? $url : $baseUrl . '/' . ltrim($url, '/');
}

$cover = '';
if ($art) {
    $pageTitle = $art['title'] . ' — ' . $siteName;
    $ogTitle   = $art['title'];
    $ogDesc    = $art['description'] ? $art['description'] : 'Read and download this article from ' . $siteName . '.';

    // Only a safe web/relative address may become the share image.
    $cover   = site_safe_url((string) $art['cover_image']) === '#' ? '' : (string) $art['cover_image'];
    $ogImage = $cover ? article_abs_url($cover, $baseUrl) : $baseUrl . '/assets/images/og-default.jpg';

    $type = strtolower((string) $art['file_type']);
    $absoluteFile = article_abs_url((string) $art['file_url'], $baseUrl);
    // PDFs open in the browser; Office files go through Microsoft's viewer.
    $openUrl = in_array($type, ['ppt', 'pptx', 'doc', 'docx'], true)
        ? 'https://view.officeapps.live.com/op/view.aspx?src=' . rawurlencode($absoluteFile)
        : $absoluteFile;
    $typeLabel = in_array($type, ['ppt', 'pptx'], true) ? 'Presentation'
        : (in_array($type, ['doc', 'docx'], true) ? 'Document' : ($type === 'pdf' ? 'PDF' : strtoupper($type)));

    $fileUrl   = site_safe_url((string) $art['file_url']);
    $safeTitle = trim(preg_replace('/[^\w\- ]+/', '', (string) $art['title']));
    $fileName  = ($safeTitle !== '' ? $safeTitle : 'article') . ($type ? '.' . $type : '');
} else {
    $pageTitle = 'Article — ' . $siteName;
    $ogTitle   = $pageTitle;
    $ogDesc    = 'Articles and presentations from ' . $siteName . ', free to read and download.';
    $ogImage   = $baseUrl . '/assets/images/og-default.jpg';
}

$ogUrl = $baseUrl . '/article.php' . ($slug !== '' ? '?slug=' . urlencode($slug) : '');

// Breadcrumbs: Home › Insights › Articles › this article.
$crumbs = $art ? [['Insights', 'insights.html'], ['Articles', 'articles.html'], [$art['title'], null]] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo h($pageTitle); ?></title>
<meta name="description" content="<?php echo h($ogDesc); ?>">
<?php if ($art): ?>
<link rel="canonical" href="<?php echo h($ogUrl); ?>">
<?php else: ?>
<!-- No published article matches this slug: keep this generic page out of search results. -->
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

<link rel="icon" href="assets/images/favicon.jpg" type="image/jpeg" sizes="192x192">
<link rel="apple-touch-icon" href="assets/images/apple-touch-icon.jpg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500&family=Libertinus+Serif:ital,wght@0,400;0,600;0,700;1,400;1,600;1,700&display=swap" rel="stylesheet">

<link rel="stylesheet" href="css/style.css">
<?php if ($crumbs) echo site_breadcrumb_jsonld($crumbs, $ogUrl) . "\n"; ?>
</head>
<body>

<div id="site-disclaimer-slot"></div>

<?php echo site_partial('header'); ?>

<section class="section" style="padding-top:3rem;">
  <div class="container">
<?php if ($art): ?>
    <?php echo site_breadcrumb_html($crumbs); ?>
    <div class="magazine-page article-page<?php if (!$cover) echo ' article-page--nocover'; ?>">
<?php if ($cover): ?>
      <a class="magazine-cover" href="<?php echo h(site_safe_url($openUrl)); ?>" target="_blank" rel="noopener noreferrer" tabindex="-1" aria-hidden="true">
        <img src="<?php echo h($cover); ?>" alt="Cover of <?php echo h($art['title']); ?>">
      </a>
<?php endif; ?>
      <div>
        <span class="eyebrow"><?php echo h($typeLabel); ?> | <?php echo site_date($art['created_at']); ?></span>
        <h1 class="section-title"><?php echo h($art['title']); ?></h1>
        <div class="divider-gold"></div>
        <?php if ($art['description']): ?><p class="lede"><?php echo nl2br(h($art['description'])); ?></p><?php endif; ?>
        <div class="magazine-actions">
          <a class="btn btn--gold" href="<?php echo h(site_safe_url($openUrl)); ?>" target="_blank" rel="noopener noreferrer">Open <?php echo h($typeLabel); ?> &rarr;</a>
          <a class="btn btn--outline-dark" href="<?php echo h($fileUrl); ?>" download="<?php echo h($fileName); ?>">Download &darr;</a>
          <?php echo site_share_button($ogUrl, $art['title']); ?>
        </div>
        <p style="margin-top:2rem;"><a href="articles.html" class="btn btn--sm btn--outline-dark">&larr; All Articles</a></p>
      </div>
    </div>
<?php else: ?>
    <div class="empty-state">This article could not be found. <a href="articles.html">See all articles</a>.</div>
<?php endif; ?>
  </div>
</section>

<?php echo site_partial('footer'); ?>

<script src="js/partials.js"></script>
<script src="js/main.js"></script>
<script src="js/api-config.js"></script>
<script src="js/share.js?v=3"></script>
</body>
</html>
