<?php
set_time_limit(0);
ini_set('memory_limit', '2048M');

$file_name = 'userbase';

generate($file_name);

function generate($file_name){

    $user_id_map = [];
    $game_id_map = [];

    $lines = file($file_name.'.csv');
    foreach($lines as $key => $line){
        if ($key === 0) {
            continue;
        }
        $tmp = explode(',', $line);
        if (!in_array($tmp[0], $user_id_map, true)) {
            $user_id_map[] = $tmp[0];
        }
        if (!in_array($tmp[1], $game_id_map, true)) {
            $game_id_map[] = $tmp[1];
        }
    }
    $user_id_map_r = array_flip($user_id_map);
    $game_id_map_r = array_flip($game_id_map);

    $fp = fopen($file_name.'_matrix.csv', 'w');
    foreach($lines as $key => $line){
        if ($key === 0) {
            fwrite($fp, $line);
            continue;
        }
        $tmp = explode(',', $line);
        fwrite($fp, $user_id_map_r[$tmp[0]].','.$game_id_map_r[$tmp[1]].','.$tmp[2]);
    }
    fclose($fp);

    $fp = fopen($file_name.'_user_map.csv', 'w');
    foreach($user_id_map as $key => $value){
        if ($key === 0) {
            fwrite($fp, 'index,uid'."\n");
        }
        fwrite($fp, $key.','.$value."\n");
    }
    fclose($fp);

    $fp = fopen($file_name.'_game_map.csv', 'w');
    foreach($game_id_map as $key => $value){
        if ($key === 0) {
            fwrite($fp, 'index,game_id'."\n");
        }
        fwrite($fp, $key.','.$value."\n");
    }
    fclose($fp);
}
