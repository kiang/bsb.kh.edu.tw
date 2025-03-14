<?php
$basePath = dirname(__DIR__);
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
$missingFh = fopen($basePath . '/data/missing.csv', 'w');
foreach (glob($basePath . '/data/raw/*.csv') as $csvFile) {
    $p = pathinfo($csvFile);
    $city = $p['filename'];
    $detailPath = $basePath . '/data/raw/' . $p['filename'];
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
            $command = <<<EOD
curl 'https://api.nlsc.gov.tw/MapSearch/ContentSearch?word=___KEYWORD___&mode=AutoComplete&count=1&feedback=XML' \
   -H 'Accept: application/xml, text/xml, */*; q=0.01' \
   -H 'Accept-Language: zh-TW,zh;q=0.9,en-US;q=0.8,en;q=0.7' \
   -H 'Connection: keep-alive' \
   -H 'Origin: https://maps.nlsc.gov.tw' \
   -H 'Referer: https://maps.nlsc.gov.tw/' \
   -H 'Sec-Fetch-Dest: empty' \
   -H 'Sec-Fetch-Mode: cors' \
   -H 'Sec-Fetch-Site: same-site' \
   -H 'User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36' \
   -H 'sec-ch-ua: "Google Chrome";v="123", "Not:A-Brand";v="8", "Chromium";v="123"' \
   -H 'sec-ch-ua-mobile: ?0' \
   -H 'sec-ch-ua-platform: "Linux"'
EOD;
            $result = shell_exec(strtr($command, [
                '___KEYWORD___' => urlencode($address),
            ]));
            $cleanKeyword = trim(strip_tags($result));

            if (!empty($cleanKeyword)) {
                $command = <<<EOD
                curl 'https://api.nlsc.gov.tw/MapSearch/QuerySearch' \
                  -H 'Accept: application/xml, text/xml, */*; q=0.01' \
                  -H 'Accept-Language: zh-TW,zh;q=0.9,en-US;q=0.8,en;q=0.7' \
                  -H 'Connection: keep-alive' \
                  -H 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8' \
                  -H 'Origin: https://maps.nlsc.gov.tw' \
                  -H 'Referer: https://maps.nlsc.gov.tw/' \
                  -H 'Sec-Fetch-Dest: empty' \
                  -H 'Sec-Fetch-Mode: cors' \
                  -H 'Sec-Fetch-Site: same-site' \
                  -H 'User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36' \
                  -H 'sec-ch-ua: "Google Chrome";v="123", "Not:A-Brand";v="8", "Chromium";v="123"' \
                  -H 'sec-ch-ua-mobile: ?0' \
                  -H 'sec-ch-ua-platform: "Linux"' \
                  --data-raw 'word=___KEYWORD___&feedback=XML&center=120.218280%2C23.007292'
                EOD;
                $result = shell_exec(strtr($command, [
                    '___KEYWORD___' => urlencode(urlencode($cleanKeyword)),
                ]));
                $json = json_decode(json_encode(simplexml_load_string($result)), true);
                if (!empty($json['ITEM']['LOCATION'])) {
                    $parts = explode(',', $json['ITEM']['LOCATION']);
                    if (count($parts) === 2) {
                        file_put_contents($jsonFile, json_encode([
                            'AddressList' => [
                                [
                                    'X' => $parts[0],
                                    'Y' => $parts[1],
                                ],
                            ],
                        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    }
                }
            }
        }
        if (file_exists($jsonFile)) {
            $json = json_decode(file_get_contents($jsonFile), true);
        }
        if (!empty($json['AddressList'][0]['X'])) {
            if (!isset($cityFc[$city])) {
                $cityFc[$city] = $fc;
            }
            $targets = [];
            $detailFile = $detailPath . '/' . $data['代號'] . '.json';
            if (file_exists($detailFile)) {
                $detail = json_decode(file_get_contents($detailFile), true);
                foreach ($detail['核准科目'] as $item) {
                    $targets[$item['招生對象']] = true;
                }
            }
            $cityFc[$city]['features'][$data['代號']] = [
                'type' => 'Feature',
                'properties' => [
                    'code' => $data['代號'],
                    'name' => $data['補習班'],
                    'county' => $city,
                    'class' => isset($detail['補習班類別/科目']) ? $detail['補習班類別/科目'] : '',
                    'students' => implode(',', array_keys($targets)),
                    'date_closed' => isset($detail['廢止、註銷日期']) ? $detail['廢止、註銷日期'] : '',
                ],
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [
                        floatval($json['AddressList'][0]['X']),
                        floatval($json['AddressList'][0]['Y'])
                    ],
                ],
            ];
        } else {
            fputcsv($missingFh, [
                $data['代號'],
                $data['補習班'],
                $address,
            ]);
        }
    }
}

foreach ($cityFc as $city => $fc) {
    ksort($fc['features']);
    $fc['features'] = array_values($fc['features']);
    file_put_contents($basePath . '/data/map/' . $city . '.json', json_encode($fc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "{$city}: " . count($fc['features']) . " \n";
}
