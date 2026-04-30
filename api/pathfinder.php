<?php
header('Content-Type: application/json; charset=utf-8');

// 가능한 모든 경로 테스트
$candidates = [
    '/app/user_data',
    '/app/data',
    '/app/storage',
    '/tmp/user_data',
    '/var/tmp/user_data',
    '/app/api/../user_data',
    '/app/uploads',
    '/app/correct_files',
    '/tmp',
    '/var/tmp',
];

$results = [];
foreach ($candidates as $path) {
    $real = realpath($path) ?: $path;
    $exists   = is_dir($path);
    $writable = $exists && is_writable($path);
    // 쓰기 시도
    $write_ok = false;
    if ($exists && $writable) {
        $tf = $path . '/.write_test_' . uniqid();
        $write_ok = @file_put_contents($tf, 'test') !== false;
        if ($write_ok) @unlink($tf);
    } else if (!$exists) {
        $write_ok = @mkdir($path, 0755, true);
        if ($write_ok) {
            $tf = $path . '/.write_test';
            $write_ok = @file_put_contents($tf, 'test') !== false;
            if ($write_ok) @unlink($tf);
            @rmdir($path);
        }
    }
    $results[$path] = [
        'exists'   => $exists,
        'writable' => $writable,
        'write_ok' => $write_ok,
        'realpath' => $real,
    ];
}

// /app 전체 구조 확인
$app_dirs = [];
if (is_dir('/app')) {
    foreach (scandir('/app') as $item) {
        if ($item === '.' || $item === '..') continue;
        $full = '/app/' . $item;
        $app_dirs[$item] = [
            'is_dir'   => is_dir($full),
            'writable' => is_writable($full),
        ];
    }
}

echo json_encode([
    'path_tests' => $results,
    'app_structure' => $app_dirs,
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size'       => ini_get('post_max_size'),
    'php_version'         => PHP_VERSION,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
