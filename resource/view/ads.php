<?php
/*
Full example: "Web rewarded ads" implementation (PHP + JS)
Files included in this single document as sections:
  1) config.php        - DB + site config
  2) migrations.sql    - SQL to create tables
  3) db.php            - simple PDO wrapper
  4) ad_page.php       - page user visits to view ad (shows Google AdSense / placeholder)
  5) create_token.php  - AJAX endpoint to create view token
  6) claim_reward.php  - AJAX endpoint user calls after countdown to claim coins
  7) helpers.php       - helper functions (rate-limits, IP checks)
  8) static/script.js  - client-side JS for countdown and AJAX

Notes / caveats:
 - This implementation uses a "time-on-page + single-use token + server checks" approach suitable for web.
 - AdSense does NOT provide a reliable server callback for rewarded views on web. This method reduces abuse but cannot guarantee Google paid for each view.
 - Keep sensible limits (daily caps, IP/device watch patterns) and monitor ad network policies to avoid policy violations.
 - Replace placeholders for AdSense ad code with your own publisher ad snippet carefully.
 - For production: use HTTPS, secure cookies, CSRF protections, and more robust bot detection.
*/

// -------------------- config.php --------------------
// Put these settings in a separate file config.php in production
$CONFIG = [
    'db_dsn' => 'mysql:host=127.0.0.1;dbname=adreward;charset=utf8mb4',
    'db_user' => 'dbuser',
    'db_pass' => 'dbpass',
    'reward_coins' => 2,
    'daily_limit' => 10,
    'token_ttl' => 120, // seconds
    'min_view_seconds' => 25, // seconds user must stay before claiming
];

// -------------------- migrations.sql --------------------
/*
-- Run this SQL to create required tables (MySQL example)
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  coins INT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE ad_rewards (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  reward_coins INT NOT NULL,
  ip VARCHAR(45),
  user_agent TEXT,
  token VARCHAR(64),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE ad_tokens (
  token VARCHAR(64) PRIMARY KEY,
  user_id INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  claimed TINYINT(1) DEFAULT 0
);
*/

// -------------------- db.php --------------------
class DB {
    private static $pdo = null;
    public static function init($cfg){
        if(self::$pdo) return;
        self::$pdo = new PDO($cfg['db_dsn'], $cfg['db_user'], $cfg['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    public static function pdo(){ return self::$pdo; }
}

// -------------------- helpers.php --------------------
function getUserId() {
    // In real app, use your auth system (session / JWT). Here we assume user logged-in with $_SESSION['user_id']
    session_start();
    return $_SESSION['user_id'] ?? null;
}

function ipAddress(){
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function userAgent(){
    return $_SERVER['HTTP_USER_AGENT'] ?? '';
}

function countTodayViews($pdo, $userId){
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ad_rewards WHERE user_id = ? AND DATE(created_at) = CURDATE()");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function createToken($pdo, $userId, $expiresAt){
    $token = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare("INSERT INTO ad_tokens (token, user_id, created_at, expires_at) VALUES (?, ?, NOW(), ?)");
    $stmt->execute([$token, $userId, $expiresAt]);
    return $token;
}

function validateAndConsumeToken($pdo, $token, $userId){
    // Atomically validate token and mark claimed
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT token, user_id, expires_at, claimed FROM ad_tokens WHERE token = ? FOR UPDATE");
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if(!$row){ $pdo->rollBack(); return ['ok'=>false,'msg'=>'invalid_token']; }
    if((int)$row['claimed']){ $pdo->rollBack(); return ['ok'=>false,'msg'=>'already_claimed']; }
    if($row['user_id'] != $userId){ $pdo->rollBack(); return ['ok'=>false,'msg'=>'token_user_mismatch']; }
    if(strtotime($row['expires_at']) < time()){ $pdo->rollBack(); return ['ok'=>false,'msg'=>'token_expired']; }
    // mark claimed
    $stmt2 = $pdo->prepare("UPDATE ad_tokens SET claimed = 1 WHERE token = ?");
    $stmt2->execute([$token]);
    $pdo->commit();
    return ['ok'=>true];
}

// -------------------- create_token.php --------------------
// Endpoint: POST /create_token.php
// Creates one-time token when user clicks "Start ad" and server will return JSON {token: ...}
if(false){ // this block is just for bundling into single file; in production separate files
    // example usage
}

// --- Example separate file content for create_token.php ---
/*
<?php
require 'config.php'; require 'db.php'; require 'helpers.php';
header('Content-Type: application/json');
DB::init($CONFIG);
$pdo = DB::pdo();
$userId = getUserId();
if(!$userId){ http_response_code(401); echo json_encode(['error'=>'not_logged_in']); exit; }
// rate limit checks
$today = countTodayViews($pdo, $userId);
if($today >= $CONFIG['daily_limit']){ echo json_encode(['error'=>'daily_limit']); exit; }
$expiresAt = date('Y-m-d H:i:s', time() + $CONFIG['token_ttl']);
$token = createToken($pdo, $userId, $expiresAt);
echo json_encode(['token'=>$token, 'min_view_seconds'=>$CONFIG['min_view_seconds']]);
*/

// -------------------- claim_reward.php --------------------
// Endpoint: POST /claim_reward.php
// Client sends { token: '...', view_seconds: 30 }
/*
<?php
require 'config.php'; require 'db.php'; require 'helpers.php';
header('Content-Type: application/json');
DB::init($CONFIG);
$pdo = DB::pdo();
$userId = getUserId();
if(!$userId){ http_response_code(401); echo json_encode(['error'=>'not_logged_in']); exit; }
$input = json_decode(file_get_contents('php://input'), true);
$token = $input['token'] ?? '';
$viewSec = (int)($input['view_seconds'] ?? 0);
if($viewSec < $CONFIG['min_view_seconds']){ echo json_encode(['error'=>'insufficient_view_time']); exit; }
// validate token
$valid = validateAndConsumeToken($pdo, $token, $userId);
if(!$valid['ok']){ echo json_encode(['error'=>$valid['msg']]); exit; }
// check daily limit again
$today = countTodayViews($pdo, $userId);
if($today >= $CONFIG['daily_limit']){ echo json_encode(['error'=>'daily_limit']); exit; }
// everything ok: grant coins, insert ad_rewards, update users
$coins = $CONFIG['reward_coins'];
$pdo->beginTransaction();
$stmt = $pdo->prepare("INSERT INTO ad_rewards (user_id, reward_coins, ip, user_agent, token, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
$stmt->execute([$userId, $coins, ipAddress(), userAgent(), $token]);
$stmt2 = $pdo->prepare("UPDATE users SET coins = coins + ? WHERE id = ?");
$stmt2->execute([$coins, $userId]);
$pdo->commit();
// optional: also insert into transactions table if you have one
echo json_encode(['ok'=>true, 'added'=>$coins]);
*/

// -------------------- ad_page.php --------------------
/*
This is the public page where user clicks "Start ad" then reads countdown then clicks "Claim".
Replace the placeholder ad container with your AdSense ad code (or any ad provider).

Key points:
 - Request /create_token.php to get token
 - Load ad (AdSense) into page
 - Start JS countdown (min_view_seconds from server)
 - When countdown finishes enable "Claim" which calls /claim_reward.php

*/

// -------------------- static/script.js --------------------
/*
Client pseudocode (to be served at static/script.js or inline):

async function startAd() {
  // call server to create token
  const resp = await fetch('/create_token.php', {method:'POST', credentials:'include'});
  const data = await resp.json();
  if(data.error) { alert(data.error); return; }
  const token = data.token;
  const minSec = data.min_view_seconds || 25;
  // show ad container, load AdSense ad, start countdown
  let sec = minSec;
  const timerEl = document.getElementById('timer');
  const claimBtn = document.getElementById('claimBtn');
  claimBtn.disabled = true;
  timerEl.textContent = sec;
  const t = setInterval(()=>{
    sec--;
    timerEl.textContent = sec;
    if(sec<=0){ clearInterval(t); claimBtn.disabled = false; }
  }, 1000);
  // store token in DOM so claim can send it
  claimBtn.dataset.token = token;
}

async function claimReward(){
  const btn = document.getElementById('claimBtn');
  const token = btn.dataset.token;
  if(!token) { alert('No token'); return; }
  // measure actual view time optionally
  const viewSeconds = parseInt(document.getElementById('minViewSeconds').value) || 30;
  const resp = await fetch('/claim_reward.php', {
    method: 'POST',
    credentials: 'include',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ token: token, view_seconds: viewSeconds })
  });
  const data = await resp.json();
  if(data.ok){ alert('Bạn nhận được ' + data.added + ' xu'); location.reload(); }
  else alert('Lỗi: ' + (data.error || data));
}
*/

// -------------------- Deployment & testing notes --------------------
/*
1) Put config.php, db.php, helpers.php, create_token.php, claim_reward.php on your PHP server.
2) Serve ad_page.php to logged-in users only (use your auth). Replace ad placeholder with real AdSense snippet.
3) Ensure sessions work (cookies) and credentials included in fetch calls.
4) Monitor ad_rewards table for abnormal patterns (many rows same IP/day => investigate).
5) Consider additional protections: captcha for suspicious accounts, device fingerprinting, rate-limits by IP and account, and manual review for new accounts.
6) IMPORTANT: do not coerce clicks on AdSense ads or instruct users to click ads — that breaches Google policies. Use time-on-page reward, not click-based reward.
*/

echo "// Implementation file bundled. Open this file in editor and split into separate files as indicated in comments.";

?>
