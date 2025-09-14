<?php
date_default_timezone_set("UTC");

$LOG_DIR = '/opt/eyes/logs';
if (!is_dir($LOG_DIR)) mkdir($LOG_DIR, 0750, true);

$input = file_get_contents("php://input");
$data = json_decode($input, true);
if (!is_array($data)) {
    http_response_code(400);
    echo "invalid_json";
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

// dedupe hash (fingerprint + source + ip + 5-min bucket)
$fingerprint = $data['_fingerprint']['ua_hash'] ?? substr($ua,0,200);
$screen_hash = $data['_fingerprint']['screen_hash'] ?? '';
$source = $data['source'] ?? 'unknown';
$bucket = floor(time() / 300); // 5-minute bucket

$hash = hash('sha256', "$ip|$source|$fingerprint|$screen_hash|$bucket");
$dedupe_file = "$LOG_DIR/seen_hashes.txt";
$seen_hashes = is_file($dedupe_file) ? file($dedupe_file, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) : [];

if (!in_array($hash, $seen_hashes, true)) {
    $entry = [
        'time' => gmdate("Y-m-d\TH:i:s\Z"),
        'ip' => $ip,
        'ua' => $ua,
        'source' => $source,
        'fingerprint' => $fingerprint,
        'screen_hash' => $screen_hash,
        'data' => $data
    ];
    file_put_contents("$LOG_DIR/data.txt", json_encode($entry) . PHP_EOL, FILE_APPEND | LOCK_EX);
    file_put_contents($dedupe_file, $hash . PHP_EOL, FILE_APPEND | LOCK_EX);
}

http_response_code(200);
echo "ok";
