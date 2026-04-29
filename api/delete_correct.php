<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

define('DATA_BASE','/app/user_data');
$ANSWER_DIR=DATA_BASE.'/correct_files/';

$subject=strtolower(trim($_POST['subject']??''));
$round_id=preg_replace('/[^a-zA-Z0-9_\-]/','',($_POST['round_id']??''));

if(!in_array($subject,['excel','ppt','hangul'])){echo json_encode(['error'=>'과목 오류']);exit;}
if(!$round_id){echo json_encode(['error'=>'회차 ID 없음']);exit;}

$deleted=false;
foreach(['xlsx','xls','pptx','ppt','hwpx','hwp'] as $ext){
    $path=$ANSWER_DIR.$subject.'/'.$round_id.'.'.$ext;
    if(file_exists($path)){@unlink($path);$deleted=true;}
}
echo json_encode(['ok'=>$deleted]);