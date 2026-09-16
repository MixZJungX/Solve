<?php
// ZP Helper functions for Cookie Checker and Get Cookie
// Included in api.php

function zp_check_cookies($checkerKey, $cookiesMap) {
    if (empty($cookiesMap)) return [];
    
    $payload = [];
    foreach ($cookiesMap as $user => $cookie) {
        $payload[] = $user . "::::" . $cookie;
    }
    
    $ch = curl_init('https://zeropoint.to/api/cookie-checker-api/submit');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['cookies' => implode("\n", $payload)]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-Key: ' . $checkerKey,
        'Content-Type: application/json',
        'User-Agent: Mozilla/5.0'
    ]);
    
    $res = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($status !== 200) {
        return [];
    }
    
    $data = json_decode($res, true);
    if (empty($data['session_id'])) return [];
    
    $sessionId = $data['session_id'];
    
    $maxWait = 30; // 30 seconds max
    $start = time();
    $deadUsers = [];
    
    while (time() - $start < $maxWait) {
        sleep(2);
        $ch = curl_init('https://zeropoint.to/api/cookie-checker-api/status/' . $sessionId);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-API-Key: ' . $checkerKey,
            'User-Agent: Mozilla/5.0'
        ]);
        $res = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($status === 200) {
            $data = json_decode($res, true);
            // Debug log if called from admin_zp_fetch (traceId is null)
            if ($traceId === null) {
                $statusTxt = $data['status'] ?? 'unknown';
                $completed = $data['completed'] ?? 0;
                $total = $data['total'] ?? count($passwordsMap);
                writeAdminLog('AdminFetch', "ZP Status: {$statusTxt} - {$completed}/{$total}");
            }
            $data = json_decode($res, true);
            if ($data['status'] === 'completed') {
                $badTypes = ['dead', 'face_lock', 'captcha_lock', 'ban_warn'];
                foreach ($badTypes as $type) {
                    $countKey = $type . '_count';
                    if (!empty($data[$countKey]) && $data[$countKey] > 0) {
                        $ch2 = curl_init('https://zeropoint.to/api/cookie-checker-api/download/' . $sessionId . '/' . $type);
                        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, 0);
                        curl_setopt($ch2, CURLOPT_HTTPHEADER, [
                            'X-API-Key: ' . $checkerKey,
                            'User-Agent: Mozilla/5.0'
                        ]);
                        $res2 = curl_exec($ch2);
                        $status2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                        curl_close($ch2);
                        if ($status2 === 200 && !empty($res2)) {
                            $lines = explode("\n", $res2);
                            foreach ($lines as $line) {
                                $line = trim($line);
                                if (!empty($line)) {
                                    $parts = explode("::::", $line, 2);
                                    if (count($parts) === 2) {
                                        $deadUsers[] = strtolower(trim($parts[0]));
                                    }
                                }
                            }
                        }
                    }
                }
                break;
            } else if ($data['status'] === 'error') {
                break;
            }
        }
    }
    
    return array_values(array_unique($deadUsers));
}

function zp_get_cookies($getKey, $passwordsMap, $traceId = null) {
    if (empty($passwordsMap)) return [];
    
    $payload = [];
    foreach ($passwordsMap as $user => $pass) {
        $payload[] = $user . ":" . $pass;
    }
    
    if ($traceId === null) writeAdminLog('AdminFetch', "ZP Request: เริ่มยิง API Get Cookie จำนวน " . count($payload) . " ไอดี");
    
    $ch = curl_init('https://zeropoint.to/api/getcookie-api/submit');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['accounts' => implode("\n", $payload)]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-Key: ' . $getKey,
        'Content-Type: application/json',
        'User-Agent: Mozilla/5.0'
    ]);
    
    $res = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($status !== 200) {
        if ($traceId === null) writeAdminLog('AdminFetch', "ZP Request: ยิง API ล้มเหลว Status=$status, ตอบกลับ=" . substr($res, 0, 100));
        return [];
    }
    
    $data = json_decode($res, true);
    if (empty($data['job_id'])) {
        if ($traceId === null) writeAdminLog('AdminFetch', "ZP Request: ไม่ได้ Job ID กลับมา ตอบกลับ=" . substr($res, 0, 100));
        return [];
    }
    
    $jobId = $data['job_id'];
    if ($traceId === null) writeAdminLog('AdminFetch', "ZP Request: ได้ Job ID = $jobId, เริ่มรอผล...");
    
    $maxWait = 180; // 180 seconds max
    $start = time();
    $newCookies = [];
    
    while (time() - $start < $maxWait) {
        sleep(2);
        $ch = curl_init('https://zeropoint.to/api/getcookie-api/status/' . $jobId);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-API-Key: ' . $getKey,
            'User-Agent: Mozilla/5.0'
        ]);
        $res = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($status === 200) {
            $data = json_decode($res, true);
            $statusTxt = $data['status'] ?? 'unknown';
            
            if ($traceId === null) {
                $completed = $data['completed'] ?? 0;
                $total = $data['total'] ?? count($passwordsMap);
                writeAdminLog('AdminFetch', "ZP Status Check: $statusTxt ($completed/$total)");
            }
            
            if ($traceId && isset($data['status'])) {
                if ($statusTxt === 'processing' || $statusTxt === 'pending') {
                    $completed = $data['completed'] ?? 0;
                    $total = $data['total'] ?? count($passwordsMap);
                    updateTrace($traceId, "กำลังหมุนดึงคุกกี้ใหม่ (ZP: {$statusTxt} - {$completed}/{$total})...");
                }
            }
            if ($statusTxt === 'completed' || $statusTxt === 'failed' || $statusTxt === 'cancelled') {
                if ($traceId === null) writeAdminLog('AdminFetch', "ZP เสร็จสิ้นด้วยสถานะ: $statusTxt");
                if (!empty($data['result_files'])) {
                    $cookieFile = null;
                    foreach ($data['result_files'] as $f) {
                        if (str_starts_with($f, 'cookies_')) {
                            $cookieFile = $f;
                            break;
                        }
                    }
                    if ($cookieFile) {
                        if ($traceId === null) writeAdminLog('AdminFetch', "ZP ดาวน์โหลดไฟล์: $cookieFile");
                        $ch2 = curl_init('https://zeropoint.to/api/getcookie-api/download/' . $jobId . '/' . $cookieFile);
                        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, 0);
                        curl_setopt($ch2, CURLOPT_HTTPHEADER, [
                            'X-API-Key: ' . $getKey,
                            'User-Agent: Mozilla/5.0'
                        ]);
                        $res2 = curl_exec($ch2);
                        $status2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                        curl_close($ch2);
                        if ($status2 === 200 && !empty($res2)) {
                            if ($traceId === null) writeAdminLog('AdminFetch', "ZP ดาวน์โหลดไฟล์สำเร็จ เริ่มแปลงผล");
                            $lines = explode("\n", $res2);
                            foreach ($lines as $line) {
                                $line = trim($line);
                                if (!empty($line)) {
                                    $parts = explode(":", $line);
                                    if (count($parts) >= 2) {
                                        $u = trim($parts[0]);
                                        
                                        $c = '';
                                        $p = '';
                                        foreach ($parts as $i => $part) {
                                            if ($i == 0) continue;
                                            if (strpos($part, '_|WARNING') !== false) {
                                                $c = trim($part);
                                                if ($i == 2) $p = trim($parts[1]);
                                                break;
                                            }
                                        }
                                        if (empty($c) && count($parts) >= 3) {
                                            $c = trim($parts[2]);
                                            $p = trim($parts[1]);
                                        }
                                        if (empty($p)) {
                                            $p = $passwordsMap[strtolower($u)] ?? '';
                                        }
                                        
                                        if (!empty($c)) {
                                            $newCookies[strtolower($u)] = [
                                                'password' => $p,
                                                'cookie' => $c
                                            ];
                                        }
                                    }
                                }
                            }
                        } else {
                            if ($traceId === null) writeAdminLog('AdminFetch', "ZP ดาวน์โหลดไฟล์ล้มเหลว (Status: $status2)");
                        }
                    }
                } else {
                    if ($traceId === null) writeAdminLog('AdminFetch', "ZP ไม่มีไฟล์ result_files");
                }
                break;
            }
        } else {
            if ($traceId === null) writeAdminLog('AdminFetch', "ZP Status Check Failed: Status=$status");
        }
    }
    
    if (empty($newCookies)) {
        if ($traceId === null) writeAdminLog('AdminFetch', "สรุป: ไม่ได้คุกกี้กลับมาเลย (วนลูปจบ)");
    }
    
    return $newCookies;
}
