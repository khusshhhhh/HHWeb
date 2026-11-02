<?php
/* =========================================================
   Hills & Harbour — House & Land listings data
   A single SQLite file in data/ (created on first use, blocked
   from the web, kept out of git). Needs only PDO SQLite.
   ========================================================= */

define('DATA_DIR', dirname(__DIR__) . '/data');
define('UPLOAD_DIR', dirname(__DIR__) . '/assets/uploads/listings');
const STATUSES = ['available' => 'Available', 'under-offer' => 'Under offer', 'sold' => 'Sold'];

function db() {
  static $pdo = null;
  if ($pdo) return $pdo;
  if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
    file_put_contents(DATA_DIR . '/.htaccess', "Require all denied\n");
  }
  $pdo = new PDO('sqlite:' . DATA_DIR . '/listings.sqlite', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  $pdo->exec('PRAGMA foreign_keys = ON');
  $pdo->exec("CREATE TABLE IF NOT EXISTS listings (
    id INTEGER PRIMARY KEY,
    slug TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    published INTEGER NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'available',
    price TEXT NOT NULL DEFAULT '',
    address TEXT NOT NULL DEFAULT '',
    suburb TEXT NOT NULL DEFAULT '',
    description TEXT NOT NULL DEFAULT '',
    bedrooms INTEGER,
    bathrooms INTEGER,
    car_spaces INTEGER,
    land_size INTEGER,
    house_size INTEGER,
    features TEXT NOT NULL DEFAULT '',
    lat REAL,
    lng REAL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
  )");
  $pdo->exec("CREATE TABLE IF NOT EXISTS listing_images (
    id INTEGER PRIMARY KEY,
    listing_id INTEGER NOT NULL REFERENCES listings(id) ON DELETE CASCADE,
    file TEXT NOT NULL,
    sort INTEGER NOT NULL DEFAULT 0
  )");
  return $pdo;
}

function e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

function listing_images($id) {
  $q = db()->prepare('SELECT * FROM listing_images WHERE listing_id = ? ORDER BY sort, id');
  $q->execute([$id]);
  return $q->fetchAll();
}

function listing_features($row) {
  return array_values(array_filter(array_map('trim', explode("\n", $row['features']))));
}

// "4 bed", "2 bath", ... for whichever numbers the listing has
function listing_specs($row) {
  $specs = [];
  foreach (['bedrooms' => 'bed', 'bathrooms' => 'bath', 'car_spaces' => 'car'] as $k => $unit) {
    if ($row[$k] !== null) $specs[] = $row[$k] . ' ' . $unit;
  }
  if ($row['land_size'] !== null) $specs[] = number_format($row['land_size']) . ' m² land';
  return $specs;
}

function status_chip($status) {
  $cls = $status === 'available' ? 'chip--granted' : 'chip--dark';
  return '<span class="chip ' . $cls . '">' . e(STATUSES[$status] ?? $status) . '</span>';
}
