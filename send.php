<?php
/* =========================================================
   Hills & Harbour — enquiry mailer
   GET  send.php?token  -> a signed, timestamped human-check token
   POST send.php        -> validates the form and emails it to the office
   Plain PHP mail(), so it runs on cPanel / most shared hosting.
   ========================================================= */

const TO_EMAIL   = 'admin@hillsandharbour.com.au';
const FROM_EMAIL = 'no-reply@hillsandharbour.com.au';
const FROM_NAME  = 'Hills & Harbour website';
const MIN_SECONDS = 3;      // a person takes at least this long between ticking the box and sending
const MAX_SECONDS = 7200;   // tokens expire after two hours
const MAX_PER_HOUR = 6;     // per IP address

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function reply($ok, $msg, $code = 200) {
  http_response_code($code);
  echo json_encode(['ok' => $ok, 'message' => $msg]);
  exit;
}
function sign($t) { return hash_hmac('sha256', (string) $t, SECRET); }
function clean($v, $max = 200) {
  $v = is_array($v) ? implode(', ', $v) : (string) $v;
  $v = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v));
  return mb_substr($v, 0, $max);
}
function one_line($v) { return preg_replace('/[\r\n]+/', ' ', $v); }

// The secret that signs "not a robot" tokens lives in send-config.php, which is kept out of git.
// The server writes it with a fresh random value the first time this script runs.
$cfgFile = __DIR__ . '/send-config.php';
if (!is_file($cfgFile)) {
  @file_put_contents($cfgFile, "<?php\nreturn ['secret' => '" . bin2hex(random_bytes(32)) . "'];\n", LOCK_EX);
}
$cfg = is_file($cfgFile) ? include $cfgFile : null;
if (empty($cfg['secret'])) reply(false, 'Enquiries are unavailable just now. Please email admin@hillsandharbour.com.au or call 0415 386 318.', 500);
define('SECRET', $cfg['secret']);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token'])) {
  $t = time();
  echo json_encode(['t' => $t, 's' => sign($t)]);
  exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') reply(false, 'Method not allowed.', 405);

// ---- Human check ----
if (!empty($_POST['website'])) reply(true, 'Thanks.'); // honeypot: quietly drop bots
$t = (int) ($_POST['hc_t'] ?? 0);
$s = (string) ($_POST['hc_s'] ?? '');
$age = time() - $t;
if (!$t || !hash_equals(sign($t), $s)) reply(false, 'Please tick "I\'m not a robot" and try again.', 400);
if ($age < MIN_SECONDS) reply(false, 'That was quick. Please wait a moment and send again.', 429);
if ($age > MAX_SECONDS) reply(false, 'The check expired. Please tick "I\'m not a robot" again.', 400);

// ---- Rate limit per IP ----
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$log = sys_get_temp_dir() . '/hh-enquiry-' . md5($ip . SECRET);
$hits = array_filter(@file($log, FILE_IGNORE_NEW_LINES) ?: [], fn($x) => (int) $x > time() - 3600);
if (count($hits) >= MAX_PER_HOUR) reply(false, 'Too many enquiries from this connection. Please call us instead.', 429);

// ---- Fields ----
$form  = clean($_POST['form'] ?? 'contact', 40);
$name  = one_line(clean($_POST['name'] ?? '', 120));
$email = one_line(clean($_POST['email'] ?? '', 160));
$phone = one_line(clean($_POST['phone'] ?? '', 40));
$msg   = clean($_POST['message'] ?? '', 5000);

if (mb_strlen($name) < 2) reply(false, 'Please add your name.', 400);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) reply(false, 'Please add a valid email.', 400);
if ($form === 'contact' && mb_strlen($msg) < 2) reply(false, 'Please tell us a little about your project.', 400);

// Every other field is optional and listed as sent, with labels from the form
$labels = [
  'project'   => 'Development project',
  'buyer'     => 'I am a',
  'ask'       => 'Asking about',
  'finance'   => 'Finance',
  'contact_by'=> 'Preferred contact',
  'best_time' => 'Best time to call',
  'suburb'    => 'Suburb / site address',
  'land'      => 'Land',
  'type'      => 'Services',
  'stage'     => 'Where they are at',
  'block'     => 'Block size',
  'dwellings' => 'Number of dwellings',
  'budget'    => 'Budget',
  'timeframe' => 'Timeframe',
  'heard'     => 'Heard about us',
];
$rows = [
  'Name'  => $name,
  'Email' => $email,
  'Phone' => $phone ?: '—',
];
foreach ($labels as $key => $label) {
  if (!isset($_POST[$key])) continue;
  $v = clean($_POST[$key], 300);
  if ($v !== '') $rows[$label] = one_line($v);
}

$isDev = $form === 'development';
$subject = $isDev
  ? 'Development enquiry · ' . ($rows['Development project'] ?? 'General') . ' · ' . $name
  : 'Project enquiry · ' . ($rows['Services'] ?? 'General') . ' · ' . $name;

$w = max(array_map('mb_strlen', array_keys($rows))) + 2;
$body = ($isDev ? "New development project enquiry" : "New project enquiry") . " from hillsandharbour.com.au\n";
$body .= str_repeat('-', 48) . "\n";
foreach ($rows as $k => $v) $body .= str_pad($k . ':', $w) . $v . "\n";
$body .= str_repeat('-', 48) . "\n\n";
$body .= ($msg !== '' ? $msg : '(No message)') . "\n\n";
$body .= str_repeat('-', 48) . "\n";
$body .= 'Sent ' . (new DateTime('now', new DateTimeZone('Australia/Adelaide')))->format('j M Y, g:i a T') . " · IP $ip\n";
$body .= "Reply to this email to answer $name directly.\n";

$headers = [
  'From: ' . '=?UTF-8?B?' . base64_encode(FROM_NAME) . '?= <' . FROM_EMAIL . '>',
  'Reply-To: ' . '=?UTF-8?B?' . base64_encode($name) . '?= <' . $email . '>',
  'MIME-Version: 1.0',
  'Content-Type: text/plain; charset=UTF-8',
  'Content-Transfer-Encoding: 8bit',
  'X-Mailer: PHP/' . phpversion(),
];
$sent = mail(TO_EMAIL, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers), '-f' . FROM_EMAIL);
if (!$sent) reply(false, 'We couldn\'t send that just now. Please email admin@hillsandharbour.com.au or call 0415 386 318.', 500);

$hits[] = time();
@file_put_contents($log, implode("\n", $hits));
reply(true, 'Thanks. Your enquiry is with us.');
