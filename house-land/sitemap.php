<?php
// Published listings for search engines; listed in robots.txt next to the static sitemap.
require __DIR__ . '/../includes/db.php';

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach (db()->query('SELECT slug FROM listings WHERE published = 1') as $r) {
  echo '  <url><loc>https://hillsandharbour.com.au/house-land/' . e($r['slug']) . "</loc></url>\n";
}
echo "</urlset>\n";
