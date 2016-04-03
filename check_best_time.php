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
    '08:00',
    '08:15',
    '09:30',
    '10:30',
    '11:30',
    '12:30',
    '13:30',
    '15:30',
    '16:30',
    '17:30',
    '18:00',
    '18:30',
    '19:30',
));

echo "timeId : " . $timeId . "\n";
echo "\nTime ranges: "; print_r($timeRanges);
echo "\n";
