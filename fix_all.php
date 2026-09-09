<?php
$c = file_get_contents("api.php");

// 1. customer_submit: zp_api_key -> zerosolver_api_key
$c = preg_replace(
    "/if \(\\\$provider === 'zeropoint'\) \{\s+\\\$zpKey = DB::getSetting\('zp_api_key', ''\);\s+if \(empty\(\\\$zpKey\)\) \{\s+jsonResponse\(\['success' => false, 'error' => 'แอดมินยังไม่ได้ตั้งค่า ZeroPoint API Key'\], 503\);\s+\}/u",
    "if (\$provider === 'zeropoint') {\n                \$zpKey = DB::getSetting('zerosolver_api_key', '');\n                if (empty(\$zpKey)) {\n                    jsonResponse(['success' => false, 'error' => 'แอดมินยังไม่ได้ตั้งค่า ZeroSolver API Key'], 503);\n                }",
    $c
);

// 2. SSL verify in customer_submit
$c = str_replace(
    "CURLOPT_URL => \"https://zeropoint.to/api/zerosolver-api/submit\",\n                    CURLOPT_RETURNTRANSFER => true,",
    "CURLOPT_URL => \"https://zeropoint.to/api/zerosolver-api/submit\",\n                    CURLOPT_RETURNTRANSFER => true,\n                    CURLOPT_SSL_VERIFYPEER => false,\n                    CURLOPT_SSL_VERIFYHOST => 0,",
    $c
);

// 3. SSL verify and else block in customer_job_status
$c = str_replace(
    "CURLOPT_URL => \"https://zeropoint.to/api/zerosolver-api/status/{\$id}\",\n                    CURLOPT_RETURNTRANSFER => true,",
    "CURLOPT_URL => \"https://zeropoint.to/api/zerosolver-api/status/{\$id}\",\n                    CURLOPT_RETURNTRANSFER => true,\n                    CURLOPT_SSL_VERIFYPEER => false,\n                    CURLOPT_SSL_VERIFYHOST => 0,",
    $c
);

// We need to inject the else block. Let's use preg_replace.
$c = preg_replace(
    "/(\s+)\]\);\s+\}\s+\}(\s+)\\\$res = callHighspec\(\"\/external\/job\/{\\\$id}\"\);/s",
    "$1]);$1} else { jsonResponse(['success' => false, 'error' => \$zpData['error'] ?? 'Failed to connect to ZeroSolver'], \$httpCode ?: 500); }$1}$2\$res = callHighspec(\"/external/job/{\$id}\");",
    $c
);

file_put_contents("api.php", $c);

