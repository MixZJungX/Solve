<?php
// ZP Helper functions for Cookie Checker and Get Cookie
// Included in api.php

function zp_check_cookies($checkerKey, $cookiesMap) {
    // $cookiesMap: [ 'username' => 'cookie_string' ]
    if (empty($cookiesMap)) return [];
    
    $payload = [];
    foreach ($cookiesMap as $user => $cookie) {
        // Send format: user::::cookie so we can map it back if needed
        // But actually, we only need to extract dead cookies.
        $payload[] = $user . "::::" . $cookie;
    }
    
    $ch = curl_init('https://zeropoint.to/api/cookie-checker-api/submit');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['cookies' => implode("\n", $payload)]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-Key: ' . $checkerKey,
        'Content-Type: application/json'
    ]);
    
    $res = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($status !== 200) {
        // Fallback: if checker fails, assume cookies are alive to let Highspec try them
        // or assume we can't check. Let's just return empty array (nothing is dead).
        return [];
    }
    
    $data = json_decode($res, true);
    if (empty($data['session_id'])) return [];
    
    $sessionId = $data['session_id'];
    
    // Poll
    $maxWait = 30; // 30 seconds max
    $start = time();
    $deadCookiesFile = null;
    
    while (time() - $start < $maxWait) {
        sleep(2);
        $ch = curl_init('https://zeropoint.to/api/cookie-checker-api/status/' . $sessionId);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-API-Key: ' . $checkerKey]);
        $res = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($res, true);
        if ($data && ($data['status'] === 'completed' || $data['status'] === 'error')) {
            if ($data['status'] === 'completed' && !empty($data['download_files'])) {
                foreach ($data['download_files'] as $f) {
                    if ($f['type'] === 'dead') {
                        $deadCookiesFile = $f['url'];
                        break;
                    }
                }
            }
            break;
        }
    }
    
    $deadUsers = [];
    if ($deadCookiesFile) {
        $ch = curl_init('https://zeropoint.to' . $deadCookiesFile);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-API-Key: ' . $checkerKey]);
        $res = curl_exec($ch);
        curl_close($ch);
        
        if ($res) {
            $lines = explode("\n", $res);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                $parts = explode("::::", $line);
                if (count($parts) >= 2) {
                    $deadUsers[] = strtolower(trim($parts[0]));
                }
            }
        }
    }
    
    return $deadUsers;
}

function zp_get_cookies($getKey, $accountsMap) {
    // $accountsMap: [ 'username' => 'password' ]
    if (empty($accountsMap)) return [];
    
    $payload = [];
    foreach ($accountsMap as $user => $pass) {
        $payload[] = $user . ":" . $pass;
    }
    
    $ch = curl_init('https://zeropoint.to/api/getcookie-api/submit');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['accounts' => implode("\n", $payload)]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-Key: ' . $getKey,
        'Content-Type: application/json'
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
    
    $maxWait = 35; 
    $start = time();
    $resultFile = null;
    
    while (time() - $start < $maxWait) {
        sleep(2);
        $ch = curl_init('https://zeropoint.to/api/getcookie-api/status/' . $jobId);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-API-Key: ' . $getKey]);
        $res = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($res, true);
        if ($data && in_array($data['status'], ['completed', 'failed', 'cancelled'])) {
            if ($data['status'] === 'completed' && !empty($data['result_files'])) {
                $resultFile = $data['result_files'][0];
            }
            break;
        }
    }
    
    $validCookies = [];
    if ($resultFile) {
        $ch = curl_init('https://zeropoint.to/api/getcookie-api/download/' . $jobId . '/' . $resultFile);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-API-Key: ' . $getKey]);
        $res = curl_exec($ch);
        curl_close($ch);
        
        if ($res) {
            $lines = explode("\n", $res);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                // Get Cookie result format: user:pass:cookie
                $parts = explode(":", $line, 3);
                if (count($parts) === 3) {
                    $u = strtolower(trim($parts[0]));
                    $cookie = trim($parts[2]);
                    if (str_contains($cookie, '_|WARNING')) {
                        $validCookies[$u] = [
                            'password' => trim($parts[1]),
                            'cookie' => $cookie
                        ];
                    }
                }
            }
        }
    }
    
    return $validCookies;
}
