<?php
$lines = file("api.php");
$inSubmit = false;
$inStatus = false;
$inGetStatus = false;

for ($i=0; $i<count($lines); $i++) {
    if (strpos($lines[$i], "case 'customer_submit':") !== false) $inSubmit = true;
    if (strpos($lines[$i], "case 'customer_job_status':") !== false) { $inSubmit = false; $inStatus = true; }
    if (strpos($lines[$i], "case 'get_job_status':") !== false) { $inStatus = false; $inGetStatus = true; }
    if (strpos($lines[$i], "case 'admin_job_detail':") !== false) { $inGetStatus = false; }
    
    // Fix zp_api_key -> zerosolver_api_key in customer_submit
    if ($inSubmit && strpos($lines[$i], "\$zpKey = DB::getSetting('zp_api_key', '');") !== false) {
        $lines[$i] = str_replace("zp_api_key", "zerosolver_api_key", $lines[$i]);
    }
    if ($inSubmit && strpos($lines[$i], "'แอดมินยังไม่ได้ตั้งค่า ZeroPoint API Key'") !== false) {
        $lines[$i] = str_replace("ZeroPoint", "ZeroSolver", $lines[$i]);
    }
    
    // Fix SSL in customer_submit
    if ($inSubmit && strpos($lines[$i], "CURLOPT_URL => \"https://zeropoint.to/api/zerosolver-api/submit\",") !== false) {
        $lines[$i+1] = "                    CURLOPT_RETURNTRANSFER => true,\n                    CURLOPT_SSL_VERIFYPEER => false,\n                    CURLOPT_SSL_VERIFYHOST => 0,\n";
    }
    
    // Fix SSL in customer_job_status
    if ($inStatus && strpos($lines[$i], "CURLOPT_URL => \"https://zeropoint.to/api/zerosolver-api/status/{\$id}\",") !== false) {
        $lines[$i+1] = "                    CURLOPT_RETURNTRANSFER => true,\n                    CURLOPT_SSL_VERIFYPEER => false,\n                    CURLOPT_SSL_VERIFYHOST => 0,\n";
    }
    // Fix missing else in customer_job_status
    if ($inStatus && strpos($lines[$i], "\$res = callHighspec(\"/external/job/{\$id}\");") !== false) {
        if (strpos($lines[$i-1], "}") !== false && strpos($lines[$i-2], "}") !== false) {
            $lines[$i-1] = "                } else { jsonResponse(['success' => false, 'error' => \$zpData['error'] ?? 'Failed to fetch from ZeroSolver API'], \$httpCode ?: 500); }\n            }\n";
        }
    }
    
    // Fix SSL in get_job_status
    if ($inGetStatus && strpos($lines[$i], "CURLOPT_URL => \"https://zeropoint.to/api/zerosolver-api/status/{\$id}\",") !== false) {
        $lines[$i+1] = "                    CURLOPT_RETURNTRANSFER => true,\n                    CURLOPT_SSL_VERIFYPEER => false,\n                    CURLOPT_SSL_VERIFYHOST => 0,\n";
    }
    // Fix missing else in get_job_status
    if ($inGetStatus && strpos($lines[$i], "\$res = callHighspec(\"/external/job/{\$id}\");") !== false) {
        if (strpos($lines[$i-1], "}") !== false && strpos($lines[$i-2], "}") !== false) {
            $lines[$i-1] = "                } else { jsonResponse(['success' => false, 'error' => \$zpData['error'] ?? 'Failed to fetch from ZeroSolver API'], \$httpCode ?: 500); }\n            }\n";
        }
    }
}
file_put_contents("api.php", implode("", $lines));

