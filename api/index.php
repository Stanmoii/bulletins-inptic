<?php
// Vercel entrypoint: dispatch PHP requests to existing project files.
$projectRoot = realpath(__DIR__ . '/..');
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$normalized = trim($requestPath, '/');

if ($normalized === '' || $normalized === 'index.php') {
    $normalized = 'inptic_asur/index.php';
}

if (strpos($normalized, '..') !== false) {
    http_response_code(400);
    exit('Invalid path');
}

$target = realpath($projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized));

// Backward-compatible fallback for top-level PHP routes like /login.php.
if (($target === false || !is_file($target)) && strpos($normalized, '/') === false) {
    $fallback = 'inptic_asur/' . $normalized;
    $target = realpath($projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $fallback));
}

if ($target === false || strpos($target, $projectRoot) !== 0 || !is_file($target)) {
    http_response_code(404);
    exit('Not Found');
}

if (substr($target, -4) !== '.php') {
    http_response_code(403);
    exit('Forbidden');
}

chdir(dirname($target));
require $target;
