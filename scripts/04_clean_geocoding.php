<?php
$basePath = dirname(__DIR__);
$rawPath = $basePath . '/data/geocoding';

foreach (glob($rawPath . '/*.json') as $jsonFile) {
    $json = json_decode(file_get_contents($jsonFile), true);
    if (empty($json['AddressList'])) {
        unlink($jsonFile);
    }
}
