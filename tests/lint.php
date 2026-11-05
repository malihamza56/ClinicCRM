<?php
declare(strict_types=1);

/*
 * Quick static checks:  php tests/lint.php
 *  - every PHP file compiles
 *  - app code does not contain leftover debugging calls
 *  - views contain no inline scripts / on*= handlers (the CSP would block them)
 *  - views never echo a bare variable without e() (catches the most common XSS slip)
 */

$root = dirname(__DIR__);
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$errors = [];
$count = 0;

foreach ($files as $file) {
    $path = $file->getPathname();
    if (!str_ends_with($path, '.php') || str_contains($path, '/vendor/') || str_contains($path, '/node_modules/')) {
        continue;
    }
    $count++;
    $relative = substr($path, strlen($root) + 1);
    exec('php -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
    if ($code !== 0) {
        $errors[] = "$relative: syntax error";
    }
    $source = (string) file_get_contents($path);
    if (str_starts_with($relative, 'app/') && preg_match('/\b(var_dump|print_r|dd|dump)\s*\(/', $source)) {
        $errors[] = "$relative: debugging call left in code";
    }
    if (str_starts_with($relative, 'app/Views/') || $relative === 'public/index.php') {
        // The Content-Security-Policy forbids inline scripts and event-handler attributes, so templates must not use them.
        if (preg_match('/\son(click|change|submit|input|load|error|focus|blur|keydown|keyup)\s*=/i', $source) || preg_match('/<script(?![^>]*\ssrc=)[^>]*>/i', $source)) {
            $errors[] = "$relative: inline script or event-handler attribute (blocked by the CSP)";
        }
    }
    if (str_starts_with($relative, 'app/Views/')) {
        // Rule: a template may never echo a bare variable with the short echo tag. Text goes through e(), and HTML-producing
        // helpers (icon, field, status_badge ...) escape internally. The only allowed bare variable is the layout's $content.
        preg_match_all('/<\?=\s*(.+?)\s*\?>/s', $source, $m);
        foreach ($m[1] as $expr) {
            if (preg_match('/^\$[A-Za-z_][A-Za-z0-9_\[\]\'"]*$/', $expr) === 1 && $expr !== '$content' && $expr !== '$tile') {
                $errors[] = "$relative: unescaped output: $expr";
            }
        }
    }
}

echo "Checked $count PHP files.\n";
if ($errors) {
    echo implode("\n", $errors) . "\n";
    exit(1);
}
echo "No problems found.\n";
