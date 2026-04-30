<?php
/**
 * ITQ 채점 통합 API
 * POST /api/grade.php             → 채점
 * POST /api/grade.php?action=upload_correct → 정답파일 업로드
 * GET  /api/grade.php?action=rounds[&subject=excel] → 회차목록
 * GET  /api/grade.php?action=test  → 환경진단
 * GET  /api/grade.php?action=delete_correct → 정답파일 삭제
 */

@ini_set('upload_max_filesize','200M');
@ini_set('post_max_size','210M');
@ini_set('memory_limit','512M');
@ini_set('max_execution_time','300');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
if ($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(204);exit;}

define('DATA_BASE','/app/user_data');
define('ANSWER_DIR',DATA_BASE.'/correct_files/');
define('UPLOAD_DIR',DATA_BASE.'/uploads/');
define('RESULTS_DIR',DATA_BASE.'/results/');
define('SCRIPT_DIR',__DIR__);
define('MAX_UPLOAD',200*1024*1024);
define('ALLOWED_EXT',['xlsx','xls','pptx','ppt','hwpx','hwp']);

// 디렉토리 자동 생성
$base=DATA_BASE;
foreach([$base,ANSWER_DIR,UPLOAD_DIR,RESULTS_DIR,
         ANSWER_DIR.'excel',ANSWER_DIR.'ppt',ANSWER_DIR.'hangul'] as $d){
    if(!is_dir($d)) @mkdir($d,0755,true);
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// ── 라우팅 ──────────────────────────────────────────
if ($action === 'test') { do_test(); exit; }
if ($action === 'rounds') { do_rounds(); exit; }
if ($action === 'upload_correct') { do_upload_correct(); exit; }
if ($action === 'delete_correct') { do_delete_correct(); exit; }

// 기본: 채점
if ($_SERVER['REQUEST_METHOD']!=='POST') json_err('POST만 허용');
do_grade();

// ── 환경 진단 ──────────────────────────────────────
function do_test(){
    $base=DATA_BASE;
    echo json_encode([
        'php_version'         => PHP_VERSION,
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size'       => ini_get('post_max_size'),
        'user_data_writable'  => is_writable($base)?'YES':'NO',
        'correct_excel'       => is_dir(ANSWER_DIR.'excel')?'YES':'NO',
        'correct_ppt'         => is_dir(ANSWER_DIR.'ppt')?'YES':'NO',
        'correct_hangul'      => is_dir(ANSWER_DIR.'hangul')?'YES':'NO',
        'zip_available'       => class_exists('ZipArchive')?'YES':'NO',
        'python3'             => trim(shell_exec('which python3 2>/dev/null')?:'NOT FOUND'),
        'excel_grader'        => file_exists(SCRIPT_DIR.'/excel_grader.py')?'YES':'NO',
        'ppt_grader'          => file_exists(SCRIPT_DIR.'/ppt_grader.py')?'YES':'NO',
        'hangul_grader'       => file_exists(SCRIPT_DIR.'/hangul_grader.py')?'YES':'NO',
        'api_files'           => scandir(SCRIPT_DIR),
    ],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
}

// ── 회차 목록 ──────────────────────────────────────
function do_rounds(){
    $subject=strtolower(trim($_GET['subject']??''));
    if(!in_array($subject,['excel','ppt','hangul'])){
        $all=[];
        foreach(['excel','ppt','hangul'] as $s) $all[$s]=get_rounds($s);
        echo json_encode(['rounds'=>$all],JSON_UNESCAPED_UNICODE);
        return;
    }
    echo json_encode(['subject'=>$subject,'rounds'=>get_rounds($subject)],JSON_UNESCAPED_UNICODE);
}
function get_rounds(string $subject):array{
    $folder=ANSWER_DIR.$subject.'/';
    if(!is_dir($folder)) return [];
    $rounds=[];
    foreach(glob($folder.'*.*')?:[] as $f){
        $name=pathinfo($f,PATHINFO_FILENAME);
        $rounds[]=['id'=>$name,'label'=>round_label($name),'file'=>basename($f)];
    }
    usort($rounds,fn($a,$b)=>strcmp($b['id'],$a['id']));
    return $rounds;
}
function round_label(string $id):string{
    if(preg_match('/^(\d{4})_(\d{2})$/',$id,$m)){
        $mo=['01'=>'1월','02'=>'2월','03'=>'3월','04'=>'4월','05'=>'5월','06'=>'6월',
             '07'=>'7월','08'=>'8월','09'=>'9월','10'=>'10월','11'=>'11월','12'=>'12월'];
        return $m[1].'년 '.($mo[$m[2]]??$m[2]);
    }
    return $id;
}

// ── 정답 파일 업로드 ────────────────────────────────
function do_upload_correct(){
    $subject  = strtolower(trim($_POST['subject']??''));
    $round_id = preg_replace('/[^a-zA-Z0-9_\-]/','',($_POST['round_id']??''));

    if(!in_array($subject,['excel','ppt','hangul'])) json_err("과목 오류: '$subject'");
    if(!preg_match('/^\d{4}_\d{2}$/',$round_id)) json_err('회차 ID 형식 오류 (예: 2026_01)');

    if(!isset($_FILES['file'])){
        $cl=(int)($_SERVER['CONTENT_LENGTH']??0);
        $pm=return_bytes(ini_get('post_max_size'));
        if($cl>0&&$cl>$pm) json_err("파일 너무 큼 ({$cl}B > {$pm}B)");
        json_err('파일 없음');
    }
    $ferr=$_FILES['file']['error'];
    if($ferr!==UPLOAD_ERR_OK){
        $em=[1=>'upload_max_filesize 초과',2=>'post_max_size 초과',3=>'일부만 업로드',4=>'파일 없음',6=>'임시폴더 없음',7=>'쓰기 실패'];
        json_err("업로드 오류 code $ferr: ".($em[$ferr]??'알 수 없음'));
    }
    $f=$_FILES['file'];
    $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
    if(!in_array($ext,ALLOWED_EXT)) json_err("지원하지 않는 형식: .$ext");
    if($f['size']>MAX_UPLOAD) json_err("파일 크기 초과 (".round($f['size']/1024/1024,1)."MB > 200MB)");
    if($f['size']===0) json_err('빈 파일');

    $dir=ANSWER_DIR.$subject.'/';
    if(!is_dir($dir)&&!mkdir($dir,0755,true)) json_err("디렉토리 생성 실패: $dir");
    foreach(ALLOWED_EXT as $e){ $old=$dir.$round_id.'.'.$e; if(file_exists($old)) @unlink($old); }
    $dest=$dir.$round_id.'.'.$ext;
    if(!move_uploaded_file($f['tmp_name'],$dest)) json_err("저장 실패: $dest (writable:".is_writable($dir).")");

    echo json_encode(['ok'=>true,'subject'=>$subject,'round_id'=>$round_id,
        'file'=>basename($dest),'size_mb'=>round($f['size']/1024/1024,2)],JSON_UNESCAPED_UNICODE);
}

// ── 정답 파일 삭제 ──────────────────────────────────
function do_delete_correct(){
    $subject  = strtolower(trim($_POST['subject']??$_GET['subject']??''));
    $round_id = preg_replace('/[^a-zA-Z0-9_\-]/','',($_POST['round_id']??$_GET['round_id']??''));
    if(!in_array($subject,['excel','ppt','hangul'])){echo json_encode(['error'=>'과목 오류']);exit;}
    $deleted=false;
    foreach(ALLOWED_EXT as $ext){
        $p=ANSWER_DIR.$subject.'/'.$round_id.'.'.$ext;
        if(file_exists($p)){@unlink($p);$deleted=true;}
    }
    echo json_encode(['ok'=>$deleted]);
}

// ── 채점 ────────────────────────────────────────────
function do_grade(){
    $subject       = strtolower(trim($_POST['subject']??''));
    $round_id      = preg_replace('/[^a-zA-Z0-9_\-]/','',($_POST['round_id']??''));
    $examinee_id   = preg_replace('/[^A-Za-z0-9_\-]/','',($_POST['examinee_id']??'UNKNOWN'));
    $examinee_name = mb_substr(strip_tags($_POST['examinee_name']??'수험자'),0,20);

    if(!in_array($subject,['excel','ppt','hangul'])) json_err('과목 오류');
    if(!$round_id) json_err('회차를 선택하세요');

    $correct_path=null;
    foreach(ALLOWED_EXT as $ext){
        $p=ANSWER_DIR.$subject.'/'.$round_id.'.'.$ext;
        if(file_exists($p)){$correct_path=$p;break;}
    }
    if(!$correct_path) json_err("정답 파일 없음: [$subject/$round_id] — 관리자 페이지에서 먼저 정답 파일을 올려주세요.");

    if(!isset($_FILES['answer'])||$_FILES['answer']['error']!==UPLOAD_ERR_OK)
        json_err('답안 파일 업로드 오류: code '.($_FILES['answer']['error']??-1));
    $file=$_FILES['answer'];
    if($file['size']>20*1024*1024) json_err('답안 파일 크기 초과(최대 20MB)');
    $ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
    if(!in_array($ext,ALLOWED_EXT)) json_err("지원하지 않는 형식: .$ext");
    $tmp_path=UPLOAD_DIR.uniqid("ans_{$subject}_",true).".$ext";
    if(!move_uploaded_file($file['tmp_name'],$tmp_path)) json_err('답안 파일 저장 실패');

    $scripts=['excel'=>'excel_grader.py','ppt'=>'ppt_grader.py','hangul'=>'hangul_grader.py'];
    $script=SCRIPT_DIR.'/'.$scripts[$subject];
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
        json_err('채점 엔진 없음: python='.($python?:'없음').' script='.$script);
    }

    $result['examinee_id']  =$examinee_id;
    $result['examinee_name']=$examinee_name;
    $result['round_id']     =$round_id;
    $result['graded_at']    =date('Y-m-d H:i:s');
    $result['filename']     =$file['name'];

    $save=RESULTS_DIR."{$examinee_id}_{$subject}_{$round_id}_".date('Ymd_His').'.json';
    file_put_contents($save,json_encode($result,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
    echo json_encode($result,JSON_UNESCAPED_UNICODE);
}

function json_err(string $msg):void{
    http_response_code(400);
    echo json_encode(['error'=>$msg],JSON_UNESCAPED_UNICODE);
    exit;
}
function return_bytes(string $v):int{
    if(!$v) return 0;
    $l=strtolower($v[strlen($v)-1]);$n=(int)$v;
    return match($l){'g'=>$n*1073741824,'m'=>$n*1048576,'k'=>$n*1024,default=>$n};
}
