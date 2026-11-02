<?php
/* =========================================================
   Hills & Harbour — House & Land admin
   One page: first-run setup, sign-in, listing table, edit form.
   First run needs the code the server writes to data/setup-code.txt,
   so nobody else can claim the dashboard before you do.
   ========================================================= */

require __DIR__ . '/../includes/db.php';

const MAX_LOGIN_FAILS = 8;     // per IP, per 15 minutes
const MAX_IMAGE_BYTES = 12000000;
const MAX_IMAGE_WIDTH = 2000;  // larger uploads are scaled down when GD is available
const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => !empty($_SERVER['HTTPS'])]);
session_name('hh_admin');
session_start();
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
header('X-Frame-Options: DENY');

db(); // creates data/ on first use
$authFile = DATA_DIR . '/admin-auth.php';
$codeFile = DATA_DIR . '/setup-code.txt';
$auth = is_file($authFile) ? include $authFile : null;
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
$action = $_POST['action'] ?? '';
$error = '';

function go($query = '') { header('Location: ./' . $query); exit; }
function flash($msg) { $_SESSION['flash'] = $msg; }
function csrf() {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
  return $_SESSION['csrf'];
}
function csrf_field() { return '<input type="hidden" name="csrf" value="' . csrf() . '">'; }
function fails_file() { return sys_get_temp_dir() . '/hh-admin-' . md5(($_SERVER['REMOTE_ADDR'] ?? '') . DATA_DIR); }
function recent_fails() {
  return array_filter(@file(fails_file(), FILE_IGNORE_NEW_LINES) ?: [], fn($x) => (int) $x > time() - 900);
}
function note_fail() {
  $hits = recent_fails();
  $hits[] = time();
  @file_put_contents(fails_file(), implode("\n", $hits));
}
function save_auth($file, $password) {
  file_put_contents($file, "<?php\nreturn " . var_export(['hash' => password_hash($password, PASSWORD_DEFAULT)], true) . ";\n", LOCK_EX);
}

// A post bigger than the host allows arrives with nothing in it
$tooBig = $post && !$_POST && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;

/* ---------- First-run setup ---------- */
if (!$auth) {
  if (!is_file($codeFile)) file_put_contents($codeFile, bin2hex(random_bytes(8)) . "\n", LOCK_EX);
  if ($post && $action === 'setup') {
    $pw = (string) ($_POST['password'] ?? '');
    if (count(recent_fails()) >= MAX_LOGIN_FAILS) $error = 'Too many attempts. Try again in 15 minutes.';
    elseif (!hash_equals(trim(file_get_contents($codeFile)), trim((string) ($_POST['code'] ?? '')))) { note_fail(); $error = "That setup code doesn't match."; }
    elseif (mb_strlen($pw) < 10) $error = 'Please use at least 10 characters.';
    else {
      save_auth($authFile, $pw);
      @unlink($codeFile);
      session_regenerate_id(true);
      $_SESSION['admin'] = true;
      go();
    }
  }
}
/* ---------- Sign in / out ---------- */
elseif ($post && $action === 'login') {
  if (count(recent_fails()) >= MAX_LOGIN_FAILS) $error = 'Too many attempts. Try again in 15 minutes.';
  elseif (password_verify((string) ($_POST['password'] ?? ''), $auth['hash'])) {
    @unlink(fails_file());
    session_regenerate_id(true);
    $_SESSION['admin'] = true;
    go();
  } else { note_fail(); $error = "That password isn't right."; }
}

$signedIn = $auth && !empty($_SESSION['admin']);

/* ---------- Helpers for saving ---------- */
function int_or_null($v) { $v = trim((string) $v); return $v === '' ? null : max(0, (int) $v); }
function float_or_null($v) { $v = trim((string) $v); return is_numeric($v) ? (float) $v : null; }
function slugify($title, $id) {
  $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($title)), '-') ?: 'package';
  $slug = $base;
  $q = db()->prepare('SELECT 1 FROM listings WHERE slug = ? AND id != ?');
  for ($n = 2; ; $n++) {
    $q->execute([$slug, (int) $id]);
    if (!$q->fetchColumn()) return $slug;
    $slug = $base . '-' . $n;
  }
}
// Saved under a random name with an extension taken from the real file type, never from the upload
function store_image($tmp, $listingId) {
  $type = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
  if (!isset(IMAGE_TYPES[$type]) || !getimagesize($tmp)) return 'Images must be JPG, PNG or WebP.';
  if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
  $ext = IMAGE_TYPES[$type];
  $name = bin2hex(random_bytes(12)) . '.' . $ext;
  $dest = UPLOAD_DIR . '/' . $name;
  [$w] = getimagesize($tmp);
  $scaled = false;
  if ($w > MAX_IMAGE_WIDTH && function_exists('imagescale')) {
    $src = ['jpg' => 'imagecreatefromjpeg', 'png' => 'imagecreatefrompng', 'webp' => 'imagecreatefromwebp'][$ext];
    if (function_exists($src) && ($im = @$src($tmp))) {
      if ($ext !== 'jpg') { imagealphablending($im, false); imagesavealpha($im, true); }
      $small = imagescale($im, MAX_IMAGE_WIDTH);
      if ($ext !== 'jpg') { imagealphablending($small, false); imagesavealpha($small, true); }
      $scaled = $ext === 'jpg' ? imagejpeg($small, $dest, 82) : ($ext === 'png' ? imagepng($small, $dest, 7) : imagewebp($small, $dest, 82));
    }
  }
  if (!$scaled && !move_uploaded_file($tmp, $dest)) return "An image couldn't be saved. Check assets/uploads/ is writable.";
  $sort = db()->prepare('SELECT COALESCE(MAX(sort), 0) + 1 FROM listing_images WHERE listing_id = ?');
  $sort->execute([$listingId]);
  db()->prepare('INSERT INTO listing_images (listing_id, file, sort) VALUES (?, ?, ?)')->execute([$listingId, $name, $sort->fetchColumn()]);
  return '';
}
function delete_image_file($file) {
  $path = UPLOAD_DIR . '/' . basename($file);
  if (is_file($path)) @unlink($path);
}

/* ---------- Dashboard actions ---------- */
if ($signedIn && $post) {
  if ($tooBig) {
    flash('That upload was larger than the server allows (' . ini_get('post_max_size') . ' in total). Nothing was saved, so try fewer images at a time.');
    go(isset($_GET['edit']) ? '?edit=' . (int) $_GET['edit'] : '');
  }
  if (!hash_equals(csrf(), (string) ($_POST['csrf'] ?? ''))) { http_response_code(400); exit('Your session expired. Go back and reload the page.'); }
  $id = (int) ($_POST['id'] ?? 0);

  if ($action === 'logout') { session_destroy(); go(); }

  if ($action === 'password') {
    $pw = (string) ($_POST['password'] ?? '');
    if (!password_verify((string) ($_POST['current'] ?? ''), $auth['hash'])) flash("Password not changed: the current password isn't right.");
    elseif (mb_strlen($pw) < 10) flash('Password not changed: please use at least 10 characters.');
    else { save_auth($authFile, $pw); flash('Password changed.'); }
    go();
  }

  if ($action === 'delete') {
    foreach (listing_images($id) as $im) delete_image_file($im['file']);
    db()->prepare('DELETE FROM listings WHERE id = ?')->execute([$id]);
    flash('Package deleted.');
    go();
  }

  if ($action === 'save') {
    $title = trim((string) ($_POST['title'] ?? ''));
    if ($title === '') { flash('Please add a title. Nothing was saved.'); go($id ? "?edit=$id" : '?edit=new'); }
    $status = isset(STATUSES[$_POST['status'] ?? '']) ? $_POST['status'] : 'available';
    $values = [
      $title,
      empty($_POST['published']) ? 0 : 1,
      $status,
      trim((string) ($_POST['price'] ?? '')),
      trim((string) ($_POST['address'] ?? '')),
      trim((string) ($_POST['suburb'] ?? '')),
      trim(str_replace("\r", '', (string) ($_POST['description'] ?? ''))),
      int_or_null($_POST['bedrooms'] ?? ''),
      int_or_null($_POST['bathrooms'] ?? ''),
      int_or_null($_POST['car_spaces'] ?? ''),
      int_or_null($_POST['land_size'] ?? ''),
      int_or_null($_POST['house_size'] ?? ''),
      trim(str_replace("\r", '', (string) ($_POST['features'] ?? ''))),
      float_or_null($_POST['lat'] ?? ''),
      float_or_null($_POST['lng'] ?? ''),
    ];
    $cols = 'title, published, status, price, address, suburb, description, bedrooms, bathrooms, car_spaces, land_size, house_size, features, lat, lng';
    if ($id) {
      $set = implode(', ', array_map(fn($c) => "$c = ?", explode(', ', $cols)));
      db()->prepare("UPDATE listings SET $set, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([...$values, $id]);
    } else {
      $marks = implode(', ', array_fill(0, 15, '?'));
      db()->prepare("INSERT INTO listings (slug, $cols) VALUES (?, $marks)")->execute([slugify($title, 0), ...$values]);
      $id = (int) db()->lastInsertId();
    }

    // Image order and removals, then new uploads
    foreach ((array) ($_POST['sort'] ?? []) as $imgId => $n) {
      db()->prepare('UPDATE listing_images SET sort = ? WHERE id = ? AND listing_id = ?')->execute([(int) $n, (int) $imgId, $id]);
    }
    foreach ((array) ($_POST['remove'] ?? []) as $imgId) {
      $q = db()->prepare('SELECT file FROM listing_images WHERE id = ? AND listing_id = ?');
      $q->execute([(int) $imgId, $id]);
      if ($file = $q->fetchColumn()) {
        delete_image_file($file);
        db()->prepare('DELETE FROM listing_images WHERE id = ?')->execute([(int) $imgId]);
      }
    }
    $problems = [];
    $up = $_FILES['images'] ?? null;
    foreach ($up ? (array) $up['tmp_name'] : [] as $i => $tmp) {
      $err = $up['error'][$i];
      if ($err === UPLOAD_ERR_NO_FILE) continue;
      if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE || $up['size'][$i] > MAX_IMAGE_BYTES) { $problems[] = e($up['name'][$i]) . ' is too large (the server allows ' . ini_get('upload_max_filesize') . ' per image).'; continue; }
      if ($err !== UPLOAD_ERR_OK) { $problems[] = e($up['name'][$i]) . " didn't upload."; continue; }
      if ($msg = store_image($tmp, $id)) $problems[] = e($up['name'][$i]) . ': ' . $msg;
    }
    flash($problems ? 'Saved, but: ' . implode(' ', $problems) : 'Saved.');
    go("?edit=$id");
  }
}

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

/* ---------- Which screen ---------- */
$edit = null;
if ($signedIn && isset($_GET['edit'])) {
  if ($_GET['edit'] === 'new') {
    $edit = ['id' => 0, 'slug' => '', 'title' => '', 'published' => 0, 'status' => 'available', 'price' => '', 'address' => '', 'suburb' => '', 'description' => '', 'bedrooms' => null, 'bathrooms' => null, 'car_spaces' => null, 'land_size' => null, 'house_size' => null, 'features' => '', 'lat' => null, 'lng' => null];
  } else {
    $q = db()->prepare('SELECT * FROM listings WHERE id = ?');
    $q->execute([(int) $_GET['edit']]);
    $edit = $q->fetch() ?: null;
    if (!$edit) go();
  }
}
?><!doctype html>
<html lang="en-AU">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>House &amp; Land admin · Hills &amp; Harbour</title>
<link rel="icon" href="../assets/img/favicon-32.png" sizes="32x32" type="image/png">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@500;600;700&family=Geist+Mono:wght@400;600;700&display=swap">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">
<?php if ($edit): ?><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css"><?php endif; ?>
</head>
<body class="admin">

<header class="admin-bar">
  <a href="./" class="admin-bar__brand"><img src="../assets/img/hh-logo-white.png" alt="Hills &amp; Harbour" width="92" height="35"><span>House &amp; Land admin</span></a>
  <?php if ($signedIn): ?>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="logout"><a href="../house-land/" target="_blank" rel="noopener">View site ↗</a><button type="submit">Sign out</button></form>
  <?php endif; ?>
</header>

<main class="admin-main">
<?php if ($flash): ?><p class="admin-flash" role="status"><?= $flash ?></p><?php endif; ?>
<?php if ($tooBig && !$signedIn) $error = 'That request was too large.'; ?>

<?php if (!$auth): ?>
  <form class="admin-card admin-auth" method="post">
    <h1 class="display-sm">Set up your admin password</h1>
    <p class="muted">Open <code>data/setup-code.txt</code> on the server (cPanel File Manager) and paste the code here. It's a one-time check that this is really you.</p>
    <?php if ($error): ?><p class="admin-error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <input type="hidden" name="action" value="setup">
    <label>Setup code<input name="code" required autocomplete="off"></label>
    <label>New password (10+ characters)<input name="password" type="password" required minlength="10" autocomplete="new-password"></label>
    <button class="btn btn--solid" type="submit">Create password</button>
  </form>

<?php elseif (!$signedIn): ?>
  <form class="admin-card admin-auth" method="post">
    <h1 class="display-sm">Sign in</h1>
    <?php if ($error): ?><p class="admin-error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <input type="hidden" name="action" value="login">
    <label>Password<input name="password" type="password" required autofocus autocomplete="current-password"></label>
    <button class="btn btn--solid" type="submit">Sign in</button>
  </form>

<?php elseif ($edit): $imgs = $edit['id'] ? listing_images($edit['id']) : []; ?>
  <p><a class="link-arrow" href="./">← All packages</a></p>
  <h1 class="display-md"><?= $edit['id'] ? e($edit['title']) : 'New package' ?></h1>
  <form class="admin-form" method="post" enctype="multipart/form-data" action="./?edit=<?= (int) $edit['id'] ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">

    <fieldset class="admin-card"><legend>Listing</legend>
      <label class="wide">Title *<input name="title" required maxlength="120" value="<?= e($edit['title']) ?>" placeholder="Lot 12, Example Street"></label>
      <label>Price<input name="price" maxlength="60" value="<?= e($edit['price']) ?>" placeholder="$749,000 (blank shows Price on request)"></label>
      <label>Status<select name="status"><?php foreach (STATUSES as $k => $v): ?><option value="<?= e($k) ?>"<?= $edit['status'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
      <label class="wide">Description<textarea name="description" rows="7"><?= e($edit['description']) ?></textarea></label>
      <label class="admin-check wide"><input type="checkbox" name="published" value="1"<?= $edit['published'] ? ' checked' : '' ?>> Published (visible on the website)</label>
    </fieldset>

    <fieldset class="admin-card"><legend>Property features</legend>
      <div class="admin-nums">
        <label>Bedrooms<input name="bedrooms" type="number" min="0" value="<?= e($edit['bedrooms']) ?>"></label>
        <label>Bathrooms<input name="bathrooms" type="number" min="0" value="<?= e($edit['bathrooms']) ?>"></label>
        <label>Car spaces<input name="car_spaces" type="number" min="0" value="<?= e($edit['car_spaces']) ?>"></label>
        <label>Land (m²)<input name="land_size" type="number" min="0" value="<?= e($edit['land_size']) ?>"></label>
        <label>Home (m²)<input name="house_size" type="number" min="0" value="<?= e($edit['house_size']) ?>"></label>
      </div>
      <label class="wide">Other features, one per line<textarea name="features" rows="6" placeholder="Stone benchtops&#10;Ducted air conditioning"><?= e($edit['features']) ?></textarea></label>
    </fieldset>

    <fieldset class="admin-card"><legend>Location</legend>
      <label class="wide">Address<input name="address" id="f-address" maxlength="160" value="<?= e($edit['address']) ?>"></label>
      <label>Suburb<input name="suburb" id="f-suburb" maxlength="80" value="<?= e($edit['suburb']) ?>" placeholder="Enfield, SA 5085"></label>
      <div class="admin-mapbar wide"><button class="btn" type="button" id="f-find">Find address on map</button><button class="btn" type="button" id="f-clear">Remove pin</button><span class="caption" id="f-mapmsg">Click the map to place the pin. Without a pin, the page shows no map.</span></div>
      <div class="admin-map wide" id="f-map"></div>
      <input type="hidden" name="lat" id="f-lat" value="<?= e($edit['lat']) ?>">
      <input type="hidden" name="lng" id="f-lng" value="<?= e($edit['lng']) ?>">
    </fieldset>

    <fieldset class="admin-card"><legend>Images</legend>
      <?php if ($imgs): ?>
      <div class="admin-imgs wide">
        <?php foreach ($imgs as $i => $im): ?>
        <div>
          <img src="../assets/uploads/listings/<?= e($im['file']) ?>" alt="">
          <label>Order<input name="sort[<?= (int) $im['id'] ?>]" type="number" value="<?= $i + 1 ?>"></label>
          <label class="admin-check"><input type="checkbox" name="remove[]" value="<?= (int) $im['id'] ?>"> Remove</label>
        </div>
        <?php endforeach; ?>
      </div>
      <p class="caption wide">The lowest order number is the cover image.</p>
      <?php endif; ?>
      <label class="wide">Add images (JPG, PNG or WebP, <?= e(ini_get('upload_max_filesize')) ?> each at most)<input name="images[]" type="file" multiple accept="image/jpeg,image/png,image/webp"></label>
    </fieldset>

    <div class="admin-actions">
      <button class="btn btn--solid" type="submit">Save package</button>
      <?php if ($edit['id'] && $edit['published']): ?><a class="link-arrow" href="../house-land/<?= e($edit['slug']) ?>" target="_blank" rel="noopener">View live ↗</a><?php endif; ?>
    </div>
  </form>
  <?php if ($edit['id']): ?>
  <form class="admin-delete" method="post" action="./" onsubmit="return confirm('Delete this package and its images? This cannot be undone.')">
    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
    <button type="submit">Delete package</button>
  </form>
  <?php endif; ?>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
  <script>
    (function () {
      var $ = function (id) { return document.getElementById(id); };
      var lat = $('f-lat'), lng = $('f-lng'), msg = $('f-mapmsg');
      var has = lat.value !== '' && lng.value !== '';
      var map = L.map('f-map').setView(has ? [+lat.value, +lng.value] : [-34.9285, 138.6007], has ? 15 : 10);
      L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
      var pin = null;
      function place(a, b) {
        lat.value = a.toFixed(6); lng.value = b.toFixed(6);
        if (pin) pin.setLatLng([a, b]);
        else pin = L.circleMarker([a, b], { radius: 10, color: '#141414', weight: 2, fillColor: '#2fd27a', fillOpacity: 1 }).addTo(map);
      }
      if (has) place(+lat.value, +lng.value);
      map.on('click', function (e) { place(e.latlng.lat, e.latlng.lng); });
      $('f-clear').addEventListener('click', function () { lat.value = lng.value = ''; if (pin) { pin.remove(); pin = null; } });
      $('f-find').addEventListener('click', function () {
        var q = ($('f-address').value + ' ' + $('f-suburb').value).trim();
        if (!q) { msg.textContent = 'Type an address first.'; return; }
        msg.textContent = 'Searching…';
        fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=au&q=' + encodeURIComponent(q))
          .then(function (r) { return r.json(); })
          .then(function (d) {
            if (!d.length) { msg.textContent = "Couldn't find that address. Click the map to place the pin."; return; }
            place(+d[0].lat, +d[0].lon); map.setView([+d[0].lat, +d[0].lon], 16);
            msg.textContent = 'Pin placed. Drag the map and click to adjust.';
          })
          .catch(function () { msg.textContent = "Search isn't available just now. Click the map to place the pin."; });
      });
    })();
  </script>

<?php else: $rows = db()->query('SELECT l.*, (SELECT COUNT(*) FROM listing_images i WHERE i.listing_id = l.id) AS images FROM listings l ORDER BY l.updated_at DESC, l.id DESC')->fetchAll(); ?>
  <div class="admin-head">
    <h1 class="display-md">Packages</h1>
    <a class="btn btn--solid" href="./?edit=new">Add package <span class="arrow">→</span></a>
  </div>
  <?php if (!$rows): ?>
    <p class="admin-card muted">No packages yet. Add the first one and tick Published when it's ready.</p>
  <?php else: ?>
  <div class="admin-table">
    <table>
      <thead><tr><th>Package</th><th>Price</th><th>Status</th><th>Website</th><th>Images</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><a href="./?edit=<?= (int) $r['id'] ?>"><strong><?= e($r['title']) ?></strong></a><br><span class="caption"><?= e($r['suburb'] ?: $r['address']) ?></span></td>
          <td><?= e($r['price'] ?: '—') ?></td>
          <td><?= e(STATUSES[$r['status']] ?? $r['status']) ?></td>
          <td><?= $r['published'] ? 'Published' : 'Draft' ?></td>
          <td><?= (int) $r['images'] ?></td>
          <td><a class="link-arrow" href="./?edit=<?= (int) $r['id'] ?>">Edit</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
  <details class="admin-card admin-pw">
    <summary>Change password</summary>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="password">
      <label>Current password<input name="current" type="password" required autocomplete="current-password"></label>
      <label>New password (10+ characters)<input name="password" type="password" required minlength="10" autocomplete="new-password"></label>
      <button class="btn" type="submit">Change password</button>
    </form>
  </details>
<?php endif; ?>
</main>
</body>
</html>
