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

echo "\nResult City: "; print_r(count($scanner->modelCity->getAllItems()));
echo "\nResult Lpu: "; print_r(count($scanner->modelLpu->getAllItems()));
echo "\nResult DoctorType: "; print_r(count($scanner->modelDoctorType->getAllItems()));
echo "\nResult Doctor: "; print_r(count($scanner->modelDoctor->getAllItems()));

echo "\nErrors: "; print_r($scanner->getErrors());

/*
$scanner->modelCity->saveToFile();
$scanner->modelLpu->saveToFile();
$scanner->modelDoctorType->saveToFile();
$scanner->modelDoctor->saveToFile();
*/

$profiler->stop(TimeProfiler::total, $pKey);
echo $profiler->getStat() . "\n";
$profiler->clear();
