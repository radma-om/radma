<?php
// Router for PHP's built-in server: serves real files as-is,
// resolves directory requests to their index.html (Apache's DirectoryIndex
// does this automatically in production; PHP's built-in server needs it spelled out),
// and falls back to index.html for client-side (React Router) routes.
$path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . $path;

// PHP's built-in server ignores .htaccess, so mirror the production
// access rules here: /private is never web-accessible, and /price-calc/api
// only ever serves the known submit endpoints.
if (preg_match('#^/(private|\.git|\.claude)(/|$)#', $path)) {
    http_response_code(403);
    exit;
}
if (in_array(ltrim($path, '/'), ['router.php', 'start-server.bat', 'stop-server.bat', 'update-site.bat', 'README.md', '.gitignore', '.gitattributes'], true)) {
    http_response_code(403);
    exit;
}
if (preg_match('#^/price-calc/api/#', $path) && !in_array(basename($path), ['submit-quote.php', 'submit-contact.php'], true)) {
    http_response_code(403);
    exit;
}

if (is_dir($file)) {
    $dirIndex = rtrim($file, '/') . '/index.html';
    if (file_exists($dirIndex)) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($dirIndex);
        return true;
    }
}

if ($path !== '/' && file_exists($file) && !is_dir($file)) {
    return false;
}

readfile(__DIR__ . '/index.html');
