<?php
// 업로드 제한 최대로 확장
@ini_set('upload_max_filesize', '200M');
@ini_set('post_max_size',        '210M');
@ini_set('memory_limit',         '512M');
@ini_set('max_execution_time',   '300');
@ini_set('max_input_time',       '300');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

define('DATA_BASE', '/app/user_data');
$ANSWER_DIR = DATA_BASE . '/correct_files/';
$MAX_SIZE   = 200 * 1024 * 1024; // 200MB (동영상 포함 pptx 대응)
$ALLOWED    = ['xlsx','xls','pptx','ppt','hwpx','hwp'];

// 디렉토리 자동 생성
$base = DATA_BASE;
foreach ([$base, "$base/correct_files", "$base/correct_files/excel",
          "$base/correct_files/ppt", "$base/correct_files/hangul",
          "$base/uploads", "$base/results"] as $d) {
    if (!is_dir($d)) @mkdir($d, 0755, true);
}

$subject  = strtolower(trim($_POST['subject'] ?? ''));
$round_id = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['round_id'] ?? '');

if (!in_array($subject, ['excel','ppt','hangul']))
    err("과목 오류: '$subject'");
if (!preg_match('/^\d{4}_\d{2}$/', $round_id))
    err('회차 ID 형식 오류 (예: 2026_01)');

// $_FILES 가 비어있는 경우 → post_max_size 초과
if (!isset($_FILES['file'])) {
    $cl = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    $pm = return_bytes(ini_get('post_max_size'));
    if ($cl > 0 && $cl > $pm)
        err("파일이 너무 큽니다.\n서버 POST 제한: " . ini_get('post_max_size') .
            " / 파일 크기: " . round($cl/1024/1024, 1) . "MB\n" .
            "💡 동영상이 포함된 pptx의 경우 동영상을 제거하거나 압축 후 등록하세요.");
    err('업로드된 파일이 없습니다. ($_FILES 비어있음)');
}

$ferr = $_FILES['file']['error'];
if ($ferr !== UPLOAD_ERR_OK) {
    $em = [
        1 => "파일이 너무 큽니다 (upload_max_filesize=" . ini_get('upload_max_filesize') . ")\n💡 동영상이 포함된 pptx는 PowerPoint에서 동영상을 제거하거나 '미디어 압축'을 실행하세요.",
        2 => "요청 전체가 너무 큽니다 (post_max_size=" . ini_get('post_max_size') . ")",
        3 => "파일이 일부만 업로드됐습니다. 다시 시도하세요.",
        4 => "파일이 선택되지 않았습니다.",
        6 => "서버 임시 폴더 없음",
        7 => "디스크 쓰기 실패",
        8 => "PHP 확장이 업로드를 중단했습니다.",
    ];
    err("업로드 오류 (code $ferr):\n" . ($em[$ferr] ?? '알 수 없는 오류'));
}

$f   = $_FILES['file'];
$ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));

if (!in_array($ext, $ALLOWED))
    err("지원하지 않는 형식: .$ext\n지원 형식: " . implode(', .', $ALLOWED));
if ($f['size'] > $MAX_SIZE)
    err("파일 크기 초과 (최대 200MB / 현재 " . round($f['size']/1024/1024,1) . "MB)\n💡 동영상 포함 시 PowerPoint → 미디어 압축 → 인터넷 품질로 변환 후 재시도");
if ($f['size'] === 0)
    err('빈 파일입니다.');

$dir = $ANSWER_DIR . $subject . '/';
if (!is_dir($dir) && !mkdir($dir, 0755, true))
    err("디렉토리 생성 실패: $dir\n쓰기 가능 여부: " . (is_writable(DATA_BASE) ? 'YES' : 'NO'));

foreach ($ALLOWED as $e) {
    $old = $dir . $round_id . '.' . $e;
    if (file_exists($old)) @unlink($old);
}

$dest = $dir . $round_id . '.' . $ext;
if (!move_uploaded_file($f['tmp_name'], $dest))
    err("파일 저장 실패.\n경로: $dest\n디렉토리 쓰기: " . (is_writable($dir) ? 'YES' : 'NO'));

echo json_encode([
    'ok'       => true,
    'subject'  => $subject,
    'round_id' => $round_id,
    'file'     => basename($dest),
    'size_mb'  => round($f['size']/1024/1024, 2),
], JSON_UNESCAPED_UNICODE);

function err(string $m): void {
    http_response_code(400);
    echo json_encode(['error' => $m], JSON_UNESCAPED_UNICODE);
    exit;
}
function return_bytes(string $val): int {
    if (empty($val)) return 0;
    $last = strtolower($val[strlen($val)-1]);
    $num  = (int)$val;
    return match($last) { 'g'=>$num*1073741824, 'm'=>$num*1048576, 'k'=>$num*1024, default=>$num };
}
