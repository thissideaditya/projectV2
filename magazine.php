<?php
/**
 * ADA LAW Chambers — magazine.php
 * ---------------------------------------------------------------
 * One page per magazine issue: magazine.php?slug=<slug>
 *
 * Why this file exists: the Share button used to share the PDF
 * itself, and link-preview bots (WhatsApp, Facebook, LinkedIn, ...)
 * can't take a picture out of a PDF, so shared magazines showed no
 * image. This page writes the issue's cover picture into the
 * og:image tag on the server (same approach as post.php), and the
 * Share button now shares this page instead of the PDF.
 *
 * Visitors see the cover, title and description, with buttons to
 * read or download the PDF.
 * ---------------------------------------------------------------
 */

require __DIR__ . '/inc/site.php';

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

$mag = null;
if ($slug !== '') {
    // site_query() returns null instead of failing if the DB is unreachable.
    $rows = site_query("SELECT * FROM magazines WHERE slug = ? AND status = 'published' LIMIT 1", [$slug]);
    $mag = $rows ? $rows[0] : null;
    if ($rows !== null && !$mag) {
        http_response_code(404);
    }
}

// ---- Work out what to put in the tags -------------------------------
$siteName = 'ADA Law Chambers';
$baseUrl  = 'https://www.adalawchambers.com'; // the live domain — also used for the canonical URL

if ($mag) {
    $pageTitle = $mag['title'] . ' — ' . $siteName;
    $ogTitle   = $mag['title'] . ($mag['issue'] ? ' | ' . $mag['issue'] : '');
    $ogDesc    = $mag['description'] ? $mag['description'] : 'Read and download this issue of the ' . $siteName . ' magazine, free.';

    // Only a safe web/relative address may become the share image.
    $cover = site_safe_url($mag['cover_image']) === '#' ? '' : (string) $mag['cover_image'];
    if ($cover && preg_match('~^https?://~i', $cover)) {
        $ogImage = $cover; // already a full URL
    } elseif ($cover) {
        $ogImage = $baseUrl . '/' . ltrim($cover, '/');
    } else {
        $ogImage = $baseUrl . '/assets/images/og-default.jpg';
    }

    $fileUrl   = site_safe_url($mag['file_url']);
    $safeTitle = trim(preg_replace('/[^\w\- ]+/', '', (string) $mag['title']));
    $fileName  = ($safeTitle !== '' ? $safeTitle : 'magazine') . '.pdf';
} else {
    $pageTitle = 'Magazine — ' . $siteName;
    $ogTitle   = $pageTitle;
    $ogDesc    = 'Magazine issues from ' . $siteName . ', free to read and download.';
    $ogImage   = $baseUrl . '/assets/images/og-default.jpg';
}

$ogUrl = $baseUrl . '/magazine.php' . ($slug !== '' ? '?slug=' . urlencode($slug) : '');

function h($str) {
    return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
}

// Breadcrumbs: Home › Insights › Magazines › this issue.
$crumbs = $mag ? [['Insights', 'insights.html'], ['Magazines', 'magazines.html'], [$mag['title'], null]] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo h($pageTitle); ?></title>
<meta name="description" content="<?php echo h($ogDesc); ?>">
<?php if ($mag): ?>
<link rel="canonical" href="<?php echo h($ogUrl); ?>">
<?php else: ?>
<!-- No published issue matches this slug: keep this generic page out of search results. -->
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
<?php if ($mag): ?>
    <?php echo site_breadcrumb_html($crumbs); ?>
    <div class="magazine-page">
      <a class="magazine-cover" href="<?php echo h($fileUrl); ?>" target="_blank" rel="noopener" tabindex="-1" aria-hidden="true">
<?php if ($cover): ?>
        <img src="<?php echo h($cover); ?>" alt="Cover of <?php echo h($mag['title']); ?>">
<?php else: ?>
        <span class="magazine-cover-ph">
          <span class="magazine-cover-brand">ADA Law Chambers</span>
          <strong><?php echo h($mag['title']); ?></strong>
          <?php if ($mag['issue']): ?><span class="magazine-cover-issue"><?php echo h($mag['issue']); ?></span><?php endif; ?>
        </span>
<?php endif; ?>
      </a>
      <div>
        <span class="eyebrow">Magazine<?php if ($mag['issue']) echo ' | ' . h($mag['issue']); ?></span>
        <h1 class="section-title"><?php echo h($mag['title']); ?></h1>
        <div class="divider-gold"></div>
        <?php if ($mag['description']): ?><p class="lede"><?php echo nl2br(h($mag['description'])); ?></p><?php endif; ?>
        <div class="magazine-actions">
          <a class="btn btn--gold" href="<?php echo h($fileUrl); ?>" target="_blank" rel="noopener">Read Magazine &rarr;</a>
          <a class="btn btn--outline-dark" href="<?php echo h($fileUrl); ?>" download="<?php echo h($fileName); ?>">Download &darr;</a>
          <?php echo site_share_button($ogUrl, $mag['title']); ?>
        </div>
        <p style="margin-top:2rem;"><a href="magazines.html" class="btn btn--sm btn--outline-dark">&larr; All Magazines</a></p>
      </div>
    </div>
<?php else: ?>
    <div class="empty-state">This magazine issue could not be found. <a href="magazines.html">See all magazines</a>.</div>
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
