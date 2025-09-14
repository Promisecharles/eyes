<?php
// ipgeo.php - improved proxy + simple cache
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
// Optional: restrict origins if needed
// header('Access-Control-Allow-Origin: https://your-site.example');

$cacheTtl = 300; // seconds (5 minutes) - tune to your needs
$curlTimeout = 5; // seconds

// Determine client IP (consider proxies). Only trust X-Forwarded-For if you are behind a trusted proxy.
$remote = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;
$clientIp = $remote;
if ($xff) {
    // X-Forwarded-For can contain a comma-separated list; take first
    $parts = explode(',', $xff);
    $xffIp = trim($parts[0]);
    // Basic sanity check (IPv4 or IPv6-like)
    if (filter_var($xffIp, FILTER_VALIDATE_IP)) {
        $clientIp = $xffIp;
    }
}

// Simple filesystem cache (keyed by IP)
$cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ipgeo_cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0700, true);
}
$cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'ip_' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $clientIp) . '.json';

// Serve from cache if fresh
if (is_readable($cacheFile)) {
    $stat = @stat($cacheFile);
    if ($stat && (time() - $stat['mtime'] < $cacheTtl)) {
        $cached = @file_get_contents($cacheFile);
        if ($cached !== false) {
            // return cached content (assumed valid JSON)
            echo $cached;
            exit;
        }
    }
}

// Build upstream request (HTTPS!)
$fields = 'status,country,regionName,city,lat,lon,isp,query';
$apiUrl = 'https://ip-api.com/json/' . urlencode($clientIp) . '?fields=' . urlencode($fields);

// Use curl
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $curlTimeout);
curl_setopt($ch, CURLOPT_TIMEOUT, $curlTimeout + 1);
curl_setopt($ch, CURLOPT_USERAGENT, 'EyesGeoProxy/1.0 (+yourdomain.example)');

$response = curl_exec($ch);
$errNo = curl_errno($ch);
$errMsg = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;
curl_close($ch);

// Validate response
$validJson = false;
$data = null;
if ($response && is_string($response)) {
    $data = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
        $validJson = true;
    }
}

if (!$validJson) {
    // Upstream failure: return a sensible fallback
    $fallback = [
        'status' => 'fail',
        'message' => 'upstream_error',
        'error' => $errNo ? $errMsg : null,
        'query' => $clientIp
    ];
    $out = json_encode($fallback);
    // try to cache the failure briefly to avoid immediate repeats
    @file_put_contents($cacheFile, $out, LOCK_EX);
    echo $out;
    exit;
}

// Optionally normalize keys to expected names (no change needed for ip-api)
$normalized = [
    'status' => $data['status'] ?? 'fail',
    'country' => $data['country'] ?? null,
    'regionName' => $data['regionName'] ?? null,
    'city' => $data['city'] ?? null,
    'lat' => isset($data['lat']) ? (float)$data['lat'] : null,
    'lon' => isset($data['lon']) ? (float)$data['lon'] : null,
    'isp' => $data['isp'] ?? null,
    'query' => $data['query'] ?? $clientIp
];

$out = json_encode($normalized);

// Cache successful responses
if ($out !== false) {
    @file_put_contents($cacheFile, $out, LOCK_EX);
}

// Return normalized JSON
echo $out;
exit;
