<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/site.php';

$q = db()->prepare('SELECT * FROM listings WHERE slug = ? AND published = 1');
$q->execute([$_GET['slug'] ?? '']);
$r = $q->fetch();
if (!$r) {
  header('Location: ./', true, 302);
  exit;
}

$imgs = listing_images($r['id']);
$features = listing_features($r);
$where = listing_where($r);
$hasMap = $r['lat'] !== null && $r['lng'] !== null;
$facts = array_filter([
  'Bedrooms' => $r['bedrooms'],
  'Bathrooms' => $r['bathrooms'],
  'Car spaces' => $r['car_spaces'],
  'Land' => $r['land_size'] !== null ? number_format($r['land_size']) . ' m²' : null,
  'Home' => $r['house_size'] !== null ? number_format($r['house_size']) . ' m²' : null,
], fn($v) => $v !== null);

$desc = trim(mb_substr(preg_replace('/\s+/', ' ', $r['description']), 0, 155)) ?: $r['title'] . ', house and land package in ' . $r['suburb'] . ' by Hills & Harbour.';
$og = $imgs ? 'https://hillsandharbour.com.au/assets/uploads/listings/' . $imgs[0]['file'] : 'https://hillsandharbour.com.au/assets/img/hh-logo-black.png';
site_top($r['title'] . ' · House & Land · Hills & Harbour', $desc, '/house-land/' . $r['slug'], $og);
?>

<section class="listing-page band--white" data-header="light">
  <div class="container">
    <nav class="page-hero__crumbs" aria-label="Breadcrumb"><a href="../index.html">Home</a><span>/</span><a href="./">House &amp; Land</a><span>/</span><span aria-current="page"><?= e($r['title']) ?></span></nav>

    <?php if ($imgs): ?>
    <div class="gallery" data-gallery>
      <div class="gallery__main mask-img"><img data-gallery-main src="../assets/uploads/listings/<?= e($imgs[0]['file']) ?>" alt="<?= e($r['title']) ?>, image 1"></div>
      <?php if (count($imgs) > 1): ?>
      <div class="gallery__thumbs" data-lenis-prevent>
        <?php foreach ($imgs as $i => $im): $src = '../assets/uploads/listings/' . $im['file']; $alt = $r['title'] . ', image ' . ($i + 1); ?>
        <button type="button" data-gallery-thumb data-src="<?= e($src) ?>" data-alt="<?= e($alt) ?>" aria-pressed="<?= $i ? 'false' : 'true' ?>" aria-label="Show image <?= $i + 1 ?>"><img src="<?= e($src) ?>" alt="" loading="lazy"></button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="listing-layout">
      <div>
        <header class="listing-title">
          <div class="listing-title__top"><p class="listing-price"><?= e($r['price'] ?: 'Price on request') ?></p><?= status_chip($r['status']) ?></div>
          <h1 class="listing-title__h"><?= e($r['title']) ?></h1>
          <p class="muted"><?= e($where) ?></p>
        </header>
        <?php if ($facts): ?>
        <dl class="listing-facts"><?php foreach ($facts as $k => $v): ?><div><dt><?= e($k) ?></dt><dd><?= e($v) ?></dd></div><?php endforeach; ?></dl>
        <?php endif; ?>
        <?php if ($r['description'] !== ''): ?>
        <div class="listing-section"><p class="kicker">About this package</p><p class="listing-desc muted"><?= e($r['description']) ?></p></div>
        <?php endif; ?>
        <?php if ($features): ?>
        <div class="listing-section"><p class="kicker">Property features</p><ul class="listing-features"><?php foreach ($features as $f): ?><li><?= e($f) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <?php if ($hasMap): ?>
        <div class="listing-section"><p class="kicker">Location</p><div class="listing-map" id="listing-map" data-lenis-prevent data-lat="<?= e($r['lat']) ?>" data-lng="<?= e($r['lng']) ?>" role="img" aria-label="Map showing <?= e($where) ?>"></div></div>
        <?php endif; ?>
      </div>

      <aside class="listing-enquire" id="enquire">
        <h2 class="display-sm">Ask about this package</h2>
        <form class="form" action="../send.php" method="post" novalidate data-enquiry>
          <input type="hidden" name="form" value="house-land">
          <input type="hidden" name="project" value="<?= e($r['title']) ?>">
          <div class="field"><input id="l-name" name="name" type="text" placeholder=" " autocomplete="name" required><label for="l-name">Your name *</label><span class="field__err">Please add your name.</span></div>
          <div class="field"><input id="l-email" name="email" type="email" placeholder=" " autocomplete="email" required><label for="l-email">Email *</label><span class="field__err">Please add a valid email.</span></div>
          <div class="field"><input id="l-phone" name="phone" type="tel" placeholder=" " autocomplete="tel" required><label for="l-phone">Phone *</label><span class="field__err">Please add a phone number.</span></div>
          <div class="field"><textarea id="l-msg" name="message" placeholder=" " rows="3"></textarea><label for="l-msg">Questions or notes</label></div>
          <div class="hcheck" data-hcheck>
            <button class="hcheck__box" type="button" role="checkbox" aria-checked="false" aria-labelledby="hc-listing"><span class="hcheck__spin"></span><svg class="hcheck__tick" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></button>
            <span class="hcheck__text" id="hc-listing">I'm not a robot</span>
            <span class="hcheck__mark" aria-hidden="true"><svg viewBox="0 0 48 48"><path d="M6 22L24 6l18 16v20H6z"/><rect x="20" y="29" width="8" height="13"/></svg>Human check</span>
            <input type="hidden" name="hc_t"><input type="hidden" name="hc_s">
            <span class="field__err">Please confirm you're not a robot.</span>
          </div>
          <div class="hp" aria-hidden="true"><label>Leave this empty <input name="website" type="text" tabindex="-1" autocomplete="off"></label></div>
          <div class="form__foot">
            <p class="caption form__status" role="status">Goes straight to admin@hillsandharbour.com.au</p>
            <button class="btn btn--solid" type="submit"><span class="btn__label">Send enquiry</span> <span class="arrow">→</span></button>
          </div>
        </form>
        <div class="form-success" tabindex="-1">
          <span class="chip chip--granted" style="width:fit-content">✓ Enquiry sent</span>
          <h3 class="display-sm">Thanks. It's with us.</h3>
          <p class="muted">We'll be in touch within one business day.</p>
        </div>
      </aside>
    </div>
  </div>
</section>

<div class="listing-bar">
  <p><?= e($r['price'] ?: 'Price on request') ?></p>
  <a class="btn" href="tel:+61415386318">Call</a>
  <a class="btn btn--green" href="#enquire">Enquire</a>
</div>

<?php if ($hasMap): ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
  (function () {
    var el = document.getElementById('listing-map');
    var at = [+el.dataset.lat, +el.dataset.lng];
    var map = L.map(el, { scrollWheelZoom: false }).setView(at, 15);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>' }).addTo(map);
    L.circleMarker(at, { radius: 10, color: '#141414', weight: 2, fillColor: '#2fd27a', fillOpacity: 1 }).addTo(map);
  })();
</script>
<?php endif; ?>

<?php site_bottom();
