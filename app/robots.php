<?php
/** Consignes pour les robots des moteurs de recherche (servi à l'adresse /robots.txt). */
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');
echo "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /api/\nDisallow: /data/\nDisallow: /inc/\n\nSitemap: " . site_url() . "/sitemap.xml\n";
