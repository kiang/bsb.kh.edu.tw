<?php
$count = [
    'total' => 0,
    'count' => 0,
];
foreach(glob(dirname(__DIR__) . '/data/raw/*/*.json') AS $jsonFile) {
    $json = json_decode(file_get_contents($jsonFile), true);
    ++$count['total'];
    if(!empty($json['職員工'])) {
        $classCount = 0;
        foreach($json['核准科目'] AS $item) {
            $classCount += intval($item['核准班級數']);
        }
        if(count($json['職員工']) === 1 && $classCount > 10) {
            $count['count']++;
        }
    }
}
print_r($count);