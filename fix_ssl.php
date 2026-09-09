<?php
$c = file_get_contents("api.php");

// 1. customer_submit
$oldSubmit = <<<EOT
                curl_setopt_array(\$ch, [
                    CURLOPT_URL => "https://zeropoint.to/api/zerosolver-api/submit",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => [
                        "Content-Type: application/json",
                        "X-API-Key: \$zpKey"
                    ],
                    CURLOPT_POSTFIELDS => json_encode(['accounts' => implode("\n", \$zpAccounts)]),
                    CURLOPT_TIMEOUT => 30
                ]);
EOT;
$newSubmit = <<<EOT
                curl_setopt_array(\$ch, [
                    CURLOPT_URL => "https://zeropoint.to/api/zerosolver-api/submit",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => [
                        "Content-Type: application/json",
                        "X-API-Key: \$zpKey"
                    ],
                    CURLOPT_POSTFIELDS => json_encode(['accounts' => implode("\n", \$zpAccounts)]),
                    CURLOPT_TIMEOUT => 30
                ]);
EOT;
$c = str_replace(str_replace("\r\n", "\n", $oldSubmit), str_replace("\r\n", "\n", $newSubmit), $c);

// 2. customer_job_status
$oldStatus = <<<EOT
            if (\$localJobCheck && \$localJobCheck['service'] === 'captcha_zp') {
                \$zpKey = DB::getSetting('zerosolver_api_key', '');
                \$ch = curl_init();
                curl_setopt_array(\$ch, [
                    CURLOPT_URL => "https://zeropoint.to/api/zerosolver-api/status/{\$id}",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => ["X-API-Key: \$zpKey"]
                ]);
                \$raw = curl_exec(\$ch);
                \$httpCode = curl_getinfo(\$ch, CURLINFO_HTTP_CODE);
                curl_close(\$ch);
                \$zpData = json_decode(\$raw, true) ?? [];
                if (\$httpCode === 200) {
                    \$zpStatus = strtoupper(\$zpData['status'] ?? 'PENDING');
                    \$successCount = (int)(\$zpData['successful'] ?? 0);
                    \$skipCount = (int)(\$zpData['already_solved'] ?? 0);
                    \$failCount = (int)(\$zpData['failed'] ?? 0);
                    DB::updateJobStatus(\$id, [
                        'status' => \$zpStatus,
                        'total_amount' => 0,
                        'success_amount' => 0,
                        'fail_amount' => 0,
                        'skip_amount' => 0,
                        'raw' => \$zpData
                    ]);
                    jsonResponse([
                        'success' => true,
                        'data' => [
                            'id' => \$id,
                            'status' => \$zpStatus,
                            'priority' => false,
                            'queue_mode' => 'normal',
                            'total_accounts' => \$zpData['total_accounts'] ?? count(\$localJobCheck['accounts'] ?? []),
                            'success_count' => \$successCount,
                            'fail_count' => \$failCount,
                            'skip_count' => \$skipCount,
                            'accounts' => \$localJobCheck['accounts'] ?? [],
                            'accounts_detail' => []
                        ]
                    ]);
                }
            }
EOT;

$newStatus = <<<EOT
            if (\$localJobCheck && \$localJobCheck['service'] === 'captcha_zp') {
                \$zpKey = DB::getSetting('zerosolver_api_key', '');
                \$ch = curl_init();
                curl_setopt_array(\$ch, [
                    CURLOPT_URL => "https://zeropoint.to/api/zerosolver-api/status/{\$id}",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_HTTPHEADER => ["X-API-Key: \$zpKey"]
                ]);
                \$raw = curl_exec(\$ch);
                \$httpCode = curl_getinfo(\$ch, CURLINFO_HTTP_CODE);
                curl_close(\$ch);
                \$zpData = json_decode(\$raw, true) ?? [];
                if (\$httpCode === 200) {
                    \$zpStatus = strtoupper(\$zpData['status'] ?? 'PENDING');
                    \$successCount = (int)(\$zpData['successful'] ?? 0);
                    \$skipCount = (int)(\$zpData['already_solved'] ?? 0);
                    \$failCount = (int)(\$zpData['failed'] ?? 0);
                    DB::updateJobStatus(\$id, [
                        'status' => \$zpStatus,
                        'total_amount' => 0,
                        'success_amount' => 0,
                        'fail_amount' => 0,
                        'skip_amount' => 0,
                        'raw' => \$zpData
                    ]);
                    jsonResponse([
                        'success' => true,
                        'data' => [
                            'id' => \$id,
                            'status' => \$zpStatus,
                            'priority' => false,
                            'queue_mode' => 'normal',
                            'total_accounts' => \$zpData['total_accounts'] ?? count(\$localJobCheck['accounts'] ?? []),
                            'success_count' => \$successCount,
                            'fail_count' => \$failCount,
                            'skip_count' => \$skipCount,
                            'accounts' => \$localJobCheck['accounts'] ?? [],
                            'accounts_detail' => []
                        ]
                    ]);
                } else {
                    jsonResponse(['success' => false, 'error' => \$zpData['error'] ?? 'Failed to connect to ZeroSolver API'], \$httpCode ?: 500);
                }
            }
EOT;
$c = str_replace(str_replace("\r\n", "\n", $oldStatus), str_replace("\r\n", "\n", $newStatus), $c);

// 3. get_job_status
$oldAdminStatus = <<<EOT
            if (\$localJobCheck && \$localJobCheck['service'] === 'captcha_zp') {
                \$zpKey = DB::getSetting('zerosolver_api_key', '');
                \$ch = curl_init();
                curl_setopt_array(\$ch, [
                    CURLOPT_URL => "https://zeropoint.to/api/zerosolver-api/status/{\$id}",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => ["X-API-Key: \$zpKey"]
                ]);
                \$raw = curl_exec(\$ch);
                \$httpCode = curl_getinfo(\$ch, CURLINFO_HTTP_CODE);
                curl_close(\$ch);
                \$zpData = json_decode(\$raw, true) ?? [];
                if (\$httpCode === 200) {
                    \$zpStatus = strtoupper(\$zpData['status'] ?? 'PENDING');
                    \$successCount = (int)(\$zpData['successful'] ?? 0);
                    \$skipCount = (int)(\$zpData['already_solved'] ?? 0);
                    \$failCount = (int)(\$zpData['failed'] ?? 0);
                    DB::updateJobStatus(\$id, [
                        'status' => \$zpStatus,
                        'total_amount' => 0,
                        'success_amount' => 0,
                        'fail_amount' => 0,
                        'skip_amount' => 0,
                        'raw' => \$zpData
                    ]);
                    jsonResponse([
                        'success' => true,
                        'data' => [
                            'id' => \$id,
                            'status' => \$zpStatus,
                            'priority' => false,
                            'queue_mode' => 'normal',
                            'total_accounts' => \$zpData['total_accounts'] ?? count(\$localJobCheck['accounts'] ?? []),
                            'success_count' => \$successCount,
                            'fail_count' => \$failCount,
                            'skip_count' => \$skipCount,
                            'accounts' => \$localJobCheck['accounts'] ?? [],
                            'accounts_detail' => [],
                            'service' => 'captcha_zp'
                        ]
                    ]);
                }
            }
EOT;

$newAdminStatus = <<<EOT
            if (\$localJobCheck && \$localJobCheck['service'] === 'captcha_zp') {
                \$zpKey = DB::getSetting('zerosolver_api_key', '');
                \$ch = curl_init();
                curl_setopt_array(\$ch, [
                    CURLOPT_URL => "https://zeropoint.to/api/zerosolver-api/status/{\$id}",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_HTTPHEADER => ["X-API-Key: \$zpKey"]
                ]);
                \$raw = curl_exec(\$ch);
                \$httpCode = curl_getinfo(\$ch, CURLINFO_HTTP_CODE);
                curl_close(\$ch);
                \$zpData = json_decode(\$raw, true) ?? [];
                if (\$httpCode === 200) {
                    \$zpStatus = strtoupper(\$zpData['status'] ?? 'PENDING');
                    \$successCount = (int)(\$zpData['successful'] ?? 0);
                    \$skipCount = (int)(\$zpData['already_solved'] ?? 0);
                    \$failCount = (int)(\$zpData['failed'] ?? 0);
                    DB::updateJobStatus(\$id, [
                        'status' => \$zpStatus,
                        'total_amount' => 0,
                        'success_amount' => 0,
                        'fail_amount' => 0,
                        'skip_amount' => 0,
                        'raw' => \$zpData
                    ]);
                    jsonResponse([
                        'success' => true,
                        'data' => [
                            'id' => \$id,
                            'status' => \$zpStatus,
                            'priority' => false,
                            'queue_mode' => 'normal',
                            'total_accounts' => \$zpData['total_accounts'] ?? count(\$localJobCheck['accounts'] ?? []),
                            'success_count' => \$successCount,
                            'fail_count' => \$failCount,
                            'skip_count' => \$skipCount,
                            'accounts' => \$localJobCheck['accounts'] ?? [],
                            'accounts_detail' => [],
                            'service' => 'captcha_zp'
                        ]
                    ]);
                } else {
                    jsonResponse(['success' => false, 'error' => \$zpData['error'] ?? 'Failed to connect to ZeroSolver API'], \$httpCode ?: 500);
                }
            }
EOT;
$c = str_replace(str_replace("\r\n", "\n", $oldAdminStatus), str_replace("\r\n", "\n", $newAdminStatus), $c);

file_put_contents("api.php", $c);

