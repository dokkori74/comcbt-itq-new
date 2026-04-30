<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
define('DATA_BASE', '/app/user_data');
$s = strtolower(trim($_POST['subject'] ?? ''));
$r = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['round_id'] ?? '');
if (!in_array($s, ['excel','ppt','hangul'])) { echo json_encode(['error'=>'과목 오류']); exit; }
if (!$r) { echo json_encode(['error'=>'회차 ID 없음']); exit; }
$deleted = false;
foreach (['xlsx','xls','pptx','ppt','hwpx','hwp'] as $e) {
    $p = DATA_BASE . '/correct_files/' . $s . '/' . $r . '.' . $e;
    if (file_exists($p)) { @unlink($p); $deleted = true; }
}
echo json_encode(['ok' => $deleted]);
