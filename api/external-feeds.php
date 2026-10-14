<?php
/**
 * ADA LAW Chambers — api/external-feeds.php
 * ---------------------------------------------------------------
 * Latest content from our sister sites for the Home page. No database.
 *
 * PUBLIC:
 *   GET ?feed=articles   -> latest articles listed on gstindia.biz/articles
 *   GET ?feed=videos     -> latest uploads on the @GSTIndiaBiz YouTube channel
 *
 * gstindia.biz has no RSS feed, so its /articles table is read directly;
 * if that page's markup changes, update the regex in fetch_articles().
 * Each article's picture is its own share image (og:image, falling back to
 * twitter:image), read from the article page itself — see article_image().
 * Video pictures are the video's YouTube thumbnail.
 * Results are cached on disk for an hour so visitors never wait on (or
 * hammer) the other sites. A stale cache is served if a refresh fails.
 * ---------------------------------------------------------------
 */

const GSTINDIA_ARTICLES_URL = 'https://gstindia.biz/articles';
const YOUTUBE_CHANNEL_ID    = 'UCCefgg21Evs8iONwUSjVM9w'; // youtube.com/@gstindiabiz
const FEED_CACHE_SECONDS    = 3600;
const FEED_LIMIT            = 6;

header('Content-Type: application/json; charset=utf-8');

function feed_out($code, $data) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function http_get($url, $timeout = 10) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (ADA Law Chambers feed reader)',
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (PHP_VERSION_ID < 80000) curl_close($ch); // no-op since PHP 8.0, deprecated in 8.5
        return ($body !== false && $code === 200) ? $body : null;
    }
    $ctx = stream_context_create(['http' => ['timeout' => $timeout, 'user_agent' => 'Mozilla/5.0 (ADA Law Chambers feed reader)']]);
    $body = @file_get_contents($url, false, $ctx);
    return $body === false ? null : $body;
}

function clean_text($s) {
    // The source double-encodes some entities (e.g. "&amp;nbsp;").
    $s = html_entity_decode(html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = str_replace("\xC2\xA0", ' ', $s);
    return trim(preg_replace('/\s+/u', ' ', $s));
}

function fetch_articles() {
    $html = http_get(GSTINDIA_ARTICLES_URL);
    if (!$html) return null;

    // Desktop table rows: <td>#</td><td><a href=URL>Title</a></td><td>dd-mm-yyyy</td><td><a ...>Summary...</a></td>
    preg_match_all(
        '~<tr class="d-none d-md-table-row">\s*<td>\d+</td>\s*<td[^>]*>\s*<a href="(https://gstindia\.biz/articles/(\d+)/[^"]*)">(.*?)</a>\s*</td>\s*<td>(\d{2})-(\d{2})-(\d{4})</td>\s*<td>\s*<a[^>]*>(.*?)</a>~s',
        $html, $m, PREG_SET_ORDER
    );
    if (!$m) return null;

    $items = [];
    foreach ($m as $row) {
        $items[] = [
            'id'      => (int) $row[2],
            'title'   => clean_text($row[3]),
            'url'     => $row[1],
            'date'    => "$row[6]-$row[5]-$row[4]",
            // Summaries are cut mid-word by the source and often start with a bare "Introduction" heading.
            'summary' => rtrim(preg_replace('/^Introduction\s*/i', '', clean_text($row[7])), '. ') . '…',
        ];
    }
    usort($items, function ($a, $b) {
        return strcmp($b['date'], $a['date']) ?: $b['id'] - $a['id'];
    });
    $items = array_slice($items, 0, FEED_LIMIT);
    foreach ($items as &$it) {
        unset($it['id']);
        $it['image'] = article_image($it['url']);
    }
    unset($it);
    return $items;
}

// The article's own share picture: og:image, else twitter:image ('' if neither).
function article_image($url) {
    $html = http_get($url, 6);
    if (!$html) return '';
    foreach (['og:image', 'og:image:secure_url', 'twitter:image'] as $key) {
        $k = preg_quote($key, '~');
        if (preg_match('~<meta[^>]+(?:property|name)=["\']' . $k . '["\'][^>]*content=["\']([^"\']+)~i', $html, $m)
            || preg_match('~<meta[^>]+content=["\']([^"\']+)["\'][^>]*(?:property|name)=["\']' . $k . '["\']~i', $html, $m)) {
            $img = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (strpos($img, '//') === 0) $img = 'https:' . $img;
            elseif (strpos($img, '/') === 0) $img = 'https://gstindia.biz' . $img;
            if (preg_match('~^https?://~i', $img)) return $img;
        }
    }
    return '';
}

function fetch_videos() {
    $xml = http_get('https://www.youtube.com/feeds/videos.xml?channel_id=' . YOUTUBE_CHANNEL_ID);
    if (!$xml) return null;

    preg_match_all('~<entry>(.*?)</entry>~s', $xml, $entries);
    $items = [];
    foreach ($entries[1] as $e) {
        if (!preg_match('~<yt:videoId>([\w-]{11})</yt:videoId>~', $e, $id)) continue;
        preg_match('~<title>(.*?)</title>~s', $e, $title);
        preg_match('~<published>(\d{4}-\d{2}-\d{2})~', $e, $date);
        $items[] = [
            'title'     => clean_text($title[1] ?? ''),
            'url'       => 'https://www.youtube.com/watch?v=' . $id[1],
            'thumbnail' => 'https://i.ytimg.com/vi/' . $id[1] . '/hqdefault.jpg',
            'date'      => $date[1] ?? '',
        ];
        if (count($items) >= FEED_LIMIT) break;
    }
    return $items ?: null;
}

$feed = $_GET['feed'] ?? '';
if (!in_array($feed, ['articles', 'videos'], true)) {
    feed_out(400, ['ok' => false, 'error' => 'Use ?feed=articles or ?feed=videos']);
}

$cacheFile = sys_get_temp_dir() . '/ada-feed-v2-' . $feed . '.json'; // v2: articles carry an image
$cached = is_file($cacheFile) ? json_decode((string) @file_get_contents($cacheFile), true) : null;

if ($cached && filemtime($cacheFile) > time() - FEED_CACHE_SECONDS) {
    feed_out(200, ['ok' => true, 'items' => $cached]);
}

$items = $feed === 'articles' ? fetch_articles() : fetch_videos();
if ($items) {
    @file_put_contents($cacheFile, json_encode($items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    feed_out(200, ['ok' => true, 'items' => $items]);
}
if ($cached) {
    feed_out(200, ['ok' => true, 'items' => $cached, 'stale' => true]);
}
feed_out(502, ['ok' => false, 'error' => 'Could not reach the source site']);
