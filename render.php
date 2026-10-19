<?php
/**
 * ADA LAW Chambers — render.php
 * ---------------------------------------------------------------
 * Every public page (index.html, about.html, magazines.html, ...) is
 * served through this file — see the "render" rule in .htaccess. The
 * address in the browser stays the same .html URL; this just fills in
 * the page on the server before it's sent:
 *   - the shared header and footer,
 *   - breadcrumbs (visible + structured data),
 *   - database lists (Rules, Thoughts, Articles, Magazines, ...).
 * See inc/site.php for how each of those is built.
 *
 * The .html files themselves are unchanged and still work on their
 * own (e.g. opened without PHP): the browser scripts fill in anything
 * the server didn't.
 * ---------------------------------------------------------------
 */

require __DIR__ . '/inc/site.php';

$page = isset($_GET['page']) ? (string) $_GET['page'] : 'index';
if (!preg_match('~^[a-z0-9-]+$~', $page) || !is_file(__DIR__ . '/' . $page . '.html')) {
    $page = '404';
}

$html = file_get_contents(__DIR__ . '/' . $page . '.html');
$html = site_inject_partials($html);
$html = site_add_breadcrumbs($html, $page);
$html = site_render_lists($html, $page);

if ($page === '404') {
    // Also reached through ErrorDocument; make sure the status really is 404
    // (otherwise search engines index missing URLs as real pages).
    http_response_code(404);
}
header('Content-Type: text/html; charset=UTF-8');
// Lists change whenever something is published, so caches must re-check.
header('Cache-Control: no-cache, must-revalidate');
echo $html;
