<?php
$header1 = ['核准科目名稱', '核准班級數', '每班核准人數', '每週總節(時)數', '修業期限', '招生對象'];
$sleepCount = 0;
$cityTagCounts = []; // Track subject counts by city
foreach (glob(dirname(__DIR__) . '/data/raw/*.csv') as $csvFile) {
    $p = pathinfo($csvFile);
    $dataPath = dirname(__DIR__) . '/data/raw/' . $p['filename'];
    if (!file_exists($dataPath)) {
        mkdir($dataPath, 0777);
    }
    $fh = fopen($csvFile, 'r');
    $header = fgetcsv($fh, 2048);
    while ($line = fgetcsv($fh, 2048)) {
        $data = [];
        $page1 = file_get_contents('https://bsb.kh.edu.tw/afterschool/register/detail.jsp?unit=' . $line[0]);
        $partPos = strpos($page1, '<table');
        $partPosEnd = strpos($page1, '</table>', $partPos);
        $part1 = substr($page1, $partPos, $partPosEnd - $partPos);
        $part1 = str_replace('</tr>', '</td>', $part1);
        $rows = explode('</td>', $part1);
        foreach ($rows as $row) {
            $rowParts = explode('</th>', $row);
            if (count($rowParts) === 2) {
                foreach ($rowParts as $k => $v) {
                    $rowParts[$k] = trim(strip_tags($v));
                }
                $data[$rowParts[0]] = $rowParts[1];
            }
        }
        $data['負責人'] = [];
        $partPos = strpos($page1, '<table', $partPosEnd);
        $partPosEnd = strpos($page1, '</table>', $partPos);
        $partLines = explode('</tr>', substr($page1, $partPos, $partPosEnd - $partPos));
        foreach ($partLines as $partLine) {
            $partCols = explode('<td class="listBody">', $partLine);
            if (count($partCols) === 2) {
                $data['負責人'][] = trim(strip_tags($partCols[1]));
            }
        }

        $data['設立人'] = [];
        $partPos = strpos($page1, '<table', $partPosEnd);
        $partPosEnd = strpos($page1, '</table>', $partPos);
        $partLines = explode('</tr>', substr($page1, $partPos, $partPosEnd - $partPos));
        foreach ($partLines as $partLine) {
            $partCols = explode('<td class="listBody">', $partLine);
            if (count($partCols) === 2) {
                $data['設立人'][] = trim(strip_tags($partCols[1]));
            }
        }

        $data['班主任'] = [];
        $partPos = strpos($page1, '<table', $partPosEnd);
        $partPosEnd = strpos($page1, '</table>', $partPos);
        $partLines = explode('</tr>', substr($page1, $partPos, $partPosEnd - $partPos));
        foreach ($partLines as $partLine) {
            $partCols = explode('<td class="listBody">', $partLine);
            if (count($partCols) === 2) {
                $data['班主任'][] = trim(strip_tags($partCols[1]));
            }
        }

        $data['核准科目'] = [];
        $partPos = strpos($page1, '<table', $partPosEnd);
        $partPosEnd = strpos($page1, '</table>', $partPos);
        $partLines = explode('</tr>', substr($page1, $partPos, $partPosEnd - $partPos));
        foreach ($partLines as $partLine) {
            $partCols = explode('<td width="14%" class="listBody">', $partLine);
            if (count($partCols) === 6) {
                foreach ($partCols as $k => $v) {
                    $partCols[$k] = trim(strip_tags($v));
                }
                $data['核准科目'][] = array_combine($header1, $partCols);
            }
        }

        $page2 = file_get_contents('https://bsb.kh.edu.tw/afterschool/teacher/new/staff_list.jsp?unit=' . $line[0]);
        $data['職員工'] = [];
        $staffLines = explode('</tr>', $page2);
        foreach ($staffLines as $staffLine) {
            $cols = explode('</td>', $staffLine);
            if (count($cols) === 3) {
                $data['職員工'][] = trim(strip_tags($cols[1]));
            }
        }
        if (!empty($data['補習班代碼'])) {
            file_put_contents($dataPath . '/' . $line[0] . '.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo "{$line[0]}\n";
            
            // Generate tag JSON files for each subject
            $city = $p['filename']; // The city is the filename from CSV
            $code = $data['補習班代碼'];
            
            if (!empty($data['核准科目']) && is_array($data['核准科目'])) {
                $tagBasePath = dirname(__DIR__) . '/data/tag';
                foreach ($data['核准科目'] as $subject) {
                    if (!empty($subject['核准科目名稱'])) {
                        $subjectName = $subject['核准科目名稱'];
                        // Remove patterns like '[a-zA-Z]班' from subject name
                        $subjectName = preg_replace('/[a-zA-Z]+班/', '', $subjectName);
                        $cityTagPath = $tagBasePath . '/' . $city;
                        $tagFile = $cityTagPath . '/' . $subjectName . '.json';
                        
                        // Create city directory if it doesn't exist
                        if (!file_exists($cityTagPath)) {
                            mkdir($cityTagPath, 0777, true);
                        }
                        
                        // Load existing data or create new array
                        $tagData = [];
                        if (file_exists($tagFile)) {
                            $tagData = json_decode(file_get_contents($tagFile), true);
                            if (!$tagData) {
                                $tagData = [];
                            }
                        }
                        
                        // Check if code already exists
                        if (!in_array($code, $tagData)) {
                            $tagData[] = $code;
                            
                            // Save updated data
                            file_put_contents($tagFile, json_encode($tagData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                            
                            // Count subjects by city
                            if (!isset($cityTagCounts[$city])) {
                                $cityTagCounts[$city] = [];
                            }
                            if (!isset($cityTagCounts[$city][$subjectName])) {
                                $cityTagCounts[$city][$subjectName] = 0;
                            }
                            $cityTagCounts[$city][$subjectName]++;
                        }
                    }
                }
            }
        }


        if (++$sleepCount > 3) {
            sleep(1);
            $sleepCount = 0;
        }
    }
}

// Generate tag/{city}.json files with subject counts by city
$tagBasePath = dirname(__DIR__) . '/data/tag';
foreach ($cityTagCounts as $city => $subjects) {
    $cityJsonData = [];
    foreach ($subjects as $subject => $count) {
        $cityJsonData[] = [
            'tag' => $subject,
            'count' => $count
        ];
    }
    // Sort by count descending
    usort($cityJsonData, function($a, $b) {
        return $b['count'] - $a['count'];
    });
    
    $cityJsonPath = $tagBasePath . '/' . $city . '.json';
    file_put_contents($cityJsonPath, json_encode($cityJsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
