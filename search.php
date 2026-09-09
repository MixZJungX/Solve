<?php
$c = file_get_contents("api.php");
$lines = explode("\n", $c);
foreach($lines as $i => $l) {
    if (strpos($l, "case 'admin_job_detail':") !== false) {
        for($j=max(0,$i-2); $j<=min(count($lines)-1,$i+100); $j++) {
            if(strpos($lines[$j], "case 'download_database':") !== false) break;
            echo ($j+1) . ": " . $lines[$j] . "\n";
        }
    }
}

