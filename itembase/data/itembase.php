<?php
ini_set('memory_limit', '2048M');


$game_list = get_game_id_list();
$pov_list = get_pov_id_list();
$povgroup_list = get_pov_groups_list();

$cols = ['game'];
foreach ($pov_list as $pov) {
    $cols[] = (string)$pov;
}
$results = [];
$results[] = $cols;
foreach ($game_list as $game) {
    $tmp = [];
    foreach ($cols as $col) {
        if ($col === 'game') {
            $tmp[] = $game;
        } else {
            $tmp[] = $povgroup_list[$game][$col] ?? 0;
        }
    }
    $results[] = $tmp;
}
output_csv($results);



// gamelist
/*
select id,gamename,sellday,brandname,median,stdev,count2 as score_counts,model,erogame from gamelist
*/
/*
curl -o gamelist.html 'https://erogamescape.dyndns.org/~ap2/ero/toukei_kaiseki/sql_for_erogamer_form.php' \
  --data-raw 'sql=select%20id%2Cgamename%2Csellday%2Cbrandname%2Cmedian%2Cstdev%2Ccount2%20as%20score_counts%2Cmodel%2Cerogame%20from%20gamelist' \
  --compressed
*/
function get_game_id_list () {
    $filename = 'gamelist.html';
    $exr_gamelist = '/<tr><td>([0-9]*)<\/td><td>(.*)<\/td><td>([0-9]{4}-[0-9]{2}-[0-9]{2})<\/td><td>([0-9]*)<\/td><td>([0-9]*)<\/td><td>([0-9]*)<\/td><td>([0-9]*)<\/td><td>([A-Za-z0-9]*)<\/td><td>(.{1})<\/td><\/tr>/';
    $lines = file($filename);
    $results = [];
    foreach($lines as $line){
        $matches = [];
        if(preg_match($exr_gamelist, $line, $matches)){
            $results[] = $matches[1];
        }
    }
    return $results;
}


// povlist
/*
SELECT id,title from povlist
*/
/*
curl -o povlist.html 'https://erogamescape.dyndns.org/~ap2/ero/toukei_kaiseki/sql_for_erogamer_form.php' \
  --data-raw 'sql=SELECT+id%2C+title%0D%0A++FROM+povlist' \
  --compressed
*/
function get_pov_id_list () {
    $filename = 'povlist.html';
    $exr_povlist = '/<tr><td>([0-9]*)<\/td><td>(.*)<\/td><\/tr>/';
    $lines = file($filename);
    $results = [];
    foreach($lines as $line){
        $matches = [];
        if(preg_match($exr_povlist, $line, $matches)){
            $results[] = $matches[1];
        }
    }
    return $results;
}

// povgroups
/*
SELECT id, pov, game, rank from povgroups
*/
/*
curl -o povgroups.html 'https://erogamescape.dyndns.org/~ap2/ero/toukei_kaiseki/sql_for_erogamer_form.php' \
  --data-raw 'sql=SELECT%20id%2C%20pov%2C%20game%2C%20rank%20from%20povgroups' \
  --compressed
*/

function get_pov_groups_list () {
    $filename = 'povgroups.html';
    $exr_povgroups = '/<tr><td>([0-9]*)<\/td><td>([0-9]*)<\/td><td>([0-9]*)<\/td><td>(.{1})<\/td><\/tr>/';
    $lines = file($filename);
    $results = [];
    foreach($lines as $line){
        $matches = [];
        $tmp = [];
        if(preg_match($exr_povgroups, $line, $matches)){
            switch($matches[4]){
                case 'A':
                    $results[$matches[3]][$matches[2]] = 1;
                    break;
                case 'B':
                    break;
                case 'C':
                    break;
                default:
                    break;
            }
        }
    }
    return $results;
}


function output_csv($array){
    $fp = fopen('itembase.csv', 'w');
    foreach($array as $tmp){
        fwrite($fp, implode(',', $tmp).PHP_EOL);
    }
    fclose($fp);
}
