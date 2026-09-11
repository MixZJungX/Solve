<?php
require "db.php";
$pdo = DB::get();
$stmt = $pdo->query("SELECT id FROM jobs WHERE service='captcha_zp' ORDER BY created_at DESC LIMIT 1");
$jobId = $stmt->fetchColumn();

if ($jobId) {
    echo "Job ID: $jobId\n";
    $zpKey = DB::getSetting("zerosolver_api_key", "");
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://zeropoint.to/api/zerosolver-api/status/" . $jobId,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => ["X-API-Key: $zpKey"]
    ]);
    $raw = curl_exec($ch);
    echo "Response: $raw\n";
} else {
    echo "No job found\n";
}
