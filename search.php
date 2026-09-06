<?php
$c = file_get_contents("api.php");
$lines = explode("\n", $c);
foreach($lines as $i => $l) {
    if (strpos($l, "case 'get_settings':") !== false) {
        for($j=max(0,$i-2); $j<=min(count($lines)-1,$i+35); $j++) {
            echo ($j+1) . ": " . $lines[$j] . "\n";
        }
        break;
    }
}

