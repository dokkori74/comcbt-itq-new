<?php
// 서버 시작 시 /app/user_data 하위 디렉토리 자동 생성
$base = '/app/user_data';
foreach ([$base, "$base/correct_files", "$base/correct_files/excel",
          "$base/correct_files/ppt", "$base/correct_files/hangul",
          "$base/uploads", "$base/results"] as $d) {
    if (!is_dir($d)) @mkdir($d, 0755, true);
}
header('Location: /index.html');
exit;