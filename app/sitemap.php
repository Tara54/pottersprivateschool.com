<?php
/** Plan du site pour Google (servi à l'adresse /sitemap.xml). */
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
header('Content-Type: application/xml; charset=utf-8');
header('X-Content-Type-Options: nosniff');
$base = e(site_url());
$last = db()->query("SELECT MAX(d) FROM (SELECT MAX(created_at) d FROM news UNION SELECT MAX(created_at) FROM gallery UNION SELECT MAX(value) FROM settings WHERE key='updated_at')")->fetchColumn();
$lastmod = date('Y-m-d', $last ? strtotime($last) : filemtime(__DIR__ . '/index.php'));
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
<?php foreach (['fr' => '/', 'en' => '/?lang=en'] as $l => $path): ?>
  <url>
    <loc><?= $base . e($path) ?></loc>
    <lastmod><?= $lastmod ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority><?= $l === 'fr' ? '1.0' : '0.9' ?></priority>
    <xhtml:link rel="alternate" hreflang="fr" href="<?= $base ?>/"/>
    <xhtml:link rel="alternate" hreflang="en" href="<?= $base ?>/?lang=en"/>
    <image:image><image:loc><?= $base ?>/assets/photos/uniforme-trio.jpg</image:loc></image:image>
  </url>
<?php endforeach; ?>
</urlset>
