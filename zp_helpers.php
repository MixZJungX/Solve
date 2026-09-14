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

function zp_get_cookies($getKey, $passwordsMap) {
    if (empty($passwordsMap)) return [];
    
    $payload = [];
    foreach ($passwordsMap as $user => $pass) {
        $payload[] = $user . ":" . $pass;
    }
    
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
        return [];
    }
    
    $data = json_decode($res, true);
    if (empty($data['job_id'])) return [];
    
    $jobId = $data['job_id'];
    
    $maxWait = 60; // 60 seconds max
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
            if ($data['status'] === 'completed' || $data['status'] === 'failed' || $data['status'] === 'cancelled') {
                if (!empty($data['result_files'])) {
                    $cookieFile = null;
                    foreach ($data['result_files'] as $f) {
                        if (str_starts_with($f, 'cookies_')) {
                            $cookieFile = $f;
                            break;
                        }
                    }
                    if ($cookieFile) {
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
                            $lines = explode("\n", $res2);
                            foreach ($lines as $line) {
                                $line = trim($line);
                                if (!empty($line)) {
                                    $parts = explode(":", $line, 3);
                                    if (count($parts) >= 3) {
                                        $u = trim($parts[0]);
                                        $p = trim($parts[1]);
                                        $c = trim(implode(":", array_slice($parts, 2))); // everything after pass
                                        if (strpos($c, '_|WARNING:-DO-NOT-SHARE-THIS') !== false) {
                                            $newCookies[strtolower($u)] = [
                                                'password' => $p,
                                                'cookie' => $c
                                            ];
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                break;
            }
        }
    }
    
    return $newCookies;
}
