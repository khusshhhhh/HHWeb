<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/site.php';

$rows = db()->query("SELECT * FROM listings WHERE published = 1
  ORDER BY CASE status WHEN 'available' THEN 0 WHEN 'under-offer' THEN 1 ELSE 2 END, updated_at DESC")->fetchAll();

site_top('House & Land Packages Adelaide · Hills & Harbour',
  'Current house and land packages across Adelaide from Hills & Harbour: photos, features, pricing and location for each one.',
  '/house-land/');
?>

<section class="page-hero band--black grid-bg" data-header="dark">
  <div class="container">
    <nav class="page-hero__crumbs" aria-label="Breadcrumb" data-reveal="fade"><a href="../index.html">Home</a><span>/</span><span>House &amp; Land</span></nav>
    <h1 class="display-xl page-hero__title" data-split>House and land, ready to go.</h1>
    <div class="page-hero__foot" data-reveal style="--d:400ms">
      <p class="lead muted">Current packages across Adelaide. Each one shows the home, the block, the price and where it is.</p>
    </div>
  </div>
</section>

<section class="band band--white" data-header="light">
  <div class="container">
    <?php if (!$rows): ?>
    <div class="listing-empty">
      <h2 class="display-md">New packages coming soon.</h2>
      <p class="lead muted">Tell us what you're looking for and we'll let you know when something suits.</p>
      <a class="btn btn--solid" href="../contact.html">Talk to us <span class="arrow">→</span></a>
    </div>
    <?php else: ?>
    <div class="dev-grid">
      <?php foreach ($rows as $i => $r):
        $imgs = listing_images($r['id']);
        $where = $r['suburb'] ?: $r['address']; ?>
      <article class="dev-card" data-reveal style="--d:<?= ($i % 2) * 90 ?>ms">
        <a class="dev-card__media mask-img mono-img" href="<?= e($r['slug']) ?>" data-cursor="View">
          <?php if ($imgs): ?><img src="../assets/uploads/listings/<?= e($imgs[0]['file']) ?>" alt="<?= e($r['title'] . ', ' . $where) ?>" loading="lazy"><?php endif; ?>
        </a>
        <div class="dev-card__body">
          <div class="dev-card__top"><span class="listing-card__price"><?= e($r['price'] ?: 'Price on request') ?></span><?= status_chip($r['status']) ?></div>
          <h2 class="dev-card__title"><a href="<?= e($r['slug']) ?>"><?= e($r['title']) ?></a></h2>
          <p class="dev-card__meta"><?= e($where) ?></p>
          <?php if ($specs = listing_specs($r)): ?><p class="listing-specs"><?php foreach ($specs as $s): ?><span><?= e($s) ?></span><?php endforeach; ?></p><?php endif; ?>
          <div class="dev-card__actions"><a class="link-arrow" href="<?= e($r['slug']) ?>">View package <span class="arrow">→</span></a></div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php site_bottom();
