<?php
// Router for `php -S`, mirroring .htaccess so the listings, admin and send.php run locally.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$root = dirname(__DIR__);

if (preg_match('#^/(data|includes|\.claude)(/|$)#', $path)) { http_response_code(403); exit('Forbidden'); }
if (preg_match('#^/assets/uploads/.*\.ph#i', $path)) { http_response_code(403); exit('Forbidden'); }
if (preg_match('#^/house-land/([a-z0-9-]+)$#', $path, $m) && $m[1] !== 'sitemap') {
  $_GET['slug'] = $m[1];
  chdir($root . '/house-land');
  require $root . '/house-land/listing.php';
  return;
}
if (preg_match('#^/(house-land|admin)$#', $path, $m)) { header('Location: /' . $m[1] . '/', true, 301); return; }
if (preg_match('#^/(house-land|admin)/$#', $path, $m)) { require $root . '/' . $m[1] . '/index.php'; return; }
if ($path === '/house-land/sitemap') { require $root . '/house-land/sitemap.php'; return; }
if ($path !== '/' && substr($path, -1) !== '/' && is_file($root . $path . '.html')) { header('Content-Type: text/html; charset=utf-8'); readfile($root . $path . '.html'); return; }
return false;
