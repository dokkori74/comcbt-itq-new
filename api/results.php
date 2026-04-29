<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

define('DATA_BASE','/app/user_data');
$RESULTS_DIR=DATA_BASE.'/results/';

$export=isset($_GET['export']);
$sf=$_GET['subject']??'';

$files=is_dir($RESULTS_DIR)?(glob($RESULTS_DIR.'*.json')?:[]):[];
$all=[];
foreach($files as $f){
    $data=json_decode(file_get_contents($f),true);
    if(!$data) continue;
    if(isset($data['results'])){
        foreach($data['results'] as $r)
            $all[]=array_merge($r,['subject'=>$data['subject']??'','round'=>$data['round_id']??'','graded_at'=>$data['graded_at']??'']);
        continue;
    }
    $all[]=['id'=>$data['examinee_id']??'','name'=>$data['examinee_name']??'-',
            'subject'=>$data['subject']??'','round'=>$data['round_id']??'',
            'score'=>$data['score']??0,'pass'=>$data['pass']??false,
            'graded_at'=>$data['graded_at']??'','items'=>$data['items']??[]];
}
if($sf) $all=array_values(array_filter($all,fn($r)=>$r['subject']===$sf));

if($export){
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="itq_'.date('Ymd').'.csv"');
    $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
    fputcsv($out,['수험번호','이름','과목','회차','점수','합격','채점일시']);
    foreach($all as $r) fputcsv($out,[$r['id']??'',$r['name']??'-',
        ['excel'=>'ITQ Excel','ppt'=>'ITQ PPT','hangul'=>'ITQ 한글'][$r['subject']??'']??'',
        $r['round']??'',$r['score']??0,($r['pass']??false)?'합격':'불합격',$r['graded_at']??'']);
    fclose($out);exit;
}
echo json_encode(['count'=>count($all),'results'=>$all],JSON_UNESCAPED_UNICODE);