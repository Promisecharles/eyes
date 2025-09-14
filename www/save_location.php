<?php
date_default_timezone_set("UTC");

$LOG_DIR = '/opt/eyes/logs';
if (!is_dir($LOG_DIR)) { mkdir($LOG_DIR, 0750, true); }

$input = file_get_contents("php://input");
$data = json_decode($input, true);
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

$entry = [
  'time' => gmdate("Y-m-d\TH:i:s\Z"),
  'ip' => $ip,
  'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
  'data' => $data
];

file_put_contents("$LOG_DIR/data.txt", json_encode($entry) . PHP_EOL, FILE_APPEND | LOCK_EX);

if (isset($data['type']) && $data['type'] === 'ip') {
  file_put_contents("$LOG_DIR/ip.txt", "IP: $ip\n", FILE_APPEND | LOCK_EX);
}

http_response_code(200);
echo "ok";
?>
