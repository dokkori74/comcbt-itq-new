<?php
header('Content-Type: application/json; charset=utf-8');

// 디렉토리 자동 생성 시도
$base = '/app/user_data';
$dirs = ["$base/correct_files/excel", "$base/correct_files/ppt",
         "$base/correct_files/hangul", "$base/uploads", "$base/results"];
$mkdir_results = [];
foreach ([$base, ...$dirs] as $d) {
    if (!is_dir($d)) {
        $ok = @mkdir($d, 0755, true);
        $mkdir_results[$d] = $ok ? 'created' : 'failed';
    } else {
        $mkdir_results[$d] = 'exists';
    }
}

echo json_encode([
    'php_version'           => PHP_VERSION,
    'upload_max_filesize'   => ini_get('upload_max_filesize'),
    'post_max_size'         => ini_get('post_max_size'),
    'memory_limit'          => ini_get('memory_limit'),
    'max_execution_time'    => ini_get('max_execution_time'),
    'user_data_exists'      => is_dir($base) ? 'YES' : 'NO',
    'user_data_writable'    => is_writable($base) ? 'YES' : 'NO',
    'correct_excel'         => is_dir("$base/correct_files/excel")  ? 'YES' : 'NO',
    'correct_ppt'           => is_dir("$base/correct_files/ppt")    ? 'YES' : 'NO',
    'correct_hangul'        => is_dir("$base/correct_files/hangul") ? 'YES' : 'NO',
    'uploads_dir'           => is_dir("$base/uploads")  ? 'YES' : 'NO',
    'results_dir'           => is_dir("$base/results")  ? 'YES' : 'NO',
    'zip_available'         => class_exists('ZipArchive') ? 'YES' : 'NO',
    'python3'               => trim(shell_exec('which python3 2>/dev/null') ?: 'NOT FOUND'),
    'excel_grader'          => file_exists('/app/api/excel_grader.py')  ? 'YES' : 'NO',
    'ppt_grader'            => file_exists('/app/api/ppt_grader.py')    ? 'YES' : 'NO',
    'hangul_grader'         => file_exists('/app/api/hangul_grader.py') ? 'YES' : 'NO',
    'api_files'             => scandir('/app/api'),
    'mkdir_results'         => $mkdir_results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
