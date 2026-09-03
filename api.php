<?php
// api.php — Highspec Direct API Gateway & Local Management
ini_set('display_errors', '0');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

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
    return !empty($_SESSION['is_admin']);
}

function requireAdmin(): void {
    if (!isAdmin()) {
        jsonResponse(['success' => false, 'error' => 'จำเป็นต้องเข้าสู่ระบบแอดมินก่อนดำเนินการ', 'auth_required' => true], 401);
    }
}

function getApiKey(): ?string {
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

            // Admin decides queue mode (normal or priority x2)
            $queueMode = DB::getSetting('queue_mode', 'normal');
            $priority = ($queueMode === 'priority');

            $note = 'Lemon Shop Customer (' . count($jobAccounts) . ' accs)';
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
            break;

        case 'customer_job_status':
            $id = trim($_GET['id'] ?? '');
            if (empty($id)) {
                jsonResponse(['success' => false, 'error' => 'กรุณาระบุ Job ID'], 400);
            }

            $res = callHighspec("/external/job/{$id}");
            if ($res['status'] === 200 && isset($res['data']['data'])) {
                $info = $res['data']['data'];

                $accRes = callHighspec("/external/job/{$id}/accounts");
                $accountsDetail = [];
                $successCount = 0;
                $failCount = 0;

                if ($accRes['status'] === 200 && isset($accRes['data']['data']['accounts'])) {
                    foreach ($accRes['data']['data']['accounts'] as $item) {
                        $u = explode(':', $item['combo'] ?? '')[0] ?? 'Unknown';
                        $st = strtoupper($item['status'] ?? 'PENDING');
                        if (in_array($st, ['COMPLETED', 'SUCCESS'])) {
                            $successCount++;
                        } elseif (in_array($st, ['FAILED', 'COOKIE_BROKEN', 'FACE_LOCK'])) {
                            $failCount++;
                        }
                        $accountsDetail[] = [
                            'username' => $u,
                            'status' => $item['status'] ?? 'PENDING'
                        ];
                    }
                } elseif (isset($info['success_accounts'])) {
                    $successCount = (int)$info['success_accounts'];
                    $failCount = (int)$info['fail_accounts'];
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
                        'total_accounts' => $localJob['total_accounts'] ?? count($localJob['accounts'] ?? []),
                        'success_count' => $successCount,
                        'fail_count' => $failCount,
                        'accounts' => $localJob['accounts'] ?? [],
                        'accounts_detail' => $accountsDetail
                    ]
                ]);
            } else {
                $localJob = DB::getJob($id);
                if ($localJob) {
                    $sCount = 0;
                    $fCount = 0;
                    if (!empty($localJob['accounts_detail'])) {
                        foreach ($localJob['accounts_detail'] as $a) {
                            $st = strtoupper($a['status'] ?? '');
                            if (in_array($st, ['COMPLETED', 'SUCCESS'])) $sCount++;
                            elseif (in_array($st, ['FAILED', 'COOKIE_BROKEN', 'FACE_LOCK'])) $fCount++;
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
                            'accounts' => $localJob['accounts']
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
                jsonResponse(['success' => true, 'message' => 'เข้าสู่ระบบสำเร็จ']);
            } else {
                jsonResponse(['success' => false, 'error' => 'รหัสผ่านแอดมินไม่ถูกต้อง'], 401);
            }
            break;

        case 'admin_logout':
            $_SESSION['is_admin'] = false;
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
            jsonResponse([
                'success' => true,
                'data' => [
                    'has_key' => !empty($apiKey),
                    'masked_key' => $maskedKey,
                    'queue_mode' => $queueMode // 'normal' or 'priority'
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
            }

            $res = callHighspec("/external/job/{$id}");
            if ($res['status'] === 200 && isset($res['data']['data'])) {
                $info = $res['data']['data'];
                $accRes = callHighspec("/external/job/{$id}/accounts");
                $accountsDetail = [];
                if ($accRes['status'] === 200 && isset($accRes['data']['data']['accounts'])) {
                    $accountsDetail = $accRes['data']['data']['accounts'];
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

        default:
            jsonResponse(['success' => false, 'error' => 'Action ไม่ถูกต้อง: ' . htmlspecialchars($action)], 404);
            break;
    }
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'error' => 'Server Error: ' . $e->getMessage()], 500);
}
