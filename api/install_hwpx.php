<?php
set_time_limit(120);
header("Content-Type: application/json");
$base="/app/user_data/correct_files/hangul";
if(!is_dir($base))mkdir($base,0755,true);
$r=[];