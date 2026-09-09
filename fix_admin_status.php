<?php
$c = file_get_contents("api.php");
$lines = explode("\n", $c);
$newLines = [];
foreach($lines as $i => $l) {
    if ($i == 773) {
        $newLines[] = "            \$localJobCheck = DB::getJob(\$id);";
        $newLines[] = "            if (\$localJobCheck && \$localJobCheck['service'] === 'captcha_zp') {";
        $newLines[] = "                \$zpKey = DB::getSetting('zp_api_key', '');";
        $newLines[] = "                \$ch = curl_init();";
        $newLines[] = "                curl_setopt_array(\$ch, [";
        $newLines[] = "                    CURLOPT_URL => \"https://zeropoint.to/api/zerosolver-api/status/{\$id}\",";
        $newLines[] = "                    CURLOPT_RETURNTRANSFER => true,";
        $newLines[] = "                    CURLOPT_HTTPHEADER => [\"X-API-Key: \$zpKey\"]";
        $newLines[] = "                ]);";
        $newLines[] = "                \$raw = curl_exec(\$ch);";
        $newLines[] = "                \$httpCode = curl_getinfo(\$ch, CURLINFO_HTTP_CODE);";
        $newLines[] = "                curl_close(\$ch);";
        $newLines[] = "                \$zpData = json_decode(\$raw, true) ?? [];";
        $newLines[] = "                if (\$httpCode === 200) {";
        $newLines[] = "                    \$zpStatus = strtoupper(\$zpData['status'] ?? 'PENDING');";
        $newLines[] = "                    \$successCount = (int)(\$zpData['successful'] ?? 0);";
        $newLines[] = "                    \$skipCount = (int)(\$zpData['already_solved'] ?? 0);";
        $newLines[] = "                    \$failCount = (int)(\$zpData['failed'] ?? 0);";
        $newLines[] = "                    DB::updateJobStatus(\$id, [";
        $newLines[] = "                        'status' => \$zpStatus,";
        $newLines[] = "                        'total_amount' => 0,";
        $newLines[] = "                        'success_amount' => 0,";
        $newLines[] = "                        'fail_amount' => 0,";
        $newLines[] = "                        'skip_amount' => 0,";
        $newLines[] = "                        'refunded_amount' => 0,";
        $newLines[] = "                        'accounts_detail_json' => []";
        $newLines[] = "                    ]);";
        $newLines[] = "                    if (\$zpStatus === 'COMPLETED' && \$failCount === 0 && !empty(\$localJobCheck['accounts'])) {";
        $newLines[] = "                        foreach (\$localJobCheck['accounts'] as \$u) {";
        $newLines[] = "                            DB::updateAccountStatus(\$u, 'COMPLETED');";
        $newLines[] = "                        }";
        $newLines[] = "                    }";
        $newLines[] = "                    jsonResponse([";
        $newLines[] = "                        'success' => true,";
        $newLines[] = "                        'data' => [";
        $newLines[] = "                            'id' => \$id,";
        $newLines[] = "                            'status' => \$zpStatus,";
        $newLines[] = "                            'queue_position' => 0,";
        $newLines[] = "                            'service' => 'captcha_zp',";
        $newLines[] = "                            'total_amount' => 0,";
        $newLines[] = "                            'total_thb' => '0.00',";
        $newLines[] = "                            'success_amount' => 0,";
        $newLines[] = "                            'fail_amount' => 0,";
        $newLines[] = "                            'skip_amount' => 0,";
        $newLines[] = "                            'refunded_amount' => 0,";
        $newLines[] = "                            'total_accounts' => \$zpData['total_accounts'] ?? count(\$localJobCheck['accounts'] ?? []),";
        $newLines[] = "                            'success_accounts' => \$successCount,";
        $newLines[] = "                            'fail_accounts' => \$failCount,";
        $newLines[] = "                            'skip_accounts' => \$skipCount,";
        $newLines[] = "                            'accounts_detail' => [],";
        $newLines[] = "                            'accounts' => \$localJobCheck['accounts'] ?? []";
        $newLines[] = "                        ]";
        $newLines[] = "                    ]);";
        $newLines[] = "                }";
        $newLines[] = "            }";
        $newLines[] = "";
        $newLines[] = $l; // $res = callHighspec...
        continue;
    }
    
    $newLines[] = $l;
}
file_put_contents("api.php", implode("\n", $newLines));

