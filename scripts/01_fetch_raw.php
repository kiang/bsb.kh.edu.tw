<?php
$raw = json_decode(file_get_contents('https://bsb.kh.edu.tw/afterschool/opendata/afterschool_json.jsp'), true);
$fh = [];
$rawPath = dirname(__DIR__) . '/data/raw';
if(!file_exists($rawPath)) {
    mkdir($rawPath, 0777, true);
}
foreach($raw AS $line) {
    array_pop($line);
    if(!isset($fh[$line['地區縣市']])) {
        $fh[$line['地區縣市']] = fopen($rawPath . '/' . $line['地區縣市'] . '.csv', 'w');
        fputcsv($fh[$line['地區縣市']], array_keys($line));
    }
    fputcsv($fh[$line['地區縣市']], $line);
}