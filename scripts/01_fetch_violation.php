<?php
require 'vendor/autoload.php';
use Goutte\Client;
$client = new Client();

$cities = ['24' => '基隆市', '20' => '台北市', '21' => '新北市', '33' => '桃園市', '35' => '新竹市', '36' => '新竹縣',
'37' => '苗栗縣', '42' => '台中市', '47' => '彰化縣', '55' => '雲林縣', '49' => '南投縣', '52' => '嘉義市',
'53' => '嘉義縣', '62' => '台南市', '70' => '高雄市', '87' => '屏東縣', '39' => '宜蘭縣', '38' => '花蓮縣',
'89' => '台東縣', '69' => '澎湖縣', '82' => '金門縣', '83' => '連江縣'];
$today = date('Y-m-d');
$rawPath = dirname(__DIR__) . '/data/violation';
if(!file_exists($rawPath)) {
    mkdir($rawPath, 0777, true);
}
foreach($cities AS $code => $city) {
    $fh = fopen($rawPath . '/' . $city . '.csv', 'w');
    fputcsv($fh, ['序號', '補習班名稱', '稽查日期', '班址', '違規情形', '處理情形', '發文日期', '發文字號']);
    $client->request('GET', "https://bsb.kh.edu.tw/afterschool/?usercity={$code}&violation=true");
    $client->request('GET', "https://bsb.kh.edu.tw/afterschool/violate/print_check_board.jsp?pageno=1&unit=&area=&road=&start_date=1980-01-01&end_date={$today}&pnt=2");
    $rawHtml = $client->getResponse()->getContent();
    $lines = explode('</tr>', $rawHtml);
    foreach($lines AS $line) {
        $cols = explode('</td>', $line);
        if(count($cols) === 9) {
            array_pop($cols);
            foreach($cols AS $k => $v) {
                $cols[$k] = trim(strip_tags($v));
            }
            fputcsv($fh, $cols);
        }
    }
}
