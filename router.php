<?php
// router.php — Development router for PHP built-in server
session_set_cookie_params([
    'lifetime' => 86400 * 30, // 30 days
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// API routes
if (str_starts_with($uri, '/api')) {
    require __DIR__ . '/api.php';
    exit;
}

$publicDir = __DIR__ . '/public';

// Admin route (Protected: MUST be authenticated, otherwise redirect to /login)
if ($uri === '/admin' || $uri === '/admin/' || $uri === '/admin.html') {
    $hasSession = !empty($_SESSION['is_admin']);
    $hasCookie = !empty($_COOKIE['lemon_admin_auth']);

    if (!$hasSession && !$hasCookie) {
        header('Location: /login');
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    readfile($publicDir . '/admin.html');
    exit;
}

// Login Gate route (Entry gate before admin)
if ($uri === '/login' || $uri === '/login/' || $uri === '/login.html') {
    if (!empty($_SESSION['is_admin']) || !empty($_COOKIE['lemon_admin_auth'])) {
        header('Location: /admin');
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    readfile($publicDir . '/login.html');
    exit;
}

// Customer Home route
if ($uri === '/' || $uri === '/home' || $uri === '/home/' || $uri === '/index.html') {
    header('Content-Type: text/html; charset=utf-8');
    readfile($publicDir . '/index.html');
    exit;
}

// Serve public static files directly with correct MIME types
$filePath = $publicDir . $uri;
if ($uri !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimes = [
        'css'   => 'text/css; charset=utf-8',
        'js'    => 'application/javascript; charset=utf-8',
        'json'  => 'application/json; charset=utf-8',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'svg'   => 'image/svg+xml',
        'ico'   => 'image/x-icon',
        'woff2' => 'font/woff2',
        'woff'  => 'font/woff',
        'ttf'   => 'font/ttf',
        'html'  => 'text/html; charset=utf-8'
    ];
    
    header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    readfile($filePath);
    exit;
}

// Fallback to customer home
if (file_exists($publicDir . '/index.html')) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($publicDir . '/index.html');
    exit;
}

http_response_code(404);
echo "404 Not Found";
