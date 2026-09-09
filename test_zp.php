<?php
require "db.php";
$zpKey = DB::getSetting("zerosolver_api_key", "");
$jobs = DB::listJobs(5);
foreach ($jobs as $j) {
    if ($j["service"] === "captcha_zp") {
        echo "Job ID: " . $j["id"] . "\n";
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://zeropoint.to/api/zerosolver-api/status/" . $j["id"],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["X-API-Key: $zpKey"]
        ]);
        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        echo "HTTP $httpCode: $raw\n\n";
    }
}

