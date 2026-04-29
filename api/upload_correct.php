<?php
// 런타임에 PHP 업로드 제한 확장 시도
@ini_set('upload_max_filesize', '50M');
@ini_set('post_max_size', '55M');
@ini_set('memory_limit', '256M');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(204);exit;}

define('DATA_BASE','/app/user_data');
$ANSWER_DIR=DATA_BASE.'/correct_files/';
$MAX_SIZE=50*1024*1024;
$ALLOWED=['xlsx','xls','pptx','ppt','hwpx','hwp'];

// 디렉토리 자동 생성
foreach(['excel','ppt','hangul'] as $s){
    $d=$ANSWER_DIR.$s;
    if(!is_dir($d)) @mkdir($d,0755,true);
}

$subject=strtolower(trim($_POST['subject']??''));
$round_id=preg_replace('/[^a-zA-Z0-9_\-]/','',($_POST['round_id']??''));

if(!in_array($subject,['excel','ppt','hangul']))
    err("과목 오류: '$subject'");
if(!preg_match('/^\d{4}_\d{2}$/',$round_id))
    err('회차 ID 형식 오류 (예: 2026_01)');

if(!isset($_FILES['file'])){
    $cl=intval($_SERVER['CONTENT_LENGTH']??0);
    $pm=return_bytes(ini_get('post_max_size'));
    if($cl>0&&$cl>$pm)
        err('파일이 너무 큽니다. 서버 post 제한: '.ini_get('post_max_size').' / 파일: '.round($cl/1024/1024,1).'MB');
    err('업로드된 파일이 없습니다. (FILES 비어있음)');
}

$ferr=$_FILES['file']['error'];
if($ferr!==UPLOAD_ERR_OK){
    $em=[
        0=>'성공',
        1=>'파일이 너무 큽니다 (upload_max_filesize='.ini_get('upload_max_filesize').'). pptx 파일 내 이미지를 압축하세요.',
        2=>'요청이 너무 큽니다 (post_max_size='.ini_get('post_max_size').')',
        3=>'파일이 일부만 업로드됨',4=>'파일 없음',6=>'임시 폴더 없음',7=>'디스크 쓰기 실패',8=>'PHP 확장이 업로드 중단'
    ];
    err('업로드 오류 (code '.$ferr.'): '.($em[$ferr]??'알 수 없음'));
}

$f=$_FILES['file'];
$ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
if(!in_array($ext,$ALLOWED)) err("지원하지 않는 형식: .$ext");
if($f['size']>$MAX_SIZE) err('파일 크기 초과 (최대 50MB)');
if($f['size']===0) err('빈 파일입니다');

$dir=$ANSWER_DIR.$subject.'/';
if(!is_dir($dir)) mkdir($dir,0755,true);
foreach($ALLOWED as $e){ $old=$dir.$round_id.'.'.$e; if(file_exists($old)) @unlink($old); }

$dest=$dir.$round_id.'.'.$ext;
if(!move_uploaded_file($f['tmp_name'],$dest))
    err('파일 저장 실패. 경로: '.$dest.' / 쓰기가능: '.(is_writable($dir)?'YES':'NO'));

echo json_encode(['ok'=>true,'subject'=>$subject,'round_id'=>$round_id,
    'file'=>basename($dest),'size'=>$f['size']],JSON_UNESCAPED_UNICODE);

function err(string $m):void{
    http_response_code(400);
    echo json_encode(['error'=>$m],JSON_UNESCAPED_UNICODE);
    exit;
}
function return_bytes(string $val):int{
    if(empty($val)) return 0;
    $last=strtolower($val[strlen($val)-1]);
    $num=(int)$val;
    return match($last){'g'=>$num*1073741824,'m'=>$num*1048576,'k'=>$num*1024,default=>$num};
}