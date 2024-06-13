<?php
require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\HttpClient;

$browser = new HttpBrowser(HttpClient::create());

$cities = [
    '24' => '基隆市', '20' => '台北市', '21' => '新北市', '33' => '桃園市', '35' => '新竹市', '36' => '新竹縣',
    '37' => '苗栗縣', '42' => '台中市', '47' => '彰化縣', '55' => '雲林縣', '49' => '南投縣', '52' => '嘉義市',
    '53' => '嘉義縣', '62' => '台南市', '70' => '高雄市', '87' => '屏東縣', '39' => '宜蘭縣', '38' => '花蓮縣',
    '89' => '台東縣', '69' => '澎湖縣', '82' => '金門縣', '83' => '連江縣'
];
$rawPath = dirname(__DIR__) . '/data/raw';
if (!file_exists($rawPath)) {
    mkdir($rawPath, 0777, true);
}
foreach ($cities as $code => $city) {
    $fh = fopen($rawPath . '/' . $city . '.csv', 'w');
    fputcsv($fh, ['代號', '補習班', '班址', '電話', '立案文號', '立案日期']);
    $browser->request('GET', "https://bsb.kh.edu.tw/afterschool/?usercity={$code}");
    $pageTotal = 1;
    $pageTotalDone = false;
    for ($i = 1; $i <= $pageTotal; $i++) {
        $browser->request('GET', "https://bsb.kh.edu.tw/afterschool/register/showpage.jsp?pageno={$i}&p_road=&p_name=&e_name=&p_area=&p_type=&di=&estab=&start_year=&start_month=&start_day=&end_year=&end_month=&end_day=&p_range=on&citylink={$code}");
        $rawHtml = $browser->getResponse()->getContent();
        if (false === $pageTotalDone) {
            $pageTotalDone = true;
            $pos = strpos($rawHtml, '共 <font color="#D00000">');
            $pos = strpos($rawHtml, '>', $pos) + 1;
            $posEnd = strpos($rawHtml, '筆', $pos);
            $recordCount = intval(strip_tags(substr($rawHtml, $pos, $posEnd - $pos)));
            $pageTotal = ceil($recordCount / 15);
        }
        $lines = explode('</tr>', $rawHtml);
        foreach ($lines as $line) {
            $cols = explode('</td>', $line);
            if (count($cols) === 8) {
                array_pop($cols);
                foreach ($cols as $k => $v) {
                    if($k < 6) {
                        $cols[$k] = trim(strip_tags($v));
                    } else {
                        $pos = strpos($v, 'unit=') + 5;
                        $posEnd = strpos($v, '"', $pos);
                        $cols[0] = substr($v, $pos, $posEnd - $pos);
                        array_pop($cols);
                    }
                }
                fputcsv($fh, $cols);
            }
        }
    }
}
