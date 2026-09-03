<?php
// router.php — Development router for PHP built-in server

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// API routes
if (str_starts_with($uri, '/api')) {
    require __DIR__ . '/api.php';
    exit;
}

$publicDir = __DIR__ . '/public';

// Admin route
if ($uri === '/admin' || $uri === '/admin/' || $uri === '/admin.html') {
    header('Content-Type: text/html; charset=utf-8');
    readfile($publicDir . '/admin.html');
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

// Customer page by default
if (file_exists($publicDir . '/index.html')) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($publicDir . '/index.html');
    exit;
}

http_response_code(404);
echo "404 Not Found";
