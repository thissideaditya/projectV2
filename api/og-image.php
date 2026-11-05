<?php
/**
 * ADA LAW Chambers — api/og-image.php
 * ---------------------------------------------------------------
 * GET ?type=article|magazine&slug=<slug>   (public)
 *
 * The share-preview picture (og:image) for an article or magazine.
 * WhatsApp, LinkedIn, etc. only show the large banner preview for
 * landscape images; square/portrait covers get a small side
 * thumbnail. So this places the cover, uncropped, in the middle of a
 * 1200×630 canvas in the site's green, and serves that as a JPEG.
 *
 * Only the share preview uses this — the site itself shows the
 * original cover. Results are cached in PERSIST_DIR/og/ and rebuilt
 * automatically when the cover changes. If anything goes wrong (no
 * GD, cover missing, external cover URL) it redirects to the plain
 * cover, or the default site image, so the preview never breaks.
 * ---------------------------------------------------------------
 */

require __DIR__ . '/../inc/site.php';

const OG_W = 1200;
const OG_H = 630;
const OG_PAD = 40;           // space between the cover and the canvas edge
const OG_MAX_BYTES = 290000; // WhatsApp drops images much over ~300KB
const OG_VERSION = 'v2';     // bump to rebuild every cached image after changing the design

function og_fallback(string $url): void
{
    $abs = preg_match('~^https?://~i', $url) ? $url : SITE_BASE_URL . '/' . ltrim($url, '/');
    header('Cache-Control: public, max-age=3600');
    header('Location: ' . $abs, true, 302);
    exit;
}

$tables = ['article' => 'articles', 'magazine' => 'magazines'];
$type = query_str('type');
$slug = query_str('slug');
$default = 'assets/images/og-default.jpg';

if (!isset($tables[$type]) || $slug === '') og_fallback($default);

$rows = site_query('SELECT cover_image FROM ' . $tables[$type] . " WHERE slug = ? AND status = 'published' LIMIT 1", [$slug]);
$cover = $rows ? (string) $rows[0]['cover_image'] : '';
if ($cover === '' || site_safe_url($cover) === '#') og_fallback($default);

$src = uploaded_file_path($cover);
if (!$src || !is_file($src) || !function_exists('imagecreatetruecolor')) og_fallback($cover);

// ---- Cached copy? -------------------------------------------------------
$cacheDir = PERSIST_DIR . '/og';
$cacheFile = $cacheDir . '/' . md5($src . '|' . filemtime($src) . '|' . OG_VERSION) . '.jpg';

if (!is_file($cacheFile)) {
    $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
    $img = null;
    if (($ext === 'jpg' || $ext === 'jpeg') && function_exists('imagecreatefromjpeg')) $img = @imagecreatefromjpeg($src);
    elseif ($ext === 'png' && function_exists('imagecreatefrompng')) $img = @imagecreatefrompng($src);
    elseif ($ext === 'webp' && function_exists('imagecreatefromwebp')) $img = @imagecreatefromwebp($src);
    if (!$img) og_fallback($cover);

    $sw = imagesx($img);
    $sh = imagesy($img);

    $canvas = imagecreatetruecolor(OG_W, OG_H);

    // A cover that's already landscape (close to 1200×630) fills the whole
    // preview edge to edge; at most a sliver is trimmed to match the shape.
    $ratio = $sw / $sh;
    if ($ratio >= 1.8 && $ratio <= 2.0) {
        $scale = max(OG_W / $sw, OG_H / $sh);
        $cw = (int) round(OG_W / $scale); // the part of the cover that's used
        $ch = (int) round(OG_H / $scale);
        imagecopyresampled($canvas, $img, 0, 0, (int) (($sw - $cw) / 2), (int) (($sh - $ch) / 2), OG_W, OG_H, $cw, $ch);
    } else {
        // Square / portrait / very wide: the whole cover on a green canvas.
        // Background: the site's deep green, lighter at the top (as on the
        // magazine placeholder covers).
        $top = [0x23, 0x52, 0x3e];
        $bottom = [0x0b, 0x21, 0x18];
        for ($y = 0; $y < OG_H; $y++) {
            $t = $y / (OG_H - 1);
            $c = imagecolorallocate(
                $canvas,
                (int) round($top[0] + ($bottom[0] - $top[0]) * $t),
                (int) round($top[1] + ($bottom[1] - $top[1]) * $t),
                (int) round($top[2] + ($bottom[2] - $top[2]) * $t)
            );
            imageline($canvas, 0, $y, OG_W - 1, $y, $c);
        }

        // Thin gold frame just inside the edge.
        imagesetthickness($canvas, 2);
        imagerectangle($canvas, 14, 14, OG_W - 15, OG_H - 15, imagecolorallocatealpha($canvas, 230, 201, 110, 60));
        imagesetthickness($canvas, 1);

        // The cover, scaled to fit (never cropped, never enlarged past 1.5×).
        $boxW = OG_W - 2 * OG_PAD;
        $boxH = OG_H - 2 * OG_PAD;
        $scale = min($boxW / $sw, $boxH / $sh, 1.5);
        $dw = (int) round($sw * $scale);
        $dh = (int) round($sh * $scale);
        $dx = (int) round((OG_W - $dw) / 2);
        $dy = (int) round((OG_H - $dh) / 2);

        // A soft shadow, then a gold hairline around the cover.
        for ($i = 12; $i >= 1; $i--) {
            imagefilledrectangle($canvas, $dx - $i + 4, $dy - $i + 8, $dx + $dw + $i + 4, $dy + $dh + $i + 8,
                imagecolorallocatealpha($canvas, 0, 0, 0, 122));
        }
        imagefilledrectangle($canvas, $dx - 2, $dy - 2, $dx + $dw + 1, $dy + $dh + 1, imagecolorallocate($canvas, 201, 162, 39));
        imagecopyresampled($canvas, $img, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);
    }

    // JPEG, stepping the quality down until it's small enough for WhatsApp.
    $data = '';
    foreach ([86, 78, 70, 62, 54] as $q) {
        ob_start();
        imagejpeg($canvas, null, $q);
        $data = ob_get_clean();
        if (strlen($data) <= OG_MAX_BYTES) break;
    }

    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
    if (@file_put_contents($cacheFile, $data) === false) {
        // Couldn't cache: still serve what was built.
        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=3600');
        echo $data;
        exit;
    }
}

header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($cacheFile));
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');
readfile($cacheFile);
