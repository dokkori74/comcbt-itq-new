<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
define('DATA_BASE', '/app/user_data');
$RESULTS_DIR = DATA_BASE . '/results/';
$sf = $_GET['subject'] ?? '';
$files = is_dir($RESULTS_DIR) ? (glob($RESULTS_DIR.'*.json') ?: []) : [];
$all = [];
foreach ($files as $f) {
    $data = json_decode(file_get_contents($f), true);
    if (!$data) continue;
    $all[] = [
        'id'         => $data['examinee_id']   ?? '',
        'name'       => $data['examinee_name'] ?? '-',
        'subject'    => $data['subject']        ?? '',
        'round'      => $data['round_id']       ?? '',
        'score'      => $data['score']          ?? 0,
        'pass'       => $data['pass']           ?? false,
        'graded_at'  => $data['graded_at']      ?? '',
    ];
}
if ($sf) $all = array_values(array_filter($all, fn($r) => $r['subject'] === $sf));
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="itq_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['수험번호','이름','과목','회차','점수','합격','채점일시']);
    $sl = ['excel'=>'ITQ Excel','ppt'=>'ITQ PPT','hangul'=>'ITQ 한글'];
    foreach ($all as $r)
        fputcsv($out, [$r['id'], $r['name'], $sl[$r['subject']] ?? $r['subject'],
                       $r['round'], $r['score'], $r['pass']?'합격':'불합격', $r['graded_at']]);
    fclose($out);
    exit;
}
echo json_encode(['count' => count($all), 'results' => $all], JSON_UNESCAPED_UNICODE);
