<?php
require_once 'scaner.php';

$scanner = new ZdradScanner('test_best_time');
$scanner->timePriority = array(
    '09:00' => 1,
    '10:00' => 5,
    '14:00' => 3,
    '15:00' => 5,
    '17:00' => 4,
    '18:20' => 5,
    '23:59' => 2,
);

list($timeId, $timeRanges) = $scanner->getTheBestTime(array(
    //id => time
    '0800' => '08:00',
    '0815' => '08:15',
    '0930' => '09:30',
    '1030' => '10:30',
    '1130' => '11:30',
    '1230' => '12:30',
    '1330' => '13:30',
    '1530' => '15:30',
    '1630' => '16:30',
    '1730' => '17:30',
    '1800' => '18:00',
    '1800' => '18:30',
    '1930' => '19:30',
));

echo "timeId : " . $timeId . "\n";
echo "\nTime ranges: "; print_r($timeRanges);
echo "\n";
