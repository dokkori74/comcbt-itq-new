<?php
header('Content-Type: application/json; charset=utf-8');
$base='/app/user_data';
foreach([$base,"$base/correct_files","$base/correct_files/excel","$base/correct_files/ppt",
         "$base/correct_files/hangul","$base/uploads","$base/results"] as $d){
    if(!is_dir($d)) @mkdir($d,0755,true);
}
echo json_encode([
  'php_version'          => PHP_VERSION,
  'upload_max_filesize'  => ini_get('upload_max_filesize'),
  'post_max_size'        => ini_get('post_max_size'),
  'user_data_writable'   => is_writable($base)?'YES':'NO',
  'correct_files_exists' => is_dir("$base/correct_files")?'YES':'NO',
  'excel_dir'            => is_dir("$base/correct_files/excel")?'YES':'NO',
  'ppt_dir'              => is_dir("$base/correct_files/ppt")?'YES':'NO',
  'hangul_dir'           => is_dir("$base/correct_files/hangul")?'YES':'NO',
  'zip_available'        => class_exists('ZipArchive')?'YES':'NO',
  'python3'              => trim(shell_exec('which python3 2>/dev/null')?:'NOT FOUND'),
  'excel_grader'         => file_exists('/app/api/excel_grader.py')?'YES':'NO',
  'api_files'            => scandir('/app/api'),
],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);