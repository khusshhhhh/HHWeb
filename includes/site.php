<?php
// Page shell for the PHP pages in house-land/. The body chrome is the same markup as the static pages.

// Changes whenever the file does, so browsers and host caches never serve an old copy
function asset_version($file) {
  return @filemtime(dirname(__DIR__) . '/assets/' . $file) ?: 1;
}

function site_top($title, $desc, $path, $image = 'https://hillsandharbour.com.au/assets/img/hh-logo-black.png') {
  $url = 'https://hillsandharbour.com.au' . $path;
  ?><!doctype html>
<html lang="en-AU" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<link rel="canonical" href="<?= e($url) ?>">
<meta property="og:url" content="<?= e($url) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Hills &amp; Harbour">
<meta property="og:locale" content="en_AU">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#141414">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:image" content="<?= e($image) ?>">
<link rel="icon" href="../assets/img/favicon-32.png" sizes="32x32" type="image/png">
<link rel="icon" href="../assets/img/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="../assets/img/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@500;600;700&family=Geist+Mono:wght@400;600;700&display=swap">
<link rel="stylesheet" href="../assets/css/style.css?v=<?= asset_version('css/style.css') ?>">
<script>
  (function (r) {
    try {
      var calm = matchMedia('(prefers-reduced-motion: reduce)').matches;
      if (!sessionStorage.getItem('hh-loaded') && !calm) r.classList.add('is-loading');
      if (sessionStorage.getItem('hh-pt')) { r.classList.add('pt-in'); sessionStorage.removeItem('hh-pt'); }
    } catch (e) {}
    setTimeout(function () { r.classList.remove('is-loading', 'pt-in'); }, 6000);
  })(document.documentElement);
</script>
</head>
<?php
  readfile(__DIR__ . '/chrome-top.html');
}

function site_bottom() {
  echo str_replace('assets/js/main.js', 'assets/js/main.js?v=' . asset_version('js/main.js'), file_get_contents(__DIR__ . '/chrome-bottom.html'));
}
