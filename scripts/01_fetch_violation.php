<?php
require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\Httpbrowser\Httpbrowser;

$browser = new HttpBrowser(Httpbrowser::create());

$cities = [
    '24' => '基隆市', '20' => '台北市', '21' => '新北市', '33' => '桃園市', '35' => '新竹市', '36' => '新竹縣',
    '37' => '苗栗縣', '42' => '台中市', '47' => '彰化縣', '55' => '雲林縣', '49' => '南投縣', '52' => '嘉義市',
    '53' => '嘉義縣', '62' => '台南市', '70' => '高雄市', '87' => '屏東縣', '39' => '宜蘭縣', '38' => '花蓮縣',
    '89' => '台東縣', '69' => '澎湖縣', '82' => '金門縣', '83' => '連江縣'
];
$today = date('Y-m-d');
$rawPath = dirname(__DIR__) . '/data/violation';
if (!file_exists($rawPath)) {
    mkdir($rawPath, 0777, true);
}
foreach ($cities as $code => $city) {
    $targetFile = $rawPath . '/' . $city . '.csv';
    $codePool = [];
    if (file_exists($targetFile)) {
        $fh = fopen($targetFile, 'r+');
        fgetcsv($fh, 4096);
        while ($line = fgetcsv($fh, 8000)) {
            $codePool[$line[7]] = true;
        }
    } else {
        $fh = fopen($rawPath . '/' . $city . '.csv', 'w');
        fputcsv($fh, ['序號', '補習班名稱', '稽查日期', '班址', '違規情形', '處理情形', '發文日期', '發文字號']);
    }

    $browser->request('GET', "https://bsb.kh.edu.tw/afterschool/?usercity={$code}&violation=true");
    $browser->request('GET', "https://bsb.kh.edu.tw/afterschool/violate/print_check_board.jsp?pageno=1&unit=&area=&road=&start_date=1980-01-01&end_date={$today}&pnt=2");
    $rawHtml = $browser->getResponse()->getContent();
    $lines = explode('</tr>', $rawHtml);
    foreach ($lines as $line) {
        $cols = explode('</td>', $line);
        if (count($cols) === 9) {
            array_pop($cols);
            foreach ($cols as $k => $v) {
                $cols[$k] = trim(strip_tags($v));
            }
            if(!isset($codePool[$cols[7]])) {
                fputcsv($fh, $cols);
            }
        }
    }
}
