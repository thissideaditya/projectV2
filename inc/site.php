<?php
/**
 * ADA LAW Chambers — inc/site.php
 * ---------------------------------------------------------------
 * Server-side page building, shared by render.php (every public
 * .html page) and post.php (single posts). Search engines and link
 * preview bots read the raw HTML without running JavaScript, so this
 * puts into that HTML what the browser scripts used to add later:
 *
 *   - the shared header and footer   (was js/partials.js)
 *   - breadcrumbs + BreadcrumbList structured data
 *   - the database-driven lists      (was js/*-render.js)
 *
 * Each list renderer below mirrors the markup of its JavaScript
 * counterpart, and marks its container data-ssr so that script
 * knows the list is already there and skips fetching it again. If
 * the database can't be reached, the "Loading…" container is left
 * untouched and the script loads the list in the browser as before.
 *
 * Keep the card markup here in step with the matching js/*-render.js.
 * ---------------------------------------------------------------
 */

if (!defined('ADA_PAGE_RENDER')) {
    define('ADA_PAGE_RENDER', true);
}
require_once __DIR__ . '/../api/config.php';

const SITE_BASE_URL = 'https://www.adalawchambers.com';
const SITE_ROOT = __DIR__ . '/..';

function e($str): string
{
    return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
}

/**
 * A URL that is safe to put in href/src: web, mail and phone links and
 * this site's own paths. Anything else (javascript:, data:, //other-site)
 * becomes "#". Same rule as safeUrl() in the browser scripts.
 */
function site_safe_url($url): string
{
    $u = trim((string) $url);
    $probe = preg_replace('~[\x00-\x20]+~', '', $u);
    if (preg_match('~^(https?:|mailto:|tel:)~i', $probe)) return $u;
    if (strpos($probe, ':') === false && preg_match('@^(/(?!/)|\.{1,2}/|[\w\-.~%]+(/|\?|#|$))@', $probe)) return $u;
    return '#';
}

/** "2026-10-12 09:30:00" -> "12 October 2026" (same as the scripts' en-IN format). */
function site_date($value): string
{
    $ts = strtotime((string) $value);
    return $ts ? date('j F Y', $ts) : '';
}

/** PDO connection, or null if the database can't be reached (never exits). */
function site_db(): ?PDO
{
    static $pdo = false;
    if ($pdo !== false) {
        return $pdo;
    }
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (Throwable $e) {
        $pdo = null;
    }
    return $pdo;
}

/** Runs a read query; null on any failure (missing table, DB down, ...). */
function site_query(string $sql, array $params = []): ?array
{
    $pdo = site_db();
    if (!$pdo) return null;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return null;
    }
}

// ===================================================================
// Header / footer
// ===================================================================

function site_partial(string $name): string
{
    $html = (string) @file_get_contents(SITE_ROOT . '/partials/' . $name . '.html');
    if ($name === 'footer') {
        // Filled in by main.js too; doing it here means it's never blank.
        $html = preg_replace('~(<span id="current-year">)\s*(</span>)~', '${1}' . date('Y') . '${2}', $html);
    }
    return $html;
}

/**
 * Swaps the empty header/footer slots for the real markup. The
 * disclaimer slot is left for js/partials.js: it's a per-visitor
 * overlay, not page content.
 */
function site_inject_partials(string $html): string
{
    foreach (['header', 'footer'] as $name) {
        $partial = site_partial($name);
        // Callback, not a replacement string: markup may contain "$" or "\\".
        $html = preg_replace_callback('~<div id="site-' . $name . '-slot"[^>]*></div>~', function () use ($partial) {
            return $partial;
        }, $html, 1);
    }
    return $html;
}

// ===================================================================
// Breadcrumbs
// ===================================================================

/** Trail for each page below Home: list of [label, page-or-null]. */
function site_breadcrumb_trail(string $page): ?array
{
    $about    = ['About', 'about.html'];
    $insights = ['Insights', 'insights.html'];
    $trails = [
        'about'           => [['About Us', null]],
        'team'            => [$about, ['Our Team', null]],
        'pro-bono'        => [$about, ['Pro Bono', null]],
        'practice-areas'  => [['Services', null]],
        'insights'        => [['Insights', null]],
        'rules'           => [$insights, ['Rules', null]],
        'thoughts'        => [$insights, ['Thoughts', null]],
        'articles'        => [$insights, ['Articles', null]],
        'magazines'       => [$insights, ['Magazines', null]],
        'resources'       => [$insights, ['Resources', null]],
        'important-links' => [$insights, ['Important Links', null]],
        'careers'         => [['Careers', null]],
        'contact'         => [['Contact Us', null]],
        'privacy'         => [['Privacy Policy', null]],
        'terms'           => [['Terms of Use', null]],
        'sitemap'         => [['Sitemap', null]],
    ];
    return $trails[$page] ?? null;
}

/** Visible breadcrumb nav. $trail excludes Home; the last item is the current page. */
function site_breadcrumb_html(array $trail): string
{
    $items = '<li><a href="index.html">Home</a></li>';
    $last = count($trail) - 1;
    foreach ($trail as $i => [$label, $href]) {
        $items .= ($i === $last || !$href)
            ? '<li aria-current="page">' . e($label) . '</li>'
            : '<li><a href="' . e($href) . '">' . e($label) . '</a></li>';
    }
    return '<nav class="breadcrumbs" aria-label="Breadcrumb"><ol>' . $items . '</ol></nav>';
}

/** BreadcrumbList structured data; $currentUrl is the page's own canonical URL. */
function site_breadcrumb_jsonld(array $trail, string $currentUrl): string
{
    $list = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => SITE_BASE_URL . '/']];
    $last = count($trail) - 1;
    foreach ($trail as $i => [$label, $href]) {
        $list[] = [
            '@type' => 'ListItem',
            'position' => $i + 2,
            'name' => $label,
            'item' => $i === $last ? $currentUrl : SITE_BASE_URL . '/' . $href,
        ];
    }
    $json = json_encode(
        ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
    );
    return '<script type="application/ld+json">' . $json . '</script>';
}

/** Adds breadcrumbs to a static page: inside its hero, plus the JSON-LD in <head>. */
function site_add_breadcrumbs(string $html, string $page): string
{
    $trail = site_breadcrumb_trail($page);
    if (!$trail) return $html;

    $nav = site_breadcrumb_html($trail);
    // Every page's hero opens with one of these containers (careers.html has its own intro).
    $count = 0;
    $html = preg_replace_callback('~<div class="container (?:hero-copy|cr-intro-inner)">~', function ($m) use ($nav) {
        return $m[0] . "\n    " . $nav;
    }, $html, 1, $count);
    if (!$count) return $html;

    $jsonld = site_breadcrumb_jsonld($trail, SITE_BASE_URL . '/' . $page . '.html');
    return site_add_to_head($html, $jsonld);
}

function site_add_to_head(string $html, string $markup): string
{
    $pos = stripos($html, '</head>');
    return $pos === false ? $html : substr($html, 0, $pos) . $markup . "\n" . substr($html, $pos);
}

// ===================================================================
// Lists (mirror js/*-render.js)
// ===================================================================

/**
 * Replaces a list's "Loading…" placeholder with rendered content.
 * $class (optional) is the class the script would give the container.
 */
function site_fill_mount(string $html, string $id, string $inner, string $class = ''): string
{
    $pattern = '~<div id="' . preg_quote($id, '~') . '"([^>]*)>\s*<div class="empty-state">Loading&hellip;</div>\s*</div>~';
    return preg_replace_callback($pattern, function ($m) use ($id, $inner, $class) {
        $attrs = preg_replace('~\s+aria-busy="[^"]*"~', '', $m[1]);
        $classAttr = $class !== '' ? ' class="' . e($class) . '"' : '';
        return '<div id="' . $id . '"' . $attrs . $classAttr . ' data-ssr>' . $inner . '</div>';
    }, $html, 1);
}

function site_empty(string $text): string
{
    return '<div class="empty-state">' . e($text) . '</div>';
}

/** Same markup as ADA_SHARE.button() in js/share.js (its click handler works on it). */
function site_share_button(string $url, string $title): string
{
    $icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
          . '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>'
          . '<path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/></svg>';
    return '<button type="button" class="share-btn" data-share-url="' . e($url) . '" data-share-title="' . e($title) . '"'
         . ' aria-haspopup="true" aria-expanded="false">' . $icon . ' Share</button>';
}

const SITE_FEED_ARROW = '<span class="feed-arrow" aria-hidden="true"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" '
    . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>';

// ---- Rules / Thoughts (js/posts-render.js) -----------------------------

function site_post_card(array $post): string
{
    $label = $post['category'] === 'rule' ? 'Rules' : 'Thoughts';
    $url = 'post.php?slug=' . rawurlencode($post['slug']);
    return '<article class="post-card">'
        . '<a class="thumb" href="' . e($url) . '">'
        .   '<img src="' . e(site_safe_url($post['cover_image'])) . '" alt="' . e($post['title']) . '" loading="lazy">'
        . '</a>'
        . '<div class="body">'
        .   '<span class="meta">' . $label . ' | ' . site_date($post['created_at']) . '</span>'
        .   '<h3>' . e($post['title']) . '</h3>'
        .   '<p>' . e($post['excerpt']) . '</p>'
        .   '<div class="card-actions">'
        .     '<a class="read-more" href="' . e($url) . '">Read more &rarr;</a>'
        .     site_share_button($url, $post['title'])
        .   '</div>'
        . '</div>'
        . '</article>';
}

function site_render_posts(string $html, string $category): string
{
    $posts = site_query("SELECT * FROM posts WHERE category = ? AND status = 'published' ORDER BY created_at DESC", [$category]);
    if ($posts === null) return $html;
    if (!$posts) return site_fill_mount($html, 'post-listing', site_empty('Nothing published here yet. Please check back soon.'));
    return site_fill_mount($html, 'post-listing', implode('', array_map('site_post_card', $posts)), 'grid post-grid');
}

/**
 * Post bodies come from the rich-text editor. The page's only H1 is the
 * post title, so any H1 inside the body (older posts) is shown as an H2.
 * Same rule as postBodyHtml() in js/posts-render.js.
 */
function site_post_body(string $html): string
{
    return sanitize_post_html($html); // safe HTML only; h1 -> h2 (api/config.php)
}

// ---- Articles (js/articles-render.js) ----------------------------------

function site_article_card(array $a): string
{
    $absolute = preg_match('~^https?://~i', $a['file_url']) ? $a['file_url'] : SITE_BASE_URL . '/' . ltrim($a['file_url'], '/');
    $type = strtolower((string) $a['file_type']);
    // PDFs open in the browser; Office files go through Microsoft's viewer.
    $openUrl = in_array($type, ['ppt', 'pptx', 'doc', 'docx'], true)
        ? 'https://view.officeapps.live.com/op/view.aspx?src=' . rawurlencode($absolute)
        : $absolute;
    $typeLabel = in_array($type, ['ppt', 'pptx'], true) ? 'Presentation'
        : (in_array($type, ['doc', 'docx'], true) ? 'Document' : ($type === 'pdf' ? 'PDF' : strtoupper($type)));
    $parts = explode('/', (string) $a['file_url']);
    $fileName = end($parts) ?: 'download';

    return '<article class="post-card">'
        . '<div class="body">'
        .   '<span class="meta">' . e($typeLabel) . ' &middot; ' . site_date($a['created_at']) . '</span>'
        .   '<h3><a class="article-title-link" href="' . e(site_safe_url($openUrl)) . '" target="_blank" rel="noopener noreferrer">' . e($a['title']) . '</a></h3>'
        .   ($a['description'] ? '<p>' . e($a['description']) . '</p>' : '')
        .   '<div class="card-actions" style="justify-content:flex-start;">'
        .     '<a class="btn btn--sm btn--gold" href="' . e(site_safe_url($a['file_url'])) . '" download="' . e($fileName) . '">Download &darr;</a>'
        .     site_share_button($a['file_url'], $a['title'])
        .   '</div>'
        . '</div>'
        . '</article>';
}

function site_render_articles(string $html): string
{
    $rows = site_query("SELECT * FROM articles WHERE status = 'published' ORDER BY created_at DESC");
    if ($rows === null) return $html;
    if (!$rows) return site_fill_mount($html, 'articles-listing', site_empty('No articles published yet. Please check back soon.'));
    return site_fill_mount($html, 'articles-listing', implode('', array_map('site_article_card', $rows)), 'grid post-grid');
}

// ---- Magazines (js/magazines-render.js) --------------------------------

function site_magazine_card(array $m): string
{
    $url = site_safe_url($m['file_url']);
    $safeTitle = trim(preg_replace('/[^\w\- ]+/', '', (string) $m['title']));
    $fileName = ($safeTitle !== '' ? $safeTitle : 'magazine') . '.pdf';
    $meta = $m['issue'] ?: site_date($m['created_at']);

    if ($m['cover_image']) {
        $cover = '<img src="' . e(site_safe_url($m['cover_image'])) . '" alt="Cover of ' . e($m['title']) . '" loading="lazy">';
    } else {
        $cover = '<span class="magazine-cover-ph">'
               .   '<span class="magazine-cover-brand">ADA Law Chambers</span>'
               .   '<strong>' . e($m['title']) . '</strong>'
               .   ($m['issue'] ? '<span class="magazine-cover-issue">' . e($m['issue']) . '</span>' : '')
               . '</span>';
    }

    return '<article class="feed-card magazine-card">'
        . '<a class="magazine-cover" href="' . e($url) . '" target="_blank" rel="noopener" tabindex="-1" aria-hidden="true">' . $cover . '</a>'
        . '<div class="feed-body">'
        .   ($meta ? '<span class="feed-meta">' . e($meta) . '</span>' : '')
        .   '<h3><a href="' . e($url) . '" target="_blank" rel="noopener">' . e($m['title']) . '</a></h3>'
        .   ($m['description'] ? '<p>' . e($m['description']) . '</p>' : '')
        .   '<div class="magazine-actions">'
        .     '<a class="btn btn--sm btn--outline-dark" href="' . e($url) . '" download="' . e($fileName) . '">Download &darr;</a>'
        .     site_share_button($url, $m['title'])
        .   '</div>'
        .   '<a class="feed-more" href="' . e($url) . '" target="_blank" rel="noopener">Read Magazine' . SITE_FEED_ARROW . '</a>'
        . '</div>'
        . '</article>';
}

function site_render_magazines(string $html, string $mountId, int $limit, string $emptyText): string
{
    $sql = "SELECT * FROM magazines WHERE status = 'published' ORDER BY created_at DESC, id DESC" . ($limit > 0 ? ' LIMIT ' . $limit : '');
    $rows = site_query($sql);
    if ($rows === null) return $html;
    if (!$rows) return site_fill_mount($html, $mountId, site_empty($emptyText));
    return site_fill_mount($html, $mountId, implode('', array_map('site_magazine_card', $rows)), 'grid post-grid feed-grid magazine-grid');
}

// ---- Important Links & Resources (js/links-render.js, js/resources-render.js)

function site_link_row(array $l): string
{
    return '<div class="practice-row">'
        . '<div>'
        .   '<h3><a href="' . e(site_safe_url($l['url'])) . '" target="_blank" rel="noopener">' . e($l['title']) . '</a></h3>'
        .   ($l['description'] ? '<p style="margin:.4rem 0 0;color:var(--ink-700);font-size:.92rem;max-width:60ch;">' . e($l['description']) . '</p>' : '')
        . '</div>'
        . '<a class="btn btn--sm btn--outline-dark" href="' . e(site_safe_url($l['url'])) . '" target="_blank" rel="noopener">Visit &rarr;</a>'
        . '</div>';
}

function site_render_links(string $html): string
{
    $rows = site_query('SELECT * FROM important_links ORDER BY display_order ASC, created_at DESC');
    if ($rows === null) return $html;
    if (!$rows) return site_fill_mount($html, 'links-listing', site_empty('No links published yet. Please check back soon.'));
    return site_fill_mount($html, 'links-listing', '<div class="practice-list">' . implode('', array_map('site_link_row', $rows)) . '</div>');
}

function site_render_resources(string $html): string
{
    $rows = site_query('SELECT * FROM resources ORDER BY category ASC, subcategory ASC, display_order ASC, created_at DESC');
    if ($rows === null) return $html;
    if (!$rows) return site_fill_mount($html, 'resources-listing', site_empty('No resources published yet. Please check back soon.'));

    // Category -> subcategory ("" for none) -> rows, keeping the query's order.
    $groups = [];
    foreach ($rows as $r) {
        $groups[$r['category'] ?: 'General'][$r['subcategory'] ?: ''][] = $r;
    }
    $out = '';
    foreach ($groups as $cat => $subs) {
        $out .= '<div style="margin-bottom:3rem;">';
        $out .= '<h2 class="section-title" style="font-size:1.4rem;margin-bottom:1.25rem;">' . e($cat) . '</h2>';
        foreach ($subs as $sub => $items) {
            if ($sub !== '') {
                $out .= '<span class="eyebrow" style="display:block;margin:1.25rem 0 .5rem;">' . e($sub) . '</span>';
            }
            $out .= '<div class="practice-list">' . implode('', array_map('site_link_row', $items)) . '</div>';
        }
        $out .= '</div>';
    }
    return site_fill_mount($html, 'resources-listing', $out);
}

/** Fills whichever database-driven lists the given page has. */
function site_render_lists(string $html, string $page): string
{
    switch ($page) {
        case 'index':
            return site_render_magazines($html, 'home-magazines', 3, 'Our first issue is on its way. Please check back soon.');
        case 'magazines':
            return site_render_magazines($html, 'magazines-listing', 0, 'No magazines published yet. Please check back soon.');
        case 'rules':
            return site_render_posts($html, 'rule');
        case 'thoughts':
            return site_render_posts($html, 'thought');
        case 'articles':
            return site_render_articles($html);
        case 'important-links':
            return site_render_links($html);
        case 'resources':
            return site_render_resources($html);
    }
    return $html;
}
