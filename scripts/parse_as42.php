<?php

date_default_timezone_set('UTC');

function fetchMulti($urls)
{
    $ch      = array();
    $results = array();
    $mh      = curl_multi_init();
    foreach ($urls as $key => $val) {
        $ch[$key] = curl_init();
        curl_setopt($ch[$key], CURLOPT_URL, $val);
        curl_setopt($ch[$key], CURLOPT_HEADER, 0);
        curl_setopt($ch[$key], CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch[$key], CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_3) AppleWebKit/602.4.8 (KHTML, like Gecko) Version/10.0.3 Safari/602.4.8");
        curl_setopt($ch[$key], CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($ch[$key], CURLOPT_TIMEOUT, 60);
        curl_setopt($ch[$key], CURLOPT_FOLLOWLOCATION, true);
        curl_multi_add_handle($mh, $ch[$key]);
    }
    $running = 1;
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh);
    } while ($running > 0);
    foreach ($ch as $key => $val) {
        $results[curl_getinfo($val, CURLINFO_EFFECTIVE_URL)] = curl_multi_getcontent($val);
        curl_multi_remove_handle($mh, $val);
    }
    curl_multi_close($mh);
    return $results;
}

function getLatestBaseUrl($ipVersion = 4)
{
    $regexYear  = "|<i class=\"fa fa-folder fa-fw\"></i>&nbsp;<a href=\"(?:\d+)/\"><strong>(\d+)</strong>|Usi";
    $regexMonth = "|<i class=\"fa fa-folder fa-fw\"></i>&nbsp;<a href=\"(?:\d+)/\"><strong>(\d+)</strong>|Usi";
    $baseYear   = "https://www.pch.net/resources/Routing_Data/IPv" . $ipVersion . "_daily_snapshots/";

    $str     = file_get_contents($baseYear);
    $matches = array();
    $i       = preg_match_all($regexYear, $str, $matches);
    if (isset($matches[1])) {
        $year = $matches[1];
        rsort($year);
        $year      = $year[0];
        $baseMonth = $baseYear . $year . "/";
        $str       = file_get_contents($baseMonth);
        $matches   = array();
        $i         = preg_match_all($regexMonth, $str, $matches);
        if (isset($matches[1])) {
            $month = $matches[1];
            rsort($month);
            $month = $month[0];
        } else {
            die("unable to fetch month\n");
        }
    } else {
        die("unable to fetch year\n");
    }
    return $baseYear . $year . "/" . $month . "/";
}

$dumpUrls    = [];
$threadnum   = 10;
$reqexFolder = "|<i class=\"fa fa-folder fa-fw\"></i>&nbsp;<a href=\"([a-zA-Z0-9\-\.\/]+)\"><strong>(?:[a-zA-Z0-9\-\.\/]+)</strong></a></td>|Usi";
$reqexFile   = "|<i class=\"fa fa-archive fa-fw\"></i>&nbsp;<a href=\"([a-zA-Z0-9\-\_\.\/]+)\">([a-zA-Z0-9\-\_\.\/]+)</a></td>|Usi";

foreach ([4, 6] as $ipVersions) {
    $baseUrl = getLatestBaseUrl($ipVersions);

    $str = file_get_contents($baseUrl);

    $matches = array();
    $i       = preg_match_all($reqexFolder, $str, $matches);
    if ((isset($matches[1])) && (count($matches[1]) > 0)) {
        $i = 0;
        $j = 0;
        while ($i <= count($matches[1])) {
            $urls = array();
            for ($j = 0; $j < $threadnum; $j++) {
                if (isset($matches[1][$i])) {
                    array_push($urls, $baseUrl . $matches[1][$i]);
                }

                $i++;
            }
            $pages = fetchMulti($urls);
            foreach ($pages as $url => $contents) {
                $fileUrls = preg_match_all($reqexFile, $contents, $files);
                if (isset($files[1])) {
                    rsort($files[1]);

                    $finalUrl      = $url . $files[1][0];
                    $finalUrlParts = explode(date('Y'), $finalUrl);
                    $date          = date('Y') . str_replace('.gz', '', end($finalUrlParts));
                    $fileTime      = strtotime(str_replace('.', '-', $date));

                    // Only add the file if we are dealing with dates are in last 24 hours
                    if ($fileTime > 0 && (time() - 86400) < $fileTime) {
                        $dumpUrls[] = [
                            'url'  => $finalUrl,
                            'date' => $date,
                            'name' => $files[1][0],
                        ];
                    }

                }
            }
        }
    }
}

foreach ($dumpUrls as $dumpUrl) {
    // read raw file
    $fileContent = gzdecode(file_get_contents($dumpUrl['url']));

    // Make temp file
    $tempFile = tempnam(sys_get_temp_dir(), 'pch_dump');

    file_put_contents($tempFile, $fileContent);

    exec('cat '.$tempFile .' | awk -fscripts/pch.awk', $outputLines);

    // Delete file
    unlink($tempFile);

    $outputLines = array_unique($outputLines);
    foreach ($outputLines as $outputLine) {
        echo $outputLine.PHP_EOL;
    }
}
