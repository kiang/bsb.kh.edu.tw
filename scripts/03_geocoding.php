<?php
$basePath = dirname(__DIR__);
$config = require $basePath . '/config.php';
$rawPath = $basePath . '/data/geocoding';
if (!file_exists($rawPath)) {
    mkdir($rawPath, 0777, true);
}
$fc = [
    'type' => 'FeatureCollection',
    'features' => [],
];
$cityFc = [];

$monthlyPool = [];
$tr = [
    '臺中市' => '台中市',
    '臺北市' => '台北市',
    '臺南市' => '台南市',
    '臺東縣' => '台東縣',
];
foreach (glob($basePath . '/data/raw/*.csv') as $csvFile) {
    $p = pathinfo($csvFile);
    $fh = fopen($csvFile, 'r');
    $head = fgetcsv($fh, 2048);
    while ($line = fgetcsv($fh, 2048)) {
        $data = array_combine($head, $line);
        $data['班址'] = strtr($data['班址'], $tr);
        if (false === strpos($data['班址'], $p['filename'])) {
            $data['班址'] = $p['filename'] . $data['班址'];
        }
        $pos = strpos($data['班址'], '號');
        if (false !== $pos) {
            $address = substr($data['班址'], 0, $pos) . '號';
        } else {
            $address = $data['班址'];
        }
        $jsonFile = $rawPath . '/' . $address . '.json';
        if (!file_exists($jsonFile)) {
            continue;
            $apiUrl = $config['tgos']['url'] . '?' . http_build_query([
                'oAPPId' => $config['tgos']['APPID'], //應用程式識別碼(APPId)
                'oAPIKey' => $config['tgos']['APIKey'], // 應用程式介接驗證碼(APIKey)
                'oAddress' => $address, //所要查詢的門牌位置
                'oSRS' => 'EPSG:4326', //回傳的坐標系統
                'oFuzzyType' => '2', //模糊比對的代碼
                'oResultDataType' => 'JSON', //回傳的資料格式
                'oFuzzyBuffer' => '0', //模糊比對回傳門牌號的許可誤差範圍
                'oIsOnlyFullMatch' => 'false', //是否只進行完全比對
                'oIsLockCounty' => 'true', //是否鎖定縣市
                'oIsLockTown' => 'false', //是否鎖定鄉鎮市區
                'oIsLockVillage' => 'false', //是否鎖定村里
                'oIsLockRoadSection' => 'false', //是否鎖定路段
                'oIsLockLane' => 'false', //是否鎖定巷
                'oIsLockAlley' => 'false', //是否鎖定弄
                'oIsLockArea' => 'false', //是否鎖定地區
                'oIsSameNumber_SubNumber' => 'true', //號之、之號是否視為相同
                'oCanIgnoreVillage' => 'true', //找不時是否可忽略村里
                'oCanIgnoreNeighborhood' => 'true', //找不時是否可忽略鄰
                'oReturnMaxCount' => '0', //如為多筆時，限制回傳最大筆數
            ]);
            $content = file_get_contents($apiUrl);
            $pos = strpos($content, '{');
            $posEnd = strrpos($content, '}') + 1;
            $resultline = substr($content, $pos, $posEnd - $pos);
            if (strlen($resultline) > 10) {
                $json = substr($content, $pos, $posEnd - $pos);
                file_put_contents($jsonFile, $json);
                $json = json_decode($json, true);
            }
        } else {
            $json = json_decode(file_get_contents($jsonFile), true);
        }
        if(!empty($json['AddressList'][0]['X'])) {
            if(!isset($cityFc[$json['AddressList'][0]['COUNTY']])) {
                $cityFc[$json['AddressList'][0]['COUNTY']] = $fc;
            }
            $cityFc[$json['AddressList'][0]['COUNTY']]['features'][] = [
                'type' => 'Feature',
                'properties' => [
                    'code' => $data['代號'],
                    'name' => $data['補習班'],
                    'county' => $json['AddressList'][0]['COUNTY'],
                    'town' => $json['AddressList'][0]['TOWN'],
                    'village' => $json['AddressList'][0]['VILLAGE'],
                    'address' => $data['班址'],
                    'tel' => $data['電話'],
                ],
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [
                        $json['AddressList'][0]['X'],
                        $json['AddressList'][0]['Y']
                    ],
                ],
            ];
        }
    }
}

foreach($cityFc AS $city => $fc) {
    file_put_contents($basePath . '/data/map/' . $city . '.json', json_encode($fc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "{$city}: " . count($fc['features']) . " \n";
}