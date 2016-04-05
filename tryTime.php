<?php
require_once 'libs/profiler.php';
require_once 'scaner.php';

$timePriority = array(
    '23:59' => 1,
);

$config = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : null;
if (!$config || !file_exists('config/'.$config.'.php')) {
    die("No config value or config file. Run php daemon.php <config name>\n");
}
require_once 'config/'.$config.'.php';

$scanner = new ZdradScanner($config);
$scanner->timePriority = $timePriority;
defined('EMAIL')           && $scanner->email =    EMAIL;
defined('POLIS')           && $scanner->polis =    POLIS;
defined('BIRTHDAY')        && $scanner->birthday = BIRTHDAY;
defined('LPU_CODE')        && $scanner->lpuCode =  LPU_CODE;
defined('DOCTOR')          && $scanner->doctor =   DOCTOR;
defined('DATE_APPINTMENT') && $scanner->date =     DATE_APPINTMENT;
defined('SCENERY')         && $scanner->scenery =  SCENERY;
defined('DEBUG')           && $scanner->deubg =    DEBUG;

$profiler = TimeProfiler::instance();
$pKey = $profiler->start(TimeProfiler::total);

$status = $scanner->loadTimes();
if ($status == ZdradScanner::STATUS_NO_AVAILABLE_TIME) {
    die("FAIL. We missed time!\n");
}
list($timeId, $rangeTime) = $scanner->getTheBestTime();
echo "Status: $status,  TimeId: $timeId in ranges:" . print_r($rangeTime, true) . "\n";

$res = $scanner->getResult();
echo "\nResult: "; print_r($res);
echo "\nErrors: "; print_r($scanner->getErrors());

$profiler->stop(TimeProfiler::total, $pKey);
echo $profiler->getStat() . "\n";
$profiler->clear();
