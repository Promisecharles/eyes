<?php
header('Content-Type: application/json');

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

$apiUrl = "http://ip-api.com/json/" . $ip . "?fields=status,country,regionName,city,lat,lon,isp,query";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

echo $response ?: json_encode(["status" => "fail", "query" => $ip]);
?>
