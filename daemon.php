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

$timeFrom = strtotime(TIME_FROM);
$timeTo = strtotime(TIME_TO);

if ($timeTo < time()) {
    die("Time is over: " . TIME_TO . "\n");
}
if ($timeFrom > time()) {
    $to_sleep = $timeFrom - time();
    echo date('Y-m-d H:i:s') . " wait until " . TIME_FROM . " sec: $to_sleep, $timeFrom, ".time()."  sleep: ".($to_sleep / 60 / 60)."\n";
    sleep($to_sleep);
}
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
$scanner->loadNames();


$cycle_number = 1;
$period = 5 * 1e6;

while (true) {
    $started = microtime(true);

    $profiler = TimeProfiler::instance();
    $pKey = $profiler->start(TimeProfiler::total);

    $status = $scanner->loadTimes();
    if ($status == ZdradScanner::STATUS_NO_AVAILABLE_TIME) {
        die("FAIL. We missed time!\n");
    }

    list($timeId, $rangeTime) = $scanner->getTheBestTime();
    if (is_numeric($timeId)) {
        echo "Have found free time: " . $timeId . " in ranges:" . print_r($rangeTime, true) . "\n";
        $scanner->login();
        $status = $scanner->createVisit($timeId);
    } else {
        $res = $scanner->getResult();
        echo "No time: " . print_r($res['times'], true) . "\n";
    }

    $res = $scanner->getResult();
    //echo "\nResult: "; print_r($scanner->getResult());
    //echo "\nErrors: "; print_r($scanner->getErrors());
    //echo "\nSTATUS: "; print_r($res['create_visit']['STATUS']);

    $profiler->stop(TimeProfiler::total, $pKey);
    echo $profiler->getStat() . "\n";
    $profiler->clear();

    $to_sleep = max(0, $period - (microtime(true) - $started) * 1e6);

    switch ($status) {
        case ZdradScanner::STATUS_COMPLETE:
        case ZdradScanner::STATUS_ALREADY:
            echo "\nResult: "; print_r($scanner->getResult());
            die("Finish and complete!\n");
            break;
        case ZdradScanner::STATUS_BUSY:
            $to_sleep = 0;
            break;
        case ZdradScanner::STATUS_TIME_NOT_OPENED:
        case ZdradScanner::STATUS_NO_TIME:
            // try again later
            break;
        case ZdradScanner::STATUS_ERROR_LOADING:
        case ZdradScanner::STATUS_UNKNOWN:
        default:
            echo "Something wrong with it: $status\n";
            echo "\nErrors: "; print_r($scanner->getErrors());
    }

    if ($timeTo < time()) {
        die("Time is over: " . TIME_TO . "\n");
    }

    $next_start = time() + round($to_sleep / 1e6);
    echo ("Status: $status, Next Run: " . date('Y-m-d H:i:s', $next_start) . "\n");
    usleep($to_sleep);
    $cycle_number++;
}
