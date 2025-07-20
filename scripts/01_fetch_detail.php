<?php
$header1 = ['核准科目名稱', '核准班級數', '每班核准人數', '每週總節(時)數', '修業期限', '招生對象'];
$sleepCount = 0;

// Function to fetch URL with retry logic
function fetchUrlWithRetry($url, $maxRetries = 3, $delay = 2)
{
    for ($i = 0; $i < $maxRetries; $i++) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'Mozilla/5.0 (compatible; PHP script)',
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);

        $content = @file_get_contents($url, false, $context);
        if ($content !== false) {
            return $content;
        }

        echo "Retry " . ($i + 1) . " for URL: $url\n";
        if ($i < $maxRetries - 1) {
            sleep($delay);
        }
    }

    echo "Failed to fetch after $maxRetries attempts: $url\n";
    return false;
}
foreach (glob(dirname(__DIR__) . '/data/raw/*.csv') as $csvFile) {
    $p = pathinfo($csvFile);
    $dataPath = dirname(__DIR__) . '/data/raw/' . $p['filename'];
    if (!file_exists($dataPath)) {
        mkdir($dataPath, 0777);
    }
    $fh = fopen($csvFile, 'r');
    $header = fgetcsv($fh, 2048);
    while ($line = fgetcsv($fh, 2048)) {
        $jsonFile = $dataPath . '/' . $line[0] . '.json';

        // Check if file exists and was modified within 24 hours
        $skipFetch = false;
        if (file_exists($jsonFile)) {
            $fileModTime = filemtime($jsonFile);
            $currentTime = time();
            $hoursSinceModified = ($currentTime - $fileModTime) / 3600;

            if ($hoursSinceModified < 24) {
                echo "Using cached data for {$line[0]} - fetched " . round($hoursSinceModified, 1) . " hours ago\n";
                $skipFetch = true;
                // Load existing data from JSON file
                $data = json_decode(file_get_contents($jsonFile), true);
                if (!$data) {
                    echo "Error reading cached data for {$line[0]}, will fetch fresh data\n";
                    $skipFetch = false;
                }
            }
        }

        if (!$skipFetch) {
            $data = [];
            $page1 = fetchUrlWithRetry('https://bsb.kh.edu.tw/afterschool/register/detail.jsp?unit=' . $line[0]);
            if ($page1 === false) {
                echo "Skipping {$line[0]} due to fetch failure\n";
                continue;
            }
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

            $page2 = fetchUrlWithRetry('https://bsb.kh.edu.tw/afterschool/teacher/new/staff_list.jsp?unit=' . $line[0]);
            $data['職員工'] = [];
            if ($page2 !== false) {
                $staffLines = explode('</tr>', $page2);
                foreach ($staffLines as $staffLine) {
                    $cols = explode('</td>', $staffLine);
                    if (count($cols) === 3) {
                        $data['職員工'][] = trim(strip_tags($cols[1]));
                    }
                }
            } else {
                echo "Failed to fetch staff data for {$line[0]}\n";
            }
        }


        if (++$sleepCount > 3) {
            sleep(1);
            $sleepCount = 0;
        }
    }
}