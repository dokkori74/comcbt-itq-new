<?php
header('Content-Type: application/json; charset=utf-8');
$results=[];

// hwpx 파일들은 아래 URL로 접속하면 /app/user_data/correct_files/hangul/ 에 저장됨
// 사용법: 브라우저에서 https://comcbt-itq-new.mycafe24.ai/api/install_hangul.php 접속

$dir = '/app/user_data/correct_files/hangul';
if (!is_dir($dir)) @mkdir($dir, 0755, true);
$writable = is_writable($dir) ? 'YES' : 'NO';

echo json_encode([
    'message' => '한글 정답 파일 설치 스크립트입니다. 실제 파일은 별도 PHP 스크립트로 분리됩니다.',
    'dir' => $dir,
    'writable' => $writable,
], JSON_UNESCAPED_UNICODE);