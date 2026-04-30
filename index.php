<?php
// /app/user_data 는 쓰기 가능 — 하위 디렉토리 자동 생성
$base = '/app/user_data';
foreach ([
    "$base/correct_files",
    "$base/correct_files/excel",
    "$base/correct_files/ppt",
    "$base/correct_files/hangul",
    "$base/uploads",
    "$base/results",
] as $d) {
    if (!is_dir($d)) @mkdir($d, 0755, true);
}
header('Location: /index.html');
exit;
