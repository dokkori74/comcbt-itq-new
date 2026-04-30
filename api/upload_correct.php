<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

define('DATA_BASE', '/app/user_data');
$ALLOWED = ['xlsx','xls','pptx','ppt','hwpx','hwp'];

// 디렉토리 자동 생성
foreach (['correct_files','correct_files/excel','correct_files/ppt','correct_files/hangul','uploads','results'] as $sub) {
    $d = DATA_BASE . '/' . $sub;
    if (!is_dir($d)) @mkdir($d, 0755, true);
}

$subject  = strtolower(trim($_POST['subject'] ?? ''));
$round_id = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['round_id'] ?? '');

if (!in_array($subject, ['excel','ppt','hangul'])) err("과목 오류: '$subject'");
if (!preg_match('/^\d{4}_\d{2}$/', $round_id))     err('회차 ID 형식 오류 (예: 2026_01)');

// FILES 없으면 post_max_size 초과
if (!isset($_FILES['file'])) {
    $cl = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    err($cl > 0
        ? "파일이 너무 큽니다 ({$cl}B). 서버 제한: " . ini_get('post_max_size')
        : '업로드 파일 없음');
}

$ferr = $_FILES['file']['error'];
if ($ferr !== UPLOAD_ERR_OK) {
    $em = [1=>'파일이 너무 큽니다 (upload_max_filesize='.ini_get('upload_max_filesize').')',
           2=>'요청 크기 초과', 3=>'일부만 업로드', 4=>'파일 없음', 6=>'임시폴더 없음', 7=>'디스크 쓰기 실패'];
    err('업로드 오류 (code '.$ferr.'): '.($em[$ferr] ?? '알 수 없음'));
}

$f   = $_FILES['file'];
$ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $ALLOWED)) err("지원하지 않는 형식: .$ext");
if ($f['size'] === 0) err('빈 파일입니다.');

$dir  = DATA_BASE . '/correct_files/' . $subject . '/';
if (!is_dir($dir) && !mkdir($dir, 0755, true)) err("디렉토리 생성 실패: $dir");

// 기존 파일 삭제 후 저장
foreach ($ALLOWED as $e) { $old = $dir.$round_id.'.'.$e; if (file_exists($old)) @unlink($old); }

$dest = $dir . $round_id . '.' . $ext;
if (!move_uploaded_file($f['tmp_name'], $dest)) err("파일 저장 실패: $dest (writable=" . (is_writable($dir)?'Y':'N') . ")");

echo json_encode(['ok'=>true,'subject'=>$subject,'round_id'=>$round_id,
    'file'=>basename($dest),'size_mb'=>round($f['size']/1024/1024,2)], JSON_UNESCAPED_UNICODE);

function err(string $m): void {
    http_response_code(400);
    echo json_encode(['error'=>$m], JSON_UNESCAPED_UNICODE);
    exit;
}
