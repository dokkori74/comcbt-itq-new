<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(204);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST'){json_err('POST만 허용');}

define('DATA_BASE','/app/user_data');
$ANSWER_DIR  =DATA_BASE.'/correct_files/';
$UPLOAD_DIR  =DATA_BASE.'/uploads/';
$RESULTS_DIR =DATA_BASE.'/results/';
$SCRIPT_DIR  =__DIR__;
$MAX_SIZE    =20*1024*1024;

// 디렉토리 자동 생성
foreach([$UPLOAD_DIR,$RESULTS_DIR] as $d) if(!is_dir($d)) @mkdir($d,0755,true);
foreach(['excel','ppt','hangul'] as $s) if(!is_dir($ANSWER_DIR.$s)) @mkdir($ANSWER_DIR.$s,0755,true);

$subject       =strtolower(trim($_POST['subject']??''));
$round_id      =preg_replace('/[^a-zA-Z0-9_\-]/','',($_POST['round_id']??''));
$examinee_id   =preg_replace('/[^A-Za-z0-9_\-]/','',($_POST['examinee_id']??'UNKNOWN'));
$examinee_name =mb_substr(strip_tags($_POST['examinee_name']??'수험자'),0,20);

if(!in_array($subject,['excel','ppt','hangul'])) json_err('과목 오류');
if(!$round_id) json_err('회차를 선택하세요');

// 정답 파일 찾기
$correct_path=null;
foreach(['xlsx','xls','pptx','ppt','hwpx','hwp'] as $ext){
    $p="{$ANSWER_DIR}{$subject}/{$round_id}.{$ext}";
    if(file_exists($p)){$correct_path=$p;break;}
}
if(!$correct_path) json_err("정답 파일 없음: [{$subject}/{$round_id}] — 관리자 페이지에서 먼저 정답 파일을 올려주세요.");

// 답안 파일 업로드
if(!isset($_FILES['answer'])||$_FILES['answer']['error']!==UPLOAD_ERR_OK)
    json_err('답안 파일 업로드 오류: code '.($_FILES['answer']['error']??-1));
$file=$_FILES['answer'];
if($file['size']>$MAX_SIZE) json_err('파일 크기 초과(최대 20MB)');
$ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
if(!in_array($ext,['xlsx','xls','pptx','ppt','hwp','hwpx'])) json_err("지원하지 않는 형식: .$ext");
$tmp_path=$UPLOAD_DIR.uniqid("ans_{$subject}_",true).".$ext";
if(!move_uploaded_file($file['tmp_name'],$tmp_path)) json_err('파일 저장 실패');

// Python 채점 엔진
$scripts=['excel'=>'excel_grader.py','ppt'=>'ppt_grader.py','hangul'=>'hangul_grader.py'];
$script=$SCRIPT_DIR.'/'.$scripts[$subject];
$python=trim(shell_exec('which python3 2>/dev/null')?:'');

if($python&&file_exists($script)){
    $cmd=sprintf('%s %s %s %s 2>&1',
        escapeshellcmd($python),
        escapeshellarg($script),
        escapeshellarg($tmp_path),
        escapeshellarg($correct_path)
    );
    $output=shell_exec($cmd);
    @unlink($tmp_path);
    $json_start=strpos($output,'{');
    if($json_start===false) json_err('채점 출력 오류: '.substr($output,0,400));
    $result=json_decode(substr($output,$json_start),true);
    if(!$result) json_err('JSON 파싱 오류: '.substr($output,0,300));
    if(isset($result['error'])) json_err($result['error']);
}else{
    @unlink($tmp_path);
    json_err('채점 엔진 없음: '.$script);
}

$result['examinee_id']  =$examinee_id;
$result['examinee_name']=$examinee_name;
$result['round_id']     =$round_id;
$result['graded_at']    =date('Y-m-d H:i:s');
$result['filename']     =$file['name'];

$save=$RESULTS_DIR."{$examinee_id}_{$subject}_{$round_id}_".date('Ymd_His').'.json';
file_put_contents($save,json_encode($result,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
echo json_encode($result,JSON_UNESCAPED_UNICODE);

function json_err(string $msg):void{
    http_response_code(400);
    echo json_encode(['error'=>$msg],JSON_UNESCAPED_UNICODE);
    exit;
}