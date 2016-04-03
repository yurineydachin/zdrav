<?php
require_once 'scaner.php';

$args = $_SERVER['argv'];

$scanner = new ZdradScanner('load_data');

$profiler = TimeProfiler::instance();
$pKey = $profiler->start(TimeProfiler::total);

$scanner->loadCities();

if (count($args) >= 2) { // cityId
    $scanner->loadHospitals($args[1]);
}

if (count($args) >= 3) { // hospital lpuCode
    $scanner->loadDostorTypes(explode(',', $args[2]));
}

$res = $scanner->getResult();
//echo "\nResult: "; print_r($scanner->getResult());
foreach ($res as $model => $items)
{
    echo "\nResult $model: "; print_r(count($items));
}
echo "\nErrors: "; print_r($scanner->getErrors());

foreach ($res as $model => $items)
{
    $scanner->saveModesToFile($items, 'data/'.$model);
}

$profiler->stop(TimeProfiler::total, $pKey);
echo $profiler->getStat() . "\n";
$profiler->clear();
