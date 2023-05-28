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
  print('start delete_unnecessary_data()'.PHP_EOL);
  delete_unnecessary_data();
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
* レビュー数が少ないユーザーのデータを省く
* pythonでやるとめっちゃ時間かかるのでこっちでやる
*/
function delete_unnecessary_data() {

  $min_score_count = 5;

  $lines = file('userbase_all.csv');
  $data_count_list = [];
  foreach($lines as $key => $line){
    if ($key === 0) {
      continue;
    }
    $tmp = explode(',', $line);
    if (empty($tmp[0])) {
      continue;
    }
    if (empty($data_count_list[$tmp[0]])) {
      $data_count_list[$tmp[0]] = 0;
    }
    $data_count_list[$tmp[0]]++;
  }

  $fp = fopen('userbase.csv', 'w');
  foreach($lines as $key => $line){
    if ($key === 0) {
      fwrite($fp, $line);
      continue;
    }
    $tmp = explode(',', $line);
    if (empty($data_count_list[$tmp[0]])) {
      continue;
    }
    if ($data_count_list[$tmp[0]] < $min_score_count) {
      continue;
    }
    fwrite($fp, $line);
  }
  fclose($fp);
}
