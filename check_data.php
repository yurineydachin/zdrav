<?php
require_once 'scaner.php';

$args = $_SERVER['argv'];

$scanner = new ZdradScanner('load_data');

$scanner->loadModesFromFile('city_list',        'data/city_list');
$scanner->loadModesFromFile('lpu_list',         'data/lpu_list');
$scanner->loadModesFromFile('lpu_doctor_types', 'data/lpu_doctor_types');
$scanner->loadModesFromFile('doctors',          'data/doctors');

$res = $scanner->getResult();
//echo "\nResult: "; print_r($scanner->getResult());
foreach ($res as $model => $items)
{
    echo "\nModel $model: "; print_r(count($items));
}
echo "\nErrors: "; print_r($scanner->getErrors());
