<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
define('DATA_BASE', '/app/user_data');
$ANSWER_DIR = DATA_BASE . '/correct_files/';
$s = strtolower(trim($_GET['subject'] ?? ''));
if (!in_array($s, ['excel','ppt','hangul'])) {
    $all = [];
    foreach (['excel','ppt','hangul'] as $k) $all[$k] = rounds_for($ANSWER_DIR, $k);
    echo json_encode(['rounds' => $all], JSON_UNESCAPED_UNICODE);
    exit;
}
echo json_encode(['subject'=>$s,'rounds'=>rounds_for($ANSWER_DIR,$s)], JSON_UNESCAPED_UNICODE);
function rounds_for($dir, $s) {
    $folder = $dir.$s.'/'; if (!is_dir($folder)) return [];
    $list = [];
    foreach (glob($folder.'*.*') as $f) {
        $n = pathinfo($f, PATHINFO_FILENAME);
        $list[] = ['id'=>$n, 'label'=>rlabel($n), 'file'=>basename($f)];
    }
    usort($list, fn($a,$b)=>strcmp($b['id'],$a['id']));
    return $list;
}
function rlabel($id) {
    if (preg_match('/^(\d{4})_(\d{2})$/', $id, $m)) {
        $mo = ['01'=>'1월','02'=>'2월','03'=>'3월','04'=>'4월','05'=>'5월','06'=>'6월',
               '07'=>'7월','08'=>'8월','09'=>'9월','10'=>'10월','11'=>'11월','12'=>'12월'];
        return $m[1].'년 '.($mo[$m[2]]??$m[2]);
    }
    return $id;
}
