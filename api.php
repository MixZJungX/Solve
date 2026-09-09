<?php
// api.php — Highspec Direct API Gateway & Local Management
ini_set('display_errors', '0');
error_reporting(E_ALL);

session_set_cookie_params([
    'lifetime' => 86400 * 30, // 30 days
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Admin-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? '';

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function isAdmin(): bool {
    if (!empty($_SESSION['is_admin'])) {
        return true;
    }
    $headerToken = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';
    $actualPass = DB::getSetting('admin_password', 'admin1234');
    $expectedToken = hash('sha256', $actualPass . '_lemon_salt_2026');
    if (!empty($headerToken) && hash_equals($expectedToken, $headerToken)) {
        $_SESSION['is_admin'] = true;
        return true;
    }
    if (!empty($_COOKIE['lemon_admin_auth'])) {
        $_SESSION['is_admin'] = true;
        return true;
    }
    return false;
}

function requireAdmin(): void {
    if (!isAdmin()) {
        jsonResponse(['success' => false, 'error' => 'จำเป็นต้องเข้าสู่ระบบแอดมินก่อนดำเนินการ', 'auth_required' => true], 401);
    }
}

function getApiKey(): ?string {
    $env = getenv('HIGHSPEC_API_KEY') ?: getenv('API_KEY');
    if (!empty($env)) {
        return trim($env);
    }
    return DB::getSetting('api_key');
}

function callHighspec(string $endpoint, string $method = 'GET', ?array $payload = null): array {
    $apiKey = getApiKey();
    if (empty($apiKey)) {
        return ['status' => 401, 'error' => 'API Key ของทางร้านยังไม่ได้ตั้งค่า กรุณาติดต่อแอดมินเพื่อตั้งค่า'];
    }

    $baseUrl = 'https://api.highspec.gg/api/v1';
    $url = $baseUrl . $endpoint;

    $ch = curl_init();
    $headers = [
        'X-API-Key: ' . $apiKey,
        'Accept: application/json'
    ];

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        $headers[] = 'Content-Type: application/json';
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        }
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['status' => 500, 'error' => 'การเชื่อมต่อไปยัง Highspec ล้มเหลว: ' . $curlError];
    }

    $decoded = json_decode($response, true);
    return [
        'status' => $httpCode,
        'data' => $decoded ?? $response
    ];
}

try {
    switch ($action) {

        // ===================== CUSTOMER ACTIONS (PUBLIC) =====================
        case 'customer_submit':
            $input = json_decode(file_get_contents('php://input'), true);
            $rawUsernames = $input['usernames'] ?? [];
            if (is_string($rawUsernames)) {
                $rawUsernames = preg_split("/[\r\n,]+/", $rawUsernames);
            }

            $usernames = [];
            foreach ($rawUsernames as $u) {
                $trimmed = trim($u);
                if (!empty($trimmed)) {
                    $usernames[] = $trimmed;
                }
            }
            $usernames = array_values(array_unique($usernames));

            if (empty($usernames)) {
                jsonResponse(['success' => false, 'error' => 'กรุณาระบุชื่อบัญชี (Username) อย่างน้อย 1 ชื่อ'], 400);
            }

            // Check shop API key
            $apiKey = getApiKey();
            if (empty($apiKey)) {
                jsonResponse(['success' => false, 'error' => 'ระบบยังไม่พร้อมให้บริการ กรุณาแจ้งแอดมินร้านค้า'], 503);
            }

            // Fetch accounts from local DB
            $accountMap = DB::getAccountsByUsernames($usernames);
            $missing = [];
            $jobAccounts = [];

            foreach ($usernames as $u) {
                $lower = strtolower($u);
                if (isset($accountMap[$lower])) {
                    $jobAccounts[] = [
                        'username' => $accountMap[$lower]['username'],
                        'cookie' => $accountMap[$lower]['cookie']
                    ];
                } else {
                    $missing[] = $u;
                }
            }

            if (!empty($missing)) {
                jsonResponse([
                    'success' => false,
                    'error' => 'ไม่พบบัญชีต่อไปนี้ในระบบของร้านค้า: ' . implode(', ', $missing) . ' (กรุณาแจ้งแอดมินเพื่อเพิ่มบัญชีก่อนครับ)',
                    'missing_usernames' => $missing
                ], 404);
            }

            // Admin decides queue mode and provider
            $queueMode = DB::getSetting('queue_mode', 'normal');
            $priority = ($queueMode === 'priority');
            $provider = DB::getSetting('captcha_provider', 'highspec');

            $note = 'Lemon Shop Customer (' . count($jobAccounts) . ' accs)';

            if ($provider === 'zeropoint') {
                $zpKey = DB::getSetting('zp_api_key', '');
                if (empty($zpKey)) {
                    jsonResponse(['success' => false, 'error' => 'แอดมินยังไม่ได้ตั้งค่า ZeroPoint API Key'], 503);
                }
                
                $zpAccounts = [];
                foreach ($jobAccounts as $ja) {
                    $pw = $accountMap[strtolower($ja['username'])]['password'] ?? '';
                    $zpAccounts[] = $ja['username'] . ':' . $pw . ':' . $ja['cookie'];
                }

                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => "https://zeropoint.to/api/zerosolver-api/submit",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => [
                        "Content-Type: application/json",
                        "X-API-Key: $zpKey"
                    ],
                    CURLOPT_POSTFIELDS => json_encode(['accounts' => implode("\n", $zpAccounts)]),
                    CURLOPT_TIMEOUT => 30
                ]);
                $raw = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                $resZp = json_decode($raw, true) ?? [];
                
                if ($httpCode === 200 && !empty($resZp['job_id'])) {
                    $jobId = $resZp['job_id'];
                    DB::saveJob([
                        'id' => $jobId,
                        'service' => 'captcha_zp',
                        'status' => 'PENDING',
                        'priority' => false,
                        'note' => $note,
                        'total_accounts' => count($jobAccounts),
                        'total_amount' => 0,
                        'accounts' => $usernames,
                        'raw' => $resZp
                    ]);

                    foreach ($usernames as $u) {
                        DB::updateAccountUsage($u, $jobId, 'PENDING');
                    }

                    jsonResponse([
                        'success' => true,
                        'message' => 'ส่งงานแก้ Captcha เรียบร้อยแล้ว!',
                        'data' => [
                            'job_id' => $jobId,
                            'status' => 'PENDING',
                            'queue_position' => 0,
                            'total_accounts' => count($jobAccounts),
                            'usernames' => $usernames,
                            'queue_mode' => 'normal'
                        ]
                    ], 201);
                } else {
                    jsonResponse(['success' => false, 'error' => $resZp['error'] ?? 'เกิดข้อผิดพลาดในการเชื่อมต่อ ZeroPoint'], $httpCode ?: 500);
                }
            } else {
                // HIGHSPEC LOGIC
                $payload = [
                    'note' => $note,
                    'accounts' => $jobAccounts
                ];

                $endpoint = "/external/job/captcha/submit?service=directapi";
                if ($priority) {
                    $endpoint .= '&priority=true';
                }

                $res = callHighspec($endpoint, 'POST', $payload);

                if ($res['status'] === 201) {
                    $jobData = $res['data']['data'] ?? [];
                    $jobId = $jobData['id'] ?? '';

                    DB::saveJob([
                        'id' => $jobId,
                        'service' => 'captcha',
                        'status' => $jobData['status'] ?? 'PENDING',
                        'priority' => $priority,
                        'note' => $note,
                        'total_accounts' => count($jobAccounts),
                        'total_amount' => $jobData['total_amount'] ?? 0,
                        'accounts' => $usernames,
                        'raw' => $res['data']
                    ]);

                    foreach ($usernames as $u) {
                        DB::updateAccountUsage($u, $jobId, 'PENDING');
                    }

                    jsonResponse([
                        'success' => true,
                        'message' => 'ส่งงานแก้ Captcha เรียบร้อยแล้ว!',
                        'data' => [
                            'job_id' => $jobId,
                            'status' => $jobData['status'] ?? 'PENDING',
                            'queue_position' => $jobData['queue_position'] ?? 0,
                            'total_accounts' => count($jobAccounts),
                            'usernames' => $usernames,
                            'queue_mode' => $queueMode
                        ]
                    ], 201);
                } elseif ($res['status'] === 409) {
                jsonResponse([
                    'success' => false,
                    'error' => 'บัญชีที่คุณระบุกำลังอยู่ในคิวทำงานรอบก่อนหน้า กรุณารอ 1-2 นาทีแล้วลองใหม่อีกครั้งครับ'
                ], 409);
                } else {
                    $msg = $res['data']['message'] ?? $res['error'] ?? 'เกิดข้อผิดพลาดในการส่งงาน';
                    jsonResponse(['success' => false, 'error' => $msg], 500);
                }
            } // END HIGHSPEC LOGIC
            break;

        case 'customer_job_status':
            $id = trim($_GET['id'] ?? '');
            if (empty($id)) {
                jsonResponse(['success' => false, 'error' => 'กรุณาระบุ Job ID'], 400);
            }
            $localJobCheck = DB::getJob($id);
            if ($localJobCheck && $localJobCheck['service'] === 'captcha_zp') {
                $zpKey = DB::getSetting('zerosolver_api_key', '');
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => "https://zeropoint.to/api/zerosolver-api/status/{$id}",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => ["X-API-Key: $zpKey"]
                ]);
                $raw = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                $zpData = json_decode($raw, true) ?? [];
                if ($httpCode === 200) {
                    $zpStatus = strtoupper($zpData['status'] ?? 'PENDING');
                    $successCount = (int)($zpData['successful'] ?? 0);
                    $skipCount = (int)($zpData['already_solved'] ?? 0);
                    $failCount = (int)($zpData['failed'] ?? 0);
                    DB::updateJobStatus($id, [
                        'status' => $zpStatus,
                        'total_amount' => 0,
                        'success_amount' => 0,
                        'fail_amount' => 0,
                        'skip_amount' => 0,
                        'refunded_amount' => 0,
                        'accounts_detail_json' => []
                    ]);
                    if ($zpStatus === 'COMPLETED' && $failCount === 0 && !empty($localJobCheck['accounts'])) {
                        foreach ($localJobCheck['accounts'] as $u) {
                            DB::updateAccountStatus($u, 'COMPLETED');
                        }
                    }
                    jsonResponse([
                        'success' => true,
                        'data' => [
                            'id' => $id,
                            'status' => $zpStatus,
                            'queue_position' => 0,
                            'priority' => false,
                            'queue_mode' => 'normal',
                            'total_accounts' => $zpData['total_accounts'] ?? count($localJobCheck['accounts'] ?? []),
                            'success_count' => $successCount,
                            'fail_count' => $failCount,
                            'skip_count' => $skipCount,
                            'accounts' => $localJobCheck['accounts'] ?? [],
                            'accounts_detail' => []
                        ]
                    ]);
                }
            }


            $res = callHighspec("/external/job/{$id}");
            if ($res['status'] === 200 && isset($res['data']['data'])) {
                $info = $res['data']['data'];

                $accRes = callHighspec("/external/job/{$id}/accounts");
                $accountsDetail = [];
                $successCount = 0;
                $failCount = 0;
                $skipCount = 0;

                if ($accRes['status'] === 200 && isset($accRes['data']['data']['accounts'])) {
                    foreach ($accRes['data']['data']['accounts'] as $item) {
                        $u = explode(':', $item['combo'] ?? '')[0] ?? 'Unknown';
                        $st = strtoupper(trim($item['status'] ?? 'PENDING'));
                        if (in_array($st, ['COMPLETED', 'SUCCESS'])) {
                            $successCount++;
                        } elseif (in_array($st, ['SKIP', 'SKIPPED', 'NO_CAPTCHA'])) {
                            $skipCount++;
                        } elseif (in_array($st, ['FAILED', 'FAIL', 'ERROR', 'COOKIE_BROKEN', 'FACE_LOCK', 'FACELOCK', 'WRONG_PASSWORD', 'INVALID', 'INV', 'TWO_STEP', '2STEP', '2FA', 'BANNED', 'BAN'])) {
                            $failCount++;
                        } else {
                            if (in_array(strtoupper($info['status'] ?? ''), ['COMPLETED', 'FAILED']) && !in_array($st, ['PENDING', 'PROCESSING', 'QUEUED'])) {
                                $failCount++;
                            }
                        }
                        $accountsDetail[] = [
                            'username' => $u,
                            'status' => $item['status'] ?? 'PENDING'
                        ];

                        // Update account real status in DB so it doesn't stay PENDING
                        if (!empty($u) && $u !== 'Unknown') {
                            DB::updateAccountStatus($u, $st);
                        }
                    }
                } elseif (isset($info['success_accounts'])) {
                    $successCount = (int)$info['success_accounts'];
                    $failCount = (int)$info['fail_accounts'];
                    $skipCount = (int)($info['skip_accounts'] ?? 0);
                }

                // If job completed and no accountsDetail, mark job accounts completed ONLY if no failures
                if (($info['status'] ?? '') === 'COMPLETED' && empty($accountsDetail) && $failCount === 0 && empty($info['fail_accounts'])) {
                    $localJobCheck = DB::getJob($id);
                    if (!empty($localJobCheck['accounts'])) {
                        foreach ($localJobCheck['accounts'] as $u) {
                            DB::updateAccountStatus($u, 'COMPLETED');
                        }
                    }
                }

                // Update local DB
                DB::updateJobStatus($id, [
                    'status' => $info['status'] ?? 'PENDING',
                    'total_amount' => $info['total_amount'] ?? 0,
                    'success_amount' => $info['success_amount'] ?? 0,
                    'fail_amount' => $info['fail_amount'] ?? 0,
                    'skip_amount' => $info['skip_amount'] ?? 0,
                    'refunded_amount' => $info['refunded_amount'] ?? 0,
                    'accounts_detail_json' => $accRes['data']['data']['accounts'] ?? []
                ]);

                $localJob = DB::getJob($id);

                jsonResponse([
                    'success' => true,
                    'data' => [
                        'id' => $id,
                        'status' => $info['status'] ?? 'PENDING',
                        'queue_position' => $info['queue_position'] ?? 0,
                        'priority' => !empty($info['priority']),
                        'queue_mode' => !empty($info['priority']) ? 'priority' : 'normal',
                        'total_accounts' => $localJob['total_accounts'] ?? count($localJob['accounts'] ?? []),
                        'success_count' => $successCount,
                        'fail_count' => $failCount,
                        'skip_count' => $skipCount,
                        'accounts' => $localJob['accounts'] ?? [],
                        'accounts_detail' => $accountsDetail
                    ]
                ]);
            } else {
                $localJob = DB::getJob($id);
                if ($localJob) {
                    $sCount = 0;
                    $fCount = 0;
                    $skCount = 0;
                    if (!empty($localJob['accounts_detail'])) {
                        foreach ($localJob['accounts_detail'] as $a) {
                            $st = strtoupper(trim($a['status'] ?? ''));
                            if (in_array($st, ['COMPLETED', 'SUCCESS'])) $sCount++;
                            elseif (in_array($st, ['SKIP', 'SKIPPED', 'NO_CAPTCHA'])) $skCount++;
                            elseif (in_array($st, ['FAILED', 'FAIL', 'ERROR', 'COOKIE_BROKEN', 'FACE_LOCK', 'FACELOCK', 'WRONG_PASSWORD', 'INVALID', 'INV', 'TWO_STEP', '2STEP', '2FA', 'BANNED', 'BAN'])) $fCount++;
                            elseif (!empty($st) && !in_array($st, ['PENDING', 'PROCESSING', 'QUEUED'])) $fCount++;
                        }
                    }
                    jsonResponse([
                        'success' => true,
                        'data' => [
                            'id' => $id,
                            'status' => $localJob['status'],
                            'total_accounts' => $localJob['total_accounts'],
                            'success_count' => $sCount,
                            'fail_count' => $fCount,
                            'skip_count' => $skCount,
                            'accounts' => $localJob['accounts'],
                            'accounts_detail' => $localJob['accounts_detail'] ?? []
                        ]
                    ]);
                } else {
                    jsonResponse(['success' => false, 'error' => 'ไม่พบข้อมูลงาน'], 404);
                }
            }
            break;


        // ===================== ADMIN AUTH =====================
        case 'admin_login':
            $input = json_decode(file_get_contents('php://input'), true);
            $password = trim($input['password'] ?? '');
            $adminPassword = DB::getSetting('admin_password', 'admin1234');

            if ($password === $adminPassword) {
                $_SESSION['is_admin'] = true;
                setcookie('lemon_admin_auth', '1', time() + (86400 * 30), '/', '', false, false);
                $token = hash('sha256', $adminPassword . '_lemon_salt_2026');
                jsonResponse([
                    'success' => true,
                    'message' => 'เข้าสู่ระบบสำเร็จ',
                    'token' => $token
                ]);
            } else {
                jsonResponse(['success' => false, 'error' => 'รหัสผ่านแอดมินไม่ถูกต้อง'], 401);
            }
            break;

        case 'admin_logout':
            $_SESSION['is_admin'] = false;
            unset($_SESSION['is_admin']);
            setcookie('lemon_admin_auth', '', time() - 3600, '/');
            session_destroy();
            jsonResponse(['success' => true, 'message' => 'ออกจากระบบเรียบร้อย']);
            break;

        case 'admin_check_auth':
            jsonResponse([
                'success' => true,
                'is_admin' => isAdmin()
            ]);
            break;


        // ===================== ADMIN ACTIONS (PROTECTED) =====================
        case 'get_settings':
            requireAdmin();
            $apiKey = DB::getSetting('api_key', '');
            $maskedKey = '';
            if (!empty($apiKey)) {
                $len = strlen($apiKey);
                $maskedKey = substr($apiKey, 0, 7) . str_repeat('*', max(0, $len - 11)) . substr($apiKey, -4);
            }
            $queueMode = DB::getSetting('queue_mode', 'normal');
            $zpKey = DB::getSetting('zp_api_key', '');
            $zpMasked = '';
            if (!empty($zpKey)) {
                $zpLen = strlen($zpKey);
                $zpMasked = substr($zpKey, 0, 14) . str_repeat('*', max(0, $zpLen - 18)) . substr($zpKey, -4);
            }
            $twPhone = DB::getSetting('tw_phone', '');
            $faceScanCost = DB::getSetting('face_scan_cost', '1');
            $inwKey = DB::getSetting('inw_api_key', '');
            $inwMasked = '';
            if (!empty($inwKey)) {
                $inwLen = strlen($inwKey);
                $inwMasked = substr($inwKey, 0, 8) . str_repeat('*', max(0, $inwLen - 12)) . substr($inwKey, -4);
            }
            jsonResponse([
                'success' => true,
                'data' => [
                    'has_key' => !empty($apiKey),
                    'masked_key' => $maskedKey,
                    'queue_mode' => $queueMode,
                    'has_zp_key' => !empty($zpKey),
                    'zp_masked_key' => $zpMasked,
                    'has_zs_key' => !empty(DB::getSetting('zerosolver_api_key')),
                    'tw_phone' => $twPhone,
                    'face_scan_cost' => $faceScanCost,
                    'has_inw_key' => !empty($inwKey),
                    'inw_masked_key' => $inwMasked,
                    'auto_approve_members' => DB::getSetting('auto_approve_members', 'false'),
                    'captcha_provider' => DB::getSetting('captcha_provider', 'highspec'),
                ]
            ]);
            break;

        case 'save_settings':
            requireAdmin();
            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['api_key'])) {
                $key = trim($input['api_key']);
                if ($key !== '' && !str_starts_with($key, 'hsk_')) {
                    jsonResponse(['success' => false, 'error' => 'รูปแบบ API Key ไม่ถูกต้อง ต้องขึ้นต้นด้วย hsk_'], 400);
                }
                if ($key !== '') {
                    DB::setSetting('api_key', $key);
                }
            }
            if (isset($input['queue_mode'])) {
                $mode = in_array($input['queue_mode'], ['normal', 'priority']) ? $input['queue_mode'] : 'normal';
                DB::setSetting('queue_mode', $mode);
            }
            if (!empty($input['admin_password'])) {
                DB::setSetting('admin_password', trim($input['admin_password']));
            }
            if (isset($input['tw_phone'])) {
                DB::setSetting('tw_phone', trim($input['tw_phone']));
            }
            if (isset($input['face_scan_cost'])) {
                $faceScanCost = max(1, (int)$input['face_scan_cost']);
                DB::setSetting('face_scan_cost', (string)$faceScanCost);
            }
            if (isset($input['auto_approve_members'])) {
                DB::setSetting('auto_approve_members', trim($input['auto_approve_members']) === 'true' ? 'true' : 'false');
            }
            if (isset($input['captcha_provider'])) {
                $provider = in_array($input['captcha_provider'], ['highspec', 'zeropoint']) ? $input['captcha_provider'] : 'highspec';
                DB::setSetting('captcha_provider', $provider);
            }
            if (isset($input['zerosolver_api_key'])) {
                DB::setSetting('zerosolver_api_key', trim($input['zerosolver_api_key']));
            }
            if (isset($input['inw_api_key'])) {
                $inwKey = trim($input['inw_api_key']);
                if ($inwKey !== '') {
                    DB::setSetting('inw_api_key', $inwKey);
                }
            }
            jsonResponse(['success' => true, 'message' => 'บันทึกการตั้งค่าเรียบร้อยแล้ว']);
            break;

        case 'get_balance':
            requireAdmin();
            $res = callHighspec('/external/balance');
            if ($res['status'] === 200 && isset($res['data']['data']['points'])) {
                $points = (int)$res['data']['data']['points'];
                $thb = number_format($points / 100, 2, '.', ',');
                jsonResponse([
                    'success' => true,
                    'data' => [
                        'points' => $points,
                        'thb' => $thb
                    ]
                ]);
            } else {
                $err = $res['data']['message'] ?? $res['error'] ?? 'ไม่สามารถดึงยอดคงเหลือได้ (HTTP ' . $res['status'] . ')';
                jsonResponse(['success' => false, 'error' => $err, 'status' => $res['status']], $res['status'] >= 400 ? $res['status'] : 500);
            }
            break;

        case 'get_accounts':
            requireAdmin();
            $search = $_GET['q'] ?? '';
            $accounts = DB::listAccounts($search, 20000);
            $totalCount = DB::getAccountsCount();
            jsonResponse([
                'success' => true,
                'data' => $accounts,
                'total_count' => $totalCount
            ]);
            break;

        case 'add_account':
            requireAdmin();
            $input = json_decode(file_get_contents('php://input'), true);
            $username = trim($input['username'] ?? '');
            $cookie = trim($input['cookie'] ?? '');
            $password = trim($input['password'] ?? '');
            $note = trim($input['note'] ?? '');

            if (empty($username) || empty($cookie)) {
                jsonResponse(['success' => false, 'error' => 'กรุณากรอก Username และ Cookie'], 400);
            }

            // Check if account already exists
            $stmt = DB::get()->prepare("SELECT 1 FROM accounts WHERE username = ? COLLATE NOCASE LIMIT 1");
            $stmt->execute([$username]);
            $isDuplicate = (bool)$stmt->fetchColumn();

            $ok = DB::upsertAccount($username, $cookie, $password, $note);
            $msg = $isDuplicate
                ? "อัปเดตข้อมูลบัญชีเดิม \"$username\" เรียบร้อย (มีชื่อนี้อยู่ในระบบแล้ว)"
                : "เพิ่มบัญชีใหม่ \"$username\" เรียบร้อย";

            jsonResponse([
                'success' => $ok,
                'is_duplicate' => $isDuplicate,
                'message' => $ok ? $msg : 'บันทึกบัญชีไม่สำเร็จ'
            ]);
            break;

        case 'import_accounts':
            requireAdmin();
            $input = json_decode(file_get_contents('php://input'), true);
            $text = $input['text'] ?? '';
            if (empty(trim($text))) {
                jsonResponse(['success' => false, 'error' => 'ไม่พบข้อมูลที่ต้องการนำเข้า'], 400);
            }

            $lines = preg_split("/\r\n|\n|\r/", $text);
            $imported = 0;
            $newCount = 0;
            $duplicateCount = 0;
            $invalid = 0;
            $errors = [];

            // Pre-load all existing usernames from DB
            $existingUsernames = [];
            $stmt = DB::get()->query("SELECT LOWER(username) FROM accounts");
            while ($u = $stmt->fetchColumn()) {
                $existingUsernames[$u] = true;
            }

            $seenInBatch = [];

            foreach ($lines as $lineIdx => $rawLine) {
                $line = trim($rawLine);
                if (empty($line)) continue;

                $username = '';
                $password = '';
                $cookie = '';

                if (str_contains($line, "\t")) {
                    $parts = explode("\t", $line);
                    if (count($parts) >= 3) {
                        [$username, $password, $cookie] = [$parts[0], $parts[1], $parts[2]];
                    } elseif (count($parts) == 2) {
                        [$username, $cookie] = [$parts[0], $parts[1]];
                    }
                } elseif (str_contains($line, ':')) {
                    $parts = explode(':', $line, 3);
                    if (count($parts) === 3) {
                        $username = $parts[0];
                        $password = $parts[1];
                        $cookie = $parts[2];
                    } elseif (count($parts) === 2) {
                        $username = $parts[0];
                        $cookie = $parts[1];
                    }
                }

                $username = trim($username);
                $cookie = trim($cookie);

                if (!empty($username) && !empty($cookie)) {
                    $uLower = strtolower($username);
                    if (isset($existingUsernames[$uLower]) || isset($seenInBatch[$uLower])) {
                        $duplicateCount++;
                    } else {
                        $newCount++;
                        $existingUsernames[$uLower] = true;
                    }
                    $seenInBatch[$uLower] = true;

                    DB::upsertAccount($username, $cookie, $password);
                    $imported++;
                } else {
                    $invalid++;
                    if (count($errors) < 5) {
                        $errors[] = "บรรทัดที่ " . ($lineIdx + 1) . ": รูปแบบไม่ถูกต้อง";
                    }
                }
            }

            $summaryText = "นำเข้าเรียบร้อย: เพิ่มใหม่ $newCount บัญชี";
            if ($duplicateCount > 0) {
                $summaryText .= " | ซ้ำเดิม (อัปเดต) $duplicateCount บัญชี";
            }
            if ($invalid > 0) {
                $summaryText .= " | ข้าม $invalid บรรทัด";
            }

            jsonResponse([
                'success' => true,
                'message' => $summaryText,
                'data' => [
                    'total_processed' => $imported + $invalid,
                    'new_accounts' => $newCount,
                    'duplicate_accounts' => $duplicateCount,
                    'invalid' => $invalid,
                    'errors' => $errors
                ]
            ]);
            break;

        case 'clear_all_accounts':
            requireAdmin();
            $ok = DB::clearAllAccounts();
            jsonResponse([
                'success' => $ok,
                'message' => $ok ? 'ล้างบัญชีทั้งหมดในระบบเรียบร้อยแล้ว' : 'ไม่สามารถล้างบัญชีได้'
            ]);
            break;

        case 'delete_account':
            requireAdmin();
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                jsonResponse(['success' => false, 'error' => 'รหัสบัญชีไม่ถูกต้อง'], 400);
            }
            $ok = DB::deleteAccount($id);
            jsonResponse(['success' => $ok, 'message' => $ok ? 'ลบบัญชีแล้ว' : 'ลบไม่สำเร็จ']);
            break;

        case 'get_jobs_history':
            requireAdmin();
            $jobs = DB::listJobs(50);
            jsonResponse(['success' => true, 'data' => $jobs]);
            break;

        case 'get_job_status':
            requireAdmin();
            $id = trim($_GET['id'] ?? '');
            if (empty($id)) {
                jsonResponse(['success' => false, 'error' => 'กรุณาระบุ Job ID'], 400);
            $localJobCheck = DB::getJob($id);
            if ($localJobCheck && $localJobCheck['service'] === 'captcha_zp') {
                $zpKey = DB::getSetting('zerosolver_api_key', '');
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => "https://zeropoint.to/api/zerosolver-api/status/{$id}",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => ["X-API-Key: $zpKey"]
                ]);
                $raw = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                $zpData = json_decode($raw, true) ?? [];
                if ($httpCode === 200) {
                    $zpStatus = strtoupper($zpData['status'] ?? 'PENDING');
                    $successCount = (int)($zpData['successful'] ?? 0);
                    $skipCount = (int)($zpData['already_solved'] ?? 0);
                    $failCount = (int)($zpData['failed'] ?? 0);
                    DB::updateJobStatus($id, [
                        'status' => $zpStatus,
                        'total_amount' => 0,
                        'success_amount' => 0,
                        'fail_amount' => 0,
                        'skip_amount' => 0,
                        'refunded_amount' => 0,
                        'accounts_detail_json' => []
                    ]);
                    if ($zpStatus === 'COMPLETED' && $failCount === 0 && !empty($localJobCheck['accounts'])) {
                        foreach ($localJobCheck['accounts'] as $u) {
                            DB::updateAccountStatus($u, 'COMPLETED');
                        }
                    }
                    jsonResponse([
                        'success' => true,
                        'data' => [
                            'id' => $id,
                            'status' => $zpStatus,
                            'queue_position' => 0,
                            'service' => 'captcha_zp',
                            'total_amount' => 0,
                            'total_thb' => '0.00',
                            'success_amount' => 0,
                            'fail_amount' => 0,
                            'skip_amount' => 0,
                            'refunded_amount' => 0,
                            'total_accounts' => $zpData['total_accounts'] ?? count($localJobCheck['accounts'] ?? []),
                            'success_accounts' => $successCount,
                            'fail_accounts' => $failCount,
                            'skip_accounts' => $skipCount,
                            'accounts_detail' => [],
                            'accounts' => $localJobCheck['accounts'] ?? []
                        ]
                    ]);
                }
            }

            }

            $res = callHighspec("/external/job/{$id}");
            if ($res['status'] === 200 && isset($res['data']['data'])) {
                $info = $res['data']['data'];
                $accRes = callHighspec("/external/job/{$id}/accounts");
                $accountsDetail = [];
                if ($accRes['status'] === 200 && isset($accRes['data']['data']['accounts'])) {
                    $accountsDetail = $accRes['data']['data']['accounts'];
                    foreach ($accountsDetail as $item) {
                        $u = explode(':', $item['combo'] ?? '')[0] ?? '';
                        $st = strtoupper($item['status'] ?? '');
                        if (!empty($u) && !empty($st)) {
                            DB::updateAccountStatus($u, $st);
                        }
                    }
                }

                if (($info['status'] ?? '') === 'COMPLETED' && empty($accountsDetail)) {
                    $localJobCheck = DB::getJob($id);
                    if (!empty($localJobCheck['accounts'])) {
                        foreach ($localJobCheck['accounts'] as $u) {
                            DB::updateAccountStatus($u, 'COMPLETED');
                        }
                    }
                }

                DB::updateJobStatus($id, [
                    'status' => $info['status'] ?? 'PENDING',
                    'total_amount' => $info['total_amount'] ?? 0,
                    'success_amount' => $info['success_amount'] ?? 0,
                    'fail_amount' => $info['fail_amount'] ?? 0,
                    'skip_amount' => $info['skip_amount'] ?? 0,
                    'refunded_amount' => $info['refunded_amount'] ?? 0,
                    'accounts_detail_json' => $accountsDetail
                ]);

                $localJob = DB::getJob($id);

                jsonResponse([
                    'success' => true,
                    'data' => [
                        'id' => $id,
                        'status' => $info['status'] ?? 'UNKNOWN',
                        'queue_position' => $info['queue_position'] ?? 0,
                        'service' => 'captcha',
                        'total_amount' => $info['total_amount'] ?? 0,
                        'total_thb' => number_format(($info['total_amount'] ?? 0) / 100, 2),
                        'success_amount' => $info['success_amount'] ?? 0,
                        'fail_amount' => $info['fail_amount'] ?? 0,
                        'skip_amount' => $info['skip_amount'] ?? 0,
                        'refunded_amount' => $info['refunded_amount'] ?? 0,
                        'refunded_thb' => number_format(($info['refunded_amount'] ?? 0) / 100, 2),
                        'priority' => !empty($info['priority']),
                        'accounts' => $localJob['accounts'] ?? [],
                        'accounts_detail' => $accountsDetail
                    ]
                ]);
            } else {
                $localJob = DB::getJob($id);
                if ($localJob) {
                    jsonResponse([
                        'success' => true,
                        'data' => [
                            'id' => $id,
                            'status' => $localJob['status'],
                            'total_amount' => $localJob['total_amount'],
                            'total_thb' => number_format($localJob['total_amount'] / 100, 2),
                            'success_amount' => $localJob['success_amount'],
                            'fail_amount' => $localJob['fail_amount'],
                            'accounts' => $localJob['accounts'],
                            'accounts_detail' => $localJob['accounts_detail']
                        ],
                        'from_cache' => true
                    ]);
                } else {
                    jsonResponse(['success' => false, 'error' => 'ไม่พบงาน ID นี้'], 404);
                }
            }
            break;

        case 'download_database':
            $token = $_GET['token'] ?? '';
            $adminPass = DB::getSetting('admin_password', 'admin1234');
            $expectedToken = hash('sha256', $adminPass . '_lemon_salt_2026');
            if (!isAdmin() && $token !== $expectedToken && $token !== $adminPass) {
                requireAdmin();
            }
            $dbFile = __DIR__ . '/data/highspec.db';
            if (!file_exists($dbFile)) {
                jsonResponse(['success' => false, 'error' => 'ไม่พบไฟล์ Database'], 404);
            }
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="highspec.db"');
            header('Content-Length: ' . filesize($dbFile));
            readfile($dbFile);
            exit;

        case 'export_accounts_text':
            requireAdmin();
            $accounts = DB::listAccounts(100000);
            $lines = [];
            foreach ($accounts as $a) {
                $lines[] = $a['username'] . ':' . ($a['password'] ?: 'BLANK') . ':' . $a['cookie'];
            }
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="lemon_accounts_' . date('Y-m-d') . '.txt"');
            echo implode("\n", $lines);
            exit;

        case 'upload_database':
            requireAdmin();
            if (empty($_FILES['db_file']) || $_FILES['db_file']['error'] !== UPLOAD_ERR_OK) {
                jsonResponse(['success' => false, 'error' => 'กรุณาเลือกไฟล์ .db ที่ถูกต้อง'], 400);
            }
            $target = __DIR__ . '/data/highspec.db';
            if (!is_dir(dirname($target))) {
                mkdir(dirname($target), 0777, true);
            }
            if (move_uploaded_file($_FILES['db_file']['tmp_name'], $target)) {
                jsonResponse(['success' => true, 'message' => 'อัปโหลดและแทนที่ Database สำเร็จเรียบร้อย']);
            } else {
                jsonResponse(['success' => false, 'error' => 'ไม่สามารถบันทึกไฟล์ Database ได้'], 500);
            }
            break;

        // ======= MEMBER SYSTEM (Face Unlock Access Control) =======

        case 'member_register':
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            $email = trim($body['email'] ?? '');
            $password = trim($body['password'] ?? '');

            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                jsonResponse(['success' => false, 'error' => 'กรุณาใส่อีเมลที่ถูกต้อง'], 400);
            }
            if (strlen($password) < 6) {
                jsonResponse(['success' => false, 'error' => 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร'], 400);
            }
            $existing = DB::getMemberByEmail($email);
            if ($existing) {
                jsonResponse(['success' => false, 'error' => 'อีเมลนี้ถูกใช้งานแล้ว'], 409);
            }
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $autoApprove = DB::getSetting('auto_approve_members', 'false');
            $status = ($autoApprove === 'true') ? 'approved' : 'pending';
            
            DB::createMember($email, $hash, $status);
            
            if ($status === 'approved') {
                jsonResponse(['success' => true, 'message' => 'สมัครสมาชิกสำเร็จ! สามารถเข้าใช้งานระบบสแกนหน้าได้ทันที']);
            } else {
                jsonResponse(['success' => true, 'message' => 'สมัครสมาชิกสำเร็จ! รอแอดมินอนุมัติก่อนใช้งานระบบสแกนหน้า']);
            }
            break;

        case 'member_login':
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            $email = trim($body['email'] ?? '');
            $password = trim($body['password'] ?? '');

            $member = DB::getMemberByEmail($email);
            if (!$member || !password_verify($password, $member['password_hash'])) {
                jsonResponse(['success' => false, 'error' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง'], 401);
            }
            $_SESSION['member_id'] = $member['id'];
            $_SESSION['member_email'] = $member['email'];
            $_SESSION['member_status'] = $member['status'];
            jsonResponse(['success' => true, 'status' => $member['status'], 'email' => $member['email']]);
            break;

        case 'member_logout':
            unset($_SESSION['member_id'], $_SESSION['member_email'], $_SESSION['member_status']);
            jsonResponse(['success' => true]);
            break;
            
        case 'get_site_settings':
            jsonResponse([
                'success' => true,
                'data' => [
                    'face_scan_cost' => DB::getSetting('face_scan_cost', '1')
                ]
            ]);
            break;

        case 'member_check':
            if (empty($_SESSION['member_id'])) {
                jsonResponse(['success' => false, 'logged_in' => false]);
            }
            // Refresh status from DB
            $member = DB::getMemberById((int)$_SESSION['member_id']);
            if (!$member) {
                unset($_SESSION['member_id'], $_SESSION['member_email'], $_SESSION['member_status']);
                jsonResponse(['success' => false, 'logged_in' => false]);
            }
            $_SESSION['member_status'] = $member['status'];
            jsonResponse(['success' => true, 'logged_in' => true, 'status' => $member['status'], 'email' => $member['email']]);
            break;

        // ======= FACE UNLOCK SUBMIT & STATUS =======

        case 'face_submit':
            if (empty($_SESSION['member_id'])) {
                jsonResponse(['success' => false, 'error' => 'กรุณาเข้าสู่ระบบสมาชิกก่อน'], 401);
            }
            $member = DB::getMemberById((int)$_SESSION['member_id']);
            if (!$member || $member['status'] !== 'approved') {
                jsonResponse(['success' => false, 'error' => 'บัญชีสมาชิกยังไม่ได้รับการอนุมัติ'], 403);
            }

            $zpKey = DB::getSetting('zp_api_key', '');
            if (empty($zpKey)) {
                jsonResponse(['success' => false, 'error' => 'ยังไม่ได้ตั้งค่า ZeroPoint API Key กรุณาติดต่อแอดมิน'], 503);
            }

            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            $usernames = array_filter(array_map('trim', (array)($body['usernames'] ?? [])));
            if (empty($usernames)) {
                jsonResponse(['success' => false, 'error' => 'กรุณาระบุชื่อตัวละครอย่างน้อย 1 ชื่อ'], 400);
            }

            // Fetch accounts from DB to build cookie strings
            $accountMap = DB::getAccountsByUsernames(array_values($usernames));
            $notFound = [];
            $lines = [];
            foreach ($usernames as $u) {
                $acc = $accountMap[strtolower($u)] ?? null;
                if (!$acc) {
                    $notFound[] = $u;
                    continue;
                }
                $pass = !empty($acc['password']) ? $acc['password'] : 'unknown';
                $lines[] = $acc['username'] . ':' . $pass . ':' . $acc['cookie'];
            }

            if (empty($lines)) {
                $msg = 'ไม่พบบัญชีในระบบสำหรับ: ' . implode(', ', $notFound);
                jsonResponse(['success' => false, 'error' => $msg], 404);
            }

            // ===== CHECK & DEDUCT CREDITS (1 credit per username sent) =====
            $costPerScan = (int)DB::getSetting('face_scan_cost', '1');
            $creditsNeeded = count($lines) * $costPerScan;
            $currentCredits = DB::getMemberCredits((int)$_SESSION['member_id']);
            if ($currentCredits < $creditsNeeded) {
                jsonResponse([
                    'success' => false,
                    'error' => "เครดิตไม่เพียงพอ — มี {$currentCredits} ครั้ง แต่ต้องใช้ {$creditsNeeded} ครั้ง กรุณาเติมเครดิตก่อน",
                    'credits' => $currentCredits,
                    'needed' => $creditsNeeded,
                ], 402);
            }
            // Deduct credits immediately before sending
            DB::deductMemberCredits((int)$_SESSION['member_id'], $creditsNeeded);

            $accountsStr = implode("\n", $lines);

            // POST to ZeroPoint API
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://zeropoint.to/api/faceunlock-api/submit',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'X-API-Key: ' . $zpKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => json_encode(['accounts' => $accountsStr, 'priority' => true]),
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $raw = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $resp = json_decode($raw, true);
            if ($httpCode !== 200 || empty($resp['job_id'])) {
                // Refund credits if API call failed
                DB::addMemberCredits((int)$_SESSION['member_id'], $creditsNeeded);
                $errMsg = $resp['error'] ?? $raw ?? 'ZeroPoint API Error';
                jsonResponse(['success' => false, 'error' => $errMsg], $httpCode ?: 502);
            }

            $result = [
                'job_id' => $resp['job_id'],
                'total_accounts' => count($lines),
                'credits_used' => $creditsNeeded,
                'credits_remaining' => DB::getMemberCredits((int)$_SESSION['member_id']),
            ];
            if (!empty($notFound)) {
                $result['not_found'] = $notFound;
            }
            jsonResponse(['success' => true, 'data' => $result]);
            break;

        case 'face_status':
            if (empty($_SESSION['member_id'])) {
                jsonResponse(['success' => false, 'error' => 'กรุณาเข้าสู่ระบบสมาชิกก่อน'], 401);
            }

            $zpKey = DB::getSetting('zp_api_key', '');
            if (empty($zpKey)) {
                jsonResponse(['success' => false, 'error' => 'ยังไม่ได้ตั้งค่า ZeroPoint API Key'], 503);
            }

            $jobId = trim($_GET['job_id'] ?? '');
            if (empty($jobId)) {
                jsonResponse(['success' => false, 'error' => 'กรุณาระบุ job_id'], 400);
            }

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://zeropoint.to/api/faceunlock-api/status/' . urlencode($jobId),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['X-API-Key: ' . $zpKey],
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $raw = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $resp = json_decode($raw, true);
            if ($httpCode !== 200) {
                jsonResponse(['success' => false, 'error' => $resp['error'] ?? 'ZeroPoint API Error'], $httpCode ?: 502);
            }
            jsonResponse(['success' => true, 'data' => $resp]);
            break;

        // ======= ADMIN: MEMBER MANAGEMENT =======

        case 'admin_list_members':
            requireAdmin();
            jsonResponse(['success' => true, 'members' => DB::listMembers()]);
            break;

        case 'admin_approve_member':
            requireAdmin();
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            $id = (int)($body['id'] ?? 0);
            if (!$id) jsonResponse(['success' => false, 'error' => 'ไม่พบ ID'], 400);
            DB::updateMemberStatus($id, 'approved');
            jsonResponse(['success' => true, 'message' => 'อนุมัติสมาชิกสำเร็จ']);
            break;

        case 'admin_reject_member':
            requireAdmin();
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            $id = (int)($body['id'] ?? 0);
            if (!$id) jsonResponse(['success' => false, 'error' => 'ไม่พบ ID'], 400);
            DB::updateMemberStatus($id, 'rejected');
            jsonResponse(['success' => true, 'message' => 'ปฏิเสธสมาชิกแล้ว']);
            break;

        case 'admin_delete_member':
            requireAdmin();
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            $id = (int)($body['id'] ?? 0);
            if (!$id) jsonResponse(['success' => false, 'error' => 'ไม่พบ ID'], 400);
            DB::deleteMember($id);
            jsonResponse(['success' => true]);
            break;

        case 'admin_save_zp_key':
            requireAdmin();
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            $key = trim($body['zp_api_key'] ?? '');
            DB::setSetting('zp_api_key', $key);
            jsonResponse(['success' => true, 'message' => 'บันทึก ZeroPoint API Key สำเร็จ']);
            break;

        case 'admin_topup_log':
            requireAdmin();
            jsonResponse(['success' => true, 'logs' => DB::getTopupLog(500)]);
            break;

        case 'admin_set_member_credits':
            requireAdmin();
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            $id = (int)($body['id'] ?? 0);
            $credits = (int)($body['credits'] ?? 0);
            if (!$id) jsonResponse(['success' => false, 'error' => 'ไม่พบ ID'], 400);
            DB::setMemberCredits($id, $credits);
            jsonResponse(['success' => true, 'message' => "ตั้งเครดิตเป็น {$credits} ครั้งแล้ว"]);
            break;

        case 'admin_add_member_credits':
            requireAdmin();
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            $id = (int)($body['id'] ?? 0);
            $amount = (int)($body['amount'] ?? 0);
            if (!$id || $amount <= 0) jsonResponse(['success' => false, 'error' => 'ข้อมูลไม่ถูกต้อง'], 400);
            DB::addMemberCredits($id, $amount);
            $newCredits = DB::getMemberCredits($id);
            jsonResponse(['success' => true, 'message' => "เพิ่ม {$amount} ครั้ง รวม {$newCredits} ครั้ง"]);
            break;

        // ======= MEMBER: TOPUP VOUCHER =======

        case 'topup_voucher':
            if (empty($_SESSION['member_id'])) {
                jsonResponse(['success' => false, 'error' => 'กรุณาเข้าสู่ระบบสมาชิกก่อน'], 401);
            }
            $member = DB::getMemberById((int)$_SESSION['member_id']);
            if (!$member || $member['status'] !== 'approved') {
                jsonResponse(['success' => false, 'error' => 'บัญชีสมาชิกยังไม่ได้รับการอนุมัติ'], 403);
            }

            $twPhone = DB::getSetting('tw_phone', '');
            if (empty($twPhone)) {
                jsonResponse(['success' => false, 'error' => 'ยังไม่ได้ตั้งค่าเบอร์รับซอง กรุณาติดต่อแอดมิน'], 503);
            }

            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            $link = trim($body['link'] ?? '');
            if (empty($link)) {
                jsonResponse(['success' => false, 'error' => 'กรุณาวาง link ซองก่อน'], 400);
            }
            if (!str_contains($link, 'gift.truemoney.com') && !str_contains($link, 'true')) {
                jsonResponse(['success' => false, 'error' => 'link ซองไม่ถูกต้อง — ต้องเป็น gift.truemoney.com'], 400);
            }

            // Check if voucher already used
            if (DB::checkVoucherUsed($link)) {
                jsonResponse(['success' => false, 'error' => 'ซองนี้ถูกใช้งานแล้ว'], 409);
            }

            // Extract Hash
            $hash = "";
            if (preg_match('/[?&]v=([a-zA-Z0-9]+)/', $link, $matches)) {
                $hash = $matches[1];
            } else {
                $parts = explode('?v=', $link);
                $hash = isset($parts[1]) ? $parts[1] : $link;
                $hash = preg_replace('/[^a-zA-Z0-9]/', '', $hash);
            }
            if (empty($hash)) {
                jsonResponse(['success' => false, 'error' => 'ไม่พบรหัสซองในลิ้งก์ที่ระบุ'], 400);
            }

            // Call Node.js Bridge (เพื่อหลบ Cloudflare)
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "http://127.0.0.1:3000/redeem",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'mobile' => $twPhone,
                    'voucher_hash' => $hash
                ]),
                CURLOPT_TIMEOUT => 30,
            ]);
            $raw = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($raw === false) {
                jsonResponse(['success' => false, 'error' => 'ไม่สามารถเชื่อมต่อ Node.js Bridge ได้ (ลืมรันคำสั่ง node tw_bridge.mjs หรือเปล่า?)'], 502);
            }

            if ($httpCode === 403 || str_contains($raw, 'Cloudflare') || str_contains($raw, 'Attention Required!')) {
                jsonResponse(['success' => false, 'error' => 'ถึงใช้ Node.js แล้ว เซิร์ฟเวอร์ของคุณก็ยังโดนบล็อกโดย Cloudflare อยู่ดี ต้องหา API เจ้าอื่นจริงๆ ครับ'], 403);
            }

            $twResp = json_decode($raw, true);

            // TrueMoney Direct Response logic
            $twStatus = $twResp['status']['code'] ?? '';
            if ($twStatus !== 'SUCCESS') {
                $errMsg = 'ซองไม่สามารถใช้งานได้';
                if ($twStatus === 'VOUCHER_OUT_OF_STOCK') $errMsg = 'ซองถูกรับไปหมดแล้ว';
                elseif ($twStatus === 'VOUCHER_NOT_FOUND') $errMsg = 'ไม่พบซองนี้ในระบบ หรือลิ้งก์ผิด';
                elseif ($twStatus === 'VOUCHER_EXPIRED') $errMsg = 'ซองนี้หมดอายุแล้ว';
                elseif ($twStatus === 'TARGET_USER_REDEEMED') $errMsg = 'ซองนี้คุณเคยรับไปแล้ว (เบอร์รับซองซ้ำ)';
                elseif ($twStatus === 'CANNOT_GET_OWN_VOUCHER') $errMsg = 'ไม่สามารถรับซองของตัวเองได้';
                elseif (isset($twResp['status']['message'])) $errMsg = $twResp['status']['message'];
                jsonResponse(['success' => false, 'error' => $errMsg], 400);
            }

            $amountThb = (float)($twResp['data']['voucher']['redeemed_amount_baht'] ?? 0);
            if ($amountThb <= 0) {
                $amountThb = (float)($twResp['data']['voucher']['amount_baht'] ?? 0);
            }
            if ($amountThb <= 0) {
                jsonResponse(['success' => false, 'error' => 'ไม่สามารถดึงมูลค่าซองได้ หรือซองมีมูลค่า 0 บาท'], 400);
            }

            // Calculate credits to add (1:1 ratio)
            $creditsToAdd = (int)floor($amountThb);

            if ($creditsToAdd <= 0) {
                jsonResponse(['success' => false, 'error' => "ยอดเงิน {$amountThb} บาท ไม่เพียงพอสำหรับแลกเครดิต (ขั้นต่ำ 1 บาท)"], 400);
            }

            // Add credits + log
            DB::addMemberCredits((int)$_SESSION['member_id'], $creditsToAdd);
            DB::logTopup((int)$_SESSION['member_id'], $member['email'], $link, $amountThb, $creditsToAdd);

            $newCredits = DB::getMemberCredits((int)$_SESSION['member_id']);
            jsonResponse([
                'success' => true,
                'message' => "เติมสำเร็จ! ได้รับ {$creditsToAdd} ครั้งสแกน (จาก {$amountThb} บาท)",
                'amount_thb' => $amountThb,
                'credits_added' => $creditsToAdd,
                'credits_total' => $newCredits,
            ]);
            break;

        // ======= MEMBER: GET MY CREDITS =======

        case 'promptpay_generate':
            if (empty($_SESSION['member_id'])) {
                jsonResponse(['success' => false, 'error' => 'กรุณาเข้าสู่ระบบสมาชิกก่อน'], 401);
            }
            $member = DB::getMemberById((int)$_SESSION['member_id']);
            if (!$member || $member['status'] !== 'approved') {
                jsonResponse(['success' => false, 'error' => 'บัญชีสมาชิกยังไม่ได้รับการอนุมัติ'], 403);
            }
            $inwKey = DB::getSetting('inw_api_key', '');
            if (empty($inwKey)) {
                jsonResponse(['success' => false, 'error' => 'แอดมินยังไม่ได้ตั้งค่า inwcloud API Key'], 503);
            }

            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            $amount = (float)($body['amount'] ?? 0);
            if ($amount <= 0) {
                jsonResponse(['success' => false, 'error' => 'ระบุจำนวนเงินไม่ถูกต้อง'], 400);
            }

            // Call inwcloud
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "https://api.inwcloud.shop/v1/promptpay/generate",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_HTTPHEADER => [
                    "Content-Type: application/json",
                    "Authorization: Bearer $inwKey"
                ],
                CURLOPT_POSTFIELDS => json_encode(['amount' => $amount])
            ]);
            $raw = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            $res = json_decode($raw, true);
            if ($curlError || $httpCode >= 400 || !$raw || !isset($res['status']) || $res['status'] !== 'success') {
                $apiMsg = isset($res['message']) ? $res['message'] : '';
                $errMsg = $apiMsg ? $apiMsg : 'ไม่สามารถสร้าง QR Code ได้';
                if ($httpCode === 401 || $httpCode === 403) {
                    $errMsg = 'inwcloud API Key ไม่ถูกต้อง กรุณาแจ้งแอดมินให้ตรวจสอบการตั้งค่า';
                } else if ($httpCode >= 500) {
                    $errMsg = 'ระบบ inwcloud มีปัญหาขัดข้อง กรุณาลองใหม่ภายหลัง';
                }
                jsonResponse(['success' => false, 'error' => $errMsg, 'details' => $raw]);
            }

            $txId = $res['data']['transactionId'];
            
            // Calculate credits (1:1 ratio)
            $creditsToAdd = (int)floor($amount);

            // save to db
            DB::createPromptpayTx($txId, (int)$_SESSION['member_id'], $amount, $creditsToAdd);

            jsonResponse(['success' => true, 'qr_url' => $res['data']['qr_url'], 'transactionId' => $txId, 'expires_at' => $res['data']['expires_at']]);
            break;

        case 'promptpay_check':
            if (empty($_SESSION['member_id'])) {
                jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
            }
            $inwKey = DB::getSetting('inw_api_key', '');
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            $txId = trim($body['transactionId'] ?? '');
            
            if (empty($txId) || empty($inwKey)) {
                jsonResponse(['success' => false, 'error' => 'Missing parameter'], 400);
            }
            
            $tx = DB::getPromptpayTx($txId);
            if (!$tx) {
                jsonResponse(['success' => false, 'error' => 'Transaction not found'], 404);
            }
            if ($tx['status'] === 'success') {
                jsonResponse(['success' => true, 'status' => 'success', 'already_paid' => true, 'credits_total' => DB::getMemberCredits((int)$_SESSION['member_id'])]);
            }

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "https://api.inwcloud.shop/v1/promptpay/check",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_HTTPHEADER => [
                    "Content-Type: application/json",
                    "Authorization: Bearer $inwKey"
                ],
                CURLOPT_POSTFIELDS => json_encode(['transactionId' => $txId])
            ]);
            $raw = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $res = json_decode($raw, true);
            if ($httpCode === 200 && isset($res['status']) && $res['status'] === 'success') {
                // payment success
                DB::updatePromptpayTxStatus($txId, 'success');
                DB::addMemberCredits($tx['member_id'], $tx['credits_added']);
                
                // add to topup_log
                $member = DB::getMemberById($tx['member_id']);
                DB::logTopup($tx['member_id'], $member['email'], "PromptPay: $txId", $tx['amount'], $tx['credits_added']);

                jsonResponse([
                    'success' => true, 
                    'status' => 'success',
                    'message' => "ชำระเงินสำเร็จ ได้รับ {$tx['credits_added']} เครดิต",
                    'credits_total' => DB::getMemberCredits($tx['member_id'])
                ]);
            } else {
                jsonResponse(['success' => true, 'status' => 'pending']);
            }
            break;

        case 'get_my_credits':
            if (empty($_SESSION['member_id'])) {
                jsonResponse(['success' => false, 'logged_in' => false, 'credits' => 0]);
            }
            $credits = DB::getMemberCredits((int)$_SESSION['member_id']);
            jsonResponse(['success' => true, 'credits' => $credits]);
            break;

        default:
            jsonResponse(['success' => false, 'error' => 'Action ไม่ถูกต้อง: ' . htmlspecialchars($action)], 404);
            break;
    }
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'error' => 'Server Error: ' . $e->getMessage()], 500);
}
