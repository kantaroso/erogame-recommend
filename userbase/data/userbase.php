<?php
set_time_limit(0);
ini_set('memory_limit', '2048M');

$cmd = $argv[1];

if ($cmd === 'dl') {
  print('start download_userreview()'.PHP_EOL);
  download_userreview();
} else if ($cmd === 'make') {
  print('start make_userreview()'.PHP_EOL);
  make_userreview();
} else if ($cmd === 'shape') {
  print('start dispersion_correction_data()'.PHP_EOL);
  dispersion_correction_data();
} else if ($cmd === 'reindex') {
  print('start reindex()'.PHP_EOL);
  reindex();
}

// gamelist
/*
select id,gamename,sellday,brandname,median,stdev,count2 as score_counts,model,erogame from gamelist
*/
/*
curl -o gamelist.html 'https://erogamescape.dyndns.org/~ap2/ero/toukei_kaiseki/sql_for_erogamer_form.php' \
  --data-raw 'sql=select%20id%2Cgamename%2Csellday%2Cbrandname%2Cmedian%2Cstdev%2Ccount2%20as%20score_counts%2Cmodel%2Cerogame%20from%20gamelist' \
  --compressed
*/
function get_game_id_list() {
  $filename = 'gamelist.html';
  $exr_gamelist = '/<tr><td>([0-9]+)<\/td><td>(.*)<\/td><td>([0-9]{4}-[0-9]{2}-[0-9]{2})<\/td><td>([0-9]*)<\/td><td>([0-9]*)<\/td><td>([0-9]*)<\/td><td>([0-9]*)<\/td><td>([A-Za-z0-9]*)<\/td><td>(.{1})<\/td><\/tr>/';
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
function get_game_id_list_chunked() {
  $results = get_game_id_list();
  $results_chunked = array_chunk($results, 100);
  return $results_chunked;
}

// userreview
/*
select game, uid, tokuten from userreview where game in ();
*/
/*
curl -o ___FILE_NAME___ 'https://erogamescape.dyndns.org/~ap2/ero/toukei_kaiseki/sql_for_erogamer_form.php' \
--data-raw 'sql=select+game%2C+uid%2C+tokuten+from+userreview+where+game+in+%28___IDS___%29%3B' \
--compressed
*/
function get_userreview_list(int $key = 0) {
  $filename = "userreview_${key}.html";
  $exr_gamelist = '/<tr><td>([0-9]*)<\/td><td>(.*)<\/td><td>([0-9]*)<\/td><\/tr>/';
  if (!file_exists($filename)) {
    print("empty userreview_${key}.html".PHP_EOL);
    return [];
  }
  $lines = file($filename);
  $results = [];
  foreach($lines as $line){
      $matches = [];
      $tmp = [];
      if(preg_match($exr_gamelist, $line, $matches)){
          $tmp['uid'] = str_replace(',', '.', $matches[2]);
          $tmp['game_id'] = $matches[1];
          $tmp['tokuen'] = $matches[3];
          if (!empty($tmp['tokuen'])) {
            $results[] = $tmp;
          }
      }
  }
  return $results;
}

/*
* curl でデータを取得する
* データ量が多いので分割でダウンロードする（html形式）
* 連投注意
*/
function download_userreview() {
  $search_1 = '___FILE_NAME___';
  $search_2 = '___IDS___';
  $commnad_base = <<<EOM
  curl -o ___FILE_NAME___ 'https://erogamescape.dyndns.org/~ap2/ero/toukei_kaiseki/sql_for_erogamer_form.php' \
--data-raw 'sql=select+game%2C+uid%2C+tokuten+from+userreview+where+game+in+%28___IDS___%29%3B' \
--compressed
EOM;
  $results_chunked = get_game_id_list_chunked();
  print('total : '.count($results_chunked).PHP_EOL);
  foreach($results_chunked as $key => $val){
    print("create userreview_${key}.html".PHP_EOL);
    $tmp = str_replace($search_1, "userreview_${key}.html", $commnad_base);
    $tmp = str_replace($search_2, urlencode(implode(',', $val)), $tmp);
    //print($tmp);
    //print(PHP_EOL);
    exec($tmp);
    sleep(5);
  }
}

/*
* 分割でダウンロードしたファイルを結合するしてcsvにする
* 形式 -> uid,game_id,score
*/
function make_userreview() {
  $count = count(get_game_id_list_chunked());
  $game_id_list = get_game_id_list();
  $game_id_key_map = array_flip($game_id_list);
  $results = [];

  print('total : '.$count.PHP_EOL);
  for ($i=0; $i < $count; $i++) {
    print("process userreview_${i}.html".PHP_EOL);
    $tmp_list = get_userreview_list($i);
    foreach ($tmp_list as $tmp) {
      $results[] = implode(',', $tmp);
    }
  }

  $fp = fopen('userbase_all.csv', 'w');
  fwrite($fp, 'uid,game_id,score'.PHP_EOL);
  foreach($results as $result){
      fwrite($fp, $result.PHP_EOL);
  }
  fclose($fp);
}

/*
* ばらつき、スコア補正を行う
* collaboでやるとめっちゃ時間かかる & メモリ飛ぶとやり直しなのでこっちでやる
* game -> userの順番でやる
*/
function dispersion_correction_data() {

  $min_user_score_count = 30;
  $max_user_score_count = 50;
  $min_game_score_count = 15;
  // 0点は未評価扱いにするので1にする
  $score_map = [
    /*
    [
      'max' => 100,
      'min' => 91,
      'score' => 10,
    ],
    [
      'max' => 90,
      'min' => 81,
      'score' => 9,
    ],
    [
      'max' => 80,
      'min' => 71,
      'score' => 8,
    ],
    [
      'max' => 70,
      'min' => 61,
      'score' => 7,
    ],
    [
      'max' => 60,
      'min' => 51,
      'score' => 6,
    ],
    [
      'max' => 50,
      'min' => 41,
      'score' => 5,
    ],
    [
      'max' => 40,
      'min' => 31,
      'score' => 4,
    ],
    [
      'max' => 30,
      'min' => 21,
      'score' => 3,
    ],
    [
      'max' => 20,
      'min' => 11,
      'score' => 2,
    ],
    [
      'max' => 10,
      'min' => 0,
      'score' => 1,
    ],
    */
    [
      'max' => 100,
      'min' => 81,
      'score' => 5,
    ],
    [
      'max' => 80,
      'min' => 61,
      'score' => 4,
    ],
    [
      'max' => 60,
      'min' => 41,
      'score' => 3,
    ],
    [
      'max' => 40,
      'min' => 21,
      'score' => 2,
    ],
    [
      'max' => 20,
      'min' => 0,
      'score' => 1,
    ],
  ];

  $tmp_lines = file('userbase_all.csv');

  // ------------------
  // gameデータの圧縮
  $game_count_list = [];
  $lines = [];
  foreach($tmp_lines as $key => $line){
    if ($key === 0) {
      continue;
    }
    $tmp = explode(',', $line);
    if (empty($tmp[1])) {
      continue;
    }
    if (empty($game_count_list[$tmp[1]])) {
      $game_count_list[$tmp[1]] = 0;
    }
    $game_count_list[$tmp[1]]++;
  }
  foreach($tmp_lines as $key => $line){
    if ($key === 0) {
      $lines[] = $line;
      continue;
    }
    $tmp = explode(',', $line);
    if (empty($game_count_list[$tmp[1]])) {
      continue;
    }
    if ($game_count_list[$tmp[1]] < $min_game_score_count) {
      continue;
    }
    $lines[] = $line;
  }

  // ------------------
  // ユーザーデータの圧縮
  $user_count_list = [];
  foreach($lines as $key => $line){
    if ($key === 0) {
      continue;
    }
    $tmp = explode(',', $line);
    if (empty($tmp[0])) {
      continue;
    }
    if (empty($user_count_list[$tmp[0]])) {
      $user_count_list[$tmp[0]] = 0;
    }
    $user_count_list[$tmp[0]]++;
  }

  $fp = fopen('userbase.csv', 'w');
  foreach($lines as $key => $line){
    if ($key === 0) {
      fwrite($fp, $line);
      continue;
    }
    $tmp = explode(',', $line);
    $tmp_res = $tmp;
    if (empty($user_count_list[$tmp[0]])) {
      continue;
    }
    if ($user_count_list[$tmp[0]] < $min_user_score_count) {
      continue;
    }
    if ($user_count_list[$tmp[0]] > $max_user_score_count) {
      continue;
    }
    foreach ($score_map as $v) {
      if ($tmp[2] >= $v['min'] && $tmp[2] <= $v['max']) {
        $tmp_res[2] = $v['score'];
        break;
      }
    }
    fwrite($fp, implode(',', $tmp_res).PHP_EOL);
  }
  fclose($fp);
}

/*
* データを0から始まるindexに振り直す
*/
function reindex(){

  $file_name = 'userbase';

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
