<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

define('DATA_BASE','/app/user_data');
$ANSWER_DIR=DATA_BASE.'/correct_files/';

$subject=strtolower(trim($_GET['subject']??''));
if(!in_array($subject,['excel','ppt','hangul'])){
    $all=[];
    foreach(['excel','ppt','hangul'] as $s) $all[$s]=get_rounds($ANSWER_DIR,$s);
    echo json_encode(['rounds'=>$all],JSON_UNESCAPED_UNICODE);
    exit;
}
echo json_encode(['subject'=>$subject,'rounds'=>get_rounds($ANSWER_DIR,$subject)],JSON_UNESCAPED_UNICODE);

function get_rounds(string $dir,string $subject):array{
    $folder=$dir.$subject.'/';
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
