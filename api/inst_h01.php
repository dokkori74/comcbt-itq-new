<?php
$dest = '/app/user_data/correct_files/hangul/2026_01.hwpx';
$dir  = dirname($dest);
if (!is_dir($dir)) mkdir($dir, 0755, true);
$b64 = file_get_contents(__DIR__ . '/data_h01.b64');
$bytes = base64_decode(trim($b64));
if (file_put_contents($dest, $bytes) !== false) {
    echo json_encode(['ok'=>true,'file'=>'2026_01.hwpx','size'=>strlen($bytes)]);
} else {
    http_response_code(500);
    echo json_encode(['error'=>'write failed','dir_writable'=>is_writable($dir)?'yes':'no']);
}